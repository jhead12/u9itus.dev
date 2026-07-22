<?php

namespace App\Services;

use App\Models\CandidateNewsArticle;
use App\Models\Politician;
use App\Models\ProfileAddress;
use App\Models\ProfileContactMethod;
use App\Models\ProfileDonationLink;
use App\Models\ProfileEnrichmentRun;
use App\Models\ProfileSocialLink;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ProfileEnricherService
 *
 * Fetches a politician's own official / campaign website and extracts structured
 * facts: contact methods (phone/email/fax), office addresses, social/newsletter
 * links, and donation page URLs. When a Substack/newsletter link is found it also
 * pulls recent public posts (titled links only) into candidate_news_articles.
 *
 * Legal / safety scoping baked in (not just noted):
 *  - Only OFFICIAL contact info, public social/newsletter links, and link-OUT
 *    donation URLs are stored. Donation flows are never proxied or embedded.
 *  - Residential / home addresses are rejected in normalizeAddress(); the
 *    address_kind enum has no residential value, so there is no legal path for
 *    one to land in the table.
 *  - No prose, images, or logos are copied. Newsletter posts are titled links
 *    only — body_html / truncated_body_text are never persisted.
 *  - robots.txt is honored, hosts are rate-limited, and a descriptive User-Agent
 *    is sent. No credentials, no login bypass.
 *
 * Shape mirrors FECService: auto-wired, isConfigured() guards the Claude
 * fallback tier, Http facade + Cache::remember, try/catch → log helpers,
 * returns [] / null on failure (never throws), getDisplayData() returns the
 * ['source','source_url','sections'] shape the blade renderers expect.
 */
class ProfileEnricherService
{
    protected ?string $apiKey;
    protected ?string $anthropicModel;
    protected string $userAgent;
    protected int $timeout;
    protected int $cacheTtl;
    protected int $ratePerHost;
    protected int $maxPosts;

    /** Minimum fact count before the Claude fallback tier fires. */
    protected int $claudeFallbackThreshold = 3;

    public function __construct()
    {
        $this->apiKey         = config('services.profile_enricher.anthropic_key');
        $this->anthropicModel = config('services.profile_enricher.anthropic_model');
        $this->userAgent      = config('services.profile_enricher.user_agent');
        $this->timeout        = (int) config('services.profile_enricher.timeout', 20);
        $this->cacheTtl       = (int) config('services.profile_enricher.cache_ttl', 86400);
        $this->ratePerHost    = (int) config('services.profile_enricher.rate_per_host', 1);
        $this->maxPosts       = (int) config('services.profile_enricher.max_posts', 10);
    }

    /**
     * True when the Claude fallback tier is available. The HTML heuristics
     * tier always works regardless of this flag.
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    // ── Orchestrator ───────────────────────────────────────────────────────

    /**
     * Fetch → extract → normalize → persist for one politician.
     * Returns the display-shaped summary, or null on hard failure.
     */
    public function enrich(Politician $politician): ?array
    {
        $url = trim((string) $politician->website_url);
        if ($url === '') {
            return null;
        }

        try {
            $robots = $this->robotsAllowed($url);

            if (!$robots) {
                $this->logRobotsBlock($url);
                // No run row on robots-block: leaving no run keeps the profile
                // retryable on the next cron instead of marking it "fresh".
                $this->clearCache($politician);
                return null;
            }

            $page = $this->fetchPageHtml($url);

            if ($page === null) {
                // No run row on HTTP failure either — keep it retryable.
                $this->clearCache($politician);
                return null;
            }

            [$html, $finalUrl, $status] = [$page['html'], $page['final_url'], $page['status']];

            $facts = $this->extractFromHtml($html, $finalUrl);
            $usedClaude = false;

            if ($this->factCount($facts) < $this->claudeFallbackThreshold && $this->isConfigured()) {
                $claude = $this->extractWithClaude($html, $finalUrl);
                if ($claude !== null) {
                    $facts = $this->mergeClaudeFacts($facts, $claude);
                    $usedClaude = true;
                }
            }

            $normalized = $this->normalizeAll($facts, $finalUrl);

            $counts = [
                'contact_methods'  => count($normalized['contact_methods']),
                'addresses'        => count($normalized['addresses']),
                'social_links'     => count($normalized['social_links']),
                'donation_links'   => count($normalized['donation_links']),
                'newsletter_posts' => 0,
            ];

            $fetchStatus = $this->factCount($normalized) > 0 ? ($usedClaude ? 'claude_fallback' : 'ok') : 'empty';

            $run = $this->recordRun(
                $politician,
                $finalUrl,
                $fetchStatus,
                $status,
                $usedClaude,
                ['robots_allowed' => true, 'extracted_counts' => $counts]
            );

            $this->persistFacts($politician, $normalized, $finalUrl, $run);

            // Newsletter posts adapter.
            $newsletterUrl = $this->extractNewsletterUrl($normalized['social_links']);
            if ($newsletterUrl !== null) {
                $stored = $this->fetchNewsletterPosts($newsletterUrl, $politician);
                $counts['newsletter_posts'] = count($stored);
                if ($run) {
                    $run->update(['extracted_counts' => $counts]);
                }
            }

            $this->clearCache($politician);

            return [
                'source'     => 'Official campaign website',
                'source_url' => $finalUrl,
                'sections'   => $this->buildSections($politician, $counts),
            ];
        } catch (\Throwable $e) {
            $this->logProviderException('enrich', $e, ['politician_id' => $politician->id]);
            // No run row on exception — keep the profile retryable.
            $this->clearCache($politician);
            return null;
        }
    }

