<?php

namespace App\Services\Web3;

use App\Models\Politician;
use App\Services\PlatformSettingsService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sprint 7 — MeToken Subgraph Read-Only Enrichment
 *
 * Fetches on-chain meToken stats (supply, holders, DAI collateral) for a
 * politician's wallet from the public Goldsky-hosted MeTokens subgraph and
 * caches the result. Cache-first, fail-open: any HTTP failure returns null
 * so the profile page continues to render without the panel.
 *
 * Mirrors the BallotpediaService pattern:
 *   - 24-hour default cache TTL
 *   - Http::timeout(6) — short so a slow subgraph does not stall page load
 *   - Log::warning on every failure; never throws upward
 *   - isConfigured() gate: config URL set + PlatformSetting kill-switch on
 *
 * Raw amounts from the subgraph use 1e18 fixed-point (standard ERC-20).
 * This service normalizes them into human-readable floats before returning.
 */
class MeTokenSubgraphService
{
    protected string $subgraphUrl;
    protected string $basescanPrefix;
    protected int $cacheTtl;

    public function __construct()
    {
        $this->subgraphUrl    = (string) config('services.metokens.subgraph_url', '');
        $this->basescanPrefix = (string) config('services.metokens.basescan_prefix', 'https://basescan.org/token/');
        $this->cacheTtl       = (int) config('services.metokens.cache_ttl', 86400);
    }

    /**
     * The service is only "live" when the subgraph URL is set AND the
     * platform-wide Web3 kill-switch is enabled. Either condition off
     * short-circuits every public method to return null without HTTP.
     */
    public function isConfigured(): bool
    {
        if ($this->subgraphUrl === '') {
            return false;
        }

        return (bool) PlatformSettingsService::get('web3_features_enabled', null, false);
    }

    /**
     * Fetch a meToken by its owner's wallet address (checksummed or lower).
     *
     * Returns a normalized array or null when:
     *   - service is not configured
     *   - address is malformed
     *   - subgraph is unreachable / returns non-2xx / returns malformed JSON
     *   - no meToken exists for this owner
     *
     * @param  string $walletAddress  Ethereum address, "0x…" (42 chars)
     * @param  bool   $forceRefresh   When true, bust the cache before fetching
     * @return array<string, mixed>|null
     */
    public function getMeTokenByOwner(string $walletAddress, bool $forceRefresh = false): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $address = $this->normalizeAddress($walletAddress);
        if ($address === null) {
            return null;
        }

