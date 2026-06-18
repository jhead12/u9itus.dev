<?php

namespace App\Console\Commands;

use App\Models\Politician;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Enriches statewide executive officeholder data using a three-tier strategy:
 *
 *  Tier 1 — Ballotpedia (REST summary scrape, no API key required)
 *  Tier 2 — Wikipedia REST API   (free, no key required)
 *  Tier 3 — OpenAI GPT-4o-mini   (optional, requires OPENAI_API_KEY)
 *            Used only when Tier 1 + 2 both fail to extract a clean name.
 *
 * Usage:
 *   php artisan politicians:enrich-statewide
 *   php artisan politicians:enrich-statewide --state=CA
 *   php artisan politicians:enrich-statewide --clean --dry-run
 *
 * --clean removes unverified statewide politician records whose full_name
 * does not match any name returned by the enrichment pass, preventing
 * garbage scraper rows (e.g. "Linda Martinez") from showing on the map.
 */
class EnrichStatewideOfficeholders extends Command
{
    protected $signature = 'politicians:enrich-statewide
        {--state=         : Two-letter state code. Omit to process all 50 states.}
        {--clean          : Delete unverified statewide records not matched by enrichment.}
        {--dry-run        : Report only — no DB writes.}
        {--force          : Re-enrich even records already marked verified.}';

    protected $description = 'Enrich statewide executive officeholder data from Ballotpedia, Wikipedia, and (optionally) OpenAI.';

    // ── State name lookup ──────────────────────────────────────────────────────
    private const STATE_NAMES = [
        'AL' => 'Alabama',    'AK' => 'Alaska',       'AZ' => 'Arizona',
        'AR' => 'Arkansas',   'CA' => 'California',   'CO' => 'Colorado',
        'CT' => 'Connecticut','DE' => 'Delaware',      'FL' => 'Florida',
        'GA' => 'Georgia',    'HI' => 'Hawaii',        'ID' => 'Idaho',
        'IL' => 'Illinois',   'IN' => 'Indiana',       'IA' => 'Iowa',
        'KS' => 'Kansas',     'KY' => 'Kentucky',      'LA' => 'Louisiana',
        'ME' => 'Maine',      'MD' => 'Maryland',      'MA' => 'Massachusetts',
        'MI' => 'Michigan',   'MN' => 'Minnesota',     'MS' => 'Mississippi',
        'MO' => 'Missouri',   'MT' => 'Montana',       'NE' => 'Nebraska',
        'NV' => 'Nevada',     'NH' => 'New_Hampshire', 'NJ' => 'New_Jersey',
        'NM' => 'New_Mexico', 'NY' => 'New_York',      'NC' => 'North_Carolina',
        'ND' => 'North_Dakota','OH' => 'Ohio',         'OK' => 'Oklahoma',
        'OR' => 'Oregon',     'PA' => 'Pennsylvania',  'RI' => 'Rhode_Island',
        'SC' => 'South_Carolina','SD' => 'South_Dakota','TN' => 'Tennessee',
        'TX' => 'Texas',      'UT' => 'Utah',          'VT' => 'Vermont',
        'VA' => 'Virginia',   'WA' => 'Washington',    'WV' => 'West_Virginia',
        'WI' => 'Wisconsin',  'WY' => 'Wyoming',
    ];

