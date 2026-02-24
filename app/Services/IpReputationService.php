<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * IP Reputation Service — Phase 8
 *
 * Detects VPN, proxy, Tor exit-node, and known datacenter IPs.
 *
 * Strategy (in order):
 *  1. Check a hardcoded CIDR blocklist for the most common datacenter ranges.
 *  2. If an ipinfo.io API key is configured, call their /json endpoint for
 *     richer signals (org, hosting flag, etc.).  Results are cached for 1 hour
 *     to avoid hammering the external API.
 *
 * The caller receives a flat result array that FraudPreventionService uses to
 * add fraud-score signals:
 *
 *   [
 *     'is_vpn'        => bool,
 *     'is_datacenter' => bool,
 *     'is_tor'        => bool,
 *     'provider'      => string|null,   // e.g. "Amazon", "DigitalOcean"
 *     'score_impact'  => int,           // total extra fraud score from IP alone
 *   ]
 */
class IpReputationService
{
    /**
     * Known datacenter / hosting provider CIDR blocks.
     *
     * These ranges are commonly used by VPN exit nodes and automation bots.
     * Expand this list as needed; for a full production system consider
     * pulling the latest ranges from MaxMind GeoIP2 or ipinfo.io.
     *
     * @var array<string>
     */
    private const DATACENTER_CIDRS = [
        // AWS EC2
        '3.0.0.0/8',
        '54.0.0.0/8',
        '35.160.0.0/13',
        '18.0.0.0/8',
        // Google Cloud
        '34.0.0.0/8',
        '35.184.0.0/13',
        // Azure
        '40.64.0.0/10',
        '52.128.0.0/9',
        // DigitalOcean
        '104.16.0.0/12',
        '167.99.0.0/16',
        '138.197.0.0/16',
        // Linode / Akamai
        '45.33.0.0/17',
        '45.56.0.0/21',
        '45.79.0.0/16',
        '66.175.208.0/20',
        // Hetzner
        '78.46.0.0/16',
        '95.216.0.0/16',
        '135.181.0.0/16',
        // OVH
        '54.37.0.0/17',
        '51.77.0.0/18',
        // Vultr
        '45.32.0.0/17',
        '149.28.0.0/17',
        // Cloudflare (Workers / IPs — legitimate CDN but used by bots)
        '104.16.0.0/12',
        // Common VPN providers (Mullvad, NordVPN, ExpressVPN exit nodes, etc.)
        '146.70.0.0/16',   // Mullvad
        '185.193.128.0/18', // NordVPN
        '103.86.96.0/22',  // NordVPN
        '217.138.192.0/20', // ExpressVPN
    ];

    /**
     * Known Tor exit-node IP prefixes (leading octets common in Tor networks).
     * A full Tor blocklist should be fetched from https://check.torproject.org/torbulkexitlist
     * and cached — this list is a minimal bootstrap.
     *
     * @var array<string>
     */
    private const TOR_EXIT_PREFIXES = [
        '185.220.',
        '199.249.',
        '204.13.',
        '5.2.',
        '23.129.',
    ];

    public function assess(string $ip): array
    {
        /** @var array $result */
        $result = Cache::remember("ip_rep:{$ip}", now()->addHour(), function () use ($ip): array {
            return $this->doAssess($ip);
        });

        return $result;
    }

    // ─── Internal ────────────────────────────────────────────────────────────

