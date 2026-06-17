<?php

namespace App\Console\Commands;

use App\Models\Politician;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Import election results (winner / loser / incumbent) from a scraped JSON file.
 *
 * The JSON is produced by:
 *   node scripts/scrape-ballotpedia.js --results --year=<YEAR>
 *   node scripts/scrape-state-voter-guides.js --year=<YEAR>
 *
 * Each record is expected to have at minimum:
 *   full_name, state, result_status ('won'|'lost'|'incumbent'|null)
 *
 * What this command does:
 *  - 'won'       → term_status='seated', is_running_candidate=false, is_active=true
 *  - 'lost'      → term_status='lost',   is_running_candidate=false
 *  - 'incumbent' → term_status='seated'  (already in office, no change to running flag)
 *  - null        → is_running_candidate=true, term_status='running'  (still in race)
 *
 * Matching strategy (in order):
 *  1. ballotpedia_id (extracted from ballotpedia_url slug)
 *  2. Exact full_name + state + political_office  (case-insensitive)
 *  3. Exact full_name + state  (any office)
 *  4. Create a new unclaimed profile (when --create-missing is passed)
 */
class ImportElectionResults extends Command
{
    protected $signature = 'politicians:import-election-results
        {--file=storage/app/imports/state-voter-guides-2026.json : Path to scraped results JSON}
        {--create-missing : Create new unclaimed profiles for candidates not yet in DB}
        {--dry-run : Parse and report only — no DB writes}';

    protected $description = 'Import election results (won/lost/incumbent) from a scraped JSON file and update politician records.';

    public function handle(): int
    {
        $fileOption    = (string) $this->option('file');
        $dryRun        = (bool)   $this->option('dry-run');
        $createMissing = (bool)   $this->option('create-missing');

        $path = str_starts_with($fileOption, '/')
            ? $fileOption
            : base_path($fileOption);

        if (! file_exists($path)) {
            $this->error("Import file not found: {$path}");
            $this->line('Run the scraper first:');
            $this->line('  node scripts/scrape-state-voter-guides.js');
            return self::FAILURE;
        }

        $rows = json_decode((string) file_get_contents($path), true);

        if (! is_array($rows)) {
            $this->error('Invalid JSON — expected an array of candidate objects.');
            return self::FAILURE;
        }

        $won     = 0;
        $lost    = 0;
        $running = 0;
        $created = 0;
        $skipped = 0;

        foreach ($rows as $idx => $row) {
            if (! is_array($row)) {
                $skipped++;
                continue;
            }

            $fullName     = trim((string) ($row['full_name'] ?? ''));
            $state        = strtoupper(trim((string) ($row['state'] ?? '')));
            $office       = trim((string) ($row['political_office'] ?? ''));
            $resultStatus = $row['result_status'] ?? null;

            if ($fullName === '' || $state === '') {
                $this->warn("Row {$idx}: skipped — missing full_name or state.");
                $skipped++;
                continue;
            }

            // ── Find existing record ──────────────────────────────────────────

            $bpId = $this->extractBallotpediaId($row['ballotpedia_url'] ?? null);

            $politician = null;

            // 1. Ballotpedia ID match (most reliable)
            if ($bpId !== null) {
                $politician = Politician::query()
                    ->where('ballotpedia_id', $bpId)
                    ->first();
            }

            // 2. Name + state + office
            if (! $politician && $office !== '') {
                $politician = Politician::query()
                    ->whereRaw('LOWER(full_name) = ?', [strtolower($fullName)])
                    ->whereRaw('UPPER(COALESCE(state, \'\')) = ?', [$state])
                    ->whereRaw('LOWER(COALESCE(political_office, \'\')) = ?', [strtolower($office)])
                    ->first();
            }

            // 3. Name + state (any office)
            if (! $politician) {
                $politician = Politician::query()
                    ->whereRaw('LOWER(full_name) = ?', [strtolower($fullName)])
                    ->whereRaw('UPPER(COALESCE(state, \'\')) = ?', [$state])
                    ->first();
            }

            // ── Apply result ──────────────────────────────────────────────────

            if ($politician) {
                $updates = $this->buildUpdates($resultStatus);

                if ($bpId !== null && $politician->ballotpedia_id === null) {
                    $updates['ballotpedia_id'] = $bpId;
                }

                $label = match ($resultStatus) {
                    'won'       => 'WON',
                    'lost'      => 'LOST',
                    'incumbent' => 'INCUMBENT',
                    default     => 'RUNNING',
                };

                $this->line("[{$label}] {$fullName} ({$state}) #{$politician->id}");

                if (! $dryRun && ! empty($updates)) {
                    $politician->update($updates);
                }

                match ($resultStatus) {
                    'won', 'incumbent' => $won++,
                    'lost'             => $lost++,
                    default            => $running++,
                };

                continue;
            }

            // ── Create missing record (opt-in) ────────────────────────────────

            if ($createMissing) {
                $slug = $this->generateSlug($fullName);

                $this->line("[CREATE] {$fullName} ({$state} {$office})");

                if (! $dryRun) {
                    try {
                        Politician::create([
                            'uuid'                 => Str::uuid(),
                            'full_name'            => $fullName,
                            'political_office'     => $office ?: null,
                            'governance_level'     => $row['governance_level'] ?? 'State',
                            'state'                => $state,
                            'district'             => $row['district'] ?? null,
                            'party_affiliation'    => $row['party_affiliation'] ?? null,
                            'ballotpedia_id'       => $bpId,
                            'is_active'            => true,
                            'is_running_candidate' => $resultStatus === null,
                            'term_status'          => $resultStatus === 'won' || $resultStatus === 'incumbent'
                                ? 'seated'
                                : ($resultStatus === 'lost' ? 'lost' : 'running'),
                            'status_updated_at'    => now(),
                            'page_published'       => true,
                            'verified_official'    => false,
                            'slug'                 => $slug,
                            'user_id'              => null,
                        ]);
                    } catch (\Throwable $e) {
                        $this->warn("Row {$idx}: DB insert failed — {$e->getMessage()}");
                        $skipped++;
                        continue;
                    }
                }

                $created++;
                continue;
            }

            $this->warn("No match: {$fullName} ({$state} {$office})");
            $skipped++;
        }

        $suffix = $dryRun ? ' (dry-run)' : '';
        $this->info(sprintf(
            "Results import complete%s: %d seated/won, %d lost, %d running, %d created, %d skipped.",
            $suffix, $won, $lost, $running, $created, $skipped
        ));

        return self::SUCCESS;
    }

