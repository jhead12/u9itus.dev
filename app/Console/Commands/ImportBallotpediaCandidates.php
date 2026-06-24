<?php

namespace App\Console\Commands;

use App\Models\ElectionCandidateRecord;
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
        {--dry-run : Parse and report only — no DB writes}
        {--election-year=2026 : Election year (used in ECR election_date fallback)}';

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

        $created       = 0;
        $updated       = 0;
        $skipped       = 0;
        $crossOffice   = 0; // seated politician running for a different office → ECR only
        $seatedSkipped = 0; // seated same-office politician → no term_status overwrite
        $electionYear  = (int) ($this->option('election-year') ?? 2026);

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

            [$politician, $isCrossOffice] = $this->findExisting($fullName, $state, $office);

            if ($politician) {
                // Cross-office: person is seated in a different office.
                // Write a candidate ECR row; never mutate the seated politician profile.
                if ($isCrossOffice) {
                    $this->line("[CROSS-OFFICE] {$fullName} ({$state}) — seated as '{$politician->political_office}', running for '{$office}'");
                    $this->writeEcr($fullName, $state, $office, $row, $electionYear, $dryRun);
                    $crossOffice++;
                    continue;
                }

                // Seated same-office: skip term_status update to avoid corrupting current holders.
                if (in_array($politician->term_status, ['seated', 'current'], true)) {
                    $this->line("[SEATED-SKIP] {$fullName} ({$state}) — already seated as '{$politician->political_office}'");
                    $seatedSkipped++;
                    continue;
                }

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
                        $normParty = $this->normaliseParty($row['party_affiliation'] ?? null);
                        if ($normParty !== null) {
                            $updates['party_affiliation'] = $normParty;
                        }
                        if ($row['campaign_website'] ?? null) {
                            $updates['website_url'] = $row['campaign_website'];
                        }
                        if ($row['bio_excerpt'] ?? null) {
                            $updates['bio'] = $row['bio_excerpt'];
                        }
                        $bpId = $this->extractBallotpediaId($row['ballotpedia_url'] ?? null);
                        if ($bpId !== null) {
                            $updates['ballotpedia_id'] = $bpId;
                        }
                    }

                    $updateFailed = false;
                    try {
                        $politician->update($updates);
                    } catch (\Throwable $e) {
                        $this->warn("Row {$idx}: DB update failed for \"{$fullName}\" — {$e->getMessage()}");
                        $skipped++;
                        $updateFailed = true;
                    }
                    if ($updateFailed) {
                        continue;
                    }
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
                try {
                    Politician::create([
                        'uuid'                 => \Illuminate\Support\Str::uuid(),
                        'full_name'            => $fullName,
                        'political_office'     => $office ?: null,
                        'governance_level'     => $row['governance_level'] ?? 'Federal',
                        'state'                => $state,
                        'district'             => $row['district'] ?? null,
                        'party_affiliation'    => $this->normaliseParty($row['party_affiliation'] ?? null),
                        'website_url'          => $row['campaign_website'] ?? null,
                        'bio'                  => $row['bio_excerpt'] ?? null,
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
                } catch (\Throwable $e) {
                    $this->warn("Row {$idx}: DB insert failed for \"{$fullName}\" ({$state}) — {$e->getMessage()}");
                    $skipped++;
                    continue;
                }
            }

            $this->line("[" . ($dryRun ? 'DRY' : 'CREATE') . "] {$fullName} ({$state} {$office}) slug={$slug}");
            $created++;
        }

        $suffix = $dryRun ? ' (dry-run)' : '';
        $this->info(
            "Import complete{$suffix}: {$created} created, {$updated} updated, "
            . "{$crossOffice} cross-office→ECR, {$seatedSkipped} seated-skipped, {$skipped} skipped."
        );

        return self::SUCCESS;
    }

    /**
     * Write a candidate entry to election_candidate_records.
     * Used for cross-office candidates (seated in one office, running for another).
     */
    private function writeEcr(
        string $fullName,
        string $state,
        string $office,
        array  $row,
        int    $electionYear,
        bool   $dryRun,
    ): void {
        if ($dryRun) {
            return;
        }

        $bpId    = $this->extractBallotpediaId($row['ballotpedia_url'] ?? null);
        $extId   = $bpId ?? strtolower("{$state}-{$electionYear}-" . Str::slug($fullName) . '-' . Str::slug($office));
        $payload = [];

        if ($row['result_status'] ?? null) {
            $resultMap = ['won' => 'advanced_to_general', 'lost' => 'eliminated'];
            $payload['primary_result'] = $resultMap[$row['result_status']] ?? null;
        }

        // Include campaign website and bio if the scraper fetched them
        if (! empty($row['campaign_website'])) {
            $payload['website'] = $row['campaign_website'];
        }
        if (! empty($row['bio_excerpt'])) {
            $payload['bio'] = $row['bio_excerpt'];
        }

        ElectionCandidateRecord::updateOrCreate(
            ['external_candidate_id' => $extId],
            [
                'full_name'          => $fullName,
                'political_office'   => $office,
                'governance_level'   => strtolower($row['governance_level'] ?? 'state'),
                'state'              => $state,
                'district'           => $row['district'] ?? 'Statewide',
                'party_affiliation'  => $this->normaliseParty($row['party_affiliation'] ?? null),
                'source'             => 'ballotpedia',
                'election_date'      => $row['election_date'] ?? "{$electionYear}-11-03",
                'payload'            => $payload ?: null,
                'last_seen_at'       => now(),
            ]
        );
    }

    /**
     * Returns [Politician|null, bool $isCrossOffice].
     * $isCrossOffice=true means the person is seated in a DIFFERENT office than
     * the one they were scraped for (e.g. Lt. Gov running for Governor).
     *
     * @return array{0: ?Politician, 1: bool}
     */
    private function findExisting(string $fullName, string $state, string $office): array
    {
        $lower       = strtolower($fullName);
        $lowerOffice = strtolower($office);

        // 1. Exact name + state + office match
        $politician = Politician::query()
            ->whereRaw('LOWER(full_name) = ?', [$lower])
            ->whereRaw('UPPER(COALESCE(state, \'\')) = ?', [$state])
            ->whereRaw('LOWER(COALESCE(political_office, \'\')) = ?', [$lowerOffice])
            ->first();

        if ($politician) {
            return [$politician, false];
        }

        // 2. Name + state + federal governance level (covers title mismatches)
        $politician = Politician::query()
            ->whereRaw('LOWER(full_name) = ?', [$lower])
            ->whereRaw('UPPER(COALESCE(state, \'\')) = ?', [$state])
            ->where('governance_level', 'Federal')
            ->first();

        if ($politician) {
            return [$politician, false];
        }

        // 3. Cross-office: same name + state, different office, currently seated.
        //    Catches e.g. Lt. Governor running for Governor in another state too.
        $seated = Politician::query()
            ->whereRaw('LOWER(full_name) = ?', [$lower])
            ->whereRaw('UPPER(COALESCE(state, \'\')) = ?', [$state])
            ->whereIn('term_status', ['seated', 'current'])
            ->whereRaw('LOWER(COALESCE(political_office, \'\')) != ?', [$lowerOffice])
            ->first();

        if ($seated) {
            return [$seated, true];
        }

        return [null, false];
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

        // Reject election index pages (singular 'election,_' and plural 'elections,_')
        // and anything with query params.
        if ($slug === '' || str_contains($slug, '?') || preg_match('/elections?,_/i', $slug)) {
            return null;
        }

        // Reject slugs that contain ':' — these are mangled URI schemes
        // (e.g. a mailto: link that slipped through the scraper filter).
        if (str_contains($slug, ':')) {
            return null;
        }

        // Hard clamp to column limit as a last-resort safety net
        return substr($slug, 0, 255);
    }

    /**
     * Normalise a raw party string from the scraper to a canonical label.
     *
     * Returns null for California's "No Party Preference" / "Decline to State"
     * ballot designations — these are not party affiliations and must not be
     * stored as one (they were causing Gavin Newsom to appear as "Independent").
     */
    private function normaliseParty(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }
        $p = strtolower(trim($raw));
        if (str_contains($p, 'democrat')) return 'Democratic';
        if (str_contains($p, 'republican')) return 'Republican';
        if (str_contains($p, 'libertarian')) return 'Libertarian';
        if (str_contains($p, 'green')) return 'Green';
        if (str_contains($p, 'independent') || $p === 'ind') return 'Independent';
        // Suppress non-party ballot designations
        if (str_contains($p, 'no party') || str_contains($p, 'non-partisan')
            || str_contains($p, 'nonpartisan') || $p === 'npp' || $p === 'dts') {
            return null;
        }
        return ucwords(trim($raw)) ?: null;
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
