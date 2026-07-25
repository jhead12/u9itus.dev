<?php

namespace App\Services;

use App\Models\PoliticianTopic;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Classifies a snippet of political discourse (a news headline+snippet, a
 * C-SPAN/YouTube clip title, a Vote Smart NPAT issue) into one issue topic from
 * the politician_topics catalog.
 *
 * Two tiers, mirroring ProfileEnricherService's heuristic-then-Claude pattern:
 *   1. Keyword tier — substring scoring against active topic slugs/names,
 *      ported from CandidateNewsService::extractTopicKey. Fast, free, runs on
 *      every clip.
 *   2. LLM fallback — when the keyword tier is unconfident and the LLM is
 *      configured, a single Claude (haiku) call maps the text to a topic_id
 *      with strict JSON output. Mirrors ProfileEnricherService::extractWithClaude.
 *
 * Returns a normalized result regardless of which tier matched:
 *   ['topic_slug' => ?string, 'topic_id' => ?int, 'confidence' => float, 'method' => 'keyword'|'llm'|'none']
 *
 * Gated by config('u9itus.issues.enabled') and config('u9itus.issues.llm_fallback').
 * News articles already carry a stored topic_key (set by CandidateNewsService),
 * so callers should prefer the stored value and only invoke this for viral
 * moments / Vote Smart positions / untagged text.
 */
class IssueClassifierService
{
    protected ?string $apiKey;

    protected ?string $model;

    /** Keyword confidence floor (matches CandidateNewsService::extractTopicKey). */
    protected float $keywordThreshold = 0.55;

    public function __construct()
    {
        $this->apiKey = config('services.anthropic.api_key');
        $this->model = (string) (config('u9itus.issues.llm_model') ?: config('services.anthropic.model', 'claude-haiku-4-5'));
    }

    public function isLlmConfigured(): bool
    {
        return (bool) config('u9itus.issues.enabled', true)
            && (bool) config('u9itus.issues.llm_fallback', true)
            && ! empty($this->apiKey);
    }

    /**
     * @return array{topic_slug: string|null, topic_id: int|null, confidence: float, method: string}
     */
    public function classify(string $text): array
    {
        $none = ['topic_slug' => null, 'topic_id' => null, 'confidence' => 0.0, 'method' => 'none'];

        $text = trim($text);
        if ($text === '') {
            return $none;
        }

        // ── Tier 1: keyword ───────────────────────────────────────────────
        $keyword = $this->classifyKeyword($text);
        if ($keyword['topic_id'] !== null && $keyword['confidence'] >= $this->keywordThreshold) {
            return $keyword;
        }

        // ── Tier 2: LLM fallback ──────────────────────────────────────────
        if ($this->isLlmConfigured()) {
            // Return the LLM result regardless of whether it matched, so the
            // `method` field reflects that the LLM was actually consulted (a
            // null topic_id with method='llm' means "LLM tried, no fit").
            return $this->classifyWithClaude($text);
        }

        // Keyword-only deployment: surface the (unconfident) keyword result so
        // callers know the keyword tier ran but didn't clear the threshold.
        return $keyword;
    }

    // ── Tier 1: keyword ───────────────────────────────────────────────────

    /**
     * Substring scoring against active topic slugs/names, ported from
     * CandidateNewsService::extractTopicKey but also resolving the topic_id.
     *
     * @return array{topic_slug: string|null, topic_id: int|null, confidence: float, method: string}
     */
    protected function classifyKeyword(string $text): array
    {
        $haystack = Str::lower($text);
        $best = null;
        $bestScore = 0.0;

        foreach ($this->topicCatalog() as $topic) {
            $slug = (string) ($topic['slug'] ?? '');
            $name = (string) ($topic['name'] ?? '');
            if ($slug === '' && $name === '') {
                continue;
            }

            $score = 0.0;
            if ($slug !== '' && str_contains($haystack, str_replace('-', ' ', $slug))) {
                $score += 0.65;
            }
            if ($name !== '' && str_contains($haystack, $name)) {
                $score += 0.55;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $topic;
            }
        }

        if ($best === null) {
            return ['topic_slug' => null, 'topic_id' => null, 'confidence' => 0.0, 'method' => 'keyword'];
        }

        return [
            'topic_slug' => $best['slug'] ?: null,
            'topic_id' => $best['id'] ?? null,
            'confidence' => round(min(1.0, $bestScore), 3),
            'method' => 'keyword',
        ];
    }

    // ── Tier 2: LLM fallback ──────────────────────────────────────────────

