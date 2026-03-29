<?php

namespace App\Services;

use App\Models\Politician;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Federal Election Commission (FEC) API Integration Service
 * 
 * Fetches official federal campaign finance filings and committee data.
 * Data is cached for 24 hours to minimize API calls.
 * 
 * API Documentation: https://api.open.fec.gov/developers/
 * Rate Limits: 1000 requests/hour (registered users)
 * 
 * Setup:
 * 1. Register for API key at https://api.data.gov/signup/
 * 2. Add to .env: FEC_API_KEY=your_api_key_here
 * 
 * Note: Only applicable to federal candidates (House, Senate, Presidential)
 */
class FECService
{
    protected string $baseUrl = 'https://api.open.fec.gov/v1';
    protected ?string $apiKey;
    protected int $cacheDuration = 86400; // 24 hours

    public function __construct()
    {
        $this->apiKey = config('services.fec.api_key');
    }

    /**
     * Check if service is properly configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Check if politician is a federal candidate
     * 
     * @param Politician $politician
     * @return bool
     */
    public function isFederalCandidate(Politician $politician): bool
    {
        $office = strtolower((string) ($politician->political_office ?? $politician->office_position ?? ''));

        if ($office === '') {
            return false;
        }

        $markers = [
            'us representative',
            'representative',
            'member of congress',
            'us senator',
            'senator',
            'president',
            'vice president',
            'house of representatives',
        ];

        foreach ($markers as $marker) {
            if (str_contains($office, $marker)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Fetch FEC filing data for a federal candidate
     * 
     * @param Politician $politician
     * @return array|null
     */
    public function fetchCandidateFilings(Politician $politician): ?array
    {
        if (!$this->isConfigured()) {
            Log::warning('FECService: API key not configured');
            return null;
        }

        if (!$politician->show_fec_data || !$this->isFederalCandidate($politician)) {
            return null;
        }

        $cacheKey = "fec.politician.{$politician->id}";

        return Cache::remember($cacheKey, $this->cacheDuration, function () use ($politician) {
            try {
                // Find candidate by name
                $candidateName = trim((string) (
                    optional($politician->user)->first_name
                    . ' '
                    . optional($politician->user)->last_name
                ));
                if ($candidateName === '') {
                    $candidateName = (string) $politician->full_name;
                }

                $candidateId = $politician->fec_candidate_id ?? $this->findCandidateId(
                    $candidateName,
                    $politician->state
                );

                if (!$candidateId) {
                    return null;
                }

                // Store FEC candidate ID
                $politician->update(['fec_candidate_id' => $candidateId]);

                // Fetch multiple data points
                return [
                    'candidate_info' => $this->getCandidateInfo($candidateId),
                    'financial_summary' => $this->getFinancialSummary($candidateId),
                    'recent_filings' => $this->getRecentFilings($candidateId),
                    'committees' => $this->getCandidateCommittees($candidateId),
                ];

            } catch (\Throwable $e) {
                $this->logProviderException('fetch_candidate_filings', $e, [
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
        $response = Http::timeout(10)->get("{$this->baseUrl}/candidates/search/", [
            'api_key' => $this->apiKey,
            'q' => $name,
            'state' => $state,
            'sort' => '-election_years',
        ]);

        if (!$response->successful()) {
            $this->logHttpFailure('find_candidate_id', $response->status(), [
                'state' => $state,
            ]);

            return null;
        }

        $results = $response->json('results', []);

        // Return the most recent candidate ID
        return $results[0]['candidate_id'] ?? null;
    }

    /**
     * Get candidate information
     * 
     * @param string $candidateId
     * @return array
     */
    protected function getCandidateInfo(string $candidateId): array
    {
        $response = Http::timeout(10)->get("{$this->baseUrl}/candidate/{$candidateId}/", [
            'api_key' => $this->apiKey,
        ]);

        if (!$response->successful()) {
            $this->logHttpFailure('get_candidate_info', $response->status(), [
                'candidate_id' => $candidateId,
            ]);

            return [];
        }

        $results = $response->json('results', []);
        $info = $results[0] ?? [];

        return [
            'candidate_id' => $info['candidate_id'] ?? $candidateId,
            'name' => $info['name'] ?? null,
            'party' => $info['party_full'] ?? $info['party'] ?? null,
            'office' => $info['office_full'] ?? null,
            'state' => $info['state'] ?? null,
            'district' => $info['district'] ?? null,
            'election_years' => $info['election_years'] ?? [],
            'cycles' => $info['cycles'] ?? [],
        ];
    }

    /**
     * Get financial summary
     * 
     * @param string $candidateId
     * @return array
     */
    protected function getFinancialSummary(string $candidateId): array
    {
        // Get the most recent 2-year cycle
        $currentYear = date('Y');
        $cycle = $currentYear % 2 === 0 ? $currentYear : $currentYear + 1;

        $response = Http::timeout(10)->get("{$this->baseUrl}/candidate/{$candidateId}/totals/", [
            'api_key' => $this->apiKey,
            'cycle' => $cycle,
            'sort' => '-cycle',
        ]);

        if (!$response->successful()) {
            $this->logHttpFailure('get_financial_summary', $response->status(), [
                'candidate_id' => $candidateId,
                'cycle' => $cycle,
            ]);

            return [];
        }

        $results = $response->json('results', []);
        $totals = $results[0] ?? [];

        return [
            'cycle' => $totals['cycle'] ?? $cycle,
            'receipts' => $this->formatCurrency($totals['receipts'] ?? 0),
            'disbursements' => $this->formatCurrency($totals['disbursements'] ?? 0),
            'cash_on_hand' => $this->formatCurrency($totals['cash_on_hand_end_period'] ?? 0),
            'debt' => $this->formatCurrency($totals['debts_owed_by_committee'] ?? 0),
            'coverage_end_date' => $totals['coverage_end_date'] ?? null,
        ];
    }

    /**
     * Get recent filings
     * 
     * @param string $candidateId
     * @return array
     */
    protected function getRecentFilings(string $candidateId): array
    {
        $response = Http::timeout(10)->get("{$this->baseUrl}/candidate/{$candidateId}/filings/", [
            'api_key' => $this->apiKey,
            'per_page' => 5,
            'sort' => '-receipt_date',
        ]);

        if (!$response->successful()) {
            $this->logHttpFailure('get_recent_filings', $response->status(), [
                'candidate_id' => $candidateId,
            ]);

            return [];
        }

        $results = $response->json('results', []);

        return array_map(function ($filing) {
            return [
                'document_description' => $filing['document_description'] ?? 'Unknown',
                'form_type' => $filing['form_type'] ?? null,
                'receipt_date' => $filing['receipt_date'] ?? null,
                'coverage_start_date' => $filing['coverage_start_date'] ?? null,
                'coverage_end_date' => $filing['coverage_end_date'] ?? null,
                'pdf_url' => $filing['pdf_url'] ?? null,
                'fec_url' => $filing['fec_url'] ?? null,
            ];
        }, $results);
    }

    /**
     * Get candidate committees
     * 
     * @param string $candidateId
     * @return array
     */
    protected function getCandidateCommittees(string $candidateId): array
    {
        $response = Http::timeout(10)->get("{$this->baseUrl}/candidate/{$candidateId}/committees/", [
            'api_key' => $this->apiKey,
        ]);

        if (!$response->successful()) {
            $this->logHttpFailure('get_candidate_committees', $response->status(), [
                'candidate_id' => $candidateId,
            ]);

            return [];
        }

        $results = $response->json('results', []);

        return array_map(function ($committee) {
            return [
                'name' => $committee['name'] ?? 'Unknown',
                'committee_id' => $committee['committee_id'] ?? null,
                'designation' => $committee['designation_full'] ?? null,
                'type' => $committee['committee_type_full'] ?? null,
            ];
        }, $results);
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
        Log::warning('FECService telemetry: HTTP request failed', array_merge($context, [
            'operation' => $operation,
            'status' => $status,
            'is_rate_limited' => $status === 429,
        ]));
    }

    protected function logProviderException(string $operation, \Throwable $exception, array $context = []): void
    {
        Log::warning('FECService telemetry: provider exception', array_merge($context, [
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
        Cache::forget("fec.politician.{$politician->id}");
    }

    /**
     * Get display-ready data for frontend
     * 
     * @param Politician $politician
     * @return array|null
     */
    public function getDisplayData(Politician $politician): ?array
    {
        $data = $this->fetchCandidateFilings($politician);

        if (!$data || empty($data['candidate_info'])) {
            return null;
        }

        $candidateId = $data['candidate_info']['candidate_id']
            ?? $politician->fec_candidate_id
            ?? null;

        return [
            'source' => 'Federal Election Commission',
            'source_url' => $candidateId
                ? "https://www.fec.gov/data/candidate/{$candidateId}/"
                : 'https://www.fec.gov/data/',
            'summary' => $data['financial_summary'] ?? [],
            'sections' => [
                [
                    'title' => 'Recent Filings',
                    'items' => $data['recent_filings'] ?? [],
                ],
                [
                    'title' => 'Committees',
                    'items' => $data['committees'] ?? [],
                ],
            ],
        ];
    }
}
