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

        // FEC reporting is a hard federal-only constraint. The per-polician
        // show_fec_data display toggle is enforced by the caller
        // (PublicProfileController::fetchTransparencyData gates on
        // show_fec_data || isUnclaimed), NOT here — re-gating here would
        // block unclaimed federal profiles (show_fec_data defaults false)
        // from ever receiving FEC data, defeating the controller's intent
        // to always surface finance data for unclaimed federal officials,
        // and would prevent the nightly enrich job from populating their
        // snapshots at all.
        if (!$this->isFederalCandidate($politician)) {
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
                    $politician->state ?? ''
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
     * Find candidate ID by name and state.
     *
     * State is omitted from the query when blank — this is the case for
     * at-large federal offices with no home state on file (e.g. President).
     *
     * @param string $name
     * @param string $state
     * @return string|null
     */
    protected function findCandidateId(string $name, string $state): ?string
    {
        $params = [
            'api_key' => $this->apiKey,
            'q' => $name,
            'sort' => '-election_years',
        ];

        if ($state !== '') {
            $params['state'] = $state;
        }

        $response = Http::timeout(10)->get("{$this->baseUrl}/candidates/search/", $params);

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
    public function getCandidateCommittees(string $candidateId): array
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
     * Get top PAC (committee-to-committee) contributions to a candidate's
     * committee, sourced directly from FEC Schedule A itemized receipts.
     *
     * Filters to `contributor_type=committee` to isolate PAC money (as
     * opposed to individual donor contributions), and takes the first page
     * sorted by amount descending — sufficient for surfacing well-known
     * PAC names without needing full keyset pagination through every
     * itemized receipt.
     *
     * @param string $committeeId
     * @param int $perPage
     * @return array<int, array{name: string, total: mixed}>
     */
    public function getCommitteeContributions(string $committeeId, int $perPage = 20): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        try {
            $response = Http::timeout(10)->get("{$this->baseUrl}/schedules/schedule_a/", [
                'api_key' => $this->apiKey,
                'committee_id' => [$committeeId],
                'contributor_type' => 'committee',
                'sort' => '-contribution_receipt_amount',
                'per_page' => $perPage,
            ]);

            if (!$response->successful()) {
                $this->logHttpFailure('get_committee_contributions', $response->status(), [
                    'committee_id' => $committeeId,
                ]);

                return [];
            }

            $results = $response->json('results', []);

            return array_map(function ($receipt) {
                return [
                    'name' => $receipt['contributor_name'] ?? 'Unknown',
                    'total' => $this->formatCurrency($receipt['contribution_receipt_amount'] ?? 0),
                ];
            }, $results);

        } catch (\Throwable $e) {
            $this->logProviderException('get_committee_contributions', $e, [
                'committee_id' => $committeeId,
            ]);

            return [];
        }
    }

    /**
     * Get independent expenditures (outside spending) reported against a
     * candidate on FEC Schedule E — i.e. committees spending independently
     * to support or oppose them, with no coordination with the campaign.
     *
     * This is the "who is trying to influence this race" signal that the
     * candidate's own filing summary can't show. Unlike Schedule A receipts
     * (which are committee-to-committee contributions to the candidate),
     * Schedule E items are stand-alone disbursements by outside groups, so
     * we aggregate them by spender × support/oppose.
     *
     * Implementation notes (verified against the live FEC API):
     *  - Schedule E line items carry the spender only as `committee_id`;
     *    `committee` (name) is consistently null and `payee_name` is the
     *    *vendor*, not the spender. So after aggregating, committee names
     *    are resolved in one batch call to /committees/ (repeated
     *    `committee_id=` params — array `[]` and comma-list forms do NOT
     *    filter on this endpoint).
     *  - The Schedule E aggregate endpoints all 404 in this API version, so
     *    we page through line items and roll them up client-side using FEC's
     *    keyset cursor (`last_index` + `last_expenditure_amount`).
     *  - `is_notice` is NOT a spam signal — legitimate 24/48-hour IE reports
     *    are `is_notice=true`. The FEC spam filings ($9.9B joke entries from
     *    a recurring bad-filer) are instead excluded by an amount cap.
     *  - Coverage: pages until a page returns fewer than `per_page` items
     *    (the candidate's full IE volume) or a 10-page cap (~1000 items), so
     *    normal candidates get exact per-spender totals while only mega-profile
     *    races (e.g. a presidential with 30k+ items) hit the cap and under-count
     *    their tail. The profile renders a footnote noting full totals may be
     *    higher, so the cap is never presented as a complete figure.
     *
     * Up to ~11 API calls per candidate (10 item pages + 1 name resolution).
     * Amounts are kept numeric so the profile blade formats them via its
     * shared `$fmtMoney` helper.
     *
     * @param string $candidateId  FEC candidate ID (e.g. H8CA32123)
     * @param int $cycle           2-year FEC cycle (e.g. 2024)
     * @return array<int, array{committee_name: string, total: float, support_oppose: 'S'|'O'}>
     */
    public function getOutsideSpending(string $candidateId, int $cycle): array
    {
        if (!$this->isConfigured() || $candidateId === '') {
            return [];
        }

        $maxPages = 10;
        $perPage = 100;

        try {
            $buckets = [];
            $lastIndex = null;
            $lastAmount = null;

            for ($page = 1; $page <= $maxPages; $page++) {
                $params = [
                    'api_key' => $this->apiKey,
                    'candidate_id' => $candidateId,
                    'two_year_transaction_period' => $cycle,
                    'sort' => '-expenditure_amount',
                    'per_page' => $perPage,
                ];
                if ($lastIndex !== null) {
                    $params['last_index'] = $lastIndex;
                    $params['last_expenditure_amount'] = $lastAmount;
                }

                $response = Http::timeout(10)->get("{$this->baseUrl}/schedules/schedule_e/", $params);

                if (!$response->successful()) {
                    $this->logHttpFailure('get_outside_spending', $response->status(), [
                        'candidate_id' => $candidateId,
                        'cycle' => $cycle,
                        'page' => $page,
                    ]);
                    break;
                }

                $results = $response->json('results', []);

                // Aggregate this page's items by spender × support/oppose.
                // Drop memoed subtotals and the $9.9B spam filings (a recurring
                // bad-filer submits absurd amounts). $500M is well above any
                // legitimate single IE; real big-money buys top out in the tens
                // of millions per disbursement.
                foreach ($results as $item) {
                    if (!empty($item['memoed_subtotal'])) {
                        continue;
                    }
                    $amount = (float) ($item['expenditure_amount'] ?? 0);
                    if ($amount > 500_000_000.0) {
                        continue;
                    }
                    // Defensive: the API filter scopes to this candidate, but a
                    // loose search could still return a mismatched row.
                    if (($item['candidate_id'] ?? null) !== $candidateId) {
                        continue;
                    }

                    $committeeId = (string) ($item['committee_id'] ?? '');
                    $bucketKey = ($committeeId !== '' ? $committeeId : ('payee:' . ($item['payee_name'] ?? '')))
                        . '|' . ($item['support_oppose_indicator'] ?? '');

                    if (!isset($buckets[$bucketKey])) {
                        $buckets[$bucketKey] = [
                            'committee_id' => $committeeId,
                            'committee_name' => $committeeId !== '' ? $committeeId : ($item['payee_name'] ?? 'Unknown spender'),
                            'total' => 0.0,
                            'support_oppose' => ($item['support_oppose_indicator'] ?? 'S') === 'O' ? 'O' : 'S',
                        ];
                    }
                    $buckets[$bucketKey]['total'] += $amount;
                }

                // Stop when there are no more pages, or we've reached the cap.
                // The cursor comes from the API's own last item (independent of
                // our amount-cap filtering), so skipping spam doesn't stall paging.
                $cursor = $response->json('pagination.last_indexes');
                if (count($results) < $perPage || empty($cursor)) {
                    break;
                }
                $lastIndex = $cursor['last_index'] ?? null;
                $lastAmount = $cursor['last_expenditure_amount'] ?? null;
                if ($lastIndex === null) {
                    break;
                }
            }

            if ($buckets === []) {
                return [];
            }

            // Rank by total desc, cap at the top 20 spenders, then resolve names.
            $ranked = array_values($buckets);
            usort($ranked, fn($a, $b) => $b['total'] <=> $a['total']);
            $ranked = array_slice($ranked, 0, 20);

            $this->resolveCommitteeNames($ranked);

            return array_map(fn($b) => [
                'committee_name' => $b['committee_name'],
                'total' => $b['total'],
                'support_oppose' => $b['support_oppose'],
            ], $ranked);

        } catch (\Throwable $e) {
            $this->logProviderException('get_outside_spending', $e, [
                'candidate_id' => $candidateId,
                'cycle' => $cycle,
            ]);

            return [];
        }
    }

    /**
     * Resolve a candidate's most recent 2-year FEC filing cycle from
     * /candidate/{id}/, cached for 24h. Used so outside-spending (and the
     * profile's displayed cycle label) targets the cycle the candidate
     * actually filed/ran in, rather than assuming the current even year —
     * which would silently return nothing for a recently-retired candidate
     * whose independent-expenditure data lives in a prior cycle.
     *
     * @param string $candidateId
     * @return int|null
     */
    public function getLatestCycle(string $candidateId): ?int
    {
        if (!$this->isConfigured() || $candidateId === '') {
            return null;
        }

        return Cache::remember("fec.candidate.{$candidateId}.cycle", $this->cacheDuration, function () use ($candidateId) {
            try {
                $response = Http::timeout(10)->get("{$this->baseUrl}/candidate/{$candidateId}/", [
                    'api_key' => $this->apiKey,
                ]);

                if (!$response->successful()) {
                    $this->logHttpFailure('get_latest_cycle', $response->status(), [
                        'candidate_id' => $candidateId,
                    ]);
                    return null;
                }

                $cycles = $response->json('results.0.cycles', []);
                return $cycles ? max(array_map('intval', $cycles)) : null;
            } catch (\Throwable $e) {
                $this->logProviderException('get_latest_cycle', $e, [
                    'candidate_id' => $candidateId,
                ]);
                return null;
            }
        });
    }

    /**
     * Resolve human-readable committee names for the top outside spenders
     * in-place, via one batch /committees/ call. Falls back to the existing
     * committee_id label when a name can't be resolved or the batch call
     * fails — never blocks returning the spending data.
     *
     * @param array<int, array{committee_id: string, committee_name: string, ...}> $ranked
     * @return void
     */
    protected function resolveCommitteeNames(array &$ranked): void
    {
        $ids = array_values(array_unique(array_filter(
            array_map(fn($b) => $b['committee_id'] ?? '', $ranked),
            fn($id) => $id !== '',
        )));

        if ($ids === []) {
            return;
        }

        try {
            // The /committees/ endpoint only filters with *repeated plain*
            // committee_id= params. Laravel's Http::get would render an array
            // value as committee_id[]=… which this endpoint silently ignores,
            // so build the query string by hand. Cap the lookup to the top 20.
            $query = 'api_key=' . urlencode((string) $this->apiKey) . '&per_page=100';
            foreach (array_slice($ids, 0, 20) as $id) {
                $query .= '&committee_id=' . urlencode($id);
            }

            $response = Http::timeout(10)->get("{$this->baseUrl}/committees/?{$query}");

            if (!$response->successful()) {
                $this->logHttpFailure('resolve_committee_names', $response->status(), [
                    'committee_ids' => implode(',', array_slice($ids, 0, 20)),
                ]);
                return;
            }

            $nameById = [];
            foreach ($response->json('results', []) as $committee) {
                if (!empty($committee['committee_id']) && !empty($committee['name'])) {
                    $nameById[$committee['committee_id']] = $committee['name'];
                }
            }

            foreach ($ranked as &$bucket) {
                $id = $bucket['committee_id'] ?? '';
                if ($id !== '' && isset($nameById[$id])) {
                    $bucket['committee_name'] = $nameById[$id];
                }
            }
            unset($bucket);
        } catch (\Throwable $e) {
            $this->logProviderException('resolve_committee_names', $e, [
                'committee_ids' => implode(',', array_slice($ids, 0, 20)),
            ]);
        }
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
        // ── 1. Try the nightly donor snapshot ────────────────────────────────
        $snapshot = \App\Models\PoliticianDonorSnapshot::where('politician_id', $politician->id)->first();

        if ($snapshot && $snapshot->enriched_at && !empty($snapshot->fec_summary)) {
            $sections = [
                'summary' => $snapshot->fec_summary,
            ];
            // Independent expenditures (Schedule E) are persisted alongside
            // the filing summary during nightly enrichment. Surface them as a
            // separate section so the blade can render the "who is spending
            // to support/oppose this candidate" block. Live-fetched outside
            // spending is intentionally NOT added to the fallback tier below
            // (extra API calls on page render; the snapshot is the source of
            // truth).
            if ($snapshot->hasOutsideSpending()) {
                $sections['outside_spending'] = ['items' => $snapshot->outside_spending];
            }

            return [
                'source'     => 'Federal Election Commission',
                'source_url' => $snapshot->fec_source_url ?? 'https://www.fec.gov/data/',
                'sections'   => $sections,
            ];
        }

        // ── 2. Fall back to live FEC API ─────────────────────────────────────
        $data = $this->fetchCandidateFilings($politician);

        if (!$data || empty($data['candidate_info'])) {
            return null;
        }

        $candidateId = $data['candidate_info']['candidate_id']
            ?? $politician->fec_candidate_id
            ?? null;

        return [
            'source'     => 'Federal Election Commission',
            'source_url' => $candidateId
                ? "https://www.fec.gov/data/candidate/{$candidateId}/"
                : 'https://www.fec.gov/data/',
            'sections'   => [
                'summary' => $data['financial_summary'] ?? [],
            ],
        ];
    }
}