        $cacheKey = "metokens.owner.{$address}";

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($address) {
            return $this->queryOwner($address);
        });
    }

    /**
     * Resolve the meToken for a politician, preferring the stored
     * metoken_address (direct contract lookup) before falling back to a
     * wallet-owner lookup. Returns null when neither is populated.
     *
     * @return array<string, mixed>|null
     */
    public function fetchForPolitician(Politician $politician, bool $forceRefresh = false): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        // Prefer direct metoken lookup — cheaper and more precise.
        $tokenAddress = $this->normalizeAddress((string) ($politician->metoken_address ?? ''));
        if ($tokenAddress !== null) {
            $cacheKey = "metokens.token.{$tokenAddress}";
            if ($forceRefresh) {
                Cache::forget($cacheKey);
            }
            return Cache::remember($cacheKey, $this->cacheTtl, function () use ($tokenAddress) {
                return $this->queryToken($tokenAddress);
            });
        }

        // Fallback: look up by wallet owner.
        $walletAddress = (string) ($politician->wallet_address ?? '');
        if ($walletAddress === '') {
            return null;
        }

        return $this->getMeTokenByOwner($walletAddress, $forceRefresh);
    }

    // ── Internal ──────────────────────────────────────────────────────────

    /**
     * Query the subgraph for a meToken by owner wallet.
     *
     * @return array<string, mixed>|null
     */
    protected function queryOwner(string $ownerAddress): ?array
    {
        $query = <<<'GQL'
        query MeTokenByOwner($owner: Bytes!) {
            metokens(where: { owner: $owner }, first: 1) {
                id
                name
                symbol
                totalSupply
                balancePooled
                balanceLocked
                holdersCount
                lastMintAt
            }
        }
        GQL;

        return $this->executeAndNormalize($query, ['owner' => strtolower($ownerAddress)]);
    }

    /**
     * Query the subgraph for a meToken directly by its contract address.
     *
     * @return array<string, mixed>|null
     */
    protected function queryToken(string $tokenAddress): ?array
    {
        $query = <<<'GQL'
        query MeTokenById($id: ID!) {
            metoken(id: $id) {
                id
                name
                symbol
                totalSupply
                balancePooled
                balanceLocked
                holdersCount
                lastMintAt
            }
        }
        GQL;

        return $this->executeAndNormalize($query, ['id' => strtolower($tokenAddress)], singular: true);
    }

    /**
     * Execute a GraphQL POST and normalize the returned entity.
     * Returns null on any error — never throws.
     *
     * @param  array<string, mixed> $variables
     * @return array<string, mixed>|null
     */
    protected function executeAndNormalize(string $query, array $variables, bool $singular = false): ?array
    {
        try {
            $response = Http::timeout(6)
                ->acceptJson()
                ->asJson()
                ->post($this->subgraphUrl, [
                    'query'     => $query,
                    'variables' => $variables,
                ]);

            if (! $response->successful()) {
                Log::warning('MeTokenSubgraphService: subgraph HTTP failure', [
                    'status'    => $response->status(),
                    'variables' => $variables,
                ]);
                return null;
            }

            $json = $response->json();
            if (! is_array($json) || ! isset($json['data'])) {
                Log::warning('MeTokenSubgraphService: malformed subgraph response', [
                    'variables' => $variables,
                ]);
                return null;
            }

            if (! empty($json['errors'])) {
                Log::warning('MeTokenSubgraphService: subgraph returned GraphQL errors', [
                    'errors'    => $json['errors'],
                    'variables' => $variables,
                ]);
                return null;
            }

            $data  = $json['data'];
            $token = $singular
                ? ($data['metoken'] ?? null)
                : (($data['metokens'] ?? [])[0] ?? null);

            if (! is_array($token) || empty($token['id'])) {
                return null;
            }

            return $this->normalize($token);
        } catch (\Throwable $e) {
            Log::warning('MeTokenSubgraphService: exception during subgraph query', [
                'error_class' => get_class($e),
                'error'       => $e->getMessage(),
                'variables'   => $variables,
            ]);
            return null;
        }
    }

    /**
     * Normalize a raw subgraph entity into a display-ready array.
     * All 1e18-scaled amounts are divided down to human-readable floats.
     *
     * @param  array<string, mixed> $raw
     * @return array<string, mixed>
     */
    protected function normalize(array $raw): array
    {
        $address = strtolower((string) $raw['id']);

        return [
            'address'               => $address,
            'name'                  => (string) ($raw['name']   ?? ''),
            'symbol'                => (string) ($raw['symbol'] ?? ''),
            'total_supply'          => $this->scaleDown($raw['totalSupply']   ?? '0'),
            'collateral_pooled_dai' => $this->scaleDown($raw['balancePooled'] ?? '0'),
            'collateral_locked_dai' => $this->scaleDown($raw['balanceLocked'] ?? '0'),
            'holders_count'         => (int) ($raw['holdersCount'] ?? 0),
            'last_mint_at'          => $this->timestampToIso($raw['lastMintAt'] ?? null),
            'basescan_url'          => $this->basescanPrefix . $address,
            'fetched_at'            => now()->toIso8601String(),
        ];
    }

    /**
     * Validate and lowercase an Ethereum address.
     * Returns null when the input is not a well-formed 0x-prefixed 40-hex-digit address.
     */
    protected function normalizeAddress(string $address): ?string
    {
        $trimmed = trim($address);
        if ($trimmed === '') {
            return null;
        }
        if (! preg_match('/^0x[a-fA-F0-9]{40}$/', $trimmed)) {
            return null;
        }
        return strtolower($trimmed);
    }

    /**
     * Convert a 1e18-scaled fixed-point integer string into a plain float
     * with up to 4 decimal places (sufficient for display).
     */
    protected function scaleDown(mixed $raw): float
    {
        if ($raw === null || $raw === '') {
            return 0.0;
        }

        $s = (string) $raw;
        if (! preg_match('/^\d+$/', $s)) {
            return (float) $s; // already a float — trust it
        }

        if (function_exists('bcdiv')) {
            return (float) bcdiv($s, '1000000000000000000', 4);
        }

        return ((float) $s) / 1e18;
    }

    /**
     * Convert a unix timestamp (int or numeric string) to ISO-8601.
     */
    protected function timestampToIso(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $ts = (int) $raw;
        if ($ts <= 0) {
            return null;
        }
        return \Carbon\Carbon::createFromTimestamp($ts)->toIso8601String();
    }
}