    // ── HTTP tier ──────────────────────────────────────────────────────────

    /**
     * Fetch the page HTML. Returns ['html','final_url','status'] or null.
     * Cached under profile_enricher.page.{sha1(url)} for cacheTtl.
     */
    public function fetchPageHtml(string $url): ?array
    {
        $cacheKey = 'profile_enricher.page.' . sha1($url);

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($url) {
            try {
                $host = parse_url($url, PHP_URL_HOST) ?: '';
                $this->respectRateLimit($host);

                $response = Http::timeout($this->timeout)
                    ->withHeaders(['User-Agent' => $this->userAgent])
                    ->get($url);

                if (!$response->ok()) {
                    $this->logHttpFailure('fetch_page_html', $response->status(), ['url' => $url]);
                    return null;
                }

                $body = $response->body();
                if (trim($body) === '') {
                    return null;
                }

                return [
                    'html'      => $body,
                    'final_url' => $response->effectiveUri() ?: $url,
                    'status'    => $response->status(),
                ];
            } catch (\Throwable $e) {
                $this->logProviderException('fetch_page_html', $e, ['url' => $url]);
                return null;
            }
        });
    }

    /**
     * Honor robots.txt for the target host. Cached per host for 24h.
     */
    public function robotsAllowed(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        if (!$host) {
            return true;
        }

        $cacheKey = 'profile_enricher.robots.' . $host;

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($host, $path) {
            try {
                $this->respectRateLimit($host);
                $response = Http::timeout($this->timeout)
                    ->withHeaders(['User-Agent' => $this->userAgent])
                    ->get("https://{$host}/robots.txt");

                if (!$response->ok() || trim($response->body()) === '') {
                    // No robots.txt → allowed.
                    return true;
                }

                return $this->parseRobotsAllows($response->body(), $path);
            } catch (\Throwable $e) {
                // Fail open (allow) on robots fetch errors.
                return true;
            }
        });
    }

    /**
     * Minimal robots.txt parser: respects User-agent: * and Disallow/Allow
     * rules for the given path. Most-specific match wins; empty Disallow = allow.
     */
    protected function parseRobotsAllows(string $robots, string $path): bool
    {
        $applies = true; // default to "User-agent: *" group
        $rules = [];
        $lines = preg_split('/\r\n|\n|\r/', $robots) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (preg_match('/^User-agent:\s*(.+)$/i', $line, $m)) {
                $applies = trim($m[1]) === '*';
                continue;
            }
            if (!$applies) {
                continue;
            }
            if (preg_match('/^(Disallow|Allow):\s*(.*)$/i', $line, $m)) {
                $type = strtolower($m[1]);
                $value = trim($m[2]);
                if ($value === '') {
                    // "Disallow:" (empty) means allow everything.
                    if ($type === 'disallow') {
                        return true;
                    }
                    continue;
                }
                $rules[] = ['type' => $type, 'value' => $value];
            }
        }

        // Most-specific (longest matching) rule wins.
        $best = null;
        foreach ($rules as $rule) {
            if ($this->robotsMatch($rule['value'], $path)) {
                if ($best === null || strlen($rule['value']) > strlen($best['value'])) {
                    $best = $rule;
                }
            }
        }

        return $best === null || $best['type'] !== 'disallow';
    }

    protected function robotsMatch(string $pattern, string $path): bool
    {
        $pattern = preg_quote($pattern, '#');
        $pattern = str_replace(['\*'], ['.*'], $pattern);
        return (bool) preg_match('#^' . $pattern . '#', $path);
    }

    protected function respectRateLimit(string $host): void
    {
        if ($host === '' || $this->ratePerHost <= 0) {
            return;
        }
        $key = 'profile_enricher.rate.' . $host;
        // Best-effort spacing; not a hard lock.
        if (Cache::has($key)) {
            usleep(max(0, $this->ratePerHost) * 1_000_000);
        }
        Cache::put($key, 1, $this->ratePerHost);
    }

    // ── Extraction tier ─────────────────────────────────────────────────────

    /**
     * Heuristic extraction via PHP's native DOMDocument/DOMXPath (libxml).
     * No external HTML parser dependency is required.
     *
     * @return array{contact_methods:array, addresses:array, social_links:array, donation_links:array, newsletter_url:?string}
     */
    public function extractFromHtml(string $html, string $url): array
    {
        $contactMethods = [];
        $addresses = [];
        $socialLinks = [];
        $donationLinks = [];

        try {
            $dom = $this->loadHtml($html);
            if ($dom === null) {
                return [
                    'contact_methods' => [], 'addresses' => [], 'social_links' => [],
                    'donation_links' => [], 'newsletter_url' => null,
                ];
            }
            $xpath = new \DOMXPath($dom);

            // Contact methods — tel: and mailto: anchors with a nearby label.
            $nodes = $xpath->query('//a[starts-with(@href,"tel:") or starts-with(@href,"mailto:")]');
            foreach ($nodes as $node) {
                $href = $node->getAttribute('href');
                $kind = str_starts_with($href, 'tel:') ? 'phone' : 'email';
                // rawurldecode (not urldecode) so a leading '+' on international
                // phone numbers isn't turned into a space.
                $value = rawurldecode(substr($href, $kind === 'phone' ? 4 : 7));
                $contactMethods[] = [
                    'kind'   => $kind,
                    'value'  => $value,
                    'label'  => $this->nearestLabel($node),
                    'source' => $url,
                ];
            }

            // Addresses — <address>, JSON-LD PostalAddress.
            foreach ($this->extractAddresses($dom, $xpath) as $addr) {
                $addr['source'] = $url;
                $addresses[] = $addr;
            }

            // Social + donation links — classify every anchor href by host/text.
            foreach ($dom->getElementsByTagName('a') as $a) {
                $href = $a->getAttribute('href');
                $text = trim($a->textContent ?? '');
                $absolute = $this->absoluteUrl($href, $url);
                if ($absolute === null) {
                    continue;
                }

                $social = $this->classifySocial($absolute);
                if ($social !== null) {
                    $social['label'] = $text;
                    $social['source'] = $url;
                    $socialLinks[] = $social;
                }

                $donation = $this->classifyDonation($absolute, $text);
                if ($donation !== null) {
                    $donation['label'] = $text;
                    $donation['source'] = $url;
                    $donationLinks[] = $donation;
                }
            }
        } catch (\Throwable $e) {
            $this->logProviderException('extract_from_html', $e, ['url' => $url]);
        }

        return [
            'contact_methods' => $contactMethods,
            'addresses'        => $addresses,
            'social_links'     => $socialLinks,
            'donation_links'   => $donationLinks,
            'newsletter_url'   => null,
        ];
    }

    /**
     * Claude haiku fallback — strict JSON extraction. Mirrors the Tier-3
     * pattern in EnrichStatewideOfficeholders::fetchWithClaude.
     */
    public function extractWithClaude(string $html, string $url): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        try {
            $cleaned = $this->stripNoise($html);
            // Keep the prompt bounded.
            $body = mb_substr($cleaned, 0, 12000);

            $system = 'You are a civic-data extraction assistant. From the provided HTML of a '
                . 'U.S. politician\'s official or campaign website, extract ONLY structured contact '
                . 'facts. Output strict JSON and nothing else — no markdown fences, no explanation. '
                . 'NEVER include residential/home addresses. For donations, return only the donate '
                . 'page URL. Do not invent data; if a category is absent, return an empty array.';

            $shape = '{"contact_methods":[{"kind":"phone|email|fax","value":"...","label":"..."}],'
                . '"addresses":[{"address_kind":"office|district|mailing","label":"...","line1":"...","line2":"...","city":"...","state":"...","postal_code":"..."}],'
                . '"social_links":[{"platform":"facebook|x_twitter|instagram|youtube|tiktok|linkedin|threads|bluesky|substack|rss|newsletter|other","url":"...","handle":"..."}],'
                . '"donation_links":[{"url":"...","platform":"actblue|winred|other","label":"..."}],'
                . '"newsletter_url":null}';

            $user = 'Extract from this page (URL: ' . $url . ') the following as JSON with this exact shape: '
                . $shape . "\n\n"
                . "Rules: only official contact info; reject any address whose label or text indicates a "
                . "home/residence; do not include donation form bodies or amounts; copy no prose.\n\n"
                . "HTML:\n```\n" . $body . "\n```";

            $response = Http::timeout(15)
                ->withHeaders([
                    'x-api-key'         => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                    'Content-Type'      => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model'      => $this->anthropicModel,
                    'max_tokens' => 1500,
                    'system'     => $system,
                    'messages'   => [['role' => 'user', 'content' => $user]],
                ]);

            if (!$response->ok()) {
                $this->logHttpFailure('extract_with_claude', $response->status(), ['url' => $url]);
                return null;
            }

            $text = trim($response->json('content.0.text') ?? '');
            // Strip accidental markdown fences.
            if (preg_match('/\{.*\}/s', $text, $m)) {
                $text = $m[0];
            }
            $decoded = json_decode($text, true);

            if (!is_array($decoded)) {
                return null;
            }

            return $this->normalizeClaudeShape($decoded);
        } catch (\Throwable $e) {
            $this->logProviderException('extract_with_claude', $e, ['url' => $url]);
            return null;
        }
    }

    // ── Newsletter adapter (Substack JSON → RSS fallback) ──────────────────

    /**
     * Pull recent public posts from a Substack/newsletter publication and store
     * them in candidate_news_articles as titled links only (no body, no image).
     * Returns the stored rows.
     */
    public function fetchNewsletterPosts(string $publicationUrl, Politician $politician): array
    {
        try {
            $root = $this->resolvePublicationRoot($publicationUrl);
            if ($root === null) {
                return [];
            }

            $limit = max(1, $this->maxPosts);
            $posts = $this->fetchSubstackJson($root, $limit);

            if ($posts === null) {
                // Custom-domain 404 → RSS fallback.
                $posts = $this->fetchRssFeed($root, $limit);
            }

            $stored = [];
            foreach ($posts as $post) {
                $row = $this->storeNewsletterPost($post, $politician);
                if ($row !== null) {
                    $stored[] = $row;
                }
            }

            return $stored;
        } catch (\Throwable $e) {
            $this->logProviderException('fetch_newsletter_posts', $e, [
                'politician_id'   => $politician->id,
                'publication_url' => $publicationUrl,
            ]);
            return [];
        }
    }

    protected function fetchSubstackJson(string $root, int $limit): ?array
    {
        try {
            $url = rtrim($root, '/') . '/api/v1/posts?limit=' . $limit . '&offset=0';
            $response = Http::timeout($this->timeout)
                ->withHeaders(['User-Agent' => $this->userAgent])
                ->get($url);

            if (!$response->ok()) {
                return null;
            }

            $data = $response->json();
            if (!is_array($data) || (array_is_list($data) === false && !isset($data['posts']))) {
                return null;
            }

            $list = array_is_list($data) ? $data : ($data['posts'] ?? []);
            $out = [];
            foreach ($list as $post) {
                if (!is_array($post) || empty($post['canonical_url']) || empty($post['title'])) {
                    continue;
                }
                $out[] = [
                    'headline'     => strip_tags((string) $post['title']),
                    'source_url'   => (string) $post['canonical_url'],
                    'published_at'  => $post['post_date'] ?? null,
                    'paywalled'     => ($post['audience'] ?? null) === 'only_paid',
                    'provider'      => 'substack_json',
                ];
            }

            return $out;
        } catch (\Throwable $e) {
            $this->logProviderException('fetch_substack_json', $e, ['root' => $root]);
            return null;
        }
    }

    protected function fetchRssFeed(string $root, int $limit): array
    {
        try {
            $url = rtrim($root, '/') . '/feed';
            $response = Http::timeout($this->timeout)
                ->withHeaders(['User-Agent' => $this->userAgent])
                ->get($url);

            if (!$response->ok() || trim($response->body()) === '') {
                return [];
            }

            $xml = @simplexml_load_string($response->body(), \SimpleXMLElement::class, LIBXML_NOCDATA);
            if ($xml === false || $xml === null) {
                return [];
            }

            $items = $xml->channel->item ?? $xml->entry ?? [];
            $out = [];
            $count = 0;
            foreach ($items as $item) {
                if ($count >= $limit) {
                    break;
                }
                $title = (string) ($item->title ?? '');
                $link = (string) ($item->link ?? '');
                if ($title === '' || $link === '') {
                    continue;
                }
                $out[] = [
                    'headline'    => $title,
                    'source_url'   => $link,
                    'published_at' => (string) ($item->pubDate ?? ''),
                    'paywalled'    => false,
                    'provider'     => 'substack_rss',
                ];
                $count++;
            }

            return $out;
        } catch (\Throwable $e) {
            $this->logProviderException('fetch_rss_feed', $e, ['root' => $root]);
            return [];
        }
    }

    protected function storeNewsletterPost(array $post, Politician $politician): ?CandidateNewsArticle
    {
        $sourceUrl = $post['source_url'];
        $hash = hash('sha256', strtolower(trim($sourceUrl)));

        $paywalled = !empty($post['paywalled']);

        return CandidateNewsArticle::firstOrCreate(
            ['source_hash' => $hash],
            [
                'politician_id'        => $politician->id,
                'candidate_name'        => $politician->full_name,
                'headline'              => $post['headline'],
                'source_name'           => null,
                'source_url'            => $sourceUrl,
                // Titled link only — never store body text or images.
                'snippet'               => null,
                'image_url'             => null,
                'published_at'          => $post['published_at'] ?: null,
                'provider'              => $post['provider'],
                'content_type'          => 'newsletter_post',
                'verification_status'    => 'verified',
                'verification_reason'   => $paywalled
                    ? 'newsletter_paywalled_titled_link_only'
                    : 'newsletter_public_titled_link_only',
                'scraped_at'            => now(),
            ]
        );
    }

    // ── Normalizers ─────────────────────────────────────────────────────────

    /**
     * Reject residential addresses. Returns null for anything that looks like
     * a home/residence. address_kind enum has no residential value, so a
     * residential address can never be persisted.
     */
    public function normalizeAddress(array $raw, string $pageUrl): ?array
    {
        $label = trim((string) ($raw['label'] ?? ''));
        $line1 = trim((string) ($raw['line1'] ?? ''));
        $line2 = trim((string) ($raw['line2'] ?? ''));
        $city = trim((string) ($raw['city'] ?? ''));

        if ($line1 === '' && $city === '') {
            return null;
        }

        $haystack = strtolower(implode(' ', array_filter([$label, $line1, $line2, $city])));
        $residentialMarkers = [
            'home', 'residence', 'residential', 'private address', 'house',
            'home office', 'personal address', 'resides at', 'dwelling',
        ];
        foreach ($residentialMarkers as $marker) {
            if ($marker !== '' && str_contains($haystack, $marker)) {
                return null;
            }
        }

        $kind = $this->inferAddressKind($label, $raw['address_kind'] ?? null);

        $state = (string) ($raw['state'] ?? '');
        $postal = (string) ($raw['postal_code'] ?? '');
        $country = (string) ($raw['country_code'] ?? 'US');

        $full = trim(preg_replace('/\s+/', ' ', implode(', ', array_filter([
            $line1, $line2, $city, $state, $postal, $country !== 'US' ? $country : '',
        ]))));

        return [
            'address_kind'  => $kind,
            'label'          => $label !== '' ? $label : null,
            'line1'          => $line1,
            'line2'          => $line2 !== '' ? $line2 : null,
            'city'           => $city,
            'state'          => $state !== '' ? $state : null,
            'postal_code'    => $postal !== '' ? $postal : null,
            'country_code'   => $country,
            'full_address'   => $full,
            'source_url'     => $raw['source'] ?? $pageUrl,
            'source_selector' => $raw['source_selector'] ?? null,
        ];
    }

    protected function inferAddressKind(?string $label, ?string $given): string
    {
        if ($given && in_array($given, ['office', 'district', 'mailing'], true)) {
            return $given;
        }
        $l = strtolower((string) $label);
        if ($l !== '') {
            if (str_contains($l, 'mailing') || str_contains($l, ' mail')) {
                return 'mailing';
            }
            if (str_contains($l, 'district') || str_contains($l, 'local office')) {
                return 'district';
            }
            if (str_contains($l, 'capitol') || str_contains($l, 'state office')) {
                return 'office';
            }
        }
        return 'office'; // safe default for an official-site source
    }

    public function normalizeSocial(array $raw, string $pageUrl): ?array
    {
        $url = trim((string) ($raw['url'] ?? ''));
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $platform = (string) ($raw['platform'] ?? $this->classifySocial($url)['platform'] ?? 'other');
        if (!in_array($platform, $this->socialPlatforms(), true)) {
            $platform = 'other';
        }

        return [
            'platform'     => $platform,
            'url'          => $url,
            'handle'        => $raw['handle'] ?? $this->parseHandle($platform, $url),
            'display_name' => $raw['display_name'] ?? null,
            'is_official'  => true, // discovered on the candidate's own site
            'source_url'    => $raw['source'] ?? $pageUrl,
        ];
    }

    public function normalizeDonation(array $raw, string $pageUrl): ?array
    {
        $url = trim((string) ($raw['url'] ?? ''));
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $platform = $raw['platform'] ?? $this->detectDonationPlatform($url);
        if (!in_array($platform, $this->donationPlatforms(), true)) {
            $platform = 'other';
        }

        return [
            'url'         => $url,
            'platform'    => $platform,
            'is_official' => true,
            'label'        => $raw['label'] ?? null,
            'source_url'   => $raw['source'] ?? $pageUrl,
        ];
    }

    public function normalizeContactMethod(array $raw, string $pageUrl): ?array
    {
        $kind = (string) ($raw['kind'] ?? '');
        $value = trim((string) ($raw['value'] ?? ''));

        if (!in_array($kind, ['phone', 'email', 'fax'], true) || $value === '') {
            return null;
        }

        if ($kind === 'email') {
            $value = strtolower($value);
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return null;
            }
        } else {
            $value = preg_replace('/[^\d\+\-\(\)\s]/', '', $value);
            $value = trim(preg_replace('/\s+/', ' ', (string) $value));
            if ($value === '') {
                return null;
            }
        }

        return [
            'kind'   => $kind,
            'value'  => $value,
            'label'  => !empty($raw['label']) ? $raw['label'] : null,
            'country_code' => null,
            'is_primary'   => false,
            'source_url'   => $raw['source'] ?? $pageUrl,
        ];
    }

    // ── Persistence ────────────────────────────────────────────────────────

    protected function persistFacts(Politician $p, array $facts, string $sourceUrl, ?ProfileEnrichmentRun $run): void
    {
        $morph = ['profilable_type' => $p::class, 'profilable_id' => $p->id];
        $runId = $run?->id;

        foreach ($facts['contact_methods'] as $raw) {
            $n = $this->normalizeContactMethod($raw, $sourceUrl);
            if ($n === null) {
                continue;
            }
            ProfileContactMethod::updateOrCreate(
                array_merge($morph, ['kind' => $n['kind'], 'value' => $n['value']]),
                array_merge($n, $morph, ['run_id' => $runId, 'source_url' => $n['source_url']])
            );
        }

        foreach ($facts['addresses'] as $raw) {
            $n = $this->normalizeAddress($raw, $sourceUrl);
            if ($n === null) {
                continue;
            }
            ProfileAddress::updateOrCreate(
                array_merge($morph, ['address_kind' => $n['address_kind'], 'full_address' => $n['full_address']]),
                array_merge($n, $morph, ['run_id' => $runId])
            );
        }

        foreach ($facts['social_links'] as $raw) {
            $n = $this->normalizeSocial($raw, $sourceUrl);
            if ($n === null) {
                continue;
            }
            ProfileSocialLink::updateOrCreate(
                array_merge($morph, ['platform' => $n['platform'], 'url' => $n['url']]),
                array_merge($n, $morph, ['run_id' => $runId])
            );
        }

        foreach ($facts['donation_links'] as $raw) {
            $n = $this->normalizeDonation($raw, $sourceUrl);
            if ($n === null) {
                continue;
            }
            ProfileDonationLink::updateOrCreate(
                array_merge($morph, ['url' => $n['url']]),
                array_merge($n, $morph, ['run_id' => $runId])
            );
        }
    }

    protected function recordRun(
        Politician $p,
        string $sourceUrl,
        string $fetchStatus,
        ?int $httpStatus,
        bool $usedClaude,
        array $extra = []
    ): ProfileEnrichmentRun {
        return ProfileEnrichmentRun::create([
            'profilable_type'       => $p::class,
            'profilable_id'          => $p->id,
            'source_url'             => $sourceUrl,
            'fetch_status'           => $fetchStatus,
            'http_status'            => $httpStatus,
            'robots_allowed'          => $extra['robots_allowed'] ?? true,
            'extracted_counts'        => $extra['extracted_counts'] ?? null,
            'used_claude_fallback'    => $usedClaude,
            'enriched_at'             => now(),
        ]);
    }

    // ── Display ─────────────────────────────────────────────────────────────

    public function getDisplayData(Politician $politician): ?array
    {
        $cacheKey = 'profile_enricher.runs.' . $politician->id;

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($politician) {
            $run = $politician->latestEnrichmentRun;
            if (!$run) {
                return null;
            }

            $counts = $run->extracted_counts ?? [];

            return [
                'source'     => 'Official campaign website',
                'source_url' => $run->source_url ?? $politician->website_url,
                'sections'   => $this->buildSections($politician, $counts),
            ];
        });
    }

    public function clearCache(Politician $politician): void
    {
        Cache::forget('profile_enricher.runs.' . $politician->id);
        $url = trim((string) $politician->website_url);
        if ($url !== '') {
            Cache::forget('profile_enricher.page.' . sha1($url));
        }
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    protected function buildSections(Politician $politician, array $counts): array
    {
        return [
            'contact_methods' => $politician->contactMethods()
                ->orderByDesc('is_primary')->get()->map(fn ($r) => [
                    'kind' => $r->kind, 'value' => $r->value, 'label' => $r->label,
                ])->all(),
            'addresses' => $politician->addresses()->get()->map(fn ($r) => [
                'address_kind' => $r->address_kind, 'label' => $r->label,
                'display' => $r->toDisplayString(),
            ])->all(),
            'social_links' => $politician->socialLinks()->get()->map(fn ($r) => [
                'platform' => $r->platform, 'url' => $r->url, 'handle' => $r->handle,
                'is_official' => (bool) $r->is_official,
            ])->all(),
            'donation_links' => $politician->donationLinks()->get()->map(fn ($r) => [
                'url' => $r->url, 'platform' => $r->platform, 'is_official' => (bool) $r->is_official,
            ])->all(),
            'newsletter_posts' => CandidateNewsArticle::query()
                ->where('politician_id', $politician->id)
                ->whereIn('provider', ['substack_json', 'substack_rss'])
                ->recent($this->maxPosts)
                ->get()->map(fn ($r) => [
                    'headline' => $r->headline, 'source_url' => $r->source_url,
                    'published_at' => optional($r->published_at)->toIso8601String(),
                    'paywalled' => $r->verification_reason === 'newsletter_paywalled_titled_link_only',
                ])->all(),
        ];
    }

    protected function factCount(array $facts): int
    {
        return count($facts['contact_methods'] ?? [])
            + count($facts['addresses'] ?? [])
            + count($facts['social_links'] ?? [])
            + count($facts['donation_links'] ?? []);
    }

    protected function normalizeAll(array $facts, string $url): array
    {
        $apply = function (array $items, callable $fn) use ($url): array {
            $out = [];
            foreach ($items as $item) {
                $n = $fn($item, $url);
                if ($n !== null) {
                    $out[] = $n;
                }
            }
            return $out;
        };

        return [
            'contact_methods' => $apply($facts['contact_methods'] ?? [], [$this, 'normalizeContactMethod']),
            'addresses'        => $apply($facts['addresses'] ?? [], [$this, 'normalizeAddress']),
            'social_links'     => $apply($facts['social_links'] ?? [], [$this, 'normalizeSocial']),
            'donation_links'   => $apply($facts['donation_links'] ?? [], [$this, 'normalizeDonation']),
        ];
    }

    /** Merge Claude facts into heuristic facts, filling gaps only. */
    protected function mergeClaudeFacts(array $facts, array $claude): array
    {
        foreach (['contact_methods', 'addresses', 'social_links', 'donation_links'] as $key) {
            $existing = $facts[$key] ?? [];
            $new = $claude[$key] ?? [];
            if (empty($existing) && !empty($new)) {
                $facts[$key] = $new;
            } elseif (!empty($existing) && !empty($new)) {
                // Append only items whose key value isn't already present.
                foreach ($new as $item) {
                    if (!$this->factExists($existing, $item, $key)) {
                        $existing[] = $item;
                    }
                }
                $facts[$key] = $existing;
            }
        }
        if (!empty($claude['newsletter_url']) && empty($facts['newsletter_url'])) {
            $facts['newsletter_url'] = $claude['newsletter_url'];
        }
        return $facts;
    }

    protected function factExists(array $items, array $candidate, string $key): bool
    {
        $matchField = match ($key) {
            'contact_methods' => 'value',
            'addresses'        => 'line1',
            'social_links'     => 'url',
            'donation_links'   => 'url',
            default            => null,
        };
        if ($matchField === null) {
            return false;
        }
        $c = strtolower(trim((string) ($candidate[$matchField] ?? '')));
        if ($c === '') {
            return false;
        }
        foreach ($items as $item) {
            if (strtolower(trim((string) ($item[$matchField] ?? ''))) === $c) {
                return true;
            }
        }
        return false;
    }

    protected function normalizeClaudeShape(array $decoded): array
    {
        $pick = static fn ($k) => is_array($decoded[$k] ?? null) ? $decoded[$k] : [];
        return [
            'contact_methods' => $pick('contact_methods'),
            'addresses'        => $pick('addresses'),
            'social_links'     => $pick('social_links'),
            'donation_links'   => $pick('donation_links'),
            'newsletter_url'   => is_string($decoded['newsletter_url'] ?? null) ? $decoded['newsletter_url'] : null,
        ];
    }

    protected function extractAddresses(\DOMDocument $dom, \DOMXPath $xpath): array
    {
        $out = [];

        // <address> blocks — capture text + nearest heading label.
        foreach ($dom->getElementsByTagName('address') as $node) {
            $text = trim(preg_replace('/\s+/', ' ', $node->textContent ?? ''));
            if ($text === '') {
                continue;
            }
            $out[] = [
                'label'            => $this->nearestLabel($node),
                'line1'             => $text,
                'source_selector' => 'address',
            ];
        }

        // JSON-LD Organization / Place / PostalAddress.
        $nodes = $xpath->query('//script[@type="application/ld+json"]');
        foreach ($nodes as $node) {
            $raw = trim($node->textContent ?? '');
            if ($raw === '') {
                continue;
            }
            $data = json_decode($raw, true);
            if (!is_array($data)) {
                continue;
            }
            $items = array_is_list($data) ? $data : [$data];
            foreach ($items as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $address = $item['address'] ?? $item['location'] ?? null;
                $name = $item['name'] ?? '';
                if (is_array($address)) {
                    $out[] = [
                        'label'            => is_string($name) && $name !== '' ? $name : null,
                        'line1'             => $address['streetAddress'] ?? '',
                        'line2'             => $address['addressLine2'] ?? null,
                        'city'              => $address['addressLocality'] ?? '',
                        'state'             => $address['addressRegion'] ?? null,
                        'postal_code'      => $address['postalCode'] ?? null,
                        'country_code'     => $address['addressCountry'] ?? 'US',
                        'source_selector'  => 'jsonld',
                    ];
                }
            }
        }

        return $out;
    }

    protected function classifySocial(string $url): ?array
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = (string) parse_url($url, PHP_URL_PATH);

        $map = [
            'facebook.com'       => 'facebook',
            'fb.com'              => 'facebook',
            'twitter.com'         => 'x_twitter',
            'x.com'               => 'x_twitter',
            'instagram.com'       => 'instagram',
            'youtube.com'         => 'youtube',
            'youtu.be'            => 'youtube',
            'tiktok.com'          => 'tiktok',
            'linkedin.com'        => 'linkedin',
            'threads.net'         => 'threads',
            'bsky.app'            => 'bluesky',
            'truthsocial.com'     => 'truth_social',
            'substack.com'        => 'substack',
        ];

        foreach ($map as $needle => $platform) {
            if ($host === $needle || str_ends_with($host, '.' . $needle)) {
                return ['platform' => $platform, 'url' => $url, 'handle' => $this->parseHandle($platform, $url)];
            }
        }

        // Mastodon / generic fediverse: any host with a /@user path.
        if (preg_match('#^/(@[^/]+)#', $path)) {
            return ['platform' => 'mastodon', 'url' => $url, 'handle' => ltrim($path, '/')];
        }

        // RSS feed links.
        if (str_ends_with($path, '/feed') || str_ends_with($path, '.rss') || str_ends_with($path, '.xml')) {
            return ['platform' => 'rss', 'url' => $url, 'handle' => null];
        }

        return null;
    }

    protected function classifyDonation(string $url, string $text): ?array
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        $platform = $this->detectDonationPlatform($url);
        if ($platform !== null) {
            return ['platform' => $platform, 'url' => $url];
        }

        if (preg_match('/\b(donate|donation|contribute|chip in|support the campaign)\b/i', $text)) {
            return ['platform' => $this->isSameHost($url, $host) ? 'candidate_site' : 'other', 'url' => $url];
        }

        return null;
    }

    protected function detectDonationPlatform(string $url): ?string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $map = [
            'actblue.com'           => 'actblue',
            'winred.com'             => 'winred',
            'anedp.com'              => 'anedp',
            'democracyengine.com'    => 'democracy_engine',
            'donorbox.org'           => 'donorbox',
            'givebutter.com'         => 'givebutter',
            'stripe.com'             => 'stripe',
            'paypal.com'             => 'paypal',
            'square.com'             => 'square',
        ];
        foreach ($map as $needle => $platform) {
            if ($host === $needle || str_ends_with($host, '.' . $needle)) {
                return $platform;
            }
        }
        return null;
    }

    protected function isSameHost(string $url, string $host): bool
    {
        return $host !== '';
    }

    protected function parseHandle(string $platform, string $url): ?string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $segments = $path === '' ? [] : explode('/', $path);
        $first = $segments[0] ?? '';
        if ($first === '' || $first === 'c' || $first === 'channel' || $first === 'watch') {
            return null;
        }
        return ltrim($first, '@') !== '' ? ltrim($first, '@') : null;
    }

    protected function extractNewsletterUrl(array $socialLinks): ?string
    {
        foreach ($socialLinks as $link) {
            if (in_array(($link['platform'] ?? null), ['substack', 'rss', 'newsletter'], true)) {
                return $link['url'];
            }
        }
        return null;
    }

    protected function resolvePublicationRoot(string $url): ?string
    {
        $parts = parse_url($url);
        if (empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }
        return $parts['scheme'] . '://' . $parts['host'];
    }

    protected function absoluteUrl(string $href, string $base): ?string
    {
        $href = trim($href);
        if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'javascript:') || str_starts_with($href, 'tel:') || str_starts_with($href, 'mailto:')) {
            return null;
        }

        // Already absolute.
        if (preg_match('#^[a-zA-Z][a-zA-Z0-9+.\-]*://#', $href)) {
            return $href;
        }
        // Protocol-relative.
        if (str_starts_with($href, '//')) {
            $scheme = parse_url($base, PHP_URL_SCHEME) ?? 'https';
            return $scheme . ':' . $href;
        }

        $baseParts = parse_url($base);
        $scheme = $baseParts['scheme'] ?? 'https';
        $host = $baseParts['host'] ?? '';
        $port = isset($baseParts['port']) ? ':' . $baseParts['port'] : '';
        $basePath = $baseParts['path'] ?? '/';

        // Absolute path.
        if (str_starts_with($href, '/')) {
            return $scheme . '://' . $host . $port . $href;
        }

        // Relative path — resolve against the base directory.
        $dir = substr($basePath, 0, strrpos($basePath, '/') + 1) ?: '/';
        return $scheme . '://' . $host . $port . $dir . $href;
    }

    protected function nearestLabel(\DOMNode $node): ?string
    {
        // Find the nearest preceding heading/label: scan previous siblings
        // (and their descendants' last heading), then climb to ancestors.
        // This catches <h2>Home</h2><address>…</address> where the heading is a
        // sibling, not an ancestor.
        $current = $node;
        while ($current !== null && $current->parentNode !== null) {
            $sibling = $current->previousSibling;
            while ($sibling !== null) {
                $text = $this->headingText($sibling);
                if ($text !== null) {
                    return $text;
                }
                $sibling = $sibling->previousSibling;
            }
            $current = $current->parentNode;
        }

        // Last resort: an ancestor that is itself a heading/label.
        $ancestor = $node->parentNode;
        while ($ancestor !== null && $ancestor instanceof \DOMNode) {
            $text = $this->headingText($ancestor);
            if ($text !== null) {
                return $text;
            }
            $ancestor = $ancestor->parentNode;
        }
        return null;
    }

    protected function headingText(\DOMNode $node): ?string
    {
        if (! $node instanceof \DOMElement) {
            return null;
        }

        $tags = ['h1','h2','h3','h4','h5','h6','dt','strong','legend','th'];
        $tag = strtolower($node->nodeName);

        if (in_array($tag, $tags, true)) {
            $text = trim(preg_replace('/\s+/', ' ', $node->textContent ?? ''));
            return $text !== '' ? mb_substr($text, 0, 128) : null;
        }

        // Last heading descendant of this sibling (document order).
        $last = null;
        foreach ($node->getElementsByTagName('*') as $child) {
            if (in_array(strtolower($child->nodeName), ['h1','h2','h3','h4','h5','h6'], true)) {
                $text = trim(preg_replace('/\s+/', ' ', $child->textContent ?? ''));
                if ($text !== '') {
                    $last = mb_substr($text, 0, 128);
                }
            }
        }
        return $last;
    }

    protected function loadHtml(string $html): ?\DOMDocument
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $wrapped = '<?xml encoding="UTF-8" ?>' . $html;
        $ok = @$dom->loadHTML($wrapped, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED);
        libxml_clear_errors();
        return $ok ? $dom : null;
    }

    protected function stripNoise(string $html): string
    {
        $html = preg_replace('#<script[\s\S]*?</script>#i', '', $html) ?? $html;
        $html = preg_replace('#<style[\s\S]*?</style>#i', '', $html) ?? $html;
        $html = preg_replace('#<svg[\s\S]*?</svg>#i', '', $html) ?? $html;
        $html = preg_replace('/<!--[\s\S]*?-->/', '', $html) ?? $html;
        return $html;
    }

    protected function socialPlatforms(): array
    {
        return ['facebook','x_twitter','instagram','youtube','tiktok','linkedin','threads','bluesky','mastodon','truth_social','substack','rss','newsletter','website','other'];
    }

    protected function donationPlatforms(): array
    {
        return ['actblue','winred','anedp','democracy_engine','donorbox','givebutter','stripe','paypal','square','candidate_site','party_committee','other'];
    }

    protected function logRobotsBlock(string $url): void
    {
        Log::info('ProfileEnricherService: robots.txt disallowed fetch', ['url' => $url]);
    }

    protected function logHttpFailure(string $operation, int $status, array $context = []): void
    {
        Log::warning('ProfileEnricherService telemetry: HTTP request failed', array_merge($context, [
            'operation'       => $operation,
            'status'           => $status,
            'is_rate_limited'  => $status === 429,
        ]));
    }

    protected function logProviderException(string $operation, \Throwable $exception, array $context = []): void
    {
        Log::warning('ProfileEnricherService telemetry: provider exception', array_merge($context, [
            'operation' => $operation,
            'error'      => $exception->getMessage(),
        ]));
    }
}