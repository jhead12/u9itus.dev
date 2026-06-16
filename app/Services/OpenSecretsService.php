<?php

namespace App\Services;

use App\Models\Politician;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OpenSecrets (Center for Responsive Politics) API Integration Service
 * 
 * Fetches campaign finance data including contributions, donors, and expenditures.
 * Data is cached for 24 hours to minimize API calls.
 * 
 * API Documentation: https://www.opensecrets.org/open-data/api-documentation
 * Rate Limits: 200 requests/day (free tier)
 * 
 * Setup:
 * 1. Register for API key at https://www.opensecrets.org/api/admin/index.php?function=signup
 * 2. Add to .env: OPENSECRETS_API_KEY=your_api_key_here
 */
class OpenSecretsService
{
    protected string $baseUrl = 'https://www.opensecrets.org/api';
    protected ?string $apiKey;
    protected int $cacheDuration = 86400; // 24 hours

    public function __construct()
    {
        $this->apiKey = config('services.opensecrets.api_key');
    }

    /**
     * Check if service is properly configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Fetch campaign finance data from OpenSecrets
     * 
     * @param Politician $politician
     * @return array|null
     */
    public function fetchCampaignFinanceData(Politician $politician): ?array
    {
        if (!$this->isConfigured()) {
            Log::warning('OpenSecretsService: API key not configured');
            return null;
        }

        // Visibility gate is handled by the caller (buildTransparencyData).
        // Do not block unclaimed profiles here.

        $cacheKey = "opensecrets.politician.{$politician->id}";

        return Cache::remember($cacheKey, $this->cacheDuration, function () use ($politician) {
            try {
                // First, find the candidate ID (CID) by name and state
                $candidateId = $politician->opensecrets_id ?? $this->findCandidateId(
                    $politician->user->first_name . ' ' . $politician->user->last_name,
                    $politician->state
                );

                if (!$candidateId) {
                    return null;
                }

                // Store the OpenSecrets CID for future lookups
                $politician->update(['opensecrets_id' => $candidateId]);

                // Fetch multiple data points
                return [
                    'candidate_summary' => $this->getCandidateSummary($candidateId),
                    'top_contributors' => $this->getTopContributors($candidateId),
                    'top_industries' => $this->getTopIndustries($candidateId),
                    'sector_totals' => $this->getSectorTotals($candidateId),
                ];

            } catch (\Throwable $e) {
                $this->logProviderException('fetch_campaign_finance_data', $e, [
                    'politician_id' => $politician->id,
                ]);

                return null;
            }
        });
    }

    /**
     * Find candidate ID by name and state
     * 
     * @param string $name
     * @param string $state
     * @return string|null
     */
    protected function findCandidateId(string $name, string $state): ?string
    {
        $response = Http::timeout(10)->get($this->baseUrl, [
            'method' => 'getLegislators',
            'id' => $state,
            'apikey' => $this->apiKey,
            'output' => 'json',
        ]);

        if (!$response->successful()) {
            $this->logHttpFailure('find_candidate_id', $response->status(), [
                'state' => $state,
            ]);

            return null;
        }

        $legislators = $response->json('response.legislator', []);

        // Find closest name match
        foreach ($legislators as $legislator) {
            $legislatorName = ($legislator['@attributes']['firstlast'] ?? '');
            if (stripos($legislatorName, $name) !== false || stripos($name, $legislatorName) !== false) {
                return $legislator['@attributes']['cid'] ?? null;
            }
        }

        return null;
    }

    /**
     * Get candidate summary (total raised, spent, etc.)
     * 
     * @param string $candidateId
     * @return array
     */
    protected function getCandidateSummary(string $candidateId): array
    {
        $response = Http::timeout(10)->get($this->baseUrl, [
            'method' => 'candSummary',
            'cid' => $candidateId,
            'apikey' => $this->apiKey,
            'output' => 'json',
        ]);

        if (!$response->successful()) {
            $this->logHttpFailure('get_candidate_summary', $response->status(), [
                'candidate_id' => $candidateId,
            ]);

            return [];
        }

        $summary = $response->json('response.summary.@attributes', []);

        return [
            'cycle' => $summary['cycle'] ?? null,
            'total_raised' => $this->formatCurrency($summary['total'] ?? 0),
            'total_spent' => $this->formatCurrency($summary['spent'] ?? 0),
            'cash_on_hand' => $this->formatCurrency($summary['cash_on_hand'] ?? 0),
            'debt' => $this->formatCurrency($summary['debt'] ?? 0),
            'last_updated' => $summary['last_updated'] ?? null,
        ];
    }

    /**
     * Get top contributors
     * 
     * @param string $candidateId
     * @return array
     */
    protected function getTopContributors(string $candidateId): array
    {
        $response = Http::timeout(10)->get($this->baseUrl, [
            'method' => 'candContrib',
            'cid' => $candidateId,
            'apikey' => $this->apiKey,
            'output' => 'json',
        ]);

        if (!$response->successful()) {
            $this->logHttpFailure('get_top_contributors', $response->status(), [
                'candidate_id' => $candidateId,
            ]);

            return [];
        }

        $contributors = $response->json('response.contributors.contributor', []);
        
        return array_map(function ($contributor) {
            $attrs = $contributor['@attributes'] ?? [];
            return [
                'name' => $attrs['org_name'] ?? 'Unknown',
                'total' => $this->formatCurrency($attrs['total'] ?? 0),
                'individuals' => $this->formatCurrency($attrs['indivs'] ?? 0),
                'pacs' => $this->formatCurrency($attrs['pacs'] ?? 0),
            ];
        }, array_slice($contributors, 0, 10));
    }