    /**
     * Ask Claude to map the text to a topic_id from the catalog, strict JSON.
     * Mirrors ProfileEnricherService::extractWithClaude (system prompt, strict
     * JSON, degrade to null on failure, log).
     *
     * @return array{topic_slug: string|null, topic_id: int|null, confidence: float, method: string}
     */
    protected function classifyWithClaude(string $text): array
    {
        $none = ['topic_slug' => null, 'topic_id' => null, 'confidence' => 0.0, 'method' => 'llm'];

        try {
            $catalog = $this->topicCatalogForPrompt();

            $system = 'You are a civic-issue classifier. Given a snippet of political discourse and a '
                .'catalog of issue topics, return the single topic_id that best matches what the snippet '
                .'is about, or null if none fit. Output strict JSON and nothing else — no markdown fences, '
                .'no explanation. Match by the issue actually being discussed, not a coincidental word.';

            $shape = '{"topic_id": <int|null>, "confidence": <0.0-1.0>}';

            $user = "Catalog (topic_id → name):\n".$catalog."\n\n"
                .'Rules: pick the topic the snippet is substantively about; if it only mentions a word in '
                ."passing, prefer null. confidence reflects how clearly the topic is the subject.\n\n"
                ."Snippet:\n".mb_substr($text, 0, 2000)."\n\n"
                .'Return JSON with this exact shape: '.$shape;

            $response = Http::timeout(15)
                ->withHeaders([
                    'x-api-key' => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => $this->model,
                    'max_tokens' => 200,
                    'system' => $system,
                    'messages' => [['role' => 'user', 'content' => $user]],
                ]);

            if (! $response->ok()) {
                $this->logHttpFailure('classify_with_claude', $response->status());

                return $none;
            }

            $raw = trim($response->json('content.0.text') ?? '');
            if (preg_match('/\{.*\}/s', $raw, $m)) {
                $raw = $m[0];
            }
            $decoded = json_decode($raw, true);
            if (! is_array($decoded) || ! array_key_exists('topic_id', $decoded)) {
                return $none;
            }

            $topicId = $decoded['topic_id'] === null ? null : (int) $decoded['topic_id'];
            if ($topicId === null) {
                return $none;
            }

            $slug = $this->slugForId($topicId);
            if ($slug === null) {
                // LLM hallucinated a topic_id not in the catalog — discard.
                return $none;
            }

            return [
                'topic_slug' => $slug,
                'topic_id' => $topicId,
                'confidence' => round(min(1.0, max(0.0, (float) ($decoded['confidence'] ?? 0.0))), 3),
                'method' => 'llm',
            ];
        } catch (\Throwable $e) {
            $this->logProviderException('classify_with_claude', $e);

            return $none;
        }
    }

    // ── Catalog helpers ───────────────────────────────────────────────────

    /**
     * Active topic catalog for scoring: [{id, slug, name}] lowercased for match.
     * Cached 5 min (separate key from CandidateNewsService since we need the id).
     *
     * @return list<array{id: int, slug: string, name: string}>
     */
    protected function topicCatalog(): array
    {
        return Cache::remember('issues:topic-catalog', 300, function () {
            return PoliticianTopic::query()
                ->where('is_active', true)
                ->get(['id', 'slug', 'name'])
                ->map(fn (PoliticianTopic $t) => [
                    'id' => (int) $t->id,
                    'slug' => strtolower((string) $t->slug),
                    'name' => strtolower((string) $t->name),
                ])
                ->all();
        });
    }

    /**
     * Compact catalog string for the LLM prompt: "1 → Healthcare\n2 → Climate Action".
     */
    protected function topicCatalogForPrompt(): string
    {
        return collect($this->topicCatalog())
            ->map(fn ($t) => "{$t['id']} → ".ucwords(str_replace('-', ' ', $t['slug'])))
            ->implode("\n");
    }

    protected function slugForId(int $topicId): ?string
    {
        foreach ($this->topicCatalog() as $t) {
            if ($t['id'] === $topicId) {
                return $t['slug'];
            }
        }

        return null;
    }

    // ── Logging ───────────────────────────────────────────────────────────

    protected function logHttpFailure(string $operation, int $status): void
    {
        Log::warning('IssueClassifierService telemetry: HTTP request failed', [
            'operation' => $operation,
            'status' => $status,
        ]);
    }

    protected function logProviderException(string $operation, \Throwable $exception): void
    {
        Log::warning('IssueClassifierService telemetry: provider exception', [
            'operation' => $operation,
            'error' => $exception->getMessage(),
        ]);
    }
}