    private function doAssess(string $ip): array
    {
        $isDatacenter = $this->isInDatacenterRange($ip);
        $isTor        = $this->isTorExitNode($ip);
        $provider     = $isDatacenter ? $this->guessProvider($ip) : null;
        $isVpn        = false;

        // Try enrichment via ipinfo.io if a key is configured.
        $enriched = $this->fetchIpInfo($ip);
        if ($enriched !== null) {
            $isVpn        = $enriched['is_vpn'] ?? $isVpn;
            $isDatacenter = $isDatacenter || ($enriched['is_datacenter'] ?? false);
            $provider     = $enriched['provider'] ?? $provider;
        }

        $scoreImpact = 0;
        if ($isTor)        { $scoreImpact += 50; }
        if ($isVpn)        { $scoreImpact += 35; }
        if ($isDatacenter) { $scoreImpact += 25; }

        return [
            'is_vpn'        => $isVpn || $isDatacenter, // treat DC IPs as VPN-like
            'is_datacenter' => $isDatacenter,
            'is_tor'        => $isTor,
            'provider'      => $provider,
            'score_impact'  => min($scoreImpact, 50), // cap contribution
        ];
    }

    /**
     * Check if an IP falls inside any of the known datacenter CIDR ranges.
     */
    private function isInDatacenterRange(string $ip): bool
    {
        $ipLong = ip2long($ip);
        if ($ipLong === false) {
            return false; // IPv6 — skip CIDR check for now
        }

        foreach (self::DATACENTER_CIDRS as $cidr) {
            [$subnet, $prefixLen] = explode('/', $cidr);
            $subnetLong = ip2long($subnet);
            $mask       = -1 << (32 - (int) $prefixLen);

            if (($ipLong & $mask) === ($subnetLong & $mask)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Heuristic Tor detection via known exit-node IP prefixes.
     */
    private function isTorExitNode(string $ip): bool
    {
        foreach (self::TOR_EXIT_PREFIXES as $prefix) {
            if (str_starts_with($ip, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Guess a human-readable provider name from the IP's first octet / range.
     */
    private function guessProvider(string $ip): ?string
    {
        $first = (int) explode('.', $ip)[0];

        return match (true) {
            str_starts_with($ip, '54.')  => 'Amazon AWS',
            str_starts_with($ip, '18.')  => 'Amazon AWS',
            str_starts_with($ip, '34.')  => 'Google Cloud',
            str_starts_with($ip, '35.')  => 'Google / AWS',
            str_starts_with($ip, '40.')  => 'Microsoft Azure',
            str_starts_with($ip, '52.')  => 'Microsoft Azure',
            str_starts_with($ip, '104.') => 'Cloudflare / DigitalOcean',
            str_starts_with($ip, '167.') => 'DigitalOcean',
            str_starts_with($ip, '45.')  => 'Linode / Vultr',
            str_starts_with($ip, '78.')  => 'Hetzner',
            str_starts_with($ip, '95.')  => 'Hetzner',
            default                      => 'Datacenter',
        };
    }

    /**
     * Optionally call the ipinfo.io API for richer classification.
     * Returns null if no API key is configured or on any error.
     *
     * @return array{is_vpn: bool, is_datacenter: bool, provider: string|null}|null
     */
    private function fetchIpInfo(string $ip): ?array
    {
        $apiKey = config('u9itus.fraud.ipinfo_api_key');
        if (empty($apiKey)) {
            return null;
        }

        try {
            $response = Http::timeout(3)->get("https://ipinfo.io/{$ip}/json", [
                'token' => $apiKey,
            ]);

            if (! $response->ok()) {
                return null;
            }

            $data     = $response->json();
            $org      = $data['org'] ?? '';
            $hostname = $data['hostname'] ?? '';

            $isDatacenter = str_contains(strtolower($org), 'hosting')
                         || str_contains(strtolower($org), 'cloud')
                         || str_contains(strtolower($org), 'datacenter')
                         || str_contains(strtolower($hostname), 'vpn')
                         || isset($data['privacy']['hosting']) && $data['privacy']['hosting'];

            $isVpn = isset($data['privacy']['vpn']) && $data['privacy']['vpn'];

            return [
                'is_vpn'        => $isVpn,
                'is_datacenter' => $isDatacenter,
                'provider'      => $data['org'] ?? null,
            ];
        } catch (\Throwable $e) {
            Log::warning('IpReputationService: ipinfo.io request failed', [
                'ip'    => $ip,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
