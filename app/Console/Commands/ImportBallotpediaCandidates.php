<?php

namespace App\Console\Commands;

use App\Models\Politician;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Import 2026 primary-election candidates scraped from Ballotpedia.
 *
 * The JSON input is produced by scripts/scrape-ballotpedia.js and each record
 * is expected to contain at minimum: full_name, state, political_office.
 *
 * Matching strategy (in order):
 *  1. Same name (case-insensitive) + state + office (exact)
 *  2. Same name + state + governance_level = Federal
 *  3. Create a new unclaimed politician record
 *
 * Existing records are updated with is_running_candidate = true but their other
 * fields are left unchanged unless --overwrite is passed.
 */
class ImportBallotpediaCandidates extends Command
{
    protected $signature = 'politicians:import-ballotpedia
        {--file=storage/app/imports/ballotpedia-2026.json : Path to the scraped JSON file}
        {--overwrite : Also overwrite district, party, photo on matched records}
        {--dry-run : Parse and report only — no DB writes}';

    protected $description = 'Import 2026 Ballotpedia primary-election candidates and mark them as running candidates.';

    public function handle(): int
    {
        $fileOption = (string) $this->option('file');
        $dryRun     = (bool) $this->option('dry-run');
        $overwrite  = (bool) $this->option('overwrite');

        $path = str_starts_with($fileOption, '/')
            ? $fileOption
            : base_path($fileOption);

        if (! file_exists($path)) {
            $this->error("Import file not found: {$path}");
            $this->line('Run the scraper first:');
            $this->line('  node scripts/scrape-ballotpedia.js');

            return self::FAILURE;
        }

        $rows = json_decode((string) file_get_contents($path), true);

        if (! is_array($rows)) {
            $this->error('Invalid JSON — expected an array of candidate objects.');

            return self::FAILURE;
        }

        $created  = 0;
        $updated  = 0;
        $skipped  = 0;

        foreach ($rows as $idx => $row) {
            if (! is_array($row)) {
                $skipped++;
                continue;
            }

            $fullName = trim((string) ($row['full_name'] ?? ''));
            $state    = strtoupper(trim((string) ($row['state'] ?? '')));
            $office   = trim((string) ($row['political_office'] ?? ''));

            if ($fullName === '' || $state === '') {
                $this->warn("Row {$idx}: skipped — missing full_name or state.");
                $skipped++;
                continue;
            }

            // ── Match existing record ────────────────────────────────────────

            $politician = $this->findExisting($fullName, $state, $office);

            if ($politician) {
                if (! $dryRun) {
                    $updates = [
                        'is_running_candidate' => true,
                        'term_status'          => 'running',
                        'status_updated_at'    => now(),
                    ];

                    if ($overwrite) {
                        if ($row['district'] ?? null) {
                            $updates['district'] = $row['district'];
                        }
                        if ($row['party_affiliation'] ?? null) {
                            $updates['party_affiliation'] = $row['party_affiliation'];
                        }
                        $bpId = $this->extractBallotpediaId($row['ballotpedia_url'] ?? null);
                        if ($bpId !== null) {
                            $updates['ballotpedia_id'] = $bpId;
                        }
                    }

                    $politician->update($updates);
                }

                $this->line("[" . ($dryRun ? 'DRY' : 'UPDATE') . "] {$fullName} ({$state}) — existing record #{$politician->id}");
                $updated++;
                continue;
            }

            // ── Create new unclaimed profile ─────────────────────────────────

            $slug = $this->generateSlug($fullName);

            $bpIdForCreate = $this->extractBallotpediaId($row['ballotpedia_url'] ?? null);
            if (($row['ballotpedia_url'] ?? null) && $bpIdForCreate === null) {
                $this->warn("Row {$idx}: ballotpedia_url rejected (external/survey URL) for \"{$fullName}\" — ballotpedia_id will be null.");
            }

            if (! $dryRun) {
                Politician::create([
                    'uuid'                 => \Illuminate\Support\Str::uuid(),
                    'full_name'            => $fullName,
                    'political_office'     => $office ?: null,
                    'governance_level'     => $row['governance_level'] ?? 'Federal',
                    'state'                => $state,
                    'district'             => $row['district'] ?? null,
                    'party_affiliation'    => $row['party_affiliation'] ?? null,
                    'website_url'          => null,
                    'ballotpedia_id'       => $bpIdForCreate,
                    'is_active'            => true,
                    'is_running_candidate' => true,
                    'term_status'          => 'running',
                    'status_updated_at'    => now(),
                    'page_published'       => true,
                    'verified_official'    => false,
                    'slug'                 => $slug,
                    'user_id'             => null,
                ]);
            }

            $this->line("[" . ($dryRun ? 'DRY' : 'CREATE') . "] {$fullName} ({$state} {$office}) slug={$slug}");
            $created++;
        }

        $suffix = $dryRun ? ' (dry-run)' : '';
        $this->info(
            "Import complete{$suffix}: {$created} created, {$updated} updated, {$skipped} skipped."
        );

        return self::SUCCESS;
    }

    private function findExisting(string $fullName, string $state, string $office): ?Politician
    {
        $lower = strtolower($fullName);

        // 1. Exact name + state + office match
        $politician = Politician::query()
            ->whereRaw('LOWER(full_name) = ?', [$lower])
            ->whereRaw('UPPER(COALESCE(state, \'\')) = ?', [$state])
            ->whereRaw('LOWER(COALESCE(political_office, \'\')) = ?', [strtolower($office)])
            ->first();

        if ($politician) {
            return $politician;
        }

        // 2. Name + state + federal governance level (covers title mismatches)
        return Politician::query()
            ->whereRaw('LOWER(full_name) = ?', [$lower])
            ->whereRaw('UPPER(COALESCE(state, \'\')) = ?', [$state])
            ->where('governance_level', 'Federal')
            ->first();
    }

    /**
     * Extract a clean Ballotpedia page slug from a URL for storage in ballotpedia_id.
     *
     * Only accepts https://ballotpedia.org/<slug> URLs with no query string.
     * Returns null for external URLs, survey/mailto links, election index pages,
     * or anything that would overflow the VARCHAR(255) column — preventing
     * SQLSTATE[22001] String data, right truncated errors.
     */
    private function extractBallotpediaId(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return null;
        }

        if (! str_starts_with($url, 'https://ballotpedia.org/')) {
            return null;
        }

        $slug = substr($url, strlen('https://ballotpedia.org/'));

        // Reject election index pages and anything with query params
        if ($slug === '' || str_contains($slug, '?') || str_contains($slug, 'election,_')) {
            return null;
        }

        // Hard clamp to column limit as a last-resort safety net
        return substr($slug, 0, 255);
    }

    private function generateSlug(string $fullName): string
    {
        $base = Str::slug($fullName);
        $slug = $base;
        $i    = 2;

        while (Politician::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}
