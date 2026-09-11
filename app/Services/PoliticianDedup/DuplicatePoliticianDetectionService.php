<?php

namespace App\Services\PoliticianDedup;

use App\Models\Politician;
use App\Support\PoliticianDataRules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Shared duplicate-detection/merge engine for Politician rows, extracted
 * from three commands that had independently reinvented the same grouping
 * key + survivor-scoring + FK-reassignment logic and had drifted apart:
 * MergeDuplicatePoliticians (name+office+state, any unclaimed row),
 * DedupeUnclaimedPoliticians (name+state, federal officials only), and
 * PruneJunkEcrs's dedup pass (a similar idea for ElectionCandidateRecord,
 * which stays separate — different table/model).
 *
 * politicians:dedupe is the single command built on top of this service;
 * see its --scope option for how the two identity strategies map to the
 * original commands' behavior.
 */
class DuplicatePoliticianDetectionService
{
    public const STRATEGY_NAME_OFFICE_STATE = 'name_office_state';
    public const STRATEGY_NAME_STATE = 'name_state';

    /**
     * Every (table, column) pair that stores a politicians.id foreign key —
     * the union of MergeDuplicatePoliticians::$referencingColumns and
     * DedupeUnclaimedPoliticians::POLITICIAN_ID_TABLES, which had drifted to
     * cover slightly different sets (only this list's `voter_politician_notes`
     * entry was missing from the former). Keep in sync if a new
     * politician_id-referencing table is added.
     *
     * @var array<int, array{0: string, 1: string}>
     */
    public const POLITICIAN_ID_COLUMNS = [
        ['political_campaigns', 'politician_id'],
        ['campaign_transactions', 'politician_id'],
        ['politician_credits', 'politician_id'],
        ['payment_methods', 'politician_id'],
        ['politicians', 'referred_by_politician_id'],
        ['voters', 'referred_by_politician_id'],
        ['referral_earnings', 'referrer_politician_id'],
        ['referral_earnings', 'politician_id'],
        ['politician_pages', 'politician_id'],
        ['politician_initiatives', 'politician_id'],
        ['candidate_identity_links', 'politician_id'],
        ['candidate_match_reviews', 'politician_id'],
        ['politician_cleanup_reviews', 'politician_id'],
        ['politician_cleanup_reviews', 'duplicate_politician_id'],
        ['referral_visits', 'referrer_politician_id'],
        ['politician_office_profiles', 'politician_id'],
        ['candidate_news_articles', 'politician_id'],
        ['politician_donor_snapshots', 'politician_id'],
        ['politician_song_picks', 'politician_id'],
        ['citizens', 'referred_by_politician_id'],
        ['voter_favorite_politicians', 'politician_id'],
        ['politician_photo_quarantines', 'politician_id'],
        ['earlybank_earnings', 'politician_id'],
        ['viral_moment_enrichment_runs', 'politician_id'],
        ['politician_viral_moments', 'politician_id'],
        ['politician_endorsements', 'politician_id'],
        ['marketing_post_drafts', 'politician_id'],
        ['politician_topic_signals', 'politician_id'],
        ['voter_politician_notes', 'politician_id'],
    ];

    /**
     * Polymorphic relations that reference a Politician by (type, id) rather
     * than a plain foreign key — checked only for "does this row have
     * related data" (DedupeUnclaimedPoliticians' safety gate), never
     * reassigned automatically.
     *
     * @var array<int, array{0: string, 1: string, 2: string}>
     */
    private const MORPH_RELATIONS = [
        ['posts', 'author_type', 'author_id'],
        ['profile_badges', 'badgeable_type', 'badgeable_id'],
    ];

    /**
     * @return Collection<int, Collection<int, Politician>> groups with more than one member
     */
    public function findGroups(Builder $query, string $strategy): Collection
    {
        $rows = $query->get(['id', 'full_name', 'political_office', 'state', 'user_id', 'governance_level', 'verified_official', 'slug', 'updated_at']);

        $bucketKey = fn (Politician $p) => $strategy === self::STRATEGY_NAME_STATE
            ? strtoupper(trim((string) $p->state))
            : strtolower(trim((string) $p->political_office)).'|'.strtoupper(trim((string) $p->state));

        $groups = collect();

        foreach ($rows->groupBy($bucketKey) as $bucketKeyValue => $bucket) {
            if (trim((string) $bucketKeyValue, '|') === '') {
                continue;
            }

            foreach ($this->clusterByName($bucket) as $cluster) {
                if ($cluster->count() > 1) {
                    $groups->push($cluster->values());
                }
            }
        }

        return $groups->values();
    }

    /**
     * Groups politicians whose full_name is identical, or where one name is
     * the other with an extra word-boundary-anchored trailing fragment
     * leaked from a headline ("Eric Swalwell" / "Eric Swalwell Officially",
     * "Steve Hilton" / "Steve Hilton Dinner") — the RSS discovery pipeline's
     * NAME_STOPWORDS list can't catch every trailing artifact, so exact
     * full_name matching alone misses these as duplicates entirely.
     *
     * @param  Collection<int, Politician>  $rows  rows already narrowed to one office+state (or state) bucket
     * @return Collection<int, Collection<int, Politician>>
     */
    private function clusterByName(Collection $rows): Collection
    {
        $clusters = collect();

        foreach ($rows->sortBy(fn (Politician $p) => mb_strlen(trim((string) $p->full_name))) as $politician) {
            $name = strtolower(trim((string) $politician->full_name));
            if ($name === '') {
                continue;
            }

            $cluster = $clusters->first(fn (array $c) => $this->namesLikelySamePerson($c['canonical'], $name));

            if ($cluster !== null) {
                $cluster['rows']->push($politician);

                continue;
            }

            $clusters->push(['canonical' => $name, 'rows' => collect([$politician])]);
        }

        return $clusters->map(fn (array $c) => $c['rows']);
    }

