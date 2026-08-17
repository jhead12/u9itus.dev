<?php

namespace App\Services\CandidateDiscovery;

use App\Contracts\CandidateLeadVerifier;
use App\Models\CandidateLead;
use App\Services\CandidateDiscovery\Concerns\ClassifiesPrimaryResultText;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Tier 1 verifier — checks whether the lead has a Ballotpedia candidate page
 * confirming the race/office, extracted from SyncPrimaryResults's inline
 * checkUrl()/classify() so the same logic is reusable/pluggable here.
 */
class BallotpediaLeadVerifier implements CandidateLeadVerifier
{
    use ClassifiesPrimaryResultText;

    public function key(): string
    {
        return 'ballotpedia';
    }

    public function verify(CandidateLead $lead): ?array
    {
        $slug = str_replace(' ', '_', $lead->full_name);
        $url = "https://ballotpedia.org/{$slug}";

        try {
            // Ballotpedia sits behind an AWS WAF that challenges (HTTP 202,
            // empty body) non-browser-looking User-Agents — a bare tool UA
            // (e.g. "u9itus-candidate-discovery/1.0") gets blocked outright,
            // so a standard browser UA string is required to get real HTML back.
            $response = Http::timeout(15)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36'])
                ->get($url);
        } catch (\Throwable $e) {
            Log::warning('BallotpediaLeadVerifier: fetch failed', ['url' => $url, 'error' => $e->getMessage()]);
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $text = strtolower(strip_tags((string) $response->body()));
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
            'confidence' => 0.9,
            'reason' => "ballotpedia page confirms {$primaryResult}",
            'verified_payload' => [
                'political_office' => $lead->office_hint,
                'governance_level' => $lead->office_hint === 'Governor' ? 'State' : 'Federal',
                'state' => $lead->state,
                'primary_result' => $primaryResult,
                'ballotpedia_url' => $url,
            ],
        ];
    }
}
