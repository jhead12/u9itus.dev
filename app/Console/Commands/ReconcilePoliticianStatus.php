<?php

namespace App\Console\Commands;

use App\Models\ElectionCandidateRecord;
use App\Models\Politician;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Reconciles unclaimed politician records against the authoritative
 * congress-legislators feed and post-election Ballotpedia results.
 *
 * Run this:
 *  - Weekly (ongoing) — marks retired/former members
 *  - After each general election (November) — marks losers, promotes winners
 *
 * What it does
 * ────────────────────────────────────────────────────────────────────────────
 * 1. SEATED check  — fetch legislators-current.json. Anyone in the feed with
 *    a current term gets term_status = 'seated'. Their is_running_candidate
 *    is cleared (they won and are now in office).
 *
 * 2. FORMER/RETIRED check — fetch legislators-historical.json. Anyone present
 *    only in historical (not current) whose term ended in the past gets
 *    term_status = 'retired' and is_active = false. Profile stays visible but
 *    marked Former.
 *
 * 3. RUNNING expiry — any record still flagged is_running_candidate = true
 *    where the election_date (stored in bio or a custom field) is in the past
 *    and they are NOT in the current seated set gets term_status = 'lost' and
 *    is_running_candidate = false.
 *
 * 4. DEACTIVATION — records marked lost or retired that have no user_id and
 *    no active campaigns get is_active = false (hidden from directory).
 *    Claimed profiles are never deactivated automatically.
 */
