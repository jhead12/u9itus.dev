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
        {--scope=all      : What to enrich: all, statewide, mayors}
        {--clean          : Delete unverified statewide records not matched by enrichment.}
        {--dry-run        : Report only — no DB writes.}
        {--force          : Re-enrich even records already marked verified.}';

    protected $description = 'Enrich statewide executive officeholder data from Ballotpedia, Wikipedia, and (optionally) OpenAI.';

    /**
     * Major city mayors — one primary city per state + DC.
     * Each entry: 'state' => two-letter code used for the Politician row,
     * 'city' => canonical city name stored on the Politician,
     * 'wiki' => Wikipedia page title patterns to try (in order).
     * 'bp'   => Ballotpedia slug template.
     *
     * Wikipedia office pages use titles like:
     *   "Mayor of Los Angeles" or "Mayor_of_New_York_City"
     */
    private const CITY_MAYORS = [
        'DC' => ['city' => 'Washington D.C.', 'state_label' => 'District of Columbia',
            'wiki' => ['Mayor_of_the_District_of_Columbia', 'Mayor_of_Washington,_D.C.'],
            'bp'   => 'Mayor_of_the_District_of_Columbia'],
        'AL' => ['city' => 'Birmingham',   'wiki' => ['Mayor_of_Birmingham,_Alabama'],    'bp' => 'Mayor_of_Birmingham,_Alabama'],
        'AK' => ['city' => 'Anchorage',    'wiki' => ['Mayor_of_Anchorage,_Alaska'],      'bp' => 'Mayor_of_Anchorage,_Alaska'],
        'AZ' => ['city' => 'Phoenix',      'wiki' => ['Mayor_of_Phoenix,_Arizona'],       'bp' => 'Mayor_of_Phoenix,_Arizona'],
        'AR' => ['city' => 'Little Rock',  'wiki' => ['Mayor_of_Little_Rock,_Arkansas'],  'bp' => 'Mayor_of_Little_Rock,_Arkansas'],
        'CA' => ['city' => 'Los Angeles',  'wiki' => ['Mayor_of_Los_Angeles'],            'bp' => 'Mayor_of_Los_Angeles'],
        'CO' => ['city' => 'Denver',       'wiki' => ['Mayor_of_Denver'],                 'bp' => 'Mayor_of_Denver,_Colorado'],
        'CT' => ['city' => 'Hartford',     'wiki' => ['Mayor_of_Hartford,_Connecticut'],  'bp' => 'Mayor_of_Hartford,_Connecticut'],
        'DE' => ['city' => 'Wilmington',   'wiki' => ['Mayor_of_Wilmington,_Delaware'],   'bp' => 'Mayor_of_Wilmington,_Delaware'],
        'FL' => ['city' => 'Jacksonville', 'wiki' => ['Mayor_of_Jacksonville,_Florida'],  'bp' => 'Mayor_of_Jacksonville,_Florida'],
        'GA' => ['city' => 'Atlanta',      'wiki' => ['Mayor_of_Atlanta'],                'bp' => 'Mayor_of_Atlanta,_Georgia'],
        'HI' => ['city' => 'Honolulu',     'wiki' => ['Mayor_of_Honolulu'],               'bp' => 'Mayor_of_Honolulu,_Hawaii'],
        'ID' => ['city' => 'Boise',        'wiki' => ['Mayor_of_Boise,_Idaho'],           'bp' => 'Mayor_of_Boise,_Idaho'],
        'IL' => ['city' => 'Chicago',      'wiki' => ['Mayor_of_Chicago'],                'bp' => 'Mayor_of_Chicago,_Illinois'],
        'IN' => ['city' => 'Indianapolis', 'wiki' => ['Mayor_of_Indianapolis'],           'bp' => 'Mayor_of_Indianapolis,_Indiana'],
        'IA' => ['city' => 'Des Moines',   'wiki' => ['Mayor_of_Des_Moines,_Iowa'],       'bp' => 'Mayor_of_Des_Moines,_Iowa'],
        'KS' => ['city' => 'Wichita',      'wiki' => ['Mayor_of_Wichita,_Kansas'],        'bp' => 'Mayor_of_Wichita,_Kansas'],
        'KY' => ['city' => 'Louisville',   'wiki' => ['Mayor_of_Louisville,_Kentucky'],   'bp' => 'Mayor_of_Louisville,_Kentucky'],
        'LA' => ['city' => 'New Orleans',  'wiki' => ['Mayor_of_New_Orleans'],            'bp' => 'Mayor_of_New_Orleans,_Louisiana'],
        'ME' => ['city' => 'Portland',     'wiki' => ['Mayor_of_Portland,_Maine'],        'bp' => 'Mayor_of_Portland,_Maine'],
        'MD' => ['city' => 'Baltimore',    'wiki' => ['Mayor_of_Baltimore'],              'bp' => 'Mayor_of_Baltimore,_Maryland'],
        'MA' => ['city' => 'Boston',       'wiki' => ['Mayor_of_Boston'],                 'bp' => 'Mayor_of_Boston,_Massachusetts'],
        'MI' => ['city' => 'Detroit',      'wiki' => ['Mayor_of_Detroit'],                'bp' => 'Mayor_of_Detroit,_Michigan'],
        'MN' => ['city' => 'Minneapolis',  'wiki' => ['Mayor_of_Minneapolis'],            'bp' => 'Mayor_of_Minneapolis,_Minnesota'],
        'MS' => ['city' => 'Jackson',      'wiki' => ['Mayor_of_Jackson,_Mississippi'],   'bp' => 'Mayor_of_Jackson,_Mississippi'],
        'MO' => ['city' => 'Kansas City',  'wiki' => ['Mayor_of_Kansas_City,_Missouri'],  'bp' => 'Mayor_of_Kansas_City,_Missouri'],
        'MT' => ['city' => 'Billings',     'wiki' => ['Mayor_of_Billings,_Montana'],      'bp' => 'Mayor_of_Billings,_Montana'],
        'NE' => ['city' => 'Omaha',        'wiki' => ['Mayor_of_Omaha,_Nebraska'],        'bp' => 'Mayor_of_Omaha,_Nebraska'],
        'NV' => ['city' => 'Las Vegas',    'wiki' => ['Mayor_of_Las_Vegas'],              'bp' => 'Mayor_of_Las_Vegas,_Nevada'],
        'NH' => ['city' => 'Manchester',   'wiki' => ['Mayor_of_Manchester,_New_Hampshire'], 'bp' => 'Mayor_of_Manchester,_New_Hampshire'],
        'NJ' => ['city' => 'Newark',       'wiki' => ['Mayor_of_Newark,_New_Jersey'],     'bp' => 'Mayor_of_Newark,_New_Jersey'],
        'NM' => ['city' => 'Albuquerque',  'wiki' => ['Mayor_of_Albuquerque,_New_Mexico'],'bp' => 'Mayor_of_Albuquerque,_New_Mexico'],
        'NY' => ['city' => 'New York City','wiki' => ['Mayor_of_New_York_City'],          'bp' => 'Mayor_of_New_York_City'],
        'NC' => ['city' => 'Charlotte',    'wiki' => ['Mayor_of_Charlotte,_North_Carolina'],'bp' => 'Mayor_of_Charlotte,_North_Carolina'],
        'ND' => ['city' => 'Fargo',        'wiki' => ['Mayor_of_Fargo,_North_Dakota'],    'bp' => 'Mayor_of_Fargo,_North_Dakota'],
        'OH' => ['city' => 'Columbus',     'wiki' => ['Mayor_of_Columbus,_Ohio'],         'bp' => 'Mayor_of_Columbus,_Ohio'],
        'OK' => ['city' => 'Oklahoma City','wiki' => ['Mayor_of_Oklahoma_City'],          'bp' => 'Mayor_of_Oklahoma_City,_Oklahoma'],
        'OR' => ['city' => 'Portland',     'wiki' => ['Mayor_of_Portland,_Oregon'],       'bp' => 'Mayor_of_Portland,_Oregon'],
        'PA' => ['city' => 'Philadelphia', 'wiki' => ['Mayor_of_Philadelphia'],           'bp' => 'Mayor_of_Philadelphia,_Pennsylvania'],
        'RI' => ['city' => 'Providence',   'wiki' => ['Mayor_of_Providence,_Rhode_Island'],'bp' => 'Mayor_of_Providence,_Rhode_Island'],
        'SC' => ['city' => 'Columbia',     'wiki' => ['Mayor_of_Columbia,_South_Carolina'],'bp' => 'Mayor_of_Columbia,_South_Carolina'],
        'SD' => ['city' => 'Sioux Falls',  'wiki' => ['Mayor_of_Sioux_Falls,_South_Dakota'],'bp' => 'Mayor_of_Sioux_Falls,_South_Dakota'],
        'TN' => ['city' => 'Nashville',    'wiki' => ['Mayor_of_Nashville,_Tennessee'],   'bp' => 'Mayor_of_Nashville,_Tennessee'],
        'TX' => ['city' => 'Houston',      'wiki' => ['Mayor_of_Houston'],                'bp' => 'Mayor_of_Houston,_Texas'],
        'UT' => ['city' => 'Salt Lake City','wiki' => ['Mayor_of_Salt_Lake_City'],        'bp' => 'Mayor_of_Salt_Lake_City,_Utah'],
        'VT' => ['city' => 'Burlington',   'wiki' => ['Mayor_of_Burlington,_Vermont'],    'bp' => 'Mayor_of_Burlington,_Vermont'],
        'VA' => ['city' => 'Virginia Beach','wiki' => ['Mayor_of_Virginia_Beach,_Virginia'],'bp' => 'Mayor_of_Virginia_Beach,_Virginia'],
        'WA' => ['city' => 'Seattle',      'wiki' => ['Mayor_of_Seattle'],                'bp' => 'Mayor_of_Seattle,_Washington'],
        'WV' => ['city' => 'Charleston',   'wiki' => ['Mayor_of_Charleston,_West_Virginia'],'bp' => 'Mayor_of_Charleston,_West_Virginia'],
        'WI' => ['city' => 'Milwaukee',    'wiki' => ['Mayor_of_Milwaukee'],              'bp' => 'Mayor_of_Milwaukee,_Wisconsin'],
        'WY' => ['city' => 'Cheyenne',     'wiki' => ['Mayor_of_Cheyenne,_Wyoming'],      'bp' => 'Mayor_of_Cheyenne,_Wyoming'],
    ];

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
        'DC' => 'District_of_Columbia',
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

    /** Whether an Anthropic API key is configured (set once in handle(), read throughout) */
    private bool $hasAI = false;

    public function handle(): int
    {
        $stateFilter = $this->option('state')
            ? strtoupper(trim((string) $this->option('state')))
            : null;
        $scope  = strtolower(trim((string) ($this->option('scope') ?? 'all')));
        $dryRun = (bool) $this->option('dry-run');
        $clean  = (bool) $this->option('clean');
        $force  = (bool) $this->option('force');

        // DC is valid for mayors but not in STATE_NAMES (no Governor), so allow it explicitly
        $validStates = array_merge(array_keys(self::STATE_NAMES), ['DC']);
        if ($stateFilter && ! in_array($stateFilter, $validStates, true)) {
            $this->error("Unknown state code: {$stateFilter}");
            return self::FAILURE;
        }

        $this->hasAI = (bool) config('services.anthropic.api_key');
        if ($dryRun) {
            $this->line('<fg=yellow>[dry-run] No database writes will occur.</>');
        }
        if ($this->hasAI) {
            $model = config('services.anthropic.model', 'claude-haiku-4-5');
            $this->line("<fg=cyan>[ai] Anthropic {$model} available as Tier 3 fallback.</>");
        }

        $upserted = 0;
        $skipped  = 0;
        $failed   = 0;

        // ── Statewide executive offices ────────────────────────────────────────
        if (in_array($scope, ['all', 'statewide'], true)) {
            $states = $stateFilter && isset(self::STATE_NAMES[$stateFilter])
                ? [$stateFilter => self::STATE_NAMES[$stateFilter]]
                : self::STATE_NAMES;

            foreach ($states as $abbr => $stateName) {
                $this->line("\n<fg=green>[{$abbr}]</> {$stateName}");

                foreach (self::OFFICES as $office => $config) {
                    $result = $this->resolveCurrentHolder($abbr, $stateName, $office, $config);

                    if ($result === null) {
                        $this->line("  <fg=yellow>✗ {$office}: could not resolve current holder</>  (skipped)");
                        $failed++;
                        continue;
                    }

                    ['name' => $name, 'party' => $party, 'source' => $source, 'bp_url' => $bpUrl] = $result;
                    $this->enrichedKeys[] = strtolower("{$abbr}|{$office}|{$name}");
                    $this->line("  <fg=cyan>✓ {$office}:</> {$name}" . ($party ? " ({$party})" : '') . " <fg=gray>[{$source}]</>");

                    if (! $dryRun) {
                        [$upserted, $skipped, $failed] = $this->upsertPolitician(
                            $abbr, $office, 'State', null, $name, $party, $bpUrl,
                            $result['photo_url'] ?? null,
                            $force, $upserted, $skipped, $failed
                        );
                    } else {
                        $upserted++;
                    }

                    usleep(self::DELAY_MS * 1000);
                }
            }
        }

        // ── City mayors ────────────────────────────────────────────────────────
        if (in_array($scope, ['all', 'mayors'], true)) {
            $mayorEntries = $stateFilter
                ? array_filter(self::CITY_MAYORS, fn($k) => $k === $stateFilter, ARRAY_FILTER_USE_KEY)
                : self::CITY_MAYORS;

            foreach ($mayorEntries as $abbr => $mayorConfig) {
                $city      = $mayorConfig['city'];
                $stateName = $mayorConfig['state_label'] ?? (self::STATE_NAMES[$abbr] ?? $abbr);
                $this->line("\n<fg=green>[{$abbr}]</> {$city} Mayor");

                $config = [
                    'wiki_patterns' => $mayorConfig['wiki'],
                    'bp_pattern'    => $mayorConfig['bp'],
                ];

                $result = $this->resolveCurrentHolder($abbr, $stateName, 'Mayor', $config);

                if ($result === null) {
                    $this->line("  <fg=yellow>✗ Mayor of {$city}: could not resolve</>  (skipped)");
                    $failed++;
                    continue;
                }

                ['name' => $name, 'party' => $party, 'source' => $source, 'bp_url' => $bpUrl] = $result;
                $this->enrichedKeys[] = strtolower("{$abbr}|mayor|{$name}");
                $this->line("  <fg=cyan>✓ Mayor of {$city}:</> {$name}" . ($party ? " ({$party})" : '') . " <fg=gray>[{$source}]</>");

                if (! $dryRun) {
                    [$upserted, $skipped, $failed] = $this->upsertPolitician(
                        $abbr, 'Mayor', 'City', $city, $name, $party, $bpUrl,
                        $result['photo_url'] ?? null,
                        $force, $upserted, $skipped, $failed
                    );
                } else {
                    $upserted++;
                }

                usleep(self::DELAY_MS * 1000);
            }
        }

        // ── Clean garbage records ──────────────────────────────────────────────
        if ($clean && ! empty($this->enrichedKeys)) {
            $this->line("\n<fg=yellow>[clean]</> Removing unverified records not matched by enrichment...");
            $this->cleanGarbageRecords($stateFilter, $scope, $dryRun);
        }

        $this->info(
            "\nEnrichment complete: {$upserted} upserted, {$skipped} skipped, {$failed} unresolvable."
        );

        return self::SUCCESS;
    }

    /**
     * Upsert a single Politician row. Returns updated [$upserted, $skipped, $failed].
     *
     * @return array{int, int, int}
     */
    private function upsertPolitician(
        string $abbr,
        string $office,
        string $governanceLevel,
        ?string $city,
        string $name,
        ?string $party,
        ?string $bpUrl,
        ?string $photoUrl,
        bool $force,
        int $upserted,
        int $skipped,
        int $failed
    ): array {
        try {
            $query = Politician::query()
                ->whereRaw('UPPER(COALESCE(state, \'\')) = ?', [$abbr])
                ->whereRaw('LOWER(COALESCE(political_office, \'\')) = ?', [strtolower($office)])
                ->where('governance_level', $governanceLevel);

            if ($city !== null) {
                $query->whereRaw('LOWER(COALESCE(city, \'\')) = ?', [strtolower($city)]);
            }

            $existing = $query->orderByDesc('verified_official')->first();
            $bpSlug   = $bpUrl ? ltrim(parse_url($bpUrl, PHP_URL_PATH) ?? '', '/') : null;

            $attributes = array_filter([
                'full_name'            => $name,
                'party_affiliation'    => $party,
                'ballotpedia_id'       => $bpSlug ? substr($bpSlug, 0, 255) : null,
                // Only set photo if we have one — never overwrite a manually-set photo with null
                'profile_photo_url'    => $photoUrl,
                'is_running_candidate' => false,
                'term_status'          => 'seated',
                'is_active'            => true,
                'status_updated_at'    => now(),
            ], fn($v) => $v !== null);

            if ($existing && (! $existing->verified_official || $force)) {
                $existing->update($attributes);
                $this->line("    → Updated existing record #{$existing->id}");
                $upserted++;
            } elseif (! $existing) {
                $slug = $this->generateSlug($name);
                Politician::create(array_merge($attributes, [
                    'uuid'             => Str::uuid(),
                    'state'            => $abbr,
                    'city'             => $city,
                    'political_office' => $office,
                    'governance_level' => $governanceLevel,
                    'page_published'   => true,
                    'verified_official'=> false,
                    'user_id'          => null,
                    'slug'             => $slug,
                ]));
                $this->line("    → Created new record (slug={$slug})");
                $upserted++;
            } else {
                $this->line("    → Skipped (already verified_official=true; use --force to override)");
                $skipped++;
            }
        } catch (\Throwable $e) {
            $this->warn("    ✗ DB write failed: " . $e->getMessage());
            Log::warning('politicians:enrich-statewide DB error', [
                'state' => $abbr, 'office' => $office, 'error' => $e->getMessage(),
            ]);
            $failed++;
        }

        return [$upserted, $skipped, $failed];
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
        array $config
    ): ?array {
        $hasAI = $this->hasAI;
        $stateSlug = str_replace(' ', '_', $stateName);

        // ── Tier 1: Ballotpedia ────────────────────────────────────────────────
        $bpSlug = str_replace('{state}', $stateSlug, $config['bp_pattern']);
        $bpUrl  = "https://ballotpedia.org/{$bpSlug}";
        $bpResult = $this->fetchBallotpediaHolder($bpUrl);
        if ($bpResult) {
            return array_merge($bpResult, ['photo_url' => null, 'source' => 'ballotpedia', 'bp_url' => $bpUrl]);
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
                return array_merge($aiResult, ['photo_url' => null, 'source' => 'claude', 'bp_url' => $bpUrl]);
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
        // The summary endpoint also returns thumbnail.source — a Wikimedia Commons
        // image URL — which we capture as the profile photo when a name is found.
        $wikiThumbnail = null;
        try {
            $encoded  = rawurlencode($titleSlug);
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => $userAgent])
                ->get("https://en.wikipedia.org/api/rest_v1/page/summary/{$encoded}");

            if ($response->ok()) {
                $data        = $response->json();
                $description = trim($data['description'] ?? '');
                $extract     = trim($data['extract'] ?? '');
                // Capture thumbnail for whichever pattern matches below
                $thumbUrl    = $data['thumbnail']['source'] ?? null;

                // "Gavin Newsom since January 2019"
                if (preg_match('/^([A-Z][a-z\'\-]+(?: [A-Z][a-z\'\-]+){1,3})\s+since\b/i', $description, $m)) {
                    return ['name' => $this->cleanName($m[1]), 'party' => null, 'photo_url' => $thumbUrl];
                }
                // Extract starts with person's name: "Daniel McKee is the 76th…"
                if (preg_match('/^([A-Z][a-z\'\-]+(?: [A-Z][a-z\'\-]+){1,3})\s+(?:is|serves as|became)\b/u', $extract, $m)) {
                    return ['name' => $this->cleanName($m[1]), 'party' => null, 'photo_url' => $thumbUrl];
                }
                // "is {Name}, an American"
                if (preg_match('/\bis ([A-Z][a-z\'\-]+(?: [A-Z][a-z\'\-]+){1,3}),\s+an?\s+American\b/u', $extract, $m)) {
                    return ['name' => $this->cleanName($m[1]), 'party' => null, 'photo_url' => $thumbUrl];
                }
                // "current governor is Name" / "currently held by Name"
                if (preg_match('/\bcurrent\w*\s+\w+\s+is\s+([A-Z][a-z\'\-]+(?: [A-Z][a-z\'\-]+){1,3})\b/i', $extract, $m)) {
                    return ['name' => $this->cleanName($m[1]), 'party' => null, 'photo_url' => $thumbUrl];
                }
                // "incumbent Name" anywhere in extract
                if (preg_match('/\bincumbent[,\s]+([A-Z][a-z\'\-]+(?: [A-Z][a-z\'\-]+){1,3})\b/i', $extract, $m)) {
                    return ['name' => $this->cleanName($m[1]), 'party' => null, 'photo_url' => $thumbUrl];
                }
                // Store thumbnail even if name not found — wikitext pass may still resolve a name
                $wikiThumbnail = $thumbUrl;
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
                    $photo_url = $wikiThumbnail; // use thumbnail captured in 2a if available
                    if (preg_match('/\|\s*party\s*=\s*\[\[[^\]]*\|([^\]]{3,40})\]\]/i', $wikitext, $pm)) {
                        $party = $this->normaliseParty(trim($pm[1]));
                    } elseif (preg_match('/\|\s*party\s*=\s*([A-Za-z ]{3,40})\n/i', $wikitext, $pm)) {
                        $party = $this->normaliseParty(trim($pm[1]));
                    }
                    return ['name' => $name, 'party' => $party, 'photo_url' => $photo_url];
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

    private function cleanGarbageRecords(?string $stateFilter, string $scope, bool $dryRun): void
    {
        $levels = match ($scope) {
            'statewide' => ['State'],
            'mayors'    => ['City'],
            default     => ['State', 'City'],
        };

        $query = Politician::query()
            ->whereIn('governance_level', $levels)
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
