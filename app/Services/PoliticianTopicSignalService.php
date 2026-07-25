<?php

namespace App\Services;

use App\Models\CandidateNewsArticle;
use App\Models\Politician;
use App\Models\PoliticianTopic;
use App\Models\PoliticianTopicSignal;
use App\Models\PoliticianViralMoment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Builds the politician × topic evidence rollup (politician_topic_signals) that
 * drives inferred issue badges.
 *
 * Three input sources, aggregated per (politician, topic):
 *   - news:        candidate_news_articles with a stored topic_key (set by
 *                  CandidateNewsService::extractTopicKey) + verification_status='verified'.
 *   - viral_moment: politician_viral_moments clip titles classified on the fly
 *                  by IssueClassifierService (or a stored topic_key).
 *   - votesmart:   VoteSmartService NPAT issue_positions, classified by
 *                  IssueClassifierService (gated by show_votesmart_data).
 *
 * Each mention contributes confidence × recency_decay (exp(-age/half_life),
 * matching MomentScoreService's shape) to its topic's per-source aggregate.
 * total_score = Σ source_weight × source_aggregate. When total_score crosses
 * config('u9itus.issues.signal_threshold'), BadgeService grants the badge.
 *
 * Stateless w.r.t. badges: this service only writes politician_topic_signals.
 */
class PoliticianTopicSignalService
{
    public function __construct(
        private readonly IssueClassifierService $classifier,
        private readonly VoteSmartService $voteSmart,
    ) {}

    /**
     * Refresh signals for one politician. Returns the kept signal rows.
     *
     * When $persist is false (dry-run), no DB writes happen — the returned
     * collection holds hydrated, unsaved PoliticianTopicSignal models so the
     * caller can report would-be scores/grants without side effects.
     *
     * @return Collection<int, PoliticianTopicSignal>
     */
    public function compute(Politician $politician, bool $persist = true): Collection
    {
        $windowDays = (int) config('u9itus.issues.recency_window_days', 90);
        $halfLife = (float) config('u9itus.issues.recency_half_life_days', 60);
        $weights = (array) config('u9itus.issues.source_weights', []);
        $wNews = (float) ($weights['news'] ?? 1.0);
        $wViral = (float) ($weights['viral_moment'] ?? 1.2);
        $wVoteSmart = (float) ($weights['votesmart'] ?? 1.5);
        $since = now()->subDays($windowDays);

        // topicId => ['news' => aggregate, 'viral' => aggregate, 'votesmart' => aggregate,
        //             'news_count' => n, 'viral_count' => n, 'votesmart_count' => n]
        $acc = [];

        // ── News (stored topic_key) ───────────────────────────────────────
        $articles = CandidateNewsArticle::query()
            ->where('politician_id', $politician->id)
            ->where('verification_status', 'verified')
            ->whereNotNull('topic_key')
            ->where(function ($q) use ($since) {
                $q->where('published_at', '>=', $since)->orWhere(function ($q2) use ($since) {
                    $q2->whereNull('published_at')->where('created_at', '>=', $since);
                });
            })
            ->get(['topic_key', 'topic_confidence', 'verification_confidence', 'published_at', 'created_at']);

        foreach ($articles as $article) {
            $topicId = $this->topicIdForSlug((string) $article->topic_key);
            if ($topicId === null) {
                continue;
            }
            $conf = (float) ($article->topic_confidence ?? $article->verification_confidence ?? 0.5);
            $age = $this->ageDays($article->published_at ?? $article->created_at);
            $decay = exp(-$age / max($halfLife, 1.0));
            $acc[$topicId]['news'] = ($acc[$topicId]['news'] ?? 0.0) + $conf * $decay;
            $acc[$topicId]['news_count'] = ($acc[$topicId]['news_count'] ?? 0) + 1;
        }

        // ── Viral moments (classify title unless topic_key stored) ────────
        $moments = PoliticianViralMoment::query()
            ->where('politician_id', $politician->id)
            ->where(function ($q) use ($since) {
                $q->where('published_at', '>=', $since)->orWhere('captured_at', '>=', $since);
            })
            ->get(['id', 'title', 'topic_key', 'topic_confidence', 'published_at', 'captured_at']);

        foreach ($moments as $moment) {
            $slug = (string) ($moment->topic_key ?? '');
            $confidence = (float) ($moment->topic_confidence ?? 0.0);
            if ($slug !== '' && $confidence > 0.0) {
                $topicId = $this->topicIdForSlug($slug);
            } else {
                $result = $this->classifier->classify((string) $moment->title);
                $topicId = $result['topic_id'];
                $confidence = $result['confidence'];
                // Cache the classification back onto the row so re-runs skip the LLM.
                if ($topicId !== null && $slug === '') {
                    $moment->updateQuietly([
                        'topic_key' => $result['topic_slug'],
                        'topic_confidence' => $confidence,
                    ]);
                }
            }
            if ($topicId === null) {
                continue;
            }
            $age = $this->ageDays($moment->published_at ?? $moment->captured_at);
            $decay = exp(-$age / max($halfLife, 1.0));
            $acc[$topicId]['viral'] = ($acc[$topicId]['viral'] ?? 0.0) + max($confidence, 0.1) * $decay;
            $acc[$topicId]['viral_count'] = ($acc[$topicId]['viral_count'] ?? 0) + 1;
        }

        // ── Vote Smart NPAT issue positions (classify issue text) ─────────
        if ($politician->show_votesmart_data) {
            try {
                $ratings = $this->voteSmart->fetchPoliticianRatings($politician);
                foreach ($ratings['issue_positions'] ?? [] as $position) {
                    $text = trim((string) ($position['issue'] ?? '').' '.(string) ($position['position'] ?? ''));
                    $result = $this->classifier->classify($text);
                    $topicId = $result['topic_id'];
                    if ($topicId === null) {
                        continue;
                    }
                    // NPAT positions are self-stated and undated → full recency weight.
                    $acc[$topicId]['votesmart'] = ($acc[$topicId]['votesmart'] ?? 0.0) + max($result['confidence'], 0.5);
                    $acc[$topicId]['votesmart_count'] = ($acc[$topicId]['votesmart_count'] ?? 0) + 1;
                }
            } catch (\Throwable $e) {
                Log::info('PoliticianTopicSignalService: Vote Smart positions skipped', [
                    'politician_id' => $politician->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // ── Materialize signals (persist, or hydrate unsaved for dry-run) ──
        $rows = [];
        foreach ($acc as $topicId => $a) {
            $rows[$topicId] = $this->rowFor($topicId, $a, $politician, $wNews, $wViral, $wVoteSmart);
        }

        if (! $persist) {
            $topics = PoliticianTopic::whereIn('id', array_keys($rows))->get()->keyBy('id');

            return collect($rows)
                ->sortByDesc(fn (array $row) => $row['total_score'])
                ->values()
                ->map(function (array $row) use ($topics) {
                    $signal = new PoliticianTopicSignal(collect($row)->except(['created_at', 'updated_at'])->toArray());
                    $signal->setRelation('topic', $topics->get($row['topic_id']));

                    return $signal;
                });
        }

        return DB::transaction(function () use ($politician, $rows) {
            $seenIds = array_keys($rows);

            foreach ($rows as $row) {
                PoliticianTopicSignal::updateOrCreate(
                    ['politician_id' => $row['politician_id'], 'topic_id' => $row['topic_id']],
                    collect($row)->except(['created_at'])->toArray(),
                );
            }

            // Drop signals for topics with no evidence this run (stale cleanup).
            PoliticianTopicSignal::where('politician_id', $politician->id)
                ->whereKeyNot($seenIds ?: [0])
                ->delete();

            return $politician->topicSignals()->topByScore()->get();
        });
    }

    /**
     * Build a politician_topic_signals row array from one topic's aggregates.
     *
     * @param  array<string, float|int>  $a
     * @return array<string, mixed>
     */
    protected function rowFor(int $topicId, array $a, Politician $politician, float $wNews, float $wViral, float $wVoteSmart): array
    {
        $newsAgg = (float) ($a['news'] ?? 0.0);
        $viralAgg = (float) ($a['viral'] ?? 0.0);
        $votesmartAgg = (float) ($a['votesmart'] ?? 0.0);
        $total = $wNews * $newsAgg + $wViral * $viralAgg + $wVoteSmart * $votesmartAgg;

        return [
            'politician_id' => $politician->id,
            'topic_id' => $topicId,
            'news_count' => (int) ($a['news_count'] ?? 0),
            'viral_moment_count' => (int) ($a['viral_count'] ?? 0),
            'votesmart_count' => (int) ($a['votesmart_count'] ?? 0),
            'total_score' => round($total, 4),
            'score_components' => json_encode([
                'news' => round($wNews * $newsAgg, 4),
                'viral_moment' => round($wViral * $viralAgg, 4),
                'votesmart' => round($wVoteSmart * $votesmartAgg, 4),
            ]),
            'last_seen_at' => now(),
            'updated_at' => now(),
            'created_at' => now(),
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    /**
     * Active topic slug (lowercased) → id map, cached 5 min.
     *
     * @return array<string, int>
     */
    protected function topicIdForSlugMap(): array
    {
        return Cache::remember('issues:slug-to-id', 300, function () {
            return PoliticianTopic::query()
                ->where('is_active', true)
                ->get(['id', 'slug'])
                ->mapWithKeys(fn (PoliticianTopic $t) => [strtolower((string) $t->slug) => (int) $t->id])
                ->all();
        });
    }

    protected function topicIdForSlug(string $slug): ?int
    {
        $slug = strtolower(trim($slug));
        if ($slug === '') {
            return null;
        }

        return $this->topicIdForSlugMap()[$slug] ?? null;
    }

    protected function ageDays(?Carbon $at): float
    {
        if ($at === null) {
            return 0.0;
        }

        return max(now()->getTimestamp() - $at->getTimestamp(), 0) / 86400.0;
    }
}
