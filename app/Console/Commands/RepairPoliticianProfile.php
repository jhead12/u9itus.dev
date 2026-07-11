<?php

namespace App\Console\Commands;

use App\Models\CandidateIdentityLink;
use App\Models\ElectionCandidateRecord;
use App\Models\Politician;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Targeted repair command for a specific politician profile that returned a
 * 500 error on its public page (/p/{slug}).
 *
 * Repair steps (in order):
 *  1. Find the politician by slug (no page_published/is_active gate).
 *  2. If not found, attempt to extract a name from the slug and create a
 *     stub from a matching election_candidate_record.
 *  3. Ensure required fields are not null / blank (governance_level, state,
 *     full_name, slug, uuid).
 *  4. Ensure a PoliticianPage row exists so the public show() method does
 *     not have to instantiate a default in a way that can fail.
 *  5. Attempt to link an unlinked election_candidate_record if no
 *     CandidateIdentityLink exists.
 *  6. Re-publish (page_published = true, is_active = true) if the profile
 *     was already visible before the error.
 *
 * Usage:
 *   php artisan politicians:repair-profile "1c8a2-united-states-representative-jefferson-shreve"
 *   php artisan politicians:repair-profile {slug} --dry-run
 */
class RepairPoliticianProfile extends Command
{
    protected $signature = 'politicians:repair-profile
        {slug : The slug of the profile that returned a 500 error}
        {--dry-run : Report what would be changed without writing to the DB}';

    protected $description = 'Repair a politician profile that is returning 500 errors on its public page.';

    public function handle(): int
    {
        $slug   = trim((string) $this->argument('slug'));
        $dryRun = (bool) $this->option('dry-run');

        if ($slug === '') {
            $this->error('Slug argument is required.');
            return self::FAILURE;
        }

        $this->line("Repairing profile: {$slug}" . ($dryRun ? ' [dry-run]' : ''));

        $politician = $this->findPoliticianBySlug($slug);

        if ($politician === null) {
            $this->warn('No politician found for slug — attempting to create from election_candidate_records.');
            $politician = $this->createFromElectionRecord($slug, $dryRun);

            if ($politician === null) {
                $this->error('Could not find or create a politician for this slug. No matching election_candidate_record found.');
                Log::error('politicians:repair-profile: no politician and no matching election record.', ['slug' => $slug]);
                return self::FAILURE;
            }

            $this->info('Created new politician #' . $politician->id . ' from election_candidate_record.');
            return self::SUCCESS;
        }

        $this->line('Found politician #' . $politician->id . ' — ' . $politician->full_name);

        $repaired = false;

        // ── Step 1: Ensure required fields are non-null ──────────────────────
        $updates = $this->buildRequiredFieldFixes($politician);
        if ($updates !== []) {
            $this->line('  Fixing required fields: ' . implode(', ', array_keys($updates)));
            if (! $dryRun) {
                // Bypass Politician::saving() observer slug regeneration by updating directly.
                DB::table('politicians')->where('id', $politician->id)->update($updates);
                $politician->refresh();
            }
            $repaired = true;
        }

        // ── Step 2: Ensure PoliticianPage row exists ─────────────────────────
        if (! $politician->relationLoaded('page')) {
            $politician->load('page');
        }
        if ($politician->page === null) {
            $this->line('  Creating missing PoliticianPage row.');
            if (! $dryRun) {
                \App\Models\PoliticianPage::firstOrCreate(
                    ['politician_id' => $politician->id],
                    \App\Models\PoliticianPage::defaults($politician->id),
                );
            }
            $repaired = true;
        }

        // ── Step 3: Ensure at least one CandidateIdentityLink ────────────────
        $hasLink = CandidateIdentityLink::where('politician_id', $politician->id)->exists();
        if (! $hasLink) {
            $record = $this->findMatchingElectionRecord($politician);
            if ($record) {
                $this->line('  Linking to election_candidate_record #' . $record->id . '.');
                if (! $dryRun) {
                    CandidateIdentityLink::updateOrCreate(
                        [
                            'politician_id'             => $politician->id,
                            'election_candidate_record_id' => $record->id,
                        ],
                        [
                            'match_score'     => 0.90,
                            'match_breakdown' => ['source' => 'repair-command', 'slug' => $slug],
                            'reviewed_at'     => null,
                        ]
                    );
                }
                $repaired = true;
            } else {
                $this->warn('  No matching election_candidate_record found to link.');
            }
        }

        // ── Step 4: Re-activate and re-publish if dormant ────────────────────
        // reconcile-status sets BOTH is_active and page_published to false.
        // resolvePublicPolitician requires BOTH to be true, so we must restore both.
        $needsActivation  = ! $politician->is_active;
        $needsPublication = ! $politician->page_published;

        if ($needsActivation || $needsPublication) {
            $restoreFields = [];
            if ($needsActivation) {
                $this->line('  Re-activating politician (is_active = true).');
                $restoreFields['is_active'] = true;
            }
            if ($needsPublication) {
                $this->line('  Re-publishing politician (page_published = true).');
                $restoreFields['page_published'] = true;
            }
            if (! $dryRun) {
                DB::table('politicians')->where('id', $politician->id)->update($restoreFields);
            }
            $repaired = true;
        }

        if ($repaired) {
            $this->info('Profile repaired successfully.' . ($dryRun ? ' (dry-run — no writes)' : ''));
            Log::info('politicians:repair-profile: repaired profile.', [
                'slug'          => $slug,
                'politician_id' => $politician->id,
                'dry_run'       => $dryRun,
            ]);
        } else {
            $this->info('No structural issues found — the 500 may be a transient error or a transparency-data provider failure.');
            Log::info('politicians:repair-profile: no structural issues found.', [
                'slug'          => $slug,
                'politician_id' => $politician->id,
            ]);
        }

        return self::SUCCESS;
    }

