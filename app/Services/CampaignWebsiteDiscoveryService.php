<?php

namespace App\Services;

use App\Models\Politician;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Discovers a candidate's official campaign website by scraping their
 * Ballotpedia page, for politicians who don't have politicians.website_url
 * set yet. Reuses the same plain-HTTP approach as SyncPrimaryResults —
 * Ballotpedia doesn't need the Playwright bot-evasion that OpenSecrets/C-SPAN
 * require.
 *
 * A discovered URL is written to politicians.website_url, which is then
 * picked up automatically by the existing politicians:enrich-profiles
 * pipeline (ProfileEnricherService) on its next run.
 */
class CampaignWebsiteDiscoveryService
{
    private const USER_AGENT = 'u9itus-sync/1.0 (website-discovery)';

    /** Hosts that are never a candidate's own campaign site. */
    private const EXCLUDED_HOSTS = [
        'ballotpedia.org', 'wikipedia.org', 'en.wikipedia.org',
        'facebook.com', 'twitter.com', 'x.com', 'instagram.com',
        'youtube.com', 'linkedin.com', 'tiktok.com', 'threads.net',
        'opensecrets.org', 'fec.gov', 'votesmart.org',
    ];

    protected int $cacheHours;

    public function __construct()
    {
        $this->cacheHours = (int) config('u9itus.website_discovery.cache_hours', 168);
    }

    /**
     * Returns a discovered campaign website URL, or null if none could be
     * found. Cached per politician since a Ballotpedia page rarely changes
     * its infobox link.
     */
    public function discoverFor(Politician $politician): ?string
    {
        $name = trim((string) $politician->full_name);
        if ($name === '') {
            return null;
        }

        return Cache::remember(
            "website_discovery.{$politician->id}",
            $this->cacheHours * 3600,
            fn () => $this->fetchFromBallotpedia($name)
        );
    }

    public function clearCache(Politician $politician): void
    {
        Cache::forget("website_discovery.{$politician->id}");
    }

    private function fetchFromBallotpedia(string $name): ?string
    {
        $slug = str_replace(' ', '_', $name);
        $url = "https://ballotpedia.org/{$slug}";

        try {
            $response = Http::timeout(15)
                ->withHeaders(['User-Agent' => self::USER_AGENT])
                ->get($url);

            if (! $response->ok()) {
                return null;
            }

            return $this->extractCampaignWebsite((string) $response->body());
        } catch (\Throwable $e) {
            Log::warning('CampaignWebsiteDiscoveryService: fetch failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Ballotpedia's candidate infobox marks the official-site link with a CSS
     * class containing "website" (attribute order varies), e.g.:
     *   <a href="https://janedoe.com" class="Website websiteedit">Website</a>
     * Falls back to a labelled infobox row ("Website:" followed shortly by a
     * link) for older page layouts. Returns null rather than guessing from an
     * arbitrary page link — a wrong URL on a candidate's profile is worse
     * than no URL.
     */
    private function extractCampaignWebsite(string $html): ?string
    {
        if (preg_match('/<a[^>]*class=["\'][^"\']*website[^"\']*["\'][^>]*href=["\']([^"\']+)["\']/i', $html, $m)
            || preg_match('/<a[^>]*href=["\']([^"\']+)["\'][^>]*class=["\'][^"\']*website[^"\']*["\']/i', $html, $m)
            || preg_match('/website\s*:?\s*<\/(?:td|div|dt|span)>\s*<(?:td|div|dd)[^>]*>.*?href=["\']([^"\']+)["\']/is', $html, $m)
        ) {
            return $this->sanitize($m[1]);
        }

        return null;
    }

    private function sanitize(string $href): ?string
    {
        $href = html_entity_decode($href);
        if (! str_starts_with($href, 'http')) {
            return null;
        }

        $host = strtolower((string) parse_url($href, PHP_URL_HOST));
        if ($host === '' || $this->isExcludedHost($host)) {
            return null;
        }

        return $href;
    }

    private function isExcludedHost(string $host): bool
    {
        foreach (self::EXCLUDED_HOSTS as $excluded) {
            if ($host === $excluded || str_ends_with($host, ".{$excluded}")) {
                return true;
            }
        }

        return false;
    }
}
