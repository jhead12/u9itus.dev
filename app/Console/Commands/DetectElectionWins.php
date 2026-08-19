<?php

namespace App\Console\Commands;

use App\Models\CandidateNewsArticle;
use App\Models\Politician;
use App\Models\StateElectionDate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Detects election wins from RSS-sourced news coverage already sitting in
 * candidate_news_articles, as a substitute for the Ballotpedia-scrape-based
 * politicians:import-election-results pipeline while Ballotpedia's WAF
 * blocks GitHub Actions runners. Runs entirely on Laravel's own scheduler —
 * no GitHub Actions, no Ballotpedia — so it isn't affected by that outage.
 *
 * Pipeline: for each still-running candidate in a state whose primary/
 * general election_date recently passed, cheap-filter that candidate's
 * cached news articles for win-signal language mentioning their name, then
 * ask Claude to confirm from the actual headline/snippet text before
 * writing anything. Mirrors ImportElectionResults::buildUpdates()'s 'won'
 * branch exactly (term_status=seated, won_at=now()) so the "Recently Won"
 * badge works identically regardless of which pipeline set it.
 */
class DetectElectionWins extends Command
{
    protected $signature = 'politicians:detect-election-wins
        {--limit=100          : Max candidates to check per run}
        {--lookback-days=21   : Only consider states whose election_date fell within this many trailing days}
        {--article-days=10    : Only consider news articles published within this many trailing days}
        {--cooldown-hours=12  : Do not re-check the same politician within this many hours}
        {--confidence=0.85    : Minimum LLM confidence required to record a win}
        {--dry-run            : Report matches without writing to the database}';

    protected $description = 'Detect election wins from RSS news coverage (candidate_news_articles) via LLM classification, as a Ballotpedia-scrape substitute.';

    private const WON_SIGNALS = '/\bwins?\b|\bwon\b|\belected\b|\bdefeats?\b|\bdeclared\s+winner\b|\bvictory\b|\bclinches?\b|\bunseats?\b|\bprevails?\b|\bnominee\b/i';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $lookbackDays = max(0, (int) $this->option('lookback-days'));
        $articleDays = max(0, (int) $this->option('article-days'));
        $cooldownHours = max(0, (int) $this->option('cooldown-hours'));
        $confidenceThreshold = (float) $this->option('confidence');
        $dryRun = (bool) $this->option('dry-run');

        $apiKey = (string) config('services.anthropic.api_key');
        if ($apiKey === '') {
            $this->warn('ANTHROPIC_API_KEY is not configured — skipping (this command requires AI confirmation before recording a win).');
            return self::SUCCESS;
        }

        $eligibleStates = StateElectionDate::query()
            ->whereNotNull('election_date')
            ->whereBetween('election_date', [
                now()->subDays($lookbackDays)->toDateString(),
                now()->toDateString(),
            ])
            ->pluck('state')
            ->map(fn ($s) => strtoupper((string) $s))
            ->unique()
            ->values();

        if ($eligibleStates->isEmpty()) {
            $this->info("No state had an election_date within the last {$lookbackDays} day(s).");
            return self::SUCCESS;
        }

        $candidates = Politician::query()
            ->whereIn('state', $eligibleStates)
            ->where('is_active', true)
            ->where('term_status', '!=', 'seated')
            ->where(function ($q) {
                $q->where('is_running_candidate', true)->orWhere('term_status', 'running');
            })
            ->limit($limit)
            ->get();

        if ($candidates->isEmpty()) {
            $this->info('No still-running candidates found in states with a recent election.');
            return self::SUCCESS;
        }

        $checked = 0;
        $confirmed = 0;
        $skippedCooldown = 0;
        $noSignal = 0;

