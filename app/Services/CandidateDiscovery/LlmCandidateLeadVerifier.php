<?php

namespace App\Services\CandidateDiscovery;

use App\Contracts\CandidateLeadVerifier;
use App\Models\CandidateLead;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Tier 3 (fallback only) — asks Claude to read the lead's raw RSS headline/
 * snippet when neither Ballotpedia nor Wikipedia has a page yet (exactly the
 * gap that hid Abdul El-Sayed: too new for a reference page). Mirrors
 * ValidatePoliticianProfilePhotos's Anthropic-gated pattern: only runs when
 * services.anthropic.api_key is configured, and signals quota exhaustion the
 * same way so the caller can open a circuit breaker.
 */
class LlmCandidateLeadVerifier implements CandidateLeadVerifier
{
    public function key(): string
    {
        return 'llm_anthropic';
    }

    public function isConfigured(): bool
    {
        return (string) config('services.anthropic.api_key') !== '';
    }

    public function verify(CandidateLead $lead): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $context = trim((string) $lead->discovery_context);
        if ($context === '') {
            return null;
        }

        $stateName = $lead->state ? (string) (config('u9itus.us_states')[$lead->state] ?? $lead->state) : 'unknown';

        try {
            $response = Http::timeout(20)
                ->withHeaders([
                    'x-api-key' => (string) config('services.anthropic.api_key'),
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model' => config('services.anthropic.model', 'claude-haiku-4-5'),
                    'max_tokens' => 300,
                    'temperature' => 0,
                    'system' => 'You verify political candidate leads discovered from news RSS headlines for a '
                        . 'civic-data site. Return ONLY compact JSON with keys: full_name_confirmed (string|null), '
                        . 'is_real_candidate (boolean), office (string|null), governance_level ("Federal"|"State"|null), '
                        . 'party_affiliation (string|null), election_date_guess (string "YYYY-MM-DD"|null), '
                        . 'status ("running"|"advanced_to_general"|"eliminated"|null), confidence (0-1), reason (short string).',
                    'messages' => [[
                        'role' => 'user',
                        'content' => "Candidate name (guessed from headline): {$lead->full_name}\n"
                            . "State: {$stateName}\nOffice hint: " . ($lead->office_hint ?? 'unknown') . "\n"
                            . "News text: {$context}\n\n"
                            . 'Does this text confirm a real person running for this office in this state? Output JSON only.',
                    ]],
                ]);
        } catch (\Throwable $e) {
            Log::warning('LlmCandidateLeadVerifier: request failed', ['lead_id' => $lead->id, 'error' => $e->getMessage()]);
            return null;
        }

        if (! $response->ok()) {
            if ($response->status() === 400 && str_contains($response->body(), 'usage limits')) {
                return ['status' => 'quota_exhausted', 'confidence' => 0.0, 'reason' => 'anthropic quota exhausted', 'verified_payload' => []];
            }

            Log::warning('LlmCandidateLeadVerifier: anthropic error', ['status' => $response->status(), 'body' => $response->body()]);
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

        if (! ($ai['is_real_candidate'] ?? false)) {
            return [
                'status' => 'rejected',
                'confidence' => (float) ($ai['confidence'] ?? 0.5),
                'reason' => (string) ($ai['reason'] ?? 'llm could not confirm a real candidate'),
                'verified_payload' => [],
            ];
        }

        return [
            'status' => (string) ($ai['status'] ?? 'running'),
            'confidence' => (float) ($ai['confidence'] ?? 0.5),
            'reason' => (string) ($ai['reason'] ?? 'llm-confirmed candidate'),
            'verified_payload' => [
                'political_office' => $ai['office'] ?? $lead->office_hint,
                'governance_level' => $ai['governance_level'] ?? ($lead->office_hint === 'Governor' ? 'State' : 'Federal'),
                'party_affiliation' => $ai['party_affiliation'] ?? null,
                'election_date' => $ai['election_date_guess'] ?? null,
                'state' => $lead->state,
                'primary_result' => $ai['status'] ?? null,
            ],
        ];
    }
}
