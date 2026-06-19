<?php

namespace App\Console\Commands;

use App\Models\Politician;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Syncs the active congressional boundary configuration into district_config.
 *
 * Determines the current Congress number from the date, resolves the correct
 * TIGERweb layer + CD field name, and builds the district → party map from
 * the seated U.S. House members in the politicians table.
 *
 * Usage:
 *   php artisan geo:sync-district-config
 *   php artisan geo:sync-district-config --dry-run
 */
class SyncDistrictConfig extends Command
{
    protected $signature = 'geo:sync-district-config
        {--dry-run : Report only — no DB writes}';

    protected $description = 'Sync current Congress number and House district party map into district_config.';

    /**
     * Known Congress start dates (Jan 3 of the first session year).
     * Add a new entry here the cycle before each new Congress is seated.
     *
     * @var array<int, string>
     */
    private const CONGRESS_STARTS = [
        118 => '2023-01-03',
        119 => '2025-01-03',
        120 => '2027-01-03',
        121 => '2029-01-03',
        122 => '2031-01-03',
    ];

    /**
     * TIGERweb Legislative MapServer layer index per Congress.
     * Layer 0 is always the current/latest Congress on Census TIGERweb.
     * When TIGERweb adds dedicated historical layers for past Congresses,
     * update the older entries here (e.g. 119 => 1 once the 120th is live).
     *
     * @var array<int, int>
     */
    private const TIGERWEB_LAYERS = [
        118 => 0,
        119 => 0,
        120 => 0,
        121 => 0,
    ];

    /**
     * Human-readable labels per Congress.
     *
     * @var array<int, string>
     */
    private const CONGRESS_LABELS = [
        118 => '118th Congress (2023–2025)',
        119 => '119th Congress (2025–2027)',
        120 => '120th Congress (2027–2029)',
        121 => '121st Congress (2029–2031)',
        122 => '122nd Congress (2031–2033)',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $congress = $this->detectCongress();
        $cdField  = 'CD' . $congress;
        $layer    = self::TIGERWEB_LAYERS[$congress] ?? 0;
        $label    = self::CONGRESS_LABELS[$congress] ?? "{$congress}th Congress";

        $this->info("Detected congress : {$congress} — {$label}");
        $this->line("TIGERweb layer    : {$layer}");
        $this->line("CD field          : {$cdField}");

        // ── Build district → party map from seated House politicians ─────────
        $partyMap = $this->buildPartyMap();
        $this->line('Party map built   : ' . count($partyMap) . ' district(s) found in DB');

        if (count($partyMap) === 0) {
            $this->warn('Warning: no seated House politicians found — party_map will be empty.');
            $this->warn('Run politicians:import-unclaimed-us first to populate federal representatives.');
        }

        if ($dryRun) {
            $this->info('[dry-run] Skipping DB write.');
            $sample = array_slice($partyMap, 0, 10, true);
            foreach ($sample as $k => $v) {
                $this->line("  {$k} → {$v}");
            }
            if (count($partyMap) > 10) {
                $this->line('  … (' . (count($partyMap) - 10) . ' more)');
            }
            return self::SUCCESS;
        }

        // ── Upsert into district_config (single-row table) ───────────────────
        DB::table('district_config')->truncate();
        DB::table('district_config')->insert([
            'congress_number' => $congress,
            'tigerweb_layer'  => $layer,
            'cd_field'        => $cdField,
            'congress_label'  => $label,
            'party_map'       => json_encode($partyMap),
            'synced_at'       => now(),
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        // Bust the API cache so the map picks up the new config immediately.
        Cache::forget('district_config');

        $this->info("district_config updated — {$congress}th Congress, {$cdField}, {$layer} layer(s).");
        return self::SUCCESS;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Walk CONGRESS_STARTS in order and return the last entry whose start date
     * is on or before today. This correctly handles mid-term runs.
     */
    private function detectCongress(): int
    {
        $today   = now()->toDateString();
        $current = array_key_first(self::CONGRESS_STARTS); // fallback to earliest known

        foreach (self::CONGRESS_STARTS as $num => $start) {
            if ($today >= $start) {
                $current = $num;
            }
        }

        return $current;
    }

    /**
     * Build a district-label → party-code map from seated House members.
     *
     * Keys use the same format as the map's DISTRICT_PARTY_MAP: 'CA-1', 'AK-AL', etc.
     * Values are 'D', 'R', 'I', or 'U'.
     *
     * @return array<string, string>
     */
    private function buildPartyMap(): array
    {
        $map = [];

        $politicians = Politician::query()
            ->whereRaw("LOWER(COALESCE(governance_level, '')) = 'federal'")
            ->whereIn('term_status', ['seated', 'active', 'current'])
            ->whereNotNull('district')
            ->whereNotNull('state')
            ->where(function ($q) {
                $q->whereRaw("LOWER(COALESCE(political_office, '')) LIKE '%representative%'")
                  ->orWhereRaw("LOWER(COALESCE(political_office, '')) LIKE '%u.s. house%'")
                  ->orWhereRaw("LOWER(COALESCE(political_office, '')) LIKE '%house of representatives%'");
            })
            ->get(['state', 'district', 'party_affiliation']);

        foreach ($politicians as $pol) {
            $rawDistrict = strtoupper(trim((string) $pol->district));
            $state       = strtoupper(trim((string) $pol->state));

            if ($rawDistrict === '' || $state === '') {
                continue;
            }

            // Normalise the district key.
            // DB stores e.g. 'CA-1', 'AK-AL', 'CA-33' — use as-is if it already
            // contains the state prefix; otherwise prepend the state.
            $key = str_contains($rawDistrict, '-')
                ? $rawDistrict
                : "{$state}-{$rawDistrict}";

            $map[$key] = $this->normalizeParty($pol->party_affiliation);
        }

        ksort($map);
        return $map;
    }

    /**
     * Normalise a raw party_affiliation string to a single-letter code.
     */
    private function normalizeParty(?string $party): string
    {
        $p = strtolower(trim((string) $party));
        if (str_contains($p, 'republican')) return 'R';
        if (str_contains($p, 'democrat'))   return 'D';
        if (str_contains($p, 'independent')
            || str_contains($p, 'libertarian')
            || str_contains($p, 'green')) return 'I';
        return 'U';
    }
}
