<?php

namespace App\Console\Commands;

use App\Models\Politician;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Merge duplicate unclaimed federal-official Politician rows created by the
 * (now-fixed) CongressGovService bug that stored the full state name instead
 * of a 2-letter code, which broke the exact-match dedup check in
 * PublicProfileController::persistDiscoveredOfficial() and let the same
 * person (e.g. "Mark Takano" / CA) get re-imported as a new row on every
 * district-lookup cache cycle instead of updating the existing one.
 *
 * Deliberately conservative and federal-only, mirroring
 * PublicProfileController::collapseDirectoryDuplicates()'s identity/preference
 * logic:
 *   - Only considers unclaimed (user_id IS NULL) federal officials, grouped
 *     by normalized full_name + state (ballotpedia_id is deliberately NOT
 *     used as a grouping key — it contains scraped junk text for hundreds
 *     of rows, e.g. "Survey" or "How do I run for office?", instead of a
 *     real Ballotpedia slug, which would merge unrelated people). Non-federal
 *     officials are skipped entirely — same name/state alone is too weak a
 *     signal for local/state offices spread across many cities.
 *   - Within a duplicate group, the "survivor" is chosen with the same
 *     preference order the directory page already uses (verified > active
 *     campaigns > correct governance_level > most recently updated).
 *   - A "loser" row is only ever deleted if it has ZERO rows referencing it
 *     across every politician_id / morph relation in the schema. Any loser
 *     with related data (a campaign, a post, a donor snapshot, etc.) is left
 *     alone and reported for manual review instead of being merged
 *     automatically — merging child data correctly is out of scope here.
 *
 * Usage:
 *   php artisan politicians:dedupe-unclaimed              # dry run (default)
 *   php artisan politicians:dedupe-unclaimed --apply       # actually delete
 *   php artisan politicians:dedupe-unclaimed --state=CA
 */
class DedupeUnclaimedPoliticians extends Command
{
    protected $signature = 'politicians:dedupe-unclaimed
        {--state=   : Two-letter state code — limit to one state}
        {--apply    : Actually delete safe-to-remove duplicate rows (default is dry-run)}';

    protected $description = 'Merge duplicate unclaimed federal-official Politician rows left over from the Congress.gov state-name bug.';

    /**
     * Every table with a politician_id foreign key, plus the two
     * polymorphic relations that reference politicians by (type, id).
     * Determined by grepping every belongsTo(Politician::class)/morphMany
     * relation and its migration in this codebase — see class docblock.
     */
    private const POLITICIAN_ID_TABLES = [
        'political_campaigns',
        'campaign_transactions',
        'payment_methods',
        'referral_earnings',
        'politician_pages',
        'politician_initiatives',
        'candidate_identity_links',
        'candidate_match_reviews',
        'politician_office_profiles',
        'candidate_news_articles',
        'politician_donor_snapshots',
        'politician_song_picks',
        'voter_favorite_politicians',
        'politician_photo_quarantines',
        'earlybank_earnings',
        'politician_endorsements',
        'marketing_post_drafts',
        'politician_credits',
        'politician_topic_signals',
        'voter_politician_notes',
        'politician_viral_moments',
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $stateFilter = $this->option('state') ? strtoupper(trim((string) $this->option('state'))) : null;

        if (! $apply) {
            $this->line('<fg=yellow>[dry-run] No rows will be deleted. Pass --apply to actually delete safe duplicates.</>');
        }

        $unclaimed = Politician::query()
            ->whereNull('user_id')
            ->when($stateFilter, fn ($q) => $q->whereRaw("UPPER(COALESCE(state, '')) = ?", [$stateFilter]))
            ->get();

        $groups = $unclaimed
            ->filter(fn (Politician $p) => $this->isFederalOfficial($p))
            ->groupBy(fn (Politician $p) => $this->identityKey($p))
            ->filter(fn ($group) => $group->count() > 1);

        if ($groups->isEmpty()) {
            $this->info('No duplicate federal-official groups found.');

            return self::SUCCESS;
        }

        $totalDeleted = 0;
        $totalSkippedWithData = 0;
        $totalGroups = 0;

        foreach ($groups as $key => $group) {
            $survivor = $group->reduce(function (?Politician $preferred, Politician $candidate): Politician {
                return $preferred === null ? $candidate : $this->prefer($preferred, $candidate);
            });

            $losers = $group->reject(fn (Politician $p) => $p->id === $survivor->id);

            $this->line("\n<fg=cyan>{$survivor->full_name}</> ({$survivor->state}) — keeping #{$survivor->id} ({$survivor->slug})");
            $totalGroups++;

            foreach ($losers as $loser) {
                $relatedTable = $this->firstTableWithRelatedRows($loser->id);

                if ($relatedTable !== null) {
                    $this->line("  <fg=yellow>⚠</> #{$loser->id} has related rows in '{$relatedTable}' — skipping, needs manual review");
                    $totalSkippedWithData++;

                    continue;
                }

                if ($apply) {
                    $loser->delete();
                    $this->line("  <fg=red>✗</> #{$loser->id} deleted");
                } else {
                    $this->line("  <fg=gray>would delete</> #{$loser->id}");
                }

                $totalDeleted++;
            }
        }

        $this->newLine();
        $verb = $apply ? 'deleted' : 'would delete';
        $this->info("{$totalGroups} duplicate group(s): {$totalDeleted} row(s) {$verb}, {$totalSkippedWithData} skipped (have related data).");

        return self::SUCCESS;
    }

