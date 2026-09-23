<?php

namespace App\Services;

use App\Models\BallotMeasure;
use App\Models\Organization;
use App\Models\Politician;
use App\Support\Concerns\ResolvesDistrictVariants;

/**
 * Resolves the candidates/ballot-measures/endorsements a white-label portal
 * shows, scoped to the organization's target_state/target_district. Shared
 * by PortalBuilderController (live preview while editing) and
 * PortalController (public render) so both always see the same data shape.
 *
 * Candidate/measure blocks intentionally reference this org-level scope
 * rather than literal picked-by-id records, so a portal stays correct as
 * candidates/measures change — see the branch plan's "not manually picked" note.
 */
class PortalDataService
{
    use ResolvesDistrictVariants;

    /**
     * @return array{candidates: \Illuminate\Support\Collection, ballotMeasures: \Illuminate\Support\Collection, endorsements: \Illuminate\Support\Collection}
     */
    public function forOrganization(Organization $organization): array
    {
        return [
            'candidates' => $this->candidates($organization),
            'ballotMeasures' => $this->ballotMeasures($organization),
            'endorsements' => $this->endorsements($organization),
        ];
    }

    protected function candidates(Organization $organization)
    {
        if (! $organization->target_state) {
            return collect();
        }

        $state = strtoupper($organization->target_state);

        $query = Politician::query()
            ->publiclyVisible()
            ->where('state', $state)
            ->select(['id', 'uuid', 'full_name', 'political_office', 'party_affiliation', 'slug', 'profile_photo_url', 'district', 'state']);

        if ($organization->target_district) {
            $variants = $this->districtVariants($state, $organization->target_district);
            $query->where(function ($q) use ($variants) {
                foreach ($variants as $variant) {
                    if (preg_match('/^\d+$/', $variant)) {
                        $q->orWhere('district', '=', $variant);
                    } else {
                        $q->orWhere('district', 'like', '%'.$variant.'%');
                    }
                }
            });
        }

        return $query->orderBy('full_name')->limit(50)->get();
    }

    protected function ballotMeasures(Organization $organization)
    {
        if (! $organization->target_state) {
            return collect();
        }

        return BallotMeasure::query()
            ->where('state', strtoupper($organization->target_state))
            ->orderBy('election_date')
            ->limit(50)
            ->get();
    }

    protected function endorsements(Organization $organization)
    {
        return $organization->endorsements()
            ->published()
            ->with(['politician:id,full_name,slug,profile_photo_url', 'ballotMeasure:id,title,measure_number'])
            ->latest()
            ->get();
    }
}
