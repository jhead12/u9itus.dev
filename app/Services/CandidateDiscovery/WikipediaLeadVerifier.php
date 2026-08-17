<?php

namespace App\Services\CandidateDiscovery;

use App\Contracts\CandidateLeadVerifier;
use App\Models\CandidateLead;
use App\Services\CandidateDiscovery\Concerns\ClassifiesPrimaryResultText;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Tier 2 verifier — falls back to a Wikipedia REST summary when the lead has
 * no Ballotpedia page, extracted from SyncPrimaryResults's inline Wikipedia
 * tier so it's reusable/pluggable here.
 */
class WikipediaLeadVerifier implements CandidateLeadVerifier
{
    use ClassifiesPrimaryResultText;

    public function key(): string
    {
        return 'wikipedia';
    }

    public function verify(CandidateLead $lead): ?array
    {
        $slug = str_replace(' ', '_', $lead->full_name);
        $url = "https://en.wikipedia.org/api/rest_v1/page/summary/{$slug}";

        try {
            $response = Http::timeout(12)
                ->withHeaders(['User-Agent' => 'u9itus-candidate-discovery/1.0'])
                ->get($url);
        } catch (\Throwable $e) {
            Log::warning('WikipediaLeadVerifier: fetch failed', ['url' => $url, 'error' => $e->getMessage()]);
            return null;
        }

        if (! $response->ok()) {
            return null;
        }

        $text = strtolower((string) ($response->json('extract') ?? ''));
        $stateName = $lead->state ? (string) (config('u9itus.us_states')[$lead->state] ?? null) : null;

        if (! $this->looksRelevant($text, $stateName, $lead->office_hint)) {
            return null;
        }

        $primaryResult = $this->classifyPrimaryResultText($text);
        if ($primaryResult === null) {
            return null;
        }

        return [
            'status' => $primaryResult,
            'confidence' => 0.8,
            'reason' => "wikipedia summary confirms {$primaryResult}",
            'verified_payload' => [
                'political_office' => $lead->office_hint,
                'governance_level' => $lead->office_hint === 'Governor' ? 'State' : 'Federal',
                'state' => $lead->state,
                'primary_result' => $primaryResult,
            ],
        ];
    }
}
