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

    /**
     * Per-process telemetry + throttle state. Static so a single `php artisan`
     * invocation shares one throttle window and one rate-limit counter across
     * all candidates in the batch; a new process starts fresh.
     *
     * The donor-enrichment run was hammering FEC with thousands of uncached
     * calls and no backoff, blowing the 1000 req/hour ceiling and spending
     * ~9 minutes failing fast on cascading 429s. These counters let the request
     * helper short-circuit once FEC has clearly rate-limited us (so the rest of
     * the run stops burning time) and let the command report what happened.
     */
    protected static ?float $lastRequestTime = null;
    protected static int $consecutiveRateLimits = 0;
    protected static int $httpCallCount = 0;
    protected static int $rateLimitCount = 0;
    protected static bool $shortCircuited = false;

    protected const MIN_REQUEST_INTERVAL_MS = 120;      // spaces calls under FEC's per-second ceiling
    protected const MAX_BACKOFF_ATTEMPTS = 3;           // 429/5xx: initial + up to 2 retries
    protected const RATE_LIMIT_SHORT_CIRCUIT_THRESHOLD = 5; // consecutive 429s → stop hitting FEC

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
     * Central GET for every FEC endpoint: 429/5xx exponential backoff (honoring
     * Retry-After), a per-second throttle, and a consecutive-rate-limit
     * short-circuit. Returns the full decoded JSON body, or null on terminal
     * failure / short-circuit. Failures route through the existing telemetry
     * helpers (with the response body captured on 4xx for diagnosis).
     *
     * @param string $operation  telemetry label, e.g. 'get_candidate_committees'
     * @param string $url        full endpoint URL
     * @param array  $params     query params WITHOUT api_key (added here)
     * @param array  $context    extra telemetry context (candidate_id, etc.)
     * @param string|null $queryString  pre-built query string for endpoints that
     *     reject Laravel's array-param rendering (e.g. repeated plain
     *     committee_id= params). When set, api_key is appended to it and the
     *     params array is ignored.
     * @return array|null
     */
    protected function request(string $operation, string $url, array $params = [], array $context = [], ?string $queryString = null): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        // Once FEC has rate-limited us repeatedly, stop asking — every further
        // call in this process would 429 again and just burn wall-clock.
        if (self::$consecutiveRateLimits >= self::RATE_LIMIT_SHORT_CIRCUIT_THRESHOLD) {
            self::$shortCircuited = true;
            return null;
        }

        $this->throttle();

        // When a caller hand-builds the query (for endpoints that reject the
        // committee_id[]= array form), bake it into the URL so Http::get gets a
        // single ready query string instead of re-rendering params.
        if ($queryString !== null) {
            $separator = str_contains($url, '?') ? '&' : '?';
            $url = $url . $separator . $queryString . '&api_key=' . urlencode((string) $this->apiKey);
            $params = [];
        } else {
            $params['api_key'] = $this->apiKey;
        }

        $attempt = 0;

        while (true) {
            $attempt++;
            try {
                $response = Http::timeout(10)->get($url, $params);
            } catch (\Throwable $e) {
                $this->logProviderException($operation, $e, $context);
                return null;
            }

            self::$httpCallCount++;

            if ($response->successful()) {
                self::$consecutiveRateLimits = 0;
                return $response->json() ?: [];
            }

            $status = $response->status();

            // Retryable: 429 (rate-limited) and 5xx (transient server error).
            if ($status === 429 || $status >= 500) {
                if ($status === 429) {
                    self::$consecutiveRateLimits++;
                    self::$rateLimitCount++;
                }
                if ($attempt < self::MAX_BACKOFF_ATTEMPTS) {
                    $this->sleepMicroseconds((int) ($this->backoffSeconds($response, $attempt) * 1_000_000));
                    $this->throttle();
                    continue;
                }
                $this->logHttpFailure($operation, $status, array_merge($context, ['attempt' => $attempt]), $response);
                return null;
            }

            // 4xx (incl. 400) — not retryable. Capture the body so the FEC
            // error text is visible in logs (the schedule_a 400s gave no clue
            // before because we only logged the status).
            $this->logHttpFailure($operation, $status, $context, $response);
            return null;
        }
    }

    /**
     * Enforce a minimum gap between FEC requests so a tight loop over
     * candidates/committees/pages can't exceed FEC's per-second ceiling.
     */
    protected function throttle(): void
    {
        if (self::$lastRequestTime !== null) {
            $elapsedMs = (microtime(true) - self::$lastRequestTime) * 1000.0;
            $waitMs = self::MIN_REQUEST_INTERVAL_MS - $elapsedMs;
            if ($waitMs > 0) {
                $this->sleepMicroseconds((int) ($waitMs * 1000));
            }
        }
        self::$lastRequestTime = microtime(true);
    }

    /**
     * Indirection over usleep() so tests can no-op the backoff/throttle
     * sleeps without slowing the suite (and without env-sniffing in prod).
     */
    protected function sleepMicroseconds(int $microseconds): void
    {
        usleep($microseconds);
    }

    /**
     * Seconds to wait before a retry. Prefer the server's Retry-After header
     * (capped, so a hostile value can't stall the run), else exponential 1/2/4s.
     *
     * @param \Illuminate\Http\Client\Response $response
     * @param int $attempt  1-based attempt that just failed
     */
    protected function backoffSeconds($response, int $attempt): float
    {
        $retryAfter = $response->header('Retry-After');
        if (is_numeric($retryAfter) && (float) $retryAfter > 0) {
            return min((float) $retryAfter, 30.0);
        }
        return (float) (2 ** ($attempt - 1)); // 1s, 2s, 4s
    }

    /**
     * Per-run telemetry for the enricher's summary line.
     */
    public static function resetTelemetry(): void
    {
        self::$lastRequestTime = null;
        self::$consecutiveRateLimits = 0;
        self::$httpCallCount = 0;
        self::$rateLimitCount = 0;
        self::$shortCircuited = false;
    }

    public static function getHttpCallCount(): int
    {
        return self::$httpCallCount;
    }

    public static function getRateLimitCount(): int
    {
        return self::$rateLimitCount;
    }

    public static function wasShortCircuited(): bool
    {
        return self::$shortCircuited;
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
            'q' => $name,
            'sort' => '-election_years',
        ];

        if ($state !== '') {
            $params['state'] = $state;
        }

        $body = $this->request('find_candidate_id', "{$this->baseUrl}/candidates/search/", $params, [
            'state' => $state,
        ]);

        $results = $body['results'] ?? [];

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
        $body = $this->request('get_candidate_info', "{$this->baseUrl}/candidate/{$candidateId}/", [], [
            'candidate_id' => $candidateId,
        ]);

        $results = $body['results'] ?? [];
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

        $body = $this->request('get_financial_summary', "{$this->baseUrl}/candidate/{$candidateId}/totals/", [
            'cycle' => $cycle,
            'sort' => '-cycle',
        ], [
            'candidate_id' => $candidateId,
            'cycle' => $cycle,
        ]);

        $results = $body['results'] ?? [];
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
        $body = $this->request('get_recent_filings', "{$this->baseUrl}/candidate/{$candidateId}/filings/", [
            'per_page' => 5,
            'sort' => '-receipt_date',
        ], [
            'candidate_id' => $candidateId,
        ]);

        $results = $body['results'] ?? [];

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
        // Cached 24h — previously uncached, so every nightly run re-fetched
        // every federal candidate's committee list (one of the calls blowing
        // the FEC 1000/hour ceiling).
        return Cache::remember("fec.candidate.{$candidateId}.committees", $this->cacheDuration, function () use ($candidateId) {
            $body = $this->request('get_candidate_committees', "{$this->baseUrl}/candidate/{$candidateId}/committees/", [], [
                'candidate_id' => $candidateId,
            ]);

            $results = $body['results'] ?? [];

            return array_map(function ($committee) {
                return [
                    'name' => $committee['name'] ?? 'Unknown',
                    'committee_id' => $committee['committee_id'] ?? null,
                    'designation' => $committee['designation_full'] ?? null,
                    'type' => $committee['committee_type_full'] ?? null,
                ];
            }, $results);
        });
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
        if (!$this->isConfigured() || $committeeId === '') {
            return [];
        }

        // Cached 24h — previously uncached, so every nightly run re-fetched
        // Schedule A for every committee of every federal candidate.
        return Cache::remember("fec.committee.{$committeeId}.contributions", $this->cacheDuration, function () use ($committeeId, $perPage) {
            // committee_id is passed as a plain STRING (not wrapped in []) so
            // Laravel renders it as `committee_id=C00…` rather than the array
            // form `committee_id[]=C00…` — the array form is what triggered the
            // schedule_a 400s (same gotcha resolveCommitteeNames works around
            // for the multi-id /committees/ call, which needs repeated params).
            $body = $this->request('get_committee_contributions', "{$this->baseUrl}/schedules/schedule_a/", [
                'committee_id' => $committeeId,
                'contributor_type' => 'committee',
                'sort' => '-contribution_receipt_amount',
                'per_page' => $perPage,
            ], [
                'committee_id' => $committeeId,
            ]);

            $results = $body['results'] ?? [];

            return array_map(function ($receipt) {
                return [
                    'name' => $receipt['contributor_name'] ?? 'Unknown',
                    'total' => $this->formatCurrency($receipt['contribution_receipt_amount'] ?? 0),
                ];
            }, $results);
        });
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

        // Cached 24h — previously uncached, so every nightly run re-paged
        // through Schedule E (up to 10 pages) for every federal candidate.
        // That paged volume was the single biggest FEC call-count contributor.
        return Cache::remember("fec.candidate.{$candidateId}.outside_spending.{$cycle}", $this->cacheDuration, function () use ($candidateId, $cycle) {
            $maxPages = 10;
            $perPage = 100;

            try {
                $buckets = [];
                $lastIndex = null;
                $lastAmount = null;

                for ($page = 1; $page <= $maxPages; $page++) {
                    $params = [
                        'candidate_id' => $candidateId,
                        'two_year_transaction_period' => $cycle,
                        'sort' => '-expenditure_amount',
                        'per_page' => $perPage,
                    ];
                    if ($lastIndex !== null) {
                        $params['last_index'] = $lastIndex;
                        $params['last_expenditure_amount'] = $lastAmount;
                    }

                    $body = $this->request('get_outside_spending', "{$this->baseUrl}/schedules/schedule_e/", $params, [
                        'candidate_id' => $candidateId,
                        'cycle' => $cycle,
                        'page' => $page,
                    ]);

                    // request() returns null on terminal failure or rate-limit
                    // short-circuit — stop paging and return what we have.
                    if ($body === null) {
                        break;
                    }

                    $results = $body['results'] ?? [];

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
                    $cursor = $body['pagination']['last_indexes'] ?? null;
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
        });
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
                $body = $this->request('get_latest_cycle', "{$this->baseUrl}/candidate/{$candidateId}/", [], [
                    'candidate_id' => $candidateId,
                ]);

                if ($body === null) {
                    return null;
                }

                $cycles = $body['results'][0]['cycles'] ?? [];
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
            // so build the query string by hand (request() appends api_key).
            // Cap the lookup to the top 20.
            $queryString = 'per_page=100';
            foreach (array_slice($ids, 0, 20) as $id) {
                $queryString .= '&committee_id=' . urlencode($id);
            }

            $body = $this->request(
                'resolve_committee_names',
                "{$this->baseUrl}/committees/",
                [],
                ['committee_ids' => implode(',', array_slice($ids, 0, 20))],
                $queryString,
            );

            if ($body === null) {
                return;
            }

            $nameById = [];
            foreach ($body['results'] ?? [] as $committee) {
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

    protected function logHttpFailure(string $operation, int $status, array $context = [], $response = null): void
    {
        // Capture a truncated response body on 4xx so FEC's actual error text
        // surfaces in logs — the schedule_a 400s were previously opaque (only
        // the status code was logged), which blocked diagnosing the bad
        // committee_id param form.
        $body = null;
        if ($response !== null && $status >= 400 && $status < 500) {
            $raw = (string) $response->body();
            $body = $raw === '' ? null : substr($raw, 0, 500);
        }

        Log::warning('FECService telemetry: HTTP request failed', array_merge($context, array_filter([
            'operation' => $operation,
            'status' => $status,
            'is_rate_limited' => $status === 429,
            'response_body' => $body,
        ])));
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