    /**
     * Get top industries
     * 
     * @param string $candidateId
     * @return array
     */
    protected function getTopIndustries(string $candidateId): array
    {
        $response = Http::timeout(10)->get($this->baseUrl, [
            'method' => 'candIndustry',
            'cid' => $candidateId,
            'apikey' => $this->apiKey,
            'output' => 'json',
        ]);

        if (!$response->successful()) {
            $this->logHttpFailure('get_top_industries', $response->status(), [
                'candidate_id' => $candidateId,
            ]);

            return [];
        }

        $industries = $response->json('response.industries.industry', []);
        
        return array_map(function ($industry) {
            $attrs = $industry['@attributes'] ?? [];
            return [
                'name' => $attrs['industry_name'] ?? 'Unknown',
                'total' => $this->formatCurrency($attrs['total'] ?? 0),
                'individuals' => $this->formatCurrency($attrs['indivs'] ?? 0),
                'pacs' => $this->formatCurrency($attrs['pacs'] ?? 0),
            ];
        }, array_slice($industries, 0, 10));
    }

    /**
     * Get sector totals
     * 
     * @param string $candidateId
     * @return array
     */
    protected function getSectorTotals(string $candidateId): array
    {
        $response = Http::timeout(10)->get($this->baseUrl, [
            'method' => 'candSector',
            'cid' => $candidateId,
            'apikey' => $this->apiKey,
            'output' => 'json',
        ]);

        if (!$response->successful()) {
            $this->logHttpFailure('get_sector_totals', $response->status(), [
                'candidate_id' => $candidateId,
            ]);

            return [];
        }

        $sectors = $response->json('response.sectors.sector', []);
        
        return array_map(function ($sector) {
            $attrs = $sector['@attributes'] ?? [];
            return [
                'name' => $attrs['sector_name'] ?? 'Unknown',
                'total' => $this->formatCurrency($attrs['total'] ?? 0),
                'individuals' => $this->formatCurrency($attrs['indivs'] ?? 0),
                'pacs' => $this->formatCurrency($attrs['pacs'] ?? 0),
            ];
        }, $sectors);
    }

    /**
     * Format currency for display
     * 
     * @param mixed $amount
     * @return string
     */
    protected function formatCurrency($amount): string
    {
        return '$' . number_format((float)$amount, 0);
    }

    protected function logHttpFailure(string $operation, int $status, array $context = []): void
    {
        Log::warning('OpenSecretsService telemetry: HTTP request failed', array_merge($context, [
            'operation' => $operation,
            'status' => $status,
            'is_rate_limited' => $status === 429,
        ]));
    }

    protected function logProviderException(string $operation, \Throwable $exception, array $context = []): void
    {
        Log::warning('OpenSecretsService telemetry: provider exception', array_merge($context, [
            'operation' => $operation,
            'error' => $exception->getMessage(),
        ]));
    }

    /**
     * Clear cached data for a politician
     * 
     * @param Politician $politician
     * @return void
     */
    public function clearCache(Politician $politician): void
    {
        Cache::forget("opensecrets.politician.{$politician->id}");
    }

    /**
     * Get display-ready data for frontend
     * 
     * @param Politician $politician
     * @return array|null
     */
    public function getDisplayData(Politician $politician): ?array
    {
        // ── 1. Try the nightly donor snapshot (no live API call needed) ──────
        $snapshot = \App\Models\PoliticianDonorSnapshot::where('politician_id', $politician->id)->first();

        if ($snapshot && $snapshot->enriched_at) {
            $topContributors = $snapshot->top_contributors ?? [];
            $topIndustries   = $snapshot->top_industries ?? [];

            if (!empty($topContributors) || !empty($topIndustries)) {
                return [
                    'source'     => 'OpenSecrets',
                    'source_url' => $snapshot->opensecrets_source_url
                        ?? ($politician->opensecrets_id
                            ? "https://www.opensecrets.org/members-of-congress/summary?cid={$politician->opensecrets_id}"
                            : null),
                    'sections' => [
                        'top_contributors' => ['items' => $topContributors],
                        'top_industries'   => ['items' => $topIndustries],
                    ],
                ];
            }
        }

        // ── 2. Fall back to live OpenSecrets API ─────────────────────────────
        $data = $this->fetchCampaignFinanceData($politician);

        if (!$data || empty($data['candidate_summary'])) {
            return null;
        }

        return [
            'source'     => 'OpenSecrets',
            'source_url' => "https://www.opensecrets.org/members-of-congress/summary?cid={$politician->opensecrets_id}",
            'sections'   => [
                'top_contributors' => ['items' => $data['top_contributors'] ?? []],
                'top_industries'   => ['items' => $data['top_industries'] ?? []],
                'sector_breakdown' => ['items' => $data['sector_totals'] ?? []],
            ],
        ];
    }
}