    /**
     * Office configurations. Each entry lists:
     *  - wiki_patterns: Wikipedia page title templates to try (in order)
     *  - bp_pattern:    Ballotpedia URL slug template
     *  - governance_level + political_office: canonical values for the DB row
     */
    private const OFFICES = [
        'Governor' => [
            'wiki_patterns' => ['Governor_of_{state}'],
            'bp_pattern'    => 'Governor_of_{state}',
        ],
        'Lieutenant Governor' => [
            'wiki_patterns' => [
                'Lieutenant_Governor_of_{state}',
                '{state}_Lieutenant_Governor',
            ],
            'bp_pattern' => 'Lieutenant_Governor_of_{state}',
        ],
        'Attorney General' => [
            'wiki_patterns' => [
                'Attorney_General_of_{state}',
                '{state}_Attorney_General',
            ],
            'bp_pattern' => '{state}_Attorney_General',
        ],
        'State Treasurer' => [
            'wiki_patterns' => [
                'State_Treasurer_of_{state}',
                '{state}_State_Treasurer',
                'Treasurer_of_{state}',
            ],
            'bp_pattern' => '{state}_State_Treasurer',
        ],
        'Secretary of State' => [
            'wiki_patterns' => [
                'Secretary_of_State_of_{state}',
                '{state}_Secretary_of_State',
            ],
            'bp_pattern' => '{state}_Secretary_of_State',
        ],
    ];

    // Throttle between HTTP calls (ms) to avoid rate limits
    private const DELAY_MS = 400;

    /** @var string[] Keys "state|office|name" successfully enriched in this run (for --clean) */
    private array $enrichedKeys = [];

    public function handle(): int
    {
        $stateFilter = $this->option('state')
            ? strtoupper(trim((string) $this->option('state')))
            : null;
        $dryRun = (bool) $this->option('dry-run');
        $clean  = (bool) $this->option('clean');
        $force  = (bool) $this->option('force');

        $states = $stateFilter
            ? [$stateFilter => self::STATE_NAMES[$stateFilter] ?? $stateFilter]
            : self::STATE_NAMES;

        if ($stateFilter && ! isset(self::STATE_NAMES[$stateFilter])) {
            $this->error("Unknown state code: {$stateFilter}");
            return self::FAILURE;
        }

        $hasAI = (bool) config('services.anthropic.api_key');
        if ($dryRun) {
            $this->line('<fg=yellow>[dry-run] No database writes will occur.</>');
        }
        if ($hasAI) {
            $model = config('services.anthropic.model', 'claude-haiku-4-5');
            $this->line("<fg=cyan>[ai] Anthropic {$model} available as Tier 3 fallback.</>");
        }

        $upserted = 0;
        $skipped  = 0;
        $failed   = 0;

        foreach ($states as $abbr => $stateName) {
            $this->line("\n<fg=green>[{$abbr}]</> {$stateName}");

            foreach (self::OFFICES as $office => $config) {
                $result = $this->resolveCurrentHolder($abbr, $stateName, $office, $config, $hasAI);

                if ($result === null) {
                    $this->line("  <fg=yellow>✗ {$office}: could not resolve current holder</>  (skipped)");
                    $failed++;
                    continue;
                }

                ['name' => $name, 'party' => $party, 'source' => $source, 'bp_url' => $bpUrl] = $result;

                $this->enrichedKeys[] = strtolower("{$abbr}|{$office}|{$name}");

                $this->line("  <fg=cyan>✓ {$office}:</> {$name}" .
                    ($party ? " ({$party})" : '') .
                    " <fg=gray>[{$source}]</>");

                if ($dryRun) {
                    $upserted++;
                    continue;
                }

                try {
                    $existing = Politician::query()
                        ->whereRaw('UPPER(COALESCE(state, \'\')) = ?', [$abbr])
                        ->whereRaw('LOWER(COALESCE(political_office, \'\')) = ?', [strtolower($office)])
                        ->where('governance_level', 'State')
                        ->orderByDesc('verified_official')
                        ->first();

                    $bpSlug = $bpUrl ? ltrim(parse_url($bpUrl, PHP_URL_PATH) ?? '', '/') : null;

                    $attributes = array_filter([
                        'full_name'            => $name,
                        'party_affiliation'    => $party,
                        'ballotpedia_id'       => $bpSlug ? substr($bpSlug, 0, 255) : null,
                        'is_running_candidate' => false,
                        'term_status'          => 'seated',
                        'is_active'            => true,
                        'status_updated_at'    => now(),
                    ], fn($v) => $v !== null);

                    if ($existing && (! $existing->verified_official || $force)) {
                        $existing->update($attributes);
                        $this->line("    → Updated existing record #{$existing->id}");
                    } elseif (! $existing) {
                        $slug = $this->generateSlug($name);
                        Politician::create(array_merge($attributes, [
                            'uuid'             => Str::uuid(),
                            'state'            => $abbr,
                            'political_office' => $office,
                            'governance_level' => 'State',
                            'page_published'   => true,
                            'verified_official'=> false,
                            'user_id'          => null,
                            'slug'             => $slug,
                        ]));
                        $this->line("    → Created new record (slug={$slug})");
                    } else {
                        $this->line("    → Skipped (already verified_official=true; use --force to override)");
                        $skipped++;
                        continue;
                    }

                    $upserted++;
                } catch (\Throwable $e) {
                    $this->warn("    ✗ DB write failed: " . $e->getMessage());
                    Log::warning('politicians:enrich-statewide DB error', [
                        'state' => $abbr, 'office' => $office, 'error' => $e->getMessage(),
                    ]);
                    $failed++;
                }

                usleep(self::DELAY_MS * 1000);
            }
        }

        // ── Clean garbage records ──────────────────────────────────────────────
        if ($clean && ! empty($this->enrichedKeys)) {
            $this->line("\n<fg=yellow>[clean]</> Removing unverified statewide records not matched by enrichment...");
            $this->cleanGarbageRecords($stateFilter, $dryRun);
        }

        $this->info(
            "\nEnrichment complete: {$upserted} upserted, {$skipped} skipped, {$failed} unresolvable."
        );

        return self::SUCCESS;
    }