    /** Build the column updates array for a given result_status. */
    private function buildUpdates(?string $resultStatus): array
    {
        return match ($resultStatus) {
            'won' => [
                'term_status'          => 'seated',
                'is_running_candidate' => false,
                'is_active'            => true,
                'status_updated_at'    => now(),
            ],
            'lost' => [
                'term_status'          => 'lost',
                'is_running_candidate' => false,
                'status_updated_at'    => now(),
            ],
            'incumbent' => [
                'term_status'       => 'seated',
                'status_updated_at' => now(),
            ],
            default => [
                'is_running_candidate' => true,
                'term_status'          => 'running',
                'status_updated_at'    => now(),
            ],
        };
    }

    /**
     * Extract Ballotpedia page slug from a ballotpedia_url.
     * Only accepts root-relative or full ballotpedia.org paths, no query strings.
     */
    private function extractBallotpediaId(?string $url): ?string
    {
        if (! $url) return null;

        if (str_starts_with($url, 'https://ballotpedia.org/')) {
            $slug = substr($url, strlen('https://ballotpedia.org/'));
        } elseif (str_starts_with($url, '/')) {
            $slug = ltrim($url, '/');
        } else {
            return null;
        }

        // Reject query strings, fragments, or survey-style URLs
        if (str_contains($slug, '?') || str_contains($slug, '#') || $slug === '') {
            return null;
        }

        return urldecode($slug);
    }

    private function generateSlug(string $fullName): string
    {
        $base = Str::slug($fullName);
        $slug = $base;
        $i    = 1;
        while (Politician::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }
        return $slug;
    }
}