        foreach ($candidates as $politician) {
            $cacheKey = "election_win_detect:{$politician->id}";
            if (Cache::has($cacheKey)) {
                $skippedCooldown++;
                continue;
            }

            $articles = CandidateNewsArticle::query()
                ->forCandidate($politician->id, (string) $politician->full_name)
                ->where('published_at', '>=', now()->subDays($articleDays))
                ->orderByDesc('published_at')
                ->limit(15)
                ->get();

            $nameNeedle = strtolower((string) $politician->full_name);
            $matches = $articles->filter(function (CandidateNewsArticle $a) use ($nameNeedle) {
                $text = strtolower($a->headline . ' ' . $a->snippet);
                return str_contains($text, $nameNeedle) && preg_match(self::WON_SIGNALS, $text) === 1;
            })->take(5);

            if ($matches->isEmpty()) {
                $noSignal++;
                Cache::put($cacheKey, true, now()->addHours($cooldownHours));
                continue;
            }

            $checked++;

            $stage = StateElectionDate::query()
                ->where('state', $politician->state)
                ->whereNotNull('election_date')
                ->whereBetween('election_date', [now()->subDays($lookbackDays)->toDateString(), now()->toDateString()])
                ->orderByDesc('election_date')
                ->value('stage_name') ?? 'Primary or General';

            $result = $this->classify($politician, $stage, $matches, $apiKey);
            Cache::put($cacheKey, true, now()->addHours($cooldownHours));

            if ($result === null) {
                continue;
            }

            $this->line(sprintf(
                '  [%s] %s (%s) — is_win=%s confidence=%.2f — %s',
                $result['is_win'] ? 'WIN' : 'no',
                $politician->full_name,
                $politician->state,
                $result['is_win'] ? 'yes' : 'no',
                $result['confidence'],
                $result['reason']
            ));

            if ($result['is_win'] && $result['confidence'] >= $confidenceThreshold) {
                $confirmed++;

                if (! $dryRun) {
                    $politician->update([
                        'term_status' => 'seated',
                        'is_running_candidate' => false,
                        'is_active' => true,
                        'status_updated_at' => now(),
                        'won_at' => now(),
                    ]);
                }
            }
        }

        $suffix = $dryRun ? ' (dry-run)' : '';
        $this->info(sprintf(
            "Detection complete%s: %d win-signal candidate(s) checked via AI, %d confirmed, %d had no signal, %d skipped (cooldown).",
            $suffix, $checked, $confirmed, $noSignal, $skippedCooldown
        ));

        return self::SUCCESS;
    }

    /**
     * @param \Illuminate\Support\Collection<int, CandidateNewsArticle> $articles
     * @return array{is_win: bool, confidence: float, reason: string}|null
     */
    private function classify(Politician $politician, string $stage, \Illuminate\Support\Collection $articles, string $apiKey): ?array
    {
        $stateName = (string) (config('u9itus.us_states')[$politician->state] ?? $politician->state);
        $office = $politician->political_office ?? 'office unknown';

        $articlesText = $articles->map(fn (CandidateNewsArticle $a) => sprintf(
            "- \"%s\" (%s): %s",
            $a->headline,
            $a->source_name,
            \Illuminate\Support\Str::limit((string) $a->snippet, 200)
        ))->implode("\n");

        try {
            $response = Http::timeout(20)
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => config('services.anthropic.model', 'claude-haiku-4-5'),
                    'max_tokens' => 200,
                    'temperature' => 0,
                    'system' => 'You confirm election-win news for a civic-data site from real news headlines/snippets. '
                        . 'Return ONLY compact JSON with keys: is_win (boolean), confidence (0-1), reason (short string). '
                        . 'Only set is_win=true if the text clearly confirms THIS SPECIFIC PERSON won their own primary '
                        . 'or general election for this office — not a poll, endorsement, prediction, or a different '
                        . "race entirely.",
                    'messages' => [[
                        'role' => 'user',
                        'content' => "Candidate: {$politician->full_name}\nState: {$stateName}\nOffice: {$office}\n"
                            . "Likely election stage: {$stage}\n\nRecent news headlines/snippets:\n{$articlesText}\n\n"
                            . 'Did this candidate win? Output JSON only.',
                    ]],
                ]);
        } catch (\Throwable $e) {
            Log::warning('DetectElectionWins: anthropic request failed', ['politician_id' => $politician->id, 'error' => $e->getMessage()]);
            return null;
        }

        if (! $response->ok()) {
            Log::warning('DetectElectionWins: anthropic error', ['status' => $response->status(), 'body' => $response->body()]);
            return null;
        }

        $text = (string) ($response->json('content.0.text') ?? '');
        if ($text === '' || preg_match('/\{.*\}/s', $text, $m) !== 1) {
            return null;
        }

        $ai = json_decode($m[0], true);
        if (! is_array($ai)) {
            return null;
        }

        return [
            'is_win' => (bool) ($ai['is_win'] ?? false),
            'confidence' => (float) ($ai['confidence'] ?? 0.0),
            'reason' => (string) ($ai['reason'] ?? ''),
        ];
    }
}
