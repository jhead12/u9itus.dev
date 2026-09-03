<?php

namespace App\Console\Commands;

use App\Models\ElectionDataSource;
use App\Support\CivicVendorClassifier;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Step 4 of the civic source pipeline (see doc/CIVIC_SOURCE_REGISTRY.md).
 *
 * Health-checks the URLs on election_data_sources rows so the scrapers only
 * ever point at live, allowed pages:
 *
 *   - HEAD (GET fallback) each URL, following redirects.
 *   - Sets scrape_status from the primary URL's result:
 *       ok | redirected | blocked (401/403/429/5xx) | dead (404/410/DNS)
 *   - Reads each host's robots.txt and sets robots_ok for the primary URL path.
 *   - Re-classifies `vendor` from the final (post-redirect) host.
 *   - Stamps last_verified_at.
 *   - With --rewrite-redirects, persists the resolved URL back into its column.
 *
 * Primary URL priority: ballot_measures_url → sample_ballot_url →
 * elections_home_url → results_url. Rows with no URLs are skipped (they stay
 * `unverified` — that's civic:resolve-official-urls' job, not this one's).
 *
 * Usage:
 *   php artisan civic:verify-sources
 *   php artisan civic:verify-sources --state=CA --level=county
 *   php artisan civic:verify-sources --stale-days=7 --rewrite-redirects
 *   php artisan civic:verify-sources --dry-run
 */
class VerifyCivicSources extends Command
{
    protected $signature = 'civic:verify-sources
        {--state=            : Limit to one state (two-letter USPS code)}
        {--level=all         : Which rows: state, county, or all}
        {--stale-days=7      : Only re-check rows not verified within this many days (0 = all)}
        {--limit=1000        : Max rows to check this run}
        {--sleep=200         : Milliseconds to pause between rows}
        {--timeout=          : Per-request timeout in seconds (default config(civic.verifier.timeout))}
        {--rewrite-redirects : Persist a redirect target back into its URL column}
        {--dry-run           : Report without writing}';

    protected $description = 'Health-check election_data_sources URLs (status, redirects, robots.txt) and update scrape_status / robots_ok / vendor.';

    private const URL_COLUMNS = [
        'ballot_measures_url',
        'sample_ballot_url',
        'elections_home_url',
        'results_url',
    ];

    private bool $rewriteRedirects = false;

    private bool $dryRun = false;

    private int $timeout = 12;

    private string $userAgent = '';

    /** @var array<string, bool> host → robots.txt allows our UA for the checked path */
    private array $robotsCache = [];

    public function handle(): int
    {
        $stateFilter = $this->option('state') ? strtoupper((string) $this->option('state')) : null;
        $level = strtolower((string) $this->option('level'));
        $staleDays = (int) $this->option('stale-days');
        $limit = (int) $this->option('limit');
        $sleepMs = max(0, (int) $this->option('sleep'));
        $this->rewriteRedirects = (bool) $this->option('rewrite-redirects');
        $this->dryRun = (bool) $this->option('dry-run');
        $this->timeout = (int) ($this->option('timeout') ?: config('civic.verifier.timeout', 12));
        $this->userAgent = (string) config('civic.verifier.user_agent', 'U9itus-civic-registry/1.0');

        $levels = match ($level) {
            'state' => ['state'],
            'county' => ['county'],
            default => ['state', 'county', 'municipal', 'township', 'special'],
        };

        $rows = ElectionDataSource::query()
            ->whereIn('level', $levels)
            ->when($stateFilter, fn ($q) => $q->where('state', $stateFilter))
            ->when($staleDays > 0, fn ($q) => $q->needsVerification($staleDays))
            ->where(function ($q) {
                foreach (self::URL_COLUMNS as $col) {
                    $q->orWhereNotNull($col);
                }
            })
            ->orderBy('level')
            ->orderBy('state')
            ->orderBy('jurisdiction_name')
            ->limit($limit)
            ->get();

        $this->info('Verifying '.$rows->count().' row(s)'.($this->dryRun ? ' [DRY RUN]' : ''));

        $tally = ['ok' => 0, 'redirected' => 0, 'blocked' => 0, 'dead' => 0];
        $robotsDisallowed = 0;

        foreach ($rows as $row) {
            $outcome = $this->verifyRow($row);
            $tally[$outcome['status']] = ($tally[$outcome['status']] ?? 0) + 1;
            if ($outcome['robots_ok'] === false) {
                $robotsDisallowed++;
            }

            $robots = $outcome['robots_ok'] === false ? ' robots=DISALLOWED' : '';
            $this->line("  {$row->state}  {$row->jurisdiction_name}  [{$outcome['status']}]{$robots}");

            if ($sleepMs > 0) {
                usleep($sleepMs * 1000);
            }
        }

        $suffix = $this->dryRun ? ' (dry-run — no DB writes)' : '';
        $this->info("Done. ok={$tally['ok']} redirected={$tally['redirected']} blocked={$tally['blocked']}"
            ." dead={$tally['dead']} | {$robotsDisallowed} with a robots.txt disallow{$suffix}");

        return self::SUCCESS;
    }

    /** @return array{status: string, robots_ok: bool|null} */
    private function verifyRow(ElectionDataSource $row): array
    {
        $primaryColumn = $this->primaryColumn($row);
        $primaryUrl = $primaryColumn ? $row->{$primaryColumn} : null;

        $changes = ['last_verified_at' => now()];
        $status = 'blocked';
        $robotsOk = null;
        $finalUrls = [];

        foreach (self::URL_COLUMNS as $col) {
            $url = $row->{$col};
            if (! $url) {
                continue;
            }

            $check = $this->checkUrl($url);
            $finalUrls[] = $check['final'];

            if ($col === $primaryColumn) {
                $status = $check['outcome'];
            }

            if ($this->rewriteRedirects && $check['outcome'] === 'redirected' && $check['final'] !== $url) {
                $changes[$col] = $check['final'];
            }
        }

        if ($primaryUrl) {
            $robotsOk = $this->robotsAllows($changes[$primaryColumn] ?? $primaryUrl);
        }

        $changes['scrape_status'] = $status;
        $changes['robots_ok'] = $robotsOk;

        $vendor = CivicVendorClassifier::fromUrls(...$finalUrls);
        if ($vendor !== null && $vendor !== $row->vendor) {
            $changes['vendor'] = $vendor;
        }

        if (! $this->dryRun) {
            $row->update($changes);
        }

        return ['status' => $status, 'robots_ok' => $robotsOk];
    }

    private function primaryColumn(ElectionDataSource $row): ?string
    {
        foreach (self::URL_COLUMNS as $col) {
            if ($row->{$col}) {
                return $col;
            }
        }

        return null;
    }

    /**
     * @return array{outcome: string, final: string, http_status: int}
     */
    private function checkUrl(string $url): array
    {
        try {
            $response = $this->request('head', $url);

            // Plenty of gov servers / WAFs reject a bare HEAD (or any HEAD) with
            // 400/403/405/501 while the same URL is fine over GET — retry once
            // before concluding the page is blocked.
            if (in_array($response->status(), [400, 403, 405, 501], true)) {
                $response = $this->request('get', $url);
            }

            $httpStatus = $response->status();
            $final = $this->finalUrl($url, $response);
            $redirected = ! $this->sameUrl($url, $final);

            $outcome = match (true) {
                $httpStatus >= 200 && $httpStatus < 300 && $redirected => 'redirected',
                $httpStatus >= 200 && $httpStatus < 300 => 'ok',
                in_array($httpStatus, [401, 403, 429], true) => 'blocked',
                in_array($httpStatus, [404, 410], true) => 'dead',
                $httpStatus >= 500 => 'blocked',
                default => 'blocked',
            };

            return ['outcome' => $outcome, 'final' => $final, 'http_status' => $httpStatus];
        } catch (ConnectionException $e) {
            // DNS failure / host gone → dead; timeout / TLS / reset → blocked (retryable).
            $dead = (bool) preg_match('/could not resolve host|name or service not known|nodename nor servname/i', $e->getMessage());

            return ['outcome' => $dead ? 'dead' : 'blocked', 'final' => $url, 'http_status' => 0];
        }
    }

    private function request(string $method, string $url): Response
    {
        return Http::withHeaders(['User-Agent' => $this->userAgent])
            ->timeout($this->timeout)
            ->withOptions(['allow_redirects' => ['max' => 5, 'track_redirects' => true]])
            ->{$method}($url);
    }

    /** Final URL after redirects, read from Guzzle's redirect-history header. */
    private function finalUrl(string $original, Response $response): string
    {
        $history = trim((string) $response->header('X-Guzzle-Redirect-History'));
        if ($history === '') {
            return $original;
        }

        $hops = array_values(array_filter(array_map('trim', explode(',', $history))));

        return $hops === [] ? $original : end($hops);
    }

    /** Same URL bar scheme (http/https), a trailing slash, or a fragment. */
    private function sameUrl(string $a, string $b): bool
    {
        return $this->canonical($a) === $this->canonical($b);
    }

    private function canonical(string $url): string
    {
        $p = parse_url($url) ?: [];
        $host = strtolower($p['host'] ?? '');
        $path = rtrim($p['path'] ?? '', '/');
        $query = isset($p['query']) ? '?'.$p['query'] : '';

        return $host.$path.$query;
    }

    /**
     * Does the host's robots.txt allow our User-Agent to fetch this URL's path?
     * Missing / unreadable robots.txt → allowed (true). Host-cached per run.
     */
    private function robotsAllows(string $url): bool
    {
        $p = parse_url($url) ?: [];
        $scheme = $p['scheme'] ?? 'https';
        $host = strtolower($p['host'] ?? '');
        $path = $p['path'] ?? '/';

        if ($host === '') {
            return true;
        }

        $cacheKey = $host.'|'.$path;
        if (array_key_exists($cacheKey, $this->robotsCache)) {
            return $this->robotsCache[$cacheKey];
        }

        $body = '';
        try {
            $resp = Http::withHeaders(['User-Agent' => $this->userAgent])
                ->timeout(min($this->timeout, 8))
                ->get("{$scheme}://{$host}/robots.txt");
            if ($resp->successful()) {
                $body = (string) $resp->body();
            }
        } catch (ConnectionException) {
            // fall through — no robots.txt reachable
        }

        return $this->robotsCache[$cacheKey] = $this->pathAllowedByRobots($body, $path);
    }

    /**
     * Minimal robots.txt evaluator: gathers Allow/Disallow rules from the `*`
     * group and any group naming our UA token, then applies longest-match wins
     * (ties → Allow). Good enough for "are we explicitly told to stay out".
     */
    private function pathAllowedByRobots(string $body, string $path): bool
    {
        if (trim($body) === '') {
            return true;
        }

        $token = strtolower(strtok($this->userAgent, '/') ?: 'u9itus');
        $rules = [];            // [[type => allow|disallow, value => string], ...]
        $activeGroups = [];     // user-agents for the current contiguous block
        $collecting = false;

        foreach (preg_split('/\r\n|\r|\n/', $body) ?: [] as $line) {
            $line = trim(preg_replace('/#.*$/', '', $line) ?? '');
            if ($line === '') {
                continue;
            }
            [$field, $value] = array_pad(array_map('trim', explode(':', $line, 2)), 2, '');
            $field = strtolower($field);

            if ($field === 'user-agent') {
                if ($collecting) {          // new block starts
                    $activeGroups = [];
                    $collecting = false;
                }
                $activeGroups[] = strtolower($value);

                continue;
            }

            if (! in_array($field, ['allow', 'disallow'], true)) {
                continue;
            }
            $collecting = true;

            $applies = false;
            foreach ($activeGroups as $ua) {
                if ($ua === '*' || ($ua !== '' && str_contains($token, $ua))) {
                    $applies = true;
                    break;
                }
            }
            if ($applies) {
                $rules[] = ['type' => $field, 'value' => $value];
            }
        }

        $bestLen = -1;
        $allowed = true;
        foreach ($rules as $rule) {
            $val = $rule['value'];
            if ($val === '') {
                // "Disallow:" (empty) explicitly allows everything for this group.
                if ($rule['type'] === 'disallow' && $bestLen < 0) {
                    $allowed = true;
                }

                continue;
            }
            if (! str_starts_with($path, rtrim($val, '*'))) {
                continue;
            }
            $len = strlen($val);
            if ($len > $bestLen) {
                $bestLen = $len;
                $allowed = $rule['type'] === 'allow';
            }
        }

        return $allowed;
    }
}