    // ─────────────────────────────────────────────────────────────────────────

    protected function findPoliticianBySlug(string $slug): ?Politician
    {
        // Intentionally skip page_published / is_active so we find dormant rows too.
        return Politician::where('slug', $slug)->first();
    }

    /**
     * Extract a human-readable name from the slug and try to match an
     * unlinked election_candidate_record, then create a Politician from it.
     */
    protected function createFromElectionRecord(string $slug, bool $dryRun): ?Politician
    {
        // Slug format: {5-char-prefix}-{seo-name}
        // Strip the prefix to get the readable portion.
        $readable = preg_replace('/^[a-f0-9]{5}-/', '', $slug) ?? $slug;

        // Convert slug tokens back to title-cased words (best effort).
        $nameGuess = Str::title(str_replace('-', ' ', $readable));

        $this->line('  Name guessed from slug: "' . $nameGuess . '"');

        $record = ElectionCandidateRecord::query()
            ->whereRaw('LOWER(full_name) LIKE ?', ['%' . strtolower($nameGuess) . '%'])
            ->whereDoesntHave('identityLinks')
            ->first();

        if ($record === null) {
            return null;
        }

        $this->line('  Matched election_candidate_record #' . $record->id . ' — ' . $record->full_name);

        if ($dryRun) {
            // Return a transient (unsaved) model for reporting.
            return new Politician(['full_name' => $record->full_name, 'id' => 0]);
        }

        $payload = $this->buildPoliticianPayload($record, $slug);
        $politician = Politician::create($payload);

        CandidateIdentityLink::create([
            'politician_id'             => $politician->id,
            'election_candidate_record_id' => $record->id,
            'match_score'               => 1.0,
            'match_breakdown'           => ['source' => 'repair-command-create', 'slug' => $slug],
        ]);

        return $politician;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildRequiredFieldFixes(Politician $politician): array
    {
        $fixes = [];

        if (empty(trim((string) ($politician->full_name ?? '')))) {
            $fixes['full_name'] = 'Unknown Politician';
        }

        if (empty(trim((string) ($politician->governance_level ?? '')))) {
            $fixes['governance_level'] = 'Federal';
        }

        if (empty(trim((string) ($politician->state ?? '')))) {
            // Try to guess from slug.
            $fixes['state'] = 'US';
        }

        if (empty(trim((string) ($politician->uuid ?? '')))) {
            $fixes['uuid'] = (string) \Illuminate\Support\Str::uuid();
        }

        return $fixes;
    }

    protected function findMatchingElectionRecord(Politician $politician): ?ElectionCandidateRecord
    {
        $query = ElectionCandidateRecord::query()
            ->whereDoesntHave('identityLinks')
            ->whereRaw('LOWER(full_name) = ?', [strtolower((string) ($politician->full_name ?? ''))]);

        if (! empty($politician->state)) {
            $query->where('state', strtoupper((string) $politician->state));
        }

        return $query->first();
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildPoliticianPayload(ElectionCandidateRecord $record, string $slug): array
    {
        $payload = is_array($record->payload) ? $record->payload : [];

        return [
            'user_id'          => null,
            'full_name'        => trim((string) $record->full_name),
            'political_office' => $this->nullableString($record->political_office),
            'governance_level' => $this->nullableString($record->governance_level) ?? 'Federal',
            'district'         => $this->nullableString($record->district),
            'party_affiliation' => $this->nullableString($record->party_affiliation),
            'state'            => strtoupper((string) ($record->state ?? 'US')),
            'city'             => $this->nullableString($record->city),
            'website_url'      => $this->nullableString($payload['website'] ?? null),
            'is_active'        => true,
            'page_published'   => true,
            'verified_official' => false,
            'slug'             => $slug,
        ];
    }

    protected function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $str = trim((string) $value);
        return $str === '' ? null : $str;
    }
}