    private function isFederalOfficial(Politician $politician): bool
    {
        if (strcasecmp((string) $politician->governance_level, 'Federal') === 0) {
            return true;
        }

        $office = strtolower(trim((string) ($politician->political_office ?? '')));
        foreach (['united states', 'u.s. senator', 'u.s. representative', 'us senator', 'us representative'] as $keyword) {
            if (str_contains($office, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function identityKey(Politician $politician): string
    {
        // Deliberately NOT grouping by ballotpedia_id: that column contains
        // scraped junk for hundreds of rows (literal nav-link text like
        // "Survey" or "How do I run for office?" instead of a real
        // Ballotpedia slug), which would silently merge unrelated
        // politicians that happen to share that garbage value. Name+state
        // is the only signal here that's actually trustworthy.
        $name = strtolower(trim((string) $politician->full_name));
        $state = strtoupper(trim((string) $politician->state));

        return 'name:'.$name.'|'.$state;
    }

    private function prefer(Politician $preferred, Politician $candidate): Politician
    {
        $senateKeywords = ['senator', 'senate'];
        $prefIsSenate = $this->officeContains($preferred, $senateKeywords);
        $candIsSenate = $this->officeContains($candidate, $senateKeywords);
        if ($candIsSenate !== $prefIsSenate) {
            return $candIsSenate ? $candidate : $preferred;
        }

        if ((bool) $candidate->verified_official !== (bool) $preferred->verified_official) {
            return $candidate->verified_official ? $candidate : $preferred;
        }

        $preferredCampaigns = $preferred->campaigns()->count();
        $candidateCampaigns = $candidate->campaigns()->count();
        if ($candidateCampaigns !== $preferredCampaigns) {
            return $candidateCampaigns > $preferredCampaigns ? $candidate : $preferred;
        }

        $prefFederal = strcasecmp((string) $preferred->governance_level, 'Federal') === 0;
        $candFederal = strcasecmp((string) $candidate->governance_level, 'Federal') === 0;
        if ($candFederal !== $prefFederal) {
            return $candFederal ? $candidate : $preferred;
        }

        $candidateUpdated = $candidate->updated_at?->getTimestamp() ?? 0;
        $preferredUpdated = $preferred->updated_at?->getTimestamp() ?? 0;
        if ($candidateUpdated !== $preferredUpdated) {
            return $candidateUpdated > $preferredUpdated ? $candidate : $preferred;
        }

        return $candidate->id > $preferred->id ? $candidate : $preferred;
    }

    private function officeContains(Politician $politician, array $keywords): bool
    {
        $office = strtolower(trim((string) ($politician->political_office ?? '')));
        foreach ($keywords as $keyword) {
            if (str_contains($office, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function firstTableWithRelatedRows(int $politicianId): ?string
    {
        foreach (self::POLITICIAN_ID_TABLES as $table) {
            if (DB::table($table)->where('politician_id', $politicianId)->exists()) {
                return $table;
            }
        }

        if (DB::table('posts')->where('author_type', Politician::class)->where('author_id', $politicianId)->exists()) {
            return 'posts';
        }

        if (DB::table('profile_badges')->where('badgeable_type', Politician::class)->where('badgeable_id', $politicianId)->exists()) {
            return 'profile_badges';
        }

        return null;
    }
}