    /**
     * $shorter is already lowercase/trimmed and no longer than $candidate.
     * They're the same person when identical, or when $candidate is
     * $shorter plus a trailing word-boundary-anchored fragment — not merely
     * a shared prefix ("eric swalwell" must not match "eric swalwellson").
     */
    private function namesLikelySamePerson(string $shorter, string $candidate): bool
    {
        if ($shorter === $candidate) {
            return true;
        }

        return str_starts_with($candidate, $shorter.' ');
    }

    /**
     * Choose which of two politicians in a duplicate group to keep, using
     * DedupeUnclaimedPoliticians' original preference order: currently
     * Senate-seat office > verified > more linked campaigns > federal
     * governance level > most recently updated > higher id.
     */
    public function scoreSurvivor(Politician $preferred, Politician $candidate): Politician
    {
        // A row whose full_name is itself an artifact (e.g. "Party" left
        // over from a "Democratic Party" placeholder row, or a title-only
        // fragment) must never be preferred over a row with a real name,
        // regardless of how it scores on every other axis below — otherwise
        // approving the merge review keeps the junk name and deletes the
        // good one.
        $preferredNameValid = PoliticianDataRules::nameViolation($preferred->full_name) === null;
        $candidateNameValid = PoliticianDataRules::nameViolation($candidate->full_name) === null;
        if ($candidateNameValid !== $preferredNameValid) {
            return $candidateNameValid ? $candidate : $preferred;
        }

        // Both names are technically valid on their own, but one may still
        // be a headline-fragment-mangled variant of the other (that's
        // exactly what clusterByName() groups together — "Eric Swalwell"
        // vs "Eric Swalwell Officially"). Prefer whichever one also passes
        // the stricter discovery-pipeline check, so the clean name survives
        // instead of losing on an unrelated tiebreak like recency/id.
        $preferredHeadlineClean = PoliticianDataRules::headlineFragmentViolation($preferred->full_name) === null;
        $candidateHeadlineClean = PoliticianDataRules::headlineFragmentViolation($candidate->full_name) === null;
        if ($candidateHeadlineClean !== $preferredHeadlineClean) {
            return $candidateHeadlineClean ? $candidate : $preferred;
        }

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

    /**
     * @param  Collection<int, Politician>  $group
     */
    public function pickSurvivor(Collection $group): Politician
    {
        return $group->reduce(
            fn (?Politician $preferred, Politician $candidate) => $preferred === null
                ? $candidate
                : $this->scoreSurvivor($preferred, $candidate),
        );
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

    /**
     * First table (simple FK or morph relation) with a row still pointing
     * at this politician, or null if it's a true orphan safe to delete.
     */
    public function firstTableWithRelatedRows(int $politicianId): ?string
    {
        foreach (self::POLITICIAN_ID_COLUMNS as [$table, $column]) {
            if (DB::table($table)->where($column, $politicianId)->exists()) {
                return $table;
            }
        }

        foreach (self::MORPH_RELATIONS as [$table, $typeColumn, $idColumn]) {
            if (DB::table($table)->where($typeColumn, Politician::class)->where($idColumn, $politicianId)->exists()) {
                return $table;
            }
        }

        return null;
    }

    /**
     * Reassign every politician_id-referencing row from $loserId to
     * $survivorId, then delete the loser. Anything left pointing at the
     * loser that isn't in POLITICIAN_ID_COLUMNS falls back to each table's
     * own cascadeOnDelete/nullOnDelete behavior as defined in its migration.
     */
    public function mergeInto(int $survivorId, int $loserId, bool $verbose = false, ?callable $onDetail = null): void
    {
        foreach (self::POLITICIAN_ID_COLUMNS as [$table, $column]) {
            $result = $this->reassignForeignKey($table, $column, $loserId, $survivorId);

            if ($verbose && $onDetail !== null && ($result['reassigned'] > 0 || $result['dropped'] > 0)) {
                $onDetail($table, $column, $result);
            }
        }

        Politician::query()->whereKey($loserId)->delete();
    }

    /**
     * @return array{reassigned: int, dropped: int}
     */
    public function reassignForeignKey(string $table, string $column, int $fromId, int $toId): array
    {
        try {
            $reassigned = DB::table($table)->where($column, $fromId)->update([$column => $toId]);

            return ['reassigned' => $reassigned, 'dropped' => 0];
        } catch (QueryException $e) {
            // A compound unique key that includes $column (e.g. one row per
            // politician+topic, politician+service+track, etc.) — the
            // survivor already has an equivalent row for at least one of
            // these, so the bulk update collided. Fall back to reassigning
            // row by row and drop only the specific rows that still collide.
        }

        $reassigned = 0;
        $dropped = 0;

        DB::table($table)->where($column, $fromId)->orderBy('id')->chunkById(100, function ($rows) use ($table, $column, $toId, &$reassigned, &$dropped): void {
            foreach ($rows as $row) {
                try {
                    DB::table($table)->where('id', $row->id)->update([$column => $toId]);
                    $reassigned++;
                } catch (QueryException $e) {
                    DB::table($table)->where('id', $row->id)->delete();
                    $dropped++;
                }
            }
        });

        return ['reassigned' => $reassigned, 'dropped' => $dropped];
    }
}
