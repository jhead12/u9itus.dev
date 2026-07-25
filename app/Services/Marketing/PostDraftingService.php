<?php

namespace App\Services\Marketing;

use App\Enums\MarketingDraftStatus;
use App\Enums\MarketingSourceType;
use App\Enums\PostStatus;
use App\Models\CandidateNewsArticle;
use App\Models\MarketingPostDraft;
use App\Models\Politician;
use App\Models\PoliticianViralMoment;
use App\Models\Post;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Marketing content agent — turns a politician's recent news / viral moments
 * into human-reviewable blog Post drafts.
 *
 * For one politician, selects the best un-drafted source (top eligible viral
 * moment above a score threshold, else most-recent verified news), asks
 * Claude to draft factual post copy (mirroring the ProfileEnricherService
 * Anthropic house pattern), resolves a featured image from the source, and
 * inside a transaction creates a Post (status = PendingApproval, authored to
 * the Politician) plus a MarketingPostDraft provenance row stamped with a
 * unique source_hash so the source is never re-drafted.
 *
 * Never throws — every failure routes into a returned `failed`/`skipped`
 * result, mirroring the MarketingChannel contract's degrade-to-result style.
 * Nothing is auto-published: the draft only becomes visible when a human
 * publishes it via PostController::publish(), and the body carries a visible
 * "Auto-drafted by u9'itus…" provenance footer.
 */
class PostDraftingService
{
    protected ?string $apiKey;
    protected ?string $anthropicModel;
    protected bool $enabled;
    protected float $scoreThreshold;
    protected int $newsRecencyLimit;
    protected int $viralEligibleDays;

    public function __construct()
    {
        $this->apiKey            = config('services.anthropic.api_key');
        $this->anthropicModel    = config('u9itus.marketing.drafting.model')
            ?: config('services.anthropic.model', 'claude-haiku-4-5');
        $this->enabled           = (bool) config('u9itus.marketing.enabled', true);
        $this->scoreThreshold    = (float) config('u9itus.marketing.drafting.moment_score_threshold', 0.0);
        $this->newsRecencyLimit  = (int) config('u9itus.marketing.drafting.news_recency_limit', 60);
        $this->viralEligibleDays = (int) config('u9itus.marketing.drafting.viral_eligible_days', 30);
    }

    /** True when the agent should run: master flag on + Anthropic key set. */
    public function isConfigured(): bool
    {
        return $this->enabled && !empty($this->apiKey);
    }

