<?php

namespace App\Services;

use App\Models\Politician;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Ballotpedia Integration Service
 *
 * fetchPoliticianData()/getDisplayData() (used by the public profile's
 * "Dig Deeper" card) scrape the politician's own Ballotpedia wiki article
 * directly — NOT an API call. Ballotpedia does not publish a public REST
 * API: the "API v4" this class originally called against does not exist
 * (verified 2026-08-03 — ballotpedia.org/API_documentation is a genuine
 * 404 per Ballotpedia's own MediaWiki page metadata, and the /api/v4/*
 * paths never resolve to real data). MediaWiki content is static
 * server-rendered HTML, so a single HTTP GET + DOM parse works fine
 * without a headless browser — see scripts/scrape-ballotpedia.js for the
 * higher-volume batch-scrape sibling of this technique.
 *
 * searchLocalCandidates()/searchCandidate()/getCandidateDetails() below
 * are unchanged and remain unused (isConfigured() still gates them off via
 * the never-set BALLOTPEDIA_API_KEY) — LocalCandidateAggregator's separate
 * "local candidates by address" feature depends on those being no-ops
 * today; fixing that is a separate task.
 */
class BallotpediaService
{
    protected string $baseUrl = 'https://ballotpedia.org/api/v4';
    protected ?string $apiKey;
    protected int $cacheDuration = 86400; // 24 hours — successful scrape

    /**
     * Cache TTL for a failed scrape attempt (WAF challenge, timeout, etc.).
     * Deliberately much shorter than the success TTL: Ballotpedia's
     * CloudFront/AWS WAF occasionally answers with an HTTP 202 bot-challenge
     * instead of the real page, which is usually transient — caching that
     * as "unavailable" for a full day would hide real data over one bad
     * request. See the "Fix Broken House/Senate Candidate Scraping" note.
     */
    protected int $failureCacheDuration = 900; // 15 minutes

    private const SCRAPE_USER_AGENT = 'u9itus-profile/1.0 (+https://u9itus.dev/about)';

    public function __construct()
    {
        $this->apiKey = config('services.ballotpedia.api_key');
    }

    /**
     * Check if service is properly configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Fetch a short profile snapshot (bio + committee assignments) by
     * scraping the politician's own Ballotpedia article.
     *
     * @return array{source_url: string, bio: ?string, committees: array<int, string>}|null
     */
    public function fetchPoliticianData(Politician $politician): ?array
    {
        $cacheKey = "ballotpedia.politician.{$politician->id}";

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached === 'MISS' ? null : $cached;
        }

        $data = $this->scrapeProfile($politician);

        Cache::put(
            $cacheKey,
            $data ?? 'MISS',
            $data !== null ? $this->cacheDuration : $this->failureCacheDuration
        );