    // ── Resolution pipeline ────────────────────────────────────────────────────

    /**
     * Try Ballotpedia → Wikipedia → OpenAI (if configured) until a name resolves.
     *
     * @param array<string, mixed> $config
     * @return array{name: string, party: ?string, source: string, bp_url: ?string}|null
     */
    private function resolveCurrentHolder(
        string $abbr,
        string $stateName,
        string $office,
        array $config,
        bool $hasAI
    ): ?array {
        $stateSlug = str_replace(' ', '_', $stateName);

        // ── Tier 1: Ballotpedia ────────────────────────────────────────────────
        $bpSlug = str_replace('{state}', $stateSlug, $config['bp_pattern']);
        $bpUrl  = "https://ballotpedia.org/{$bpSlug}";
        $bpResult = $this->fetchBallotpediaHolder($bpUrl);
        if ($bpResult) {
            return array_merge($bpResult, ['source' => 'ballotpedia', 'bp_url' => $bpUrl]);
        }

        // ── Tier 2: Wikipedia ──────────────────────────────────────────────────
        foreach ($config['wiki_patterns'] as $pattern) {
            $wikiPage = str_replace('{state}', $stateSlug, $pattern);
            $wikiResult = $this->fetchWikipediaHolder($wikiPage);
            if ($wikiResult) {
                return array_merge($wikiResult, ['source' => 'wikipedia', 'bp_url' => $bpUrl]);
            }
            usleep(self::DELAY_MS * 1000);
        }

        // ── Tier 3: Claude (fallback) ──────────────────────────────────────────
        if ($hasAI) {
            $aiResult = $this->fetchWithClaude($abbr, $stateName, $office);
            if ($aiResult) {
                return array_merge($aiResult, ['source' => 'claude', 'bp_url' => $bpUrl]);
            }
        }

        return null;
    }

    // ── Tier 1: Ballotpedia ────────────────────────────────────────────────────