    /**
     * Draft a PendingApproval Post for one politician from their freshest
     * un-drafted source. Returns a result shape — never throws.
     *
     * @return array{status: string, source_type: ?string, post_id: ?int, error: ?string}
     */
    public function draftForPolitician(int $politicianId, ?string $sourceFilter = null): array
    {
        if (!$this->isConfigured()) {
            return $this->result('skipped', null, null, 'not_configured');
        }

        $politician = Politician::find($politicianId);
        if (!$politician) {
            return $this->result('skipped', null, null, 'politician_not_found');
        }

        $source = null;
        $hash = null;

        try {
            $source = $this->selectSource($politician, $sourceFilter);
            if ($source === null) {
                return $this->result('skipped', null, null, 'no_fresh_source');
            }

            $sourceType = $source['type'];
            $hash = $this->sourceHash($politician, $sourceType, $source['model']->id);

            $copy = $this->generateCopy($politician, $source);
            if ($copy === null) {
                $this->recordFailure($politician, $source, $hash, 'copy_generation_failed');
                return $this->result('failed', $sourceType->value, null, 'copy_generation_failed');
            }

            $image = $this->resolveFeaturedImage($politician, $source);

            return DB::transaction(function () use ($politician, $source, $sourceType, $hash, $copy, $image) {
                // Race-safe dedup: a posted row for this source means done.
                if ($this->alreadyPosted($hash)) {
                    return $this->result('skipped', $sourceType->value, null, 'duplicate');
                }
                // Clear any prior `failed` attempt for this source so the
                // unique source_hash can take the new `posted` row.
                MarketingPostDraft::where('source_hash', $hash)
                    ->where('status', '!=', MarketingDraftStatus::Posted->value)
                    ->delete();

                $body = $this->withFooter($copy['body'] ?? '', $politician, $source);

                $post = Post::create([
                    'author_type'        => Politician::class,
                    'author_id'          => $politician->id,
                    'title'              => $this->trunc($copy['title'] ?? 'Untitled draft', 120),
                    'subtitle'           => $this->trunc($copy['subtitle'] ?? null, 255),
                    'excerpt'            => $this->trunc($copy['excerpt'] ?? null, 500),
                    'body'               => $body,
                    'featured_image_url' => $image,
                    'status'             => PostStatus::PendingApproval->value,
                    'published_at'       => null,
                    'meta_description'   => $this->trunc($copy['meta_description'] ?? null, 500),
                    'allow_comments'     => true,
                ]);

                MarketingPostDraft::create([
                    'politician_id'      => $politician->id,
                    'post_id'            => $post->id,
                    'source_type'        => $sourceType->value,
                    'source_id'          => $source['model']->id,
                    'source_hash'        => $hash,
                    'source_url'         => $source['url'],
                    'source_title'       => $source['title'],
                    'generated_title'     => $copy['title'] ?? null,
                    'generated_excerpt'  => $copy['excerpt'] ?? null,
                    'generated_body'     => $body,
                    'featured_image_url' => $image,
                    'status'             => MarketingDraftStatus::Posted->value,
                    'generated_at'       => now(),
                ]);

                return $this->result('posted', $sourceType->value, $post->id, null);
            });
        } catch (QueryException $e) {
            if ($this->isDuplicateViolation($e)) {
                return $this->result('skipped', $source['type']->value ?? null, null, 'duplicate');
            }
            return $this->handleFailure($politician, $source, $hash, $e->getMessage());
        } catch (\Throwable $e) {
            return $this->handleFailure($politician, $source, $hash, $e->getMessage());
        }
    }

    // ── Source selection ──────────────────────────────────────────────────

    /**
     * Pick the best un-drafted source for a politician. Prefer a top-scored
     * eligible viral moment above the threshold; fall back to the most-recent
     * verified news article. Uses the stored-only model query paths (never
     * CandidateNewsService, which can trigger a provider fetch on cache miss).
     *
     * @return array{type: MarketingSourceType, model: Model, url: ?string, title: ?string, image_url: ?string, source_name: string}|null
     */
    protected function selectSource(Politician $politician, ?string $sourceFilter): ?array
    {
        if ($sourceFilter !== 'news') {
            $moment = $this->firstUndraftedViralMoment($politician);
            if ($moment !== null) {
                return $moment;
            }
        }

        if ($sourceFilter !== 'viral') {
            $article = $this->firstUndraftedNewsArticle($politician);
            if ($article !== null) {
                return $article;
            }
        }

        return null;
    }

    /**
     * @return array{type: MarketingSourceType, model: PoliticianViralMoment, url: ?string, title: ?string, image_url: ?string, source_name: string}|null
     */
    protected function firstUndraftedViralMoment(Politician $politician): ?array
    {
        $drafted = $this->postedHashes($politician, MarketingSourceType::ViralMoment);

        $moments = $politician->viralMoments()
            ->eligible($this->viralEligibleDays)
            ->topByScore()
            ->get();

        foreach ($moments as $moment) {
            if ((float) $moment->moment_score < $this->scoreThreshold) {
                continue;
            }
            $hash = $this->sourceHash($politician, MarketingSourceType::ViralMoment, $moment->id);
            if (in_array($hash, $drafted, true)) {
                continue;
            }
            return [
                'type'        => MarketingSourceType::ViralMoment,
                'model'       => $moment,
                'url'         => $moment->url,
                'title'       => $moment->title,
                'image_url'   => $moment->thumbnail_url,
                'source_name' => $this->prettySource($moment->source),
            ];
        }

        return null;
    }

