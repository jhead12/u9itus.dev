<?php

namespace App\Support;

/**
 * Maps an election-office URL to the election-tech vendor behind it, using the
 * hostname → slug table in config('civic.vendor_hosts').
 *
 * Shared by civic:resolve-official-urls (vendor at discovery time) and
 * civic:verify-sources (re-classify after a redirect moved the host). The slug
 * doubles as the scraper adapter key (election_data_sources.platform_template),
 * so one adapter covers a whole vendor family.
 */
class CivicVendorClassifier
{
    /**
     * First of the given URLs whose host matches a configured vendor wins.
     * Returns null when nothing matches (or the matched host is deliberately
     * mapped to null — a generic gov host with no vendor).
     */
    public static function fromUrls(?string ...$urls): ?string
    {
        $hosts = (array) config('civic.vendor_hosts', []);

        foreach ($urls as $url) {
            if (! $url) {
                continue;
            }

            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            if ($host === '') {
                continue;
            }

            foreach ($hosts as $needle => $slug) {
                if (str_contains($host, (string) $needle)) {
                    return $slug;
                }
            }
        }

        return null;
    }
}