    /**
     * Fetch the current officeholder from a Ballotpedia office page.
     * Parses the infobox "Officeholder" / "Incumbent" section via HTTP.
     *
     * @return array{name: string, party: ?string}|null
     */
    private function fetchBallotpediaHolder(string $url): ?array
    {
        try {
            $response = Http::timeout(12)
                ->withHeaders(['User-Agent' => 'U9itus-civic-enrichment/1.0 (+https://u9itus.dev/about)'])
                ->get($url);

            if (! $response->ok()) {
                return null;
            }

            $html = $response->body();

            // Pattern 1: infobox table row with Officeholder/Incumbent/Current header
            if (preg_match(
                '/<th[^>]*>\s*(?:Officeholder|Incumbent|Current\s+officeholder|Current\s+holder)[^<]*<\/th>\s*<td[^>]*>.*?<a[^>]+href="https?:\/\/ballotpedia\.org\/[^"?#]+">([^<]{3,60})<\/a>/si',
                $html,
                $m
            )) {
                $name = $this->cleanName(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
                if ($name) {
                    $party = $this->extractBallotpediaParty($html);
                    return ['name' => $name, 'party' => $party];
                }
            }

            // Pattern 2: "Current: <a>Name</a>" style
            if (preg_match(
                '/Current:\s*<a[^>]+href="https:\/\/ballotpedia\.org\/[^"?#]+">([^<]{3,60})<\/a>/i',
                $html,
                $m
            )) {
                $name = $this->cleanName(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
                if ($name) {
                    return ['name' => $name, 'party' => null];
                }
            }

            // Pattern 3: any ballotpedia.org link inside a cell that also contains
            // "incumbent" or "officeholder" text (looser match for varied layouts)
            if (preg_match(
                '/(?:incumbent|officeholder)[^<]{0,200}?<a[^>]+href="https:\/\/ballotpedia\.org\/([A-Za-z_]+(?:_%28[^)]+%29)?)">([^<]{3,60})<\/a>/si',
                $html,
                $m
            )) {
                $name = $this->cleanName(html_entity_decode($m[2], ENT_QUOTES, 'UTF-8'));
                if ($name) {
                    return ['name' => $name, 'party' => null];
                }
            }

            return null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function extractBallotpediaParty(string $html): ?string
    {
        // Ballotpedia infoboxes include a "Party" row
        if (preg_match('/<th[^>]*>Party<\/th>\s*<td[^>]*>([^<]{3,40})</i', $html, $m)) {
            return trim(strip_tags($m[1])) ?: null;
        }
        return null;
    }

    // ── Tier 2: Wikipedia ─────────────────────────────────────────────────────

    /**
     * Fetch the current officeholder from Wikipedia using two strategies:
     *
     *  2a. REST summary API — fast, but the description/extract for office pages
     *      often describe the role, not the person ("The Governor of X is the head…").
     *      Several extract patterns are tried to find an inline name mention.
     *
     *  2b. Wikitext infobox — fetches the raw wikitext via the action API and
     *      parses the structured `| incumbent = [[Name]]` field.  This is the
     *      most reliable method because Wikipedia infoboxes for government offices
     *      always use this field for the current holder.
     *
     * @return array{name: string, party: ?string}|null
     */
    private function fetchWikipediaHolder(string $pageTitle): ?array
    {
        $userAgent = 'U9itus-civic-enrichment/1.0 (+https://u9itus.dev)';
        $titleSlug = str_replace(' ', '_', $pageTitle);

        // ── 2a: REST summary ──────────────────────────────────────────────────
        try {
            $encoded  = rawurlencode($titleSlug);
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => $userAgent])
                ->get("https://en.wikipedia.org/api/rest_v1/page/summary/{$encoded}");

            if ($response->ok()) {
                $data        = $response->json();
                $description = trim($data['description'] ?? '');
                $extract     = trim($data['extract'] ?? '');

                // "Gavin Newsom since January 2019"
                if (preg_match('/^([A-Z][a-z\'\-]+(?: [A-Z][a-z\'\-]+){1,3})\s+since\b/i', $description, $m)) {
                    return ['name' => $this->cleanName($m[1]), 'party' => null];
                }
                // Extract starts with person's name: "Daniel McKee is the 76th…"
                if (preg_match('/^([A-Z][a-z\'\-]+(?: [A-Z][a-z\'\-]+){1,3})\s+(?:is|serves as|became)\b/u', $extract, $m)) {
                    return ['name' => $this->cleanName($m[1]), 'party' => null];
                }
                // "is {Name}, an American"
                if (preg_match('/\bis ([A-Z][a-z\'\-]+(?: [A-Z][a-z\'\-]+){1,3}),\s+an?\s+American\b/u', $extract, $m)) {
                    return ['name' => $this->cleanName($m[1]), 'party' => null];
                }
                // "current governor is Name" / "currently held by Name"
                if (preg_match('/\bcurrent\w*\s+\w+\s+is\s+([A-Z][a-z\'\-]+(?: [A-Z][a-z\'\-]+){1,3})\b/i', $extract, $m)) {
                    return ['name' => $this->cleanName($m[1]), 'party' => null];
                }
                // "incumbent Name" anywhere in extract
                if (preg_match('/\bincumbent[,\s]+([A-Z][a-z\'\-]+(?: [A-Z][a-z\'\-]+){1,3})\b/i', $extract, $m)) {
                    return ['name' => $this->cleanName($m[1]), 'party' => null];
                }
            }
        } catch (\Throwable) {
            // fall through to wikitext
        }

        usleep(self::DELAY_MS * 500);

        // ── 2b: Wikitext infobox ──────────────────────────────────────────────
        // Wikipedia government-office infoboxes reliably use:
        //   | incumbent   = [[Full Name]]
        //   | officeholder = [[Full Name]]
        // This is structured data — far more reliable than parsing prose text.
        try {
            $wikitextResponse = Http::timeout(12)
                ->withHeaders(['User-Agent' => $userAgent])
                ->get('https://en.wikipedia.org/w/api.php', [
                    'action'       => 'query',
                    'titles'       => str_replace('_', ' ', $titleSlug),
                    'prop'         => 'revisions',
                    'rvprop'       => 'content',
                    'rvslots'      => 'main',
                    'rvsection'    => '0',
                    'format'       => 'json',
                    'formatversion'=> '2',
                ]);

            if (! $wikitextResponse->ok()) {
                return null;
            }

            $wikitext = $wikitextResponse->json('query.pages.0.revisions.0.slots.main.content') ?? '';

            if ($wikitext === '') {
                return null;
            }

            // | incumbent = [[Daniel McKee]]  or  | incumbent = [[Daniel McKee|McKee]]
            if (preg_match(
                '/\|\s*(?:incumbent|officeholder|current_holder|holder)\s*=\s*\[\[([^\]|]{3,60})/i',
                $wikitext,
                $m
            )) {
                $name = $this->cleanName($m[1]);
                if ($name) {
                    // Try to extract party from infobox: | party = [[Democratic Party (United States)|Democratic]]
                    $party = null;
                    if (preg_match('/\|\s*party\s*=\s*\[\[[^\]]*\|([^\]]{3,40})\]\]/i', $wikitext, $pm)) {
                        $party = $this->normaliseParty(trim($pm[1]));
                    } elseif (preg_match('/\|\s*party\s*=\s*([A-Za-z ]{3,40})\n/i', $wikitext, $pm)) {
                        $party = $this->normaliseParty(trim($pm[1]));
                    }
                    return ['name' => $name, 'party' => $party];
                }
            }

            return null;
        } catch (\Throwable) {
            return null;
        }
    }

    // ── Tier 3: Anthropic Claude ──────────────────────────────────────────────

    /**
     * Use Claude to identify the current officeholder when Tiers 1–2 fail.
     * Uses claude-haiku-4-5 by default (fastest + cheapest Claude model).
     * Only called if ANTHROPIC_API_KEY is configured.
     *
     * @return array{name: string, party: ?string}|null
     */
    private function fetchWithClaude(string $abbr, string $stateName, string $office): ?array
    {
        try {
            $prompt = "Who is the current {$office} of {$stateName} as of June 2026? "
                . "Reply with ONLY this format: full name | party affiliation "
                . "(example: \"Gavin Newsom | Democratic\"). "
                . "If you are not certain, reply with exactly: UNKNOWN";

            $response = Http::timeout(15)
                ->withHeaders([
                    'x-api-key'         => config('services.anthropic.api_key'),
                    'anthropic-version' => '2023-06-01',
                    'Content-Type'      => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model'      => config('services.anthropic.model', 'claude-haiku-4-5'),
                    'max_tokens' => 40,
                    'system'     => 'You are a civic data assistant. Reply only in the exact format requested — no explanation, no extra text.',
                    'messages'   => [
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);

            if (! $response->ok()) {
                Log::warning('politicians:enrich-statewide Claude API error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            $text = trim($response->json('content.0.text') ?? '');

            if (str_starts_with(strtoupper($text), 'UNKNOWN') || $text === '') {
                return null;
            }

            $parts = explode('|', $text, 2);
            $name  = $this->cleanName($parts[0]);
            $party = isset($parts[1]) ? $this->normaliseParty(trim($parts[1])) : null;

            return $name ? ['name' => $name, 'party' => $party] : null;
        } catch (\Throwable $e) {
            Log::warning('politicians:enrich-statewide Claude failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    // ── Clean garbage records ─────────────────────────────────────────────────

    private function cleanGarbageRecords(?string $stateFilter, bool $dryRun): void
    {
        $query = Politician::query()
            ->where('governance_level', 'State')
            ->where('verified_official', false)
            ->whereNull('user_id');

        if ($stateFilter) {
            $query->whereRaw('UPPER(COALESCE(state, \'\')) = ?', [$stateFilter]);
        }

        $candidates = $query->get(['id', 'full_name', 'state', 'political_office']);
        $removed = 0;

        foreach ($candidates as $pol) {
            // Keep only if the exact state|office|name tuple was enriched this run.
            // Matching by name alone allowed stale cross-office records to survive
            // (e.g. an old "Eleni Kounalakis / Governor" record surviving because she
            // was enriched as "Lieutenant Governor").
            $recKey = strtolower("{$pol->state}|{$pol->political_office}|{$pol->full_name}");
            if (in_array($recKey, $this->enrichedKeys, true)) {
                continue;
            }

            $this->line("  <fg=red>DELETE</> #{$pol->id} \"{$pol->full_name}\" ({$pol->state} {$pol->political_office})");

            if (! $dryRun) {
                $pol->delete();
            }
            $removed++;
        }

        $suffix = $dryRun ? ' (dry-run)' : '';
        $this->line("  Removed {$removed} garbage record(s){$suffix}.");
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function cleanName(string $raw): string
    {
        $name = trim(strip_tags($raw));
        // Remove trailing parentheticals like "(D)" or "(born 1967)"
        $name = preg_replace('/\s*\(.*\)\s*$/', '', $name) ?? $name;
        // Collapse whitespace
        $name = preg_replace('/\s+/', ' ', $name) ?? $name;
        return strlen($name) >= 3 ? $name : '';
    }

    private function normaliseParty(string $raw): ?string
    {
        $p = strtolower($raw);
        if (str_contains($p, 'democrat') || $p === 'd') return 'Democratic';
        if (str_contains($p, 'republican') || $p === 'r') return 'Republican';
        if (str_contains($p, 'independent')) return 'Independent';
        if (str_contains($p, 'libertarian')) return 'Libertarian';
        if (str_contains($p, 'green')) return 'Green';
        // California "No Party Preference" is a ballot designation, not a party affiliation.
        // Return null so no misleading party badge appears for the officeholder.
        if (str_contains($p, 'no party') || str_contains($p, 'non-partisan') || str_contains($p, 'nonpartisan')) return null;
        return ucwords($raw) ?: null;
    }

    private function generateSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i    = 2;
        while (Politician::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
