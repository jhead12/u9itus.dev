<?php

namespace App\Services\Marketing;

use App\Models\CitizenCampaign;
use App\Models\PoliticalCampaign;
use App\Models\Voter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Resolves the set of voters a campaign should reach — the single targeting
 * engine every marketing channel consumes. Unifies the two existing campaign
 * shapes under one abstraction AND closes two stored-but-unapplied gaps:
 *
 *   - political_campaigns.target_districts and target_governance_levels were
 *     persisted since the table shipped but never enforced (VoterController
 *     only filtered on target_states). This service enforces all three.
 *   - citizen_campaigns.target_zip_radius was stored but never applied
 *     (CitizenViewService only did exact-zip match). This service applies it
 *     via ZipCentroidService.
 *
 * Returns a Voter query builder so callers can count(), chunk(), or paginate
 * without the service eagerly loading a potentially large audience.
 */
class AudienceService
{
    public function __construct(
        protected ZipCentroidService $zipCentroid,
    ) {
    }

    /**
     * @param  Model  $campaign  PoliticalCampaign|CitizenCampaign
     * @return Builder<Voter>
     */
    public function forCampaign(Model $campaign): Builder
    {
        $query = Voter::query()
            ->where('is_active', true)
            ->where('flagged_for_fraud', false);

        if ($campaign instanceof PoliticalCampaign) {
            $this->applyPoliticalTargeting($query, $campaign);
        } elseif ($campaign instanceof CitizenCampaign) {
            $this->applyCitizenTargeting($query, $campaign);
        } else {
            throw new \InvalidArgumentException(
                'AudienceService: unsupported campaign type ' . get_class($campaign),
            );
        }

        return $query;
    }

    /**
     * political_campaigns: target_states (exact), target_districts (normalized
     * district-code match against voters.congressional_district), and
     * target_governance_levels (voter preferred_governance_levels intersects).
     * Empty targeting arrays mean "no restriction" (national/any-level reach).
     */
    protected function applyPoliticalTargeting(Builder $query, PoliticalCampaign $campaign): void
    {
        $states = $campaign->target_states ?? [];
        $districts = $campaign->target_districts ?? [];
        $levels = $campaign->target_governance_levels ?? [];

        if (! empty($states)) {
            $query->where(function ($q) use ($states): void {
                $q->whereNull('state')->orWhereIn('state', $states);
            });
        }

        if (! empty($districts)) {
            // Build the set of equivalent code forms for every target so the
            // match is tolerant of how voters.congressional_district was
            // originally written ("CA-12", "ny-7", "NY-AL", "tx 01", …). The
            // voter side can't be PHP-normalized inside SQL, so we match
            // UPPER(congressional_district) against every plausible variant
            // of each target code.
            $variants = [];
            foreach ($districts as $d) {
                $variants = array_merge($variants, $this->districtCodeVariants((string) $d));
            }
            $variants = array_values(array_unique(array_filter($variants, fn ($v) => $v !== null && $v !== '')));

            if ($variants !== []) {
                // Voters with no district on file are excluded — we can't
                // confirm they live in the targeted district, and dispatching
                // to an unconfirmed district is the kind of thing that erodes
                // the platform's transparency posture.
                $query->whereNotNull('congressional_district');
                $query->whereRaw('UPPER(congressional_district) IN (' . implode(', ', array_fill(0, count($variants), '?')) . ')', $variants);
            }
        }

        if (! empty($levels)) {
            $query->where(function ($q) use ($levels): void {
                foreach ($levels as $level) {
                    $q->orWhereJsonContains('preferred_governance_levels', $level);
                }
            });
        }
    }

    /**
     * citizen_campaigns: target_zip (exact) plus target_zip_radius (haversine
     * via ZipCentroidService). Radius expands the audience to every voter whose
     * zip centroid is within N miles of the target_zip centroid.
     *
     * Exact-zip (no radius) preserves the existing "include null-zip voters for
     * reach" behavior; radius targeting is stricter (null-zip voters can't be
     * geo-verified, so they're excluded).
     */
    protected function applyCitizenTargeting(Builder $query, CitizenCampaign $campaign): void
    {
        $zip = trim((string) ($campaign->target_zip ?? ''));
        $radius = (int) ($campaign->target_zip_radius ?? 0);

        if ($zip === '') {
            return; // no geo constraint → all active voters
        }

        if ($radius <= 0) {
            // Exact-zip: match the voter's zip OR include null-zip voters for
            // reach (mirrors CitizenViewService's existing behavior).
            $query->where(function ($q) use ($zip): void {
                $q->where('zip_code', $zip)->orWhereNull('zip_code');
            });
            return;
        }

        $center = $this->zipCentroid->centroid($zip);
        if ($center === null) {
            // Can't geocode the target zip → degrade to exact-zip rather than
            // silently dropping the campaign. Logged so ops can see the fallback.
            Log::warning('AudienceService: zip centroid unavailable, falling back to exact-zip', [
                'campaign_id' => $campaign->id,
                'target_zip'  => $zip,
            ]);
            $query->where('zip_code', $zip);
            return;
        }

        // Resolve which voter zips fall within radius once (per-zip centroid is
        // cached forever), then filter voters by that zip set. This bounds the
        // geocode work to the number of *distinct* voter zips, not voter count.
        $matchingZips = $this->zipsWithinRadius($zip, $center, $radius);
        $query->whereNotNull('zip_code')->whereIn('zip_code', $matchingZips);
    }

    /**
     * @param array{lat: float, lng: float} $center
     * @return string[]
     */
    protected function zipsWithinRadius(string $targetZip, array $center, int $radiusMiles): array
    {
        $distinctZips = Voter::query()
            ->whereNotNull('zip_code')
            ->pluck('zip_code')
            ->unique()
            ->values()
            ->all();

        $matching = [];

        foreach ($distinctZips as $zip) {
            if ($zip === $targetZip) {
                $matching[] = $zip;
                continue;
            }
            $centroid = $this->zipCentroid->centroid($zip);
            if ($centroid === null) {
                continue;
            }
            if ($this->zipCentroid->distanceMiles($center, $centroid) <= $radiusMiles) {
                $matching[] = $zip;
            }
        }

        return $matching;
    }

    /**
     * Every plausible uppercased code form for a district target, so a voter's
     * stored congressional_district ("ny-7", "NY-07", "NY-AL", …) matches the
     * target regardless of original formatting. Returns e.g. ["CA-12"] for
     * "CA-12", ["NY-07","NY-7"] for "NY-07", ["TX-01","TX-1"] for "TX-1".
     *
     * @return string[]
     */
    public static function districtCodeVariants(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        // Normalize separators (space/dot → dash) and uppercase.
        $clean = strtoupper(preg_replace('/[\s.]+/', '-', $raw) ?? $raw);

        // Code form: STATE-NN or STATE-AL.
        if (preg_match('/^([A-Z]{2})-(\d{1,2}|AL)$/i', $clean, $m)) {
            $state = strtoupper($m[1]);
            $num = strtoupper($m[2]);
            if ($num === 'AL') {
                return ["{$state}-AL"];
            }
            $padded = sprintf('%s-%02d', $state, (int) $num);
            $bare = $state . '-' . (int) $num;
            return $padded === $bare ? [$padded] : [$padded, $bare];
        }

        // Not a parseable code (e.g. "District 12") — match literally.
        return [$clean];
    }
}