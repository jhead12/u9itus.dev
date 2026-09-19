<?php

namespace App\Console\Commands;

use App\Models\Politician;
use App\Support\PoliticianDataRules;
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
 *   full_name, state, result_status ('advanced_to_general'|'won'|'lost'|'incumbent'|null)
 *
 * Primary advancement keeps candidacy active. Only explicit general/special
 * wins set won_at; serving status comes from an officeholder feed, not a win.
 * New profiles are inactive/unpublished pending review. Ambiguous outcomes
 * and cross-office matches are reported without changing the current office.
 */
class ImportElectionResults extends Command
{
    protected $signature = 'politicians:import-election-results
        {--file=storage/app/imports/state-voter-guides-2026.json : Path to scraped results JSON}
        {--create-missing : Stage unpublished profiles for candidates not yet in DB}
        {--skip-fresh-days=7 : Skip records whose result_status is already final and status_updated_at is within this many days. Set 0 to always update.}
        {--dry-run : Parse and report only — no DB writes}';

    protected $description = 'Import election results (won/lost/incumbent) from a scraped JSON file and update politician records.';

    public function handle(): int
    {
        $fileOption      = (string) $this->option('file');
        $dryRun          = (bool)   $this->option('dry-run');
        $createMissing   = (bool)   $this->option('create-missing');
        $skipFreshDays   = max(0, (int) $this->option('skip-fresh-days'));
        $skipFreshCutoff = $skipFreshDays > 0 ? now()->subDays($skipFreshDays) : null;

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
        $fresh   = 0;

        if ($skipFreshCutoff) {
            $this->line("Skipping final records updated within the last {$skipFreshDays} day(s) (pass --skip-fresh-days=0 to force).");
        }

        foreach ($rows as $idx => $row) {
            if (! is_array($row)) {
                $skipped++;
                continue;
            }

            $fullName     = trim((string) ($row['full_name'] ?? ''));
            $state        = strtoupper(trim((string) ($row['state'] ?? '')));
            $office       = trim((string) ($row['political_office'] ?? ''));
            $resultStatus = $row['result_status'] ?? null;
            $stage = strtolower(trim((string) ($row['election_stage'] ?? '')));
            if ($resultStatus === 'won' && $stage === 'primary') {
                $resultStatus = 'advanced_to_general';
            }
            if (! in_array($resultStatus, [null, 'won', 'lost', 'incumbent', 'advanced_to_general'], true)
                || ($resultStatus === 'won' && ! in_array($stage, ['general', 'special'], true))) {
                $this->warn("Row {$idx}: outcome/stage ambiguous; source review required.");
                $skipped++;
                continue;
            }

            if (PoliticianDataRules::headlineFragmentViolation($fullName) !== null || ! in_array($state, PoliticianDataRules::ALLOWED_STATES, true)) {
                $this->warn("Row {$idx}: skipped — invalid candidate name or state.");
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
                $matches = Politician::query()
                    ->whereRaw('LOWER(full_name) = ?', [strtolower($fullName)])
                    ->whereRaw("UPPER(COALESCE(state, '')) = ?", [$state])
                    ->limit(2)->get();
                if ($matches->count() > 1) {
                    $this->warn("Row {$idx}: ambiguous identity; source review required.");
                    $skipped++;
                    continue;
                }
                $politician = $matches->first();
            }

            // ── Apply result ──────────────────────────────────────────────────

            if ($politician) {
                // A person's current office is not necessarily the office they ran for.
                if (strcasecmp(trim((string) $politician->full_name), $fullName) !== 0
                    || strtoupper((string) $politician->state) !== $state
                    || ($office !== '' && $this->officeKey($office) !== $this->officeKey((string) $politician->political_office))) {
                    $this->warn("Row {$idx}: cross-office/geography match; source review required.");
                    $skipped++;
                    continue;
                }
                // Skip records that already have a final result and were updated
                // recently — no point overwriting stable data on every weekly run.
                $isFinalResult = in_array($politician->term_status, ['seated', 'lost'], true);
                if (
                    $skipFreshCutoff !== null
                    && $isFinalResult
                    && $politician->status_updated_at !== null
                    && $politician->status_updated_at->gt($skipFreshCutoff)
                ) {
                    $fresh++;
                    continue;
                }

                $updates = $this->buildUpdates($resultStatus, $politician);

                if ($bpId !== null && $politician->ballotpedia_id === null) {
                    $updates['ballotpedia_id'] = $bpId;
                }

                $label = match ($resultStatus) {
                    'won'       => 'WON',
                    'lost'      => 'LOST',
                    'incumbent' => 'INCUMBENT',
                    'advanced_to_general' => 'ADVANCED',
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
                $source = $row['source_url'] ?? $row['source_page'] ?? $row['ballotpedia_url'] ?? null;
                if ($office === '' || ! is_string($source) || ! filter_var($source, FILTER_VALIDATE_URL)
                    || ! in_array(parse_url($source, PHP_URL_SCHEME), ['https', 'http'], true)) {
                    $this->warn("Row {$idx}: cannot stage a profile without office and source URL.");
                    $skipped++;
                    continue;
                }
                $slug = $this->generateSlug($fullName);

                $this->line("[STAGE FOR REVIEW] {$fullName} ({$state} {$office})");

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
                            'is_active'            => false,
                            ...$this->buildUpdates($resultStatus),
                            'page_published'       => false,
                            'page_settings'        => ['import_review' => [
                                'status' => 'pending',
                                'source_url' => $source,
                                'election_stage' => $stage ?: null,
                                'result_status' => $resultStatus,
                                'scraped_at' => $row['scraped_at'] ?? null,
                                'imported_at' => now()->toIso8601String(),
                            ]],
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
            "Results import complete%s: %d wins/incumbents, %d lost, %d running, %d staged, %d skipped, %d already current.",
            $suffix, $won, $lost, $running, $created, $skipped, $fresh
        ));

        return self::SUCCESS;
    }

    /** Build the column updates array for a given result_status. */
    private function buildUpdates(?string $resultStatus, ?Politician $politician = null): array
    {
        return match ($resultStatus) {
            'won' => [
                // 'active' + won_at records election without claiming the term has begun.
                'term_status'          => $politician?->term_status === 'seated' ? 'seated' : 'active',
                'is_running_candidate' => false,
                'status_updated_at'    => now(),
                'won_at'               => $politician?->won_at ?? now(),
            ],
            'lost' => [
                'term_status'          => $politician?->term_status === 'seated' ? 'seated' : 'lost',
                'is_running_candidate' => false,
                'status_updated_at'    => now(),
            ],
            'incumbent' => [
                'term_status'       => 'seated',
                'status_updated_at' => now(),
            ],
            default => [
                'is_running_candidate' => true,
                'term_status'          => $politician?->term_status === 'seated' ? 'seated' : 'running',
                'status_updated_at'    => now(),
            ],
        };
    }

    private function officeKey(string $office): string
    {
        return str_replace(['united states', 'u.s.'], 'us', strtolower(trim($office)));
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
        if (str_contains($slug, '?') || str_contains($slug, '#') || $slug === ''
            || preg_match('/elections?|Elections_in_/i', urldecode($slug))) {
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