        return $data;
    }

    /**
     * @return array{source_url: string, bio: ?string, committees: array<int, string>}|null
     */
    private function scrapeProfile(Politician $politician): ?array
    {
        try {
            $url = $this->resolveProfileUrl($politician);
            if ($url === null) {
                return null;
            }

            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => self::SCRAPE_USER_AGENT])
                ->get($url);

            // A 202 means Ballotpedia's WAF served a JS bot-challenge instead
            // of the real page — evaluating that page finds nothing real, so
            // treat it the same as any other failed fetch (short cache TTL
            // lets the next profile view try again rather than waiting 24h).
            if ($response->status() === 202 || !$response->successful()) {
                $this->logHttpFailure('scrape_profile', $response->status(), ['url' => $url]);
                return null;
            }

            $html = (string) $response->body();
            $bio = $this->extractBio($html);
            $committees = $this->extractCommitteeAssignments($html);

            if ($bio === null && $committees === []) {
                return null;
            }

            return [
                'source_url' => $url,
                'bio' => $bio,
                'committees' => $committees,
            ];
        } catch (\Throwable $e) {
            $this->logProviderException('scrape_profile', $e, [
                'politician_id' => $politician->id,
            ]);

            return null;
        }
    }

    /**
     * Resolve the politician's real Ballotpedia article URL. Uses the
     * stored ballotpedia_id if the batch scraper (or a prior call to this
     * method) already identified it; otherwise looks it up via Ballotpedia's
     * MediaWiki search API and persists the match so future calls skip the
     * search step.
     */
    private function resolveProfileUrl(Politician $politician): ?string
    {
        $id = trim((string) ($politician->ballotpedia_id ?? ''));
        if ($id !== '') {
            return 'https://ballotpedia.org/' . $id;
        }

        $name = trim((string) $politician->full_name);
        if ($name === '') {
            return null;
        }

        $title = $this->searchArticleTitle($name, (string) ($politician->state ?? ''));
        if ($title === null) {
            return null;
        }

        $slug = str_replace(' ', '_', $title);
        $politician->update(['ballotpedia_id' => $slug]);

        return 'https://ballotpedia.org/' . $slug;
    }

    /**
     * Search Ballotpedia's MediaWiki search API for the best-matching
     * article title. Requires the candidate's surname to appear in the
     * matched title as a basic sanity check against unrelated results
     * (e.g. an organization's page that merely mentions the candidate).
     */
    private function searchArticleTitle(string $name, string $state): ?string
    {
        try {
            $response = Http::timeout(8)
                ->withHeaders(['User-Agent' => self::SCRAPE_USER_AGENT])
                ->get('https://ballotpedia.org/wiki/api.php', [
                    'action' => 'query',
                    'list' => 'search',
                    'srsearch' => trim("{$name} {$state}"),
                    'format' => 'json',
                    'srlimit' => 3,
                ]);

            // Laravel's successful() treats 202 as success (it's a 2xx code),
            // but Ballotpedia's WAF uses exactly that status for its empty-body
            // bot-challenge response — check for it explicitly, same as the
            // profile-page fetch above, or a challenge silently "succeeds"
            // into an empty result set.
            if ($response->status() === 202 || !$response->successful()) {
                return null;
            }

            $results = $response->json('query.search', []);
            $nameParts = preg_split('/\s+/', trim($name)) ?: [];
            $surname = strtolower((string) end($nameParts));

            foreach ($results as $result) {
                $title = (string) ($result['title'] ?? '');
                if ($surname !== '' && str_contains(strtolower($title), $surname)) {
                    return $title;
                }
            }

            return null;
        } catch (\Throwable $e) {
            $this->logProviderException('search_article_title', $e, ['name' => $name]);
            return null;
        }
    }

    /**
     * First substantive paragraph of the article body.
     */
    private function extractBio(string $html): ?string
    {
        $doc = $this->parseHtml($html);
        if ($doc === null) {
            return null;
        }

        $xpath = new DOMXPath($doc);
        $paragraphs = $xpath->query("//div[contains(@class,'mw-parser-output')]//p");

        foreach ($paragraphs as $p) {
            $text = trim(preg_replace('/\s+/', ' ', $p->textContent ?? ''));
            if (mb_strlen($text) > 40) {
                return mb_substr($text, 0, 400);
            }
        }

        return null;
    }

    /**
     * Committee-assignment list items, if the article has that section — a
     * heading containing "Committee assignment" followed by <ul> content.
     * Ballotpedia bio pages don't carry structured voting-record or
     * sponsored-legislation data (that lives in GovTrack/congress.gov-style
     * databases, not wiki prose), so this is the only structured section
     * reliably scrapable from the article itself.
     *
     * @return array<int, string>
     */
    private function extractCommitteeAssignments(string $html): array
    {
        $doc = $this->parseHtml($html);
        if ($doc === null) {
            return [];
        }

        $xpath = new DOMXPath($doc);
        $lower = "translate(., 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz')";
        $headings = $xpath->query("//h2[contains({$lower}, 'committee assignment')] | //h3[contains({$lower}, 'committee assignment')]");

        if ($headings->length === 0) {
            return [];
        }

        $items = [];
        $node = $headings->item(0)->nextSibling;
        $steps = 0;
        while ($node !== null && $steps < 20 && count($items) < 10) {
            $steps++;
            // Only h2 marks a genuine new top-level section. Ballotpedia
            // nests year-range subsections (e.g. "2025-2026") under
            // "Committee assignments" as an immediately-following h3 —
            // stopping there too (as an earlier version of this did) meant
            // the <ul> that comes after the h3 was never reached.
            if ($node->nodeName === 'h2') {
                break;
            }
            if ($node instanceof \DOMElement) {
                foreach ($xpath->query('.//li', $node) as $li) {
                    // Each <li>'s OWN text only — some committee entries have
                    // a nested <ul> of subcommittees, and $li->textContent
                    // would otherwise concatenate the parent + every nested
                    // child into one string (each child is still captured
                    // separately since .//li also matches nested <li>s).
                    $text = $this->directElementText($li);
                    if ($text !== '' && mb_strlen($text) < 200) {
                        $items[] = $text;
                    }
                }
            }
            $node = $node->nextSibling;
        }

        return array_slice($items, 0, 10);
    }

    /**
     * An element's own text, skipping any nested <ul>/<ol> (whose <li>
     * items are picked up separately by the caller's own traversal).
     */
    private function directElementText(\DOMElement $el): string
    {
        $parts = [];
        foreach ($el->childNodes as $child) {
            if (in_array($child->nodeName, ['ul', 'ol'], true)) {
                continue;
            }
            $parts[] = $child->textContent ?? '';
        }

        return trim(preg_replace('/\s+/', ' ', implode('', $parts)));
    }

    private function parseHtml(string $html): ?DOMDocument
    {
        $doc = new DOMDocument();
        $prevErrors = libxml_use_internal_errors(true);
        $loaded = $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($prevErrors);

        return $loaded ? $doc : null;
    }

    /**
     * Search for local candidates by address and election filters
     * 
     * @param string $address Full address or state
     * @param array $filters Optional filters (election_year, office_type, etc.)
     * @return array Candidate records
     */
    public function searchLocalCandidates(string $address, array $filters = []): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => "Bearer {$this->apiKey}",
                    'Accept' => 'application/json',
                ])
                ->get("{$this->baseUrl}/candidates/search", [
                    'query' => $address,
                    'limit' => $filters['limit'] ?? 50,
                ]);

            if (!$response->successful()) {
                $this->logHttpFailure('search_local_candidates', $response->status(), [
                    'address' => $address,
                ]);

                return [];
            }

            $results = $response->json('data', []);

            // Filter by governance level if specified
            if (!empty($filters['governance_levels'])) {
                $results = array_filter($results, function ($candidate) use ($filters) {
                    $officeType = strtolower($candidate['office_type'] ?? '');
                    return in_array($officeType, array_map('strtolower', $filters['governance_levels']));
                });
            }

            return array_values($results);

        } catch (\Throwable $e) {
            $this->logProviderException('search_local_candidates', $e, [
                'address' => $address,
            ]);

            return [];
        }
    }

    /**
     * Search for a candidate by name and state
     * 
     * @param string $name
     * @param string $state
     * @return array
     */
    protected function searchCandidate(string $name, string $state): array
    {
        $response = Http::timeout(10)
            ->withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Accept' => 'application/json',
            ])
            ->get("{$this->baseUrl}/candidates/search", [
                'query' => $name,
                'state' => $state,
                'limit' => 5,
            ]);

        if (!$response->successful()) {
            $this->logHttpFailure('search_candidate', $response->status(), [
                'body' => $response->body(),
            ]);

            return [];
        }

        return $response->json('data', []);
    }

    /**
     * Get detailed candidate information
     * 
     * @param string $candidateId
     * @return array|null
     */
    protected function getCandidateDetails(string $candidateId): ?array
    {
        $response = Http::timeout(10)
            ->withHeaders([
                'Authorization' => "Bearer {$this->apiKey}",
                'Accept' => 'application/json',
            ])
            ->get("{$this->baseUrl}/candidates/{$candidateId}");

        if (!$response->successful()) {
            $this->logHttpFailure('get_candidate_details', $response->status(), [
                'candidate_id' => $candidateId,
            ]);

            return null;
        }

        $data = $response->json('data', []);

        return [
            'id' => $data['id'] ?? null,
            'name' => $data['name'] ?? null,
            'office' => $data['office'] ?? null,
            'party' => $data['party'] ?? null,
            'profile_url' => $data['url'] ?? null,
            'voting_record' => $data['voting_record'] ?? [],
            'committee_assignments' => $data['committees'] ?? [],
            'sponsored_legislation' => $data['sponsored_bills'] ?? [],
            'bio' => $data['biography'] ?? null,
            'source_url' => $data['url'] ?? null,
        ];
    }

    protected function logHttpFailure(string $operation, int $status, array $context = []): void
    {
        Log::warning('BallotpediaService telemetry: HTTP request failed', array_merge($context, [
            'operation' => $operation,
            'status' => $status,
            'is_rate_limited' => $status === 429,
        ]));
    }

    protected function logProviderException(string $operation, \Throwable $exception, array $context = []): void
    {
        Log::warning('BallotpediaService telemetry: provider exception', array_merge($context, [
            'operation' => $operation,
            'error' => $exception->getMessage(),
        ]));
    }

    /**
     * Convert Ballotpedia candidate to standard candidate record format
     * 
     * @param array $candidate Ballotpedia candidate data
     * @return array Standardized record
     */
    public function convertToStandardFormat(array $candidate): array
    {
        return [
            'external_candidate_id' => 'ballotpedia_' . ($candidate['id'] ?? md5($candidate['name'] ?? '')),
            'full_name' => $candidate['name'] ?? 'Unknown',
            'political_office' => $candidate['office'] ?? 'Unknown',
            'state' => $this->extractStateFromOffice($candidate['office'] ?? ''),
            'party_affiliation' => $candidate['party'] ?? null,
            'governance_level' => $this->mapOfficeToGovernanceLevel($candidate['office'] ?? ''),
            'source' => 'ballotpedia',
            'payload' => $candidate,
        ];
    }

    /**
     * Extract state from office string
     */
    protected function extractStateFromOffice(string $office): ?string
    {
        if (preg_match('/\b([A-Z]{2})\b/', $office, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Map Ballotpedia office type to governance level
     */
    protected function mapOfficeToGovernanceLevel(string $office): string
    {
        $office = strtolower($office);

        if (strpos($office, 'congress') !== false || strpos($office, 'representative') !== false || strpos($office, 'senator') !== false) {
            return 'Federal';
        }
        if (strpos($office, 'state') !== false) {
            return 'State';
        }
        if (strpos($office, 'county') !== false) {
            return 'County';
        }
        if (strpos($office, 'city') !== false || strpos($office, 'mayor') !== false || strpos($office, 'council') !== false) {
            return 'City';
        }

        return 'Local';
    }

    /**
     * Clear cached data for a politician
     * 
     * @param Politician $politician
     * @return void
     */
    public function clearCache(Politician $politician): void
    {
        Cache::forget("ballotpedia.politician.{$politician->id}");
    }

    /**
     * Get display-ready data for the public profile's "Dig Deeper" card.
     *
     * @return array{source: string, source_url: string, summary: array<int, string>, sections: array<int, array{title: string, items: array<int, string>}>}|null
     */
    public function getDisplayData(Politician $politician): ?array
    {
        $data = $this->fetchPoliticianData($politician);

        if (!$data) {
            return null;
        }

        $sections = [];
        if (!empty($data['committees'])) {
            $sections[] = [
                'title' => 'Committee Assignments',
                'items' => $data['committees'],
            ];
        }

        $summary = [];
        if (!empty($data['bio'])) {
            $summary[] = Str::limit($data['bio'], 140);
        }

        return [
            'source' => 'Ballotpedia',
            'source_url' => $data['source_url'],
            'summary' => $summary,
            'sections' => $sections,
        ];
    }
}
