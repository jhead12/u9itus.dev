<?php

namespace App\Services;

use App\Models\Politician;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Matches Politician records to a resolved district by testing the
 * free-text `district` column against the same variant strings
 * PublicProfileController::districtLookup() matches against (there is no
 * structured district_code column on politicians to compare directly).
 */
class DistrictCandidateMatcher
{
    /**
     * @param array<string, mixed> $lookupResult Result of DistrictLookupService::lookup()
     */
    public function findCandidates(array $lookupResult, int $limit = 50): Collection
    {
        $state = strtoupper((string) ($lookupResult['state'] ?? ''));
        if ($state === '') {
            return new Collection;
        }

        [$exact, $like] = $this->districtHints($lookupResult, $state);

        if (empty($exact) && empty($like)) {
            return new Collection;
        }

        return Politician::query()
            ->where('is_active', true)
            ->whereRaw('UPPER(state) = ?', [$state])
            ->where(function (Builder $query) use ($exact, $like) {
                if (! empty($exact)) {
                    $query->orWhereIn('district', $exact);
                }
                foreach ($like as $hint) {
                    $query->orWhere('district', 'like', '%'.$hint.'%');
                }
            })
            ->orderBy('full_name')
            ->limit($limit)
            ->get(['id', 'full_name', 'state', 'political_office', 'party_affiliation', 'district']);
    }

    /**
     * @param array<string, mixed> $lookupResult
     * @return array{0: array<string>, 1: array<string>} [exactMatches, likeMatches]
     */
    protected function districtHints(array $lookupResult, string $state): array
    {
        $exact = [];
        $like = [];

        [$congressExact, $congressLike] = $this->congressionalVariants($state, $lookupResult['district_number'] ?? null);
        $exact = array_merge($exact, $congressExact);
        $like = array_merge($like, $congressLike);

        $like = array_merge($like, $this->chamberHints($state, $lookupResult['sldl_district'] ?? null, 'AD', 'Assembly District', 'State Assembly District'));
        $like = array_merge($like, $this->chamberHints($state, $lookupResult['sldu_district'] ?? null, 'SD', 'Senate District', 'State Senate District'));

        return [$exact, $like];
    }

    /**
     * @return array{0: array<string>, 1: array<string>} [exactMatches, likeMatches]
     */
    protected function congressionalVariants(string $state, ?string $districtNumber): array
    {
        if ($districtNumber === null || $districtNumber === '') {
            return [[], []];
        }

        if (strtoupper($districtNumber) === 'AL') {
            return [[], ['At-Large', 'At Large', $state.'-AL']];
        }

        $num = (string) ((int) $districtNumber);
        $padded = str_pad($num, 2, '0', STR_PAD_LEFT);

        return [
            [$num, $padded],
            ['District '.$num, 'CD '.$num, 'CD-'.$num, $state.'-'.$num, $state.'-'.$padded],
        ];
    }

    /**
     * @return array<string>
     */
    protected function chamberHints(string $state, ?string $districtNumber, string $prefix, string $label, string $stateLabel): array
    {
        if ($districtNumber === null || $districtNumber === '') {
            return [];
        }

        $num = (string) ((int) $districtNumber);
        $padded = str_pad($num, 2, '0', STR_PAD_LEFT);

        return [
            $prefix.'-'.$num, $prefix.'-'.$padded,
            $label.' '.$num, $stateLabel.' '.$num,
            $state.'-'.$prefix.'-'.$num, $state.'-'.$prefix.'-'.$padded,
        ];
    }
}