    /**
     * @return array{type: MarketingSourceType, model: CandidateNewsArticle, url: ?string, title: ?string, image_url: ?string, source_name: string}|null
     */
    protected function firstUndraftedNewsArticle(Politician $politician): ?array
    {
        $drafted = $this->postedHashes($politician, MarketingSourceType::News);

        $articles = CandidateNewsArticle::query()
            ->where('politician_id', $politician->id)
            ->verified()
            ->recent($this->newsRecencyLimit)
            ->get();

        foreach ($articles as $article) {
            $hash = $this->sourceHash($politician, MarketingSourceType::News, $article->id);
            if (in_array($hash, $drafted, true)) {
                continue;
            }
            return [
                'type'        => MarketingSourceType::News,
                'model'       => $article,
                'url'         => $article->source_url,
                'title'       => $article->headline,
                'image_url'   => $article->image_url,
                'source_name' => $article->source_name ?: 'news',
            ];
        }

        return null;
    }

    // ── Copy generation (Claude) ───────────────────────────────────────────

    /**
     * Ask Claude to draft factual post copy. Mirrors
     * ProfileEnricherService::extractWithClaude — raw Http POST to the
     * Anthropic Messages API, strict JSON return, degrade-to-null on failure.
     *
     * @return array{title: string, subtitle: ?string, excerpt: ?string, body: string, meta_description: ?string}|null
     */
    public function generateCopy(Politician $politician, array $source): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        try {
            $system = 'You are a civic news drafting assistant. From the provided source '
                . 'material about a U.S. politician, draft a short factual blog Post in JSON. '
                . 'Cite the source by name and URL. Do not editorialize, do not fabricate quotes '
                . 'or events, and omit any fact you are not certain is supported by the source. '
                . 'Output strict JSON only — no markdown fences, no explanation.';

            $shape = '{"title":"short headline (<=120 chars)","subtitle":null,'
                . '"excerpt":"1-2 sentence summary (<=500 chars)",'
                . '"body":"HTML string of <p> paragraphs only, no footer",'
                . '"meta_description":null}';

            $context = 'Politician: ' . $politician->full_name
                . ($politician->political_office ? ' (' . $politician->political_office . ')' : '')
                . ($politician->state ? ', ' . $politician->state : '')
                . "\nSource type: " . $source['type']->value
                . "\nSource name: " . $source['source_name']
                . "\nSource title: " . ($source['title'] ?? '')
                . "\nSource URL: " . ($source['url'] ?? '')
                . "\n\nSource material:\n" . $this->sourceExcerpt($source);

            $user = 'Draft a factual blog Post from this source as JSON with this exact shape: '
                . $shape . "\n\n"
                . "Rules: attribute every claim to the source; do not invent quotes, statistics, "
                . "or events; keep body to 2-4 short paragraphs of <p> tags only; do not include a "
                . "provenance/attribution footer (one is appended by the system).\n\n"
                . $context;

            $response = Http::timeout(30)
                ->withHeaders([
                    'x-api-key'         => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'Content-Type'      => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model'      => $this->anthropicModel,
                    'max_tokens' => 1200,
                    'system'     => $system,
                    'messages'   => [['role' => 'user', 'content' => $user]],
                ]);

            if (!$response->ok()) {
                Log::warning('PostDraftingService: Claude HTTP failure', [
                    'status' => $response->status(),
                ]);
                return null;
            }

            $text = trim($response->json('content.0.text') ?? '');
            if (preg_match('/\{.*\}/s', $text, $m)) {
                $text = $m[0];
            }
            $decoded = json_decode($text, true);
            if (!is_array($decoded) || empty($decoded['title']) || empty($decoded['body'])) {
                Log::warning('PostDraftingService: Claude returned invalid JSON');
                return null;
            }

            return [
                'title'            => (string) $decoded['title'],
                'subtitle'         => $this->nullableString($decoded['subtitle'] ?? null),
                'excerpt'          => $this->nullableString($decoded['excerpt'] ?? null),
                'body'             => (string) $decoded['body'],
                'meta_description' => $this->nullableString($decoded['meta_description'] ?? null),
            ];
        } catch (\Throwable $e) {
            Log::warning('PostDraftingService: Claude call failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    // ── Image + footer ────────────────────────────────────────────────────

    /**
     * Featured image priority: the source's own image (viral thumbnail or
     * news image) → the politician's profile photo. All are remote URLs
     * written directly to Post.featured_image_url; the body sanitizer allows
     * remote https <img> tags, so no re-hosting is needed.
     */
    protected function resolveFeaturedImage(Politician $politician, array $source): ?string
    {
        $image = $source['image_url'] ?? null;
        if (!empty($image)) {
            return $image;
        }
        return $politician->profile_photo_url ?: null;
    }

    /** Append the locked provenance footer to the generated body. */
    protected function withFooter(string $body, Politician $politician, array $source): string
    {
        $url = $source['url'] ?? '';
        $name = $source['source_name'] ?? 'source';
        $date = now()->format('Y-m-d');
        $linked = $url !== ''
            ? '<a href="' . e($url) . '">' . e($url) . '</a>'
            : e($name);

        return trim($body)
            . "\n"
            . '<p><em>Auto-drafted by u9\'itus from ' . e($name) . ' (' . $linked . ') on ' . $date
            . '; reviewed and published by ' . e($politician->full_name) . '.</em></p>';
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    protected function sourceHash(Politician $politician, MarketingSourceType $type, int $sourceId): string
    {
        return hash('sha256', $politician->id . '|' . $type->value . '|' . $sourceId);
    }

    /** source_hash values of already-`posted` drafts for a politician+type. */
    protected function postedHashes(Politician $politician, MarketingSourceType $type): array
    {
        return MarketingPostDraft::query()
            ->where('politician_id', $politician->id)
            ->where('source_type', $type->value)
            ->where('status', MarketingDraftStatus::Posted->value)
            ->pluck('source_hash')
            ->all();
    }

    protected function alreadyPosted(string $hash): bool
    {
        return MarketingPostDraft::query()
            ->where('source_hash', $hash)
            ->where('status', MarketingDraftStatus::Posted->value)
            ->exists();
    }

    protected function recordFailure(Politician $politician, array $source, string $hash, string $error): void
    {
        try {
            MarketingPostDraft::create([
                'politician_id'  => $politician->id,
                'post_id'        => null,
                'source_type'    => $source['type']->value,
                'source_id'      => $source['model']->id,
                'source_hash'    => $hash,
                'source_url'     => $source['url'] ?? null,
                'source_title'   => $source['title'] ?? null,
                'status'         => MarketingDraftStatus::Failed->value,
                'error'          => $this->trunc($error, 255),
                'generated_at'   => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('PostDraftingService: could not record failure row', ['error' => $e->getMessage()]);
        }
    }

    protected function handleFailure(Politician $politician, ?array $source, ?string $hash, string $error): array
    {
        if ($source !== null && $hash !== null) {
            $this->recordFailure($politician, $source, $hash, $error);
        } else {
            Log::warning('PostDraftingService: failure before source resolved', [
                'politician_id' => $politician->id,
                'error'         => $error,
            ]);
        }
        return $this->result('failed', $source['type']->value ?? null, null, $error);
    }

    protected function isDuplicateViolation(QueryException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? null;
        return $sqlState === '23000' || str_contains(strtolower($e->getMessage()), 'unique');
    }

    protected function prettySource(?string $source): string
    {
        if ($source === null || $source === '') {
            return 'source';
        }
        return match (strtolower($source)) {
            'cspan'    => 'C-SPAN',
            'youtube'  => 'YouTube',
            'news'     => 'news',
            default     => ucfirst($source),
        };
    }

    protected function sourceExcerpt(array $source): string
    {
        $model = $source['model'];
        if ($model instanceof CandidateNewsArticle) {
            return trim((string) ($model->snippet ?? $model->headline ?? ''));
        }
        if ($model instanceof PoliticianViralMoment) {
            return trim((string) ($model->title ?? ''));
        }
        return '';
    }

    protected function nullableString(mixed $v): ?string
    {
        $v = is_string($v) ? trim($v) : $v;
        return (is_string($v) && $v !== '') ? $v : null;
    }

    protected function trunc(?string $v, int $max): ?string
    {
        if ($v === null) {
            return null;
        }
        return mb_strlen($v) > $max ? mb_substr($v, 0, $max) : $v;
    }

    /**
     * @return array{status: string, source_type: ?string, post_id: ?int, error: ?string}
     */
    protected function result(string $status, ?string $sourceType, ?int $postId, ?string $error): array
    {
        return [
            'status'      => $status,
            'source_type' => $sourceType,
            'post_id'     => $postId,
            'error'       => $error,
        ];
    }
}