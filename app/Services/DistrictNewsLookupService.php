<?php

namespace App\Services;

use App\Models\DistrictNewsArticle;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Resolves a short list of recent, verified local news items for a user's
 * home district/city — used to preview "what's happening locally" in the
 * post-signup welcome email. Returns an empty collection whenever a user's
 * location isn't known yet (e.g. a politician's district hasn't been
 * matched to election data yet) rather than guessing.
 */
class DistrictNewsLookupService
{
    public function forUser(User $user, int $limit = 3): Collection
    {
        $user->loadMissing('politician', 'voter', 'citizen');

        $districtCode = $user->politician?->district ?: $user->voter?->congressional_district;

        if ($districtCode) {
            $articles = DistrictNewsArticle::query()
                ->verified()
                ->forDistrict($districtCode)
                ->recent($limit)
                ->get();

            if ($articles->isNotEmpty()) {
                return $articles;
            }
        }

        // No district match (or none known yet) — fall back to matching on
        // city/state for whichever profile carries them (citizens always
        // have city+state; politicians/voters may before their district is
        // resolved).
        $city = $user->citizen?->city ?? $user->city;
        $state = $user->citizen?->state ?? $user->state;

        if (! $city || ! $state) {
            return collect();
        }

        return DistrictNewsArticle::query()
            ->verified()
            ->where('state', $state)
            ->where('matched_locality', $city)
            ->recent($limit)
            ->get();
    }
}
