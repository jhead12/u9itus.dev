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
     * Phrase scoring against each active topic's slug, name and keywords. The one
     * keyword matcher for the app: news tagging and bill titles call it too.
     * A slug or name hit is strong on its own; the first keyword hit clears the
     * confidence floor and each further distinct keyword adds a little more.
     *
     * @return array{topic_slug: string|null, topic_id: int|null, confidence: float, method: string}
     */
    public function classifyKeyword(string $text): array
    {
        $haystack = preg_replace('/\s+/u', ' ', Str::lower($text));
        $best = null;
        $bestScore = 0.0;

        foreach ($this->topicCatalog() as $topic) {
            $score = 0.0;
            if ($topic['slug'] !== '' && $this->containsPhrase($haystack, str_replace('-', ' ', $topic['slug']))) {
                $score += 0.65;
            }
            if ($topic['name'] !== '' && $this->containsPhrase($haystack, $topic['name'])) {
                $score += 0.55;
            }

            $hits = count(array_filter($topic['keywords'], fn (string $keyword) => $this->containsPhrase($haystack, $keyword)));
            if ($hits > 0) {
                $score += 0.55 + 0.15 * ($hits - 1);
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

    /** Keyword result only when it clears the confidence floor, for callers that never use the LLM tier. */
    public function confidentKeywordMatch(string $text): ?array
    {
        $result = $this->classifyKeyword($text);

        return $result['topic_id'] !== null && $result['confidence'] >= $this->keywordThreshold ? $result : null;
    }

    // Whole-word match, so "gun" does not tag "Gunther" and "rent" does not tag "current".
    protected function containsPhrase(string $haystack, string $phrase): bool
    {
        return $phrase !== '' && preg_match('/(?<![\p{L}\p{N}])'.preg_quote($phrase, '/').'(?![\p{L}\p{N}])/u', $haystack) === 1;
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

    // ── Statements of position ────────────────────────────────────────────

    /**
     * Reads a politician's own statement (a floor speech) for the issue it is about, the
     * position taken, and a verbatim quote that shows it. The quote is dropped unless it
     * appears in the text, so a paraphrase is never shown as the speaker's words.
     *
     * @return array{topic_slug: ?string, confidence: float, stance: ?string, position: ?string, quote: ?string, topic_stance: ?string}|null
     *                                                                                                                null when the LLM is unavailable or failed
     */
    public function analyzeStatement(string $title, string $text, ?string $knownTopicSlug = null): ?array
    {
        if (! $this->isLlmConfigured() || trim($text) === '') {
            return null;
        }

        try {
            $system = 'You analyze statements by U.S. elected officials for a nonpartisan civic site. '
                .'Describe what the speaker argues in neutral, factual language; never characterize motives '
                .'or judge the position. Output strict JSON and nothing else.';

            $shape = '{"topic_id": <int|null>, "confidence": <0.0-1.0>, "stance": "support"|"oppose"|"mixed"|null, '
                .'"position": "<one neutral sentence, max 25 words, starting with a verb, e.g. Supports ...>"|null, '
                .'"quote": "<exact sentence(s) copied from the statement, max 40 words>"|null, '
                .'"topic_stance": "support"|"oppose"|null}';

            $user = "Catalog (topic_id → name):\n".$this->topicCatalogForPrompt(withStances: true)."\n\n"
                .'Rules: topic_id is the issue the statement is substantively about (null for tributes, '
                .'procedure or scheduling). stance is whether the speaker argues for or against the policy, bill '
                .'or action they discuss (null if they take no position). position names that policy. quote '
                .'must be copied character-for-character from the statement. topic_stance applies only when the '
                .'chosen topic lists [support = ...; oppose = ...]: pick the side the speaker clearly argues for, '
                ."else null. It can differ from stance, e.g. opposing a bill that restricts the topic.\n\n"
                .($knownTopicSlug !== null && ($knownId = $this->idForSlug($knownTopicSlug)) !== null
                    ? "The statement is debate on a bill filed under topic_id {$knownId}; use that topic_id.\n\n"
                    : '')
                ."Title: {$title}\n\nStatement:\n".mb_substr($text, 0, 8000)."\n\n"
                .'Return JSON with this exact shape: '.$shape;

            $response = Http::timeout(30)
                ->withHeaders([
                    'x-api-key' => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => $this->model,
                    'max_tokens' => 400,
                    'system' => $system,
                    'messages' => [['role' => 'user', 'content' => $user]],
                ]);

            if (! $response->ok()) {
                $this->logHttpFailure('analyze_statement', $response->status());

                return null;
            }

            $raw = trim($response->json('content.0.text') ?? '');
            if (preg_match('/\{.*\}/s', $raw, $m)) {
                $raw = $m[0];
            }
            $decoded = json_decode($raw, true);
            if (! is_array($decoded)) {
                return null;
            }

            $slug = isset($decoded['topic_id']) ? $this->slugForId((int) $decoded['topic_id']) : null;
            if ($knownTopicSlug !== null && $this->idForSlug($knownTopicSlug) !== null) {
                $slug = $knownTopicSlug;
            }
            $stance = in_array($decoded['stance'] ?? null, ['support', 'oppose', 'mixed'], true) ? $decoded['stance'] : null;
            $topicStance = in_array($decoded['topic_stance'] ?? null, ['support', 'oppose'], true) && $this->hasStances($slug)
                ? $decoded['topic_stance'] : null;
            $position = trim((string) ($decoded['position'] ?? ''));
            $quote = trim((string) ($decoded['quote'] ?? ''));
            $normalize = fn (string $v) => preg_replace('/\s+/u', ' ', $v);
            if ($quote === '' || ! str_contains($normalize($text), $normalize($quote))) {
                $quote = null;
            }

            return [
                'topic_slug' => $slug,
                'confidence' => $slug ? round(min(1.0, max(0.0, (float) ($decoded['confidence'] ?? 0.0))), 3) : 0.0,
                'stance' => $stance,
                'position' => $position !== '' ? mb_substr($position, 0, 500) : null,
                'quote' => $quote !== null ? mb_substr($quote, 0, 600) : null,
                'topic_stance' => $topicStance,
            ];
        } catch (\Throwable $e) {
            $this->logProviderException('analyze_statement', $e);

            return null;
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
        return Cache::remember('issues:topic-catalog-v3', 300, function () {
            return PoliticianTopic::query()
                ->where('is_active', true)
                ->get(['id', 'slug', 'name', 'keywords', 'support_label', 'oppose_label'])
                ->map(fn (PoliticianTopic $t) => [
                    'id' => (int) $t->id,
                    'slug' => strtolower((string) $t->slug),
                    'name' => strtolower((string) $t->name),
                    'keywords' => array_values(array_filter(array_map(fn ($k) => strtolower(trim((string) $k)), (array) ($t->keywords ?? [])))),
                    'stances' => $t->hasStanceLabels() ? ['support' => $t->support_label, 'oppose' => $t->oppose_label] : null,
                ])
                ->all();
        });
    }

    /**
     * Compact catalog string for the LLM prompt: "1 → Healthcare\n2 → Climate Action".
     * With $withStances, topics that define a position add its two sides.
     */
    protected function topicCatalogForPrompt(bool $withStances = false): string
    {
        return collect($this->topicCatalog())
            ->map(fn ($t) => "{$t['id']} → ".ucwords(str_replace('-', ' ', $t['slug']))
                .($withStances && ! empty($t['stances']) ? " [support = {$t['stances']['support']}; oppose = {$t['stances']['oppose']}]" : ''))
            ->implode("\n");
    }

    protected function hasStances(?string $slug): bool
    {
        foreach ($this->topicCatalog() as $t) {
            if ($slug !== null && $t['slug'] === $slug) {
                return ! empty($t['stances']);
            }
        }

        return false;
    }

    protected function idForSlug(string $slug): ?int
    {
        foreach ($this->topicCatalog() as $t) {
            if ($t['slug'] === $slug) {
                return $t['id'];
            }
        }

        return null;
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