class ReconcilePoliticianStatus extends Command
{
    protected $signature = 'politicians:reconcile-status
        {--current-url=https://unitedstates.github.io/congress-legislators/legislators-current.json}
        {--historical-url=https://unitedstates.github.io/congress-legislators/legislators-historical.json}
        {--election-date= : ISO date of general election (e.g. 2026-11-03). Defaults to today.}
        {--dry-run : Report changes without writing to DB}';

    protected $description = 'Reconcile politician term status: mark seated, retired, lost, or running.';

    /** Bioguide IDs of politicians currently in the feed */
    private array $currentBioguideIds = [];

    /** Lower-cased "name|state" keys of politicians currently in the feed */
    private array $currentNameStateKeys = [];

    /**
     * Maps "name|state" → ['political_office' => string, 'district_code' => string]
     * for seated members — used to correct stale office/district on Politician rows.
     */
    private array $currentMemberData = [];

    public function handle(): int
    {
        $dryRun       = (bool) $this->option('dry-run');
        $currentUrl   = (string) $this->option('current-url');
        $historicalUrl = (string) $this->option('historical-url');
        $electionDate = $this->option('election-date')
            ? Carbon::parse((string) $this->option('election-date'))
            : now();

        $this->info('Fetching current legislators…');
        $current = $this->fetchJson($currentUrl);
        if ($current === null) {
            $this->error('Failed to fetch current legislators feed.');
            return self::FAILURE;
        }

        $this->info('Fetching historical legislators…');
        $historical = $this->fetchJson($historicalUrl);
        if ($historical === null) {
            $this->error('Failed to fetch historical legislators feed.');
            return self::FAILURE;
        }

        // ── Build lookup sets from feeds ─────────────────────────────────────

        foreach ($current as $row) {
            if (!is_array($row)) continue;

            $bioguide = $row['id']['bioguide'] ?? null;
            if ($bioguide) {
                $this->currentBioguideIds[] = $bioguide;
            }

            $name  = strtolower(trim((string) ($row['name']['official_full'] ?? $this->buildName($row))));
            $term  = $this->latestTerm($row);
            $state = strtoupper(trim((string) ($term['state'] ?? '')));
            if ($name && $state) {
                $key = $name . '|' . $state;
                $this->currentNameStateKeys[] = $key;

                // Store current office & district so we can fix stale Politician rows
                $type = strtolower(trim((string) ($term['type'] ?? '')));
                $districtNum = isset($term['district']) ? (int) $term['district'] : null;
                $officeTitle = match ($type) {
                    'rep' => 'United States Representative',
                    'sen' => 'United States Senator',
                    default => '',
                };
                $districtCode = ($type === 'rep' && $districtNum !== null)
                    ? $state . '-' . str_pad((string) $districtNum, 2, '0', STR_PAD_LEFT)
                    : null;

                $bioguidePhoto = $bioguide
                    ? 'https://unitedstates.github.io/images/congress/225x275/' . $bioguide . '.jpg'
                    : null;

                $this->currentMemberData[$key] = [
                    'political_office' => $officeTitle,
                    'district_code'    => $districtCode,
                    'photo_url'        => $bioguidePhoto,
                ];
            }
        }

        // Build set of historical-only bioguide IDs (retired/former)
        $historicalBioguides = [];
        foreach ($historical as $row) {
            if (!is_array($row)) continue;
            $bioguide = $row['id']['bioguide'] ?? null;
            if ($bioguide && !in_array($bioguide, $this->currentBioguideIds, true)) {
                $historicalBioguides[] = $bioguide;
            }
        }

        $this->info(sprintf(
            'Feed loaded: %d seated | %d historical-only',
            count($this->currentBioguideIds),
            count($historicalBioguides),
        ));

        $stats = ['seated' => 0, 'retired' => 0, 'lost' => 0, 'deactivated' => 0, 'skipped' => 0];

        // ── Process all unclaimed federal politicians ─────────────────────────

        Politician::query()
            ->whereNull('user_id')
            ->where(function ($q) {
                $q->where('governance_level', 'Federal')
                  ->orWhereIn('political_office', [
                      'U.S. Representative', 'U.S. Senator',
                      'United States Representative', 'United States Senator',
                  ]);
            })
            ->chunkById(200, function ($politicians) use ($dryRun, $electionDate, &$stats) {
                foreach ($politicians as $politician) {
                    $result = $this->reconcileOne($politician, $dryRun, $electionDate);
                    $stats[$result]++;
                }
            });

        $suffix = $dryRun ? ' (dry-run)' : '';
        $this->info(sprintf(
            "Reconcile complete%s: %d seated | %d retired | %d lost | %d deactivated | %d skipped",
            $suffix,
            $stats['seated'],
            $stats['retired'],
            $stats['lost'],
            $stats['deactivated'],
            $stats['skipped'],
        ));

        return self::SUCCESS;
    }

    private function reconcileOne(Politician $politician, bool $dryRun, Carbon $electionDate): string
    {
        $bioguide  = $politician->ballotpedia_id
            ? null
            : ($politician->fec_candidate_id ?? null); // not reliable, fall through to name match
        $nameState = strtolower(trim((string) $politician->full_name)) . '|' . strtoupper((string) ($politician->state ?? ''));

        // ── 1. Currently seated ──────────────────────────────────────────────
        $isSeated = in_array($nameState, $this->currentNameStateKeys, true);

        if ($isSeated) {
            $feedData   = $this->currentMemberData[$nameState] ?? [];
            $feedOffice = (string) ($feedData['political_office'] ?? '');
            $feedDistrict = $feedData['district_code'] ?? null;

            $needsUpdate = $politician->term_status !== 'seated' || $politician->is_running_candidate;

            // Detect office/district mismatch (e.g. Rep→Senator after chamber switch)
            $officeChanged   = $feedOffice !== '' && $politician->political_office !== $feedOffice;
            $districtChanged = $feedDistrict !== null
                && (string) $politician->district !== $feedDistrict;

            if ($needsUpdate || $officeChanged || $districtChanged) {
                $updates = [
                    'term_status'          => 'seated',
                    'is_running_candidate' => false,
                    'is_active'            => true,
                    'status_updated_at'    => now(),
                ];

                if ($officeChanged) {
                    $updates['political_office'] = $feedOffice;
                    $this->line("[OFFICE UPDATED] {$politician->full_name}: {$politician->political_office} → {$feedOffice}");
                }

                if ($districtChanged && $feedDistrict !== null) {
                    $updates['district'] = $feedDistrict;
                    $this->line("[DISTRICT UPDATED] {$politician->full_name}: {$politician->district} → {$feedDistrict}");
                }

                // Set bioguide photo if not already set
                $feedPhoto = $feedData['photo_url'] ?? null;
                if ($feedPhoto && empty($politician->profile_photo_url)) {
                    $updates['profile_photo_url'] = $feedPhoto;
                }

                if ($needsUpdate) {
                    $this->line("[SEATED] {$politician->full_name} ({$politician->state})");
                }

                if (!$dryRun) {
                    $politician->update($updates);
                }
            }
            return 'seated';
        }

        // ── 2. Running candidate whose election date has passed → lost ───────
        if ($politician->is_running_candidate && $electionDate->isPast()) {
            $this->line("[LOST] {$politician->full_name} ({$politician->state}) — election date passed, not in current feed");
            if (!$dryRun) {
                $politician->update([
                    'term_status'          => 'lost',
                    'is_running_candidate' => false,
                    'status_updated_at'    => now(),
                ]);
                $this->expireEcrRows($politician, $dryRun);
            }
            // Fall through to deactivation check below
            $politician->refresh();
            return $this->maybeDeactivate($politician, $dryRun) ? 'deactivated' : 'lost';
        }

        // ── 3. Was previously seated but no longer in current feed → retired ─
        if (in_array($politician->term_status, ['seated', 'unknown'], true) && !$politician->is_running_candidate) {
            // Only mark retired if they have a term_ends_on in the past, or were previously seated
            $termEnded = $politician->term_ends_on && Carbon::parse($politician->term_ends_on)->lt(now());
            $wasPreviouslySeated = $politician->term_status === 'seated';

            if ($termEnded || $wasPreviouslySeated) {
                $this->line("[RETIRED] {$politician->full_name} ({$politician->state})");
                if (!$dryRun) {
                    $politician->update([
                        'term_status'       => 'retired',
                        'status_updated_at' => now(),
                    ]);
                    $this->expireEcrRows($politician, $dryRun);
                }
                $politician->refresh();
                return $this->maybeDeactivate($politician, $dryRun) ? 'deactivated' : 'retired';
            }
        }

        return 'skipped';
    }

    /**
     * Stamp matching ElectionCandidateRecord rows as eliminated so they are
     * excluded from the district-lookup "Running Candidates" section.
     * Matches by lower-cased full_name + upper-cased state.
     */
    private function expireEcrRows(Politician $politician, bool $dryRun): void
    {
        $name  = strtolower(trim((string) $politician->full_name));
        $state = strtoupper(trim((string) ($politician->state ?? '')));

        if ($name === '' || $state === '') {
            return;
        }

        $rows = ElectionCandidateRecord::query()
            ->whereRaw('LOWER(full_name) = ?', [$name])
            ->whereRaw('UPPER(state) = ?', [$state])
            ->whereRaw("COALESCE(payload->>'$.primary_result', '') != 'eliminated'")
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $this->line("  └─ [ECR EXPIRED] {$politician->full_name} — marking {$rows->count()} ECR row(s) as eliminated");

        if ($dryRun) {
            return;
        }

        foreach ($rows as $record) {
            $payload = is_array($record->payload) ? $record->payload : [];
            $payload['primary_result'] = 'eliminated';
            $record->update(['payload' => $payload]);
        }
    }

    /**
     * Deactivate a profile only if unclaimed and has no active campaigns.
     * Never deactivates claimed (user_id set) profiles.
     */
    private function maybeDeactivate(Politician $politician, bool $dryRun): bool
    {
        if ($politician->user_id !== null) {
            return false;
        }

        $hasActiveCampaigns = $politician->campaigns()
            ->where('status', 'active')
            ->exists();

        if ($hasActiveCampaigns) {
            return false;
        }

        $this->line("  └─ [DEACTIVATED] {$politician->full_name} — no active campaigns, unclaimed");
        if (!$dryRun) {
            $politician->update([
                'is_active'    => false,
                'page_published' => false,
            ]);
        }

        return true;
    }

    private function fetchJson(string $url): ?array
    {
        try {
            $response = Http::timeout(30)->get($url);
            if (!$response->ok()) {
                return null;
            }
            $data = $response->json();
            return is_array($data) ? $data : null;
        } catch (\Throwable $e) {
            Log::error('ReconcilePoliticianStatus: fetch failed', ['url' => $url, 'error' => $e->getMessage()]);
            return null;
        }
    }

    private function buildName(array $row): string
    {
        $name = $row['name'] ?? [];
        if (!is_array($name)) return '';
        return trim(implode(' ', array_filter([
            (string) ($name['first'] ?? ''),
            (string) ($name['middle'] ?? ''),
            (string) ($name['last'] ?? ''),
            (string) ($name['suffix'] ?? ''),
        ])));
    }

    private function latestTerm(array $row): ?array
    {
        $terms = $row['terms'] ?? [];
        if (!is_array($terms) || empty($terms)) return null;

        usort($terms, function ($a, $b) {
            $left  = is_array($a) ? (string) ($a['start'] ?? '') : '';
            $right = is_array($b) ? (string) ($b['start'] ?? '') : '';
            return strcmp($right, $left);
        });

        return is_array($terms[0]) ? $terms[0] : null;
    }
}
