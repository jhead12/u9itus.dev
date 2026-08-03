<?php

namespace App\Services;

use App\Models\Voter;
use App\Models\VoterFavoriteBoundary;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Rule;

/**
 * Merges guest-saved map boundaries into a real voter's
 * voter_favorite_boundaries rows — used both when a cookie-only guest logs
 * in (MergeGuestFavoriteBoundaries middleware) and when a pending
 * (user_id-null) digest-opt-in Voter gets reparented onto a real account.
 */
class GuestBoundaryMergeService
{
    /**
     * Merge raw cookie items (as read by GuestBoundaryCookie::readItems())
     * into a voter's saved boundaries. Invalid items are silently skipped —
     * this runs implicitly during login, so it must never fail the request.
     *
     * @param array<int, array<string, mixed>> $items
     */
    public function mergeCookieItemsIntoVoter(array $items, Voter $voter): void
    {
        foreach ($items as $item) {
            $data = $this->validateItem($item);

            if ($data === null) {
                continue;
            }

            $this->firstOrCreateForVoter($voter, $data);
        }
    }

    /**
     * Move every saved boundary from a pending (user_id-null) guest Voter
     * onto a real voter, then delete the now-empty pending row. Idempotent —
     * safe to call even if the real voter already has overlapping saves.
     */
    public function reparentPendingVoter(Voter $pending, Voter $real): void
    {
        if ($pending->is($real) || $pending->user_id !== null) {
            return;
        }

        foreach ($pending->favoriteBoundaries()->get() as $boundary) {
            $this->firstOrCreateForVoter($real, [
                'type' => $boundary->boundary_type,
                'state_abbr' => $boundary->state_abbr,
                'district_number' => $boundary->district_number,
                'city_name' => $boundary->city_name,
                'label' => $boundary->label,
                'lat' => $boundary->lat,
                'lng' => $boundary->lng,
            ]);
        }

        if ($pending->digest_opt_in_pending && $pending->digest_confirmed_at !== null) {
            $user = $real->user;

            if ($user) {
                $user->notificationPreference()->firstOrCreate([])->update([
                    'email_boundary_digest' => true,
                ]);
            }
        }

        $pending->delete();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function validateItem(array $item): ?array
    {
        $validator = ValidatorFacade::make($item, [
            'type' => ['required', Rule::in([VoterFavoriteBoundary::TYPE_DISTRICT, VoterFavoriteBoundary::TYPE_CITY])],
            'state_abbr' => ['required', 'string', 'size:2'],
            'district_number' => ['nullable', 'string', 'max:4', 'required_if:type,district'],
            'city_name' => ['nullable', 'string', 'max:120', 'required_if:type,city'],
            'label' => ['required', 'string', 'max:160'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        if ($validator->fails()) {
            return null;
        }

        return $validator->validated();
    }

    private function firstOrCreateForVoter(Voter $voter, array $data): VoterFavoriteBoundary
    {
        $isCity = $data['type'] === VoterFavoriteBoundary::TYPE_CITY;

        return $voter->favoriteBoundaries()->firstOrCreate(
            [
                'boundary_type' => $data['type'],
                'state_abbr' => strtoupper($data['state_abbr']),
                'district_number' => $isCity ? null : ($data['district_number'] ?? null),
                'city_name' => $isCity ? ($data['city_name'] ?? null) : null,
            ],
            [
                'label' => $data['label'],
                'lat' => $isCity ? ($data['lat'] ?? null) : null,
                'lng' => $isCity ? ($data['lng'] ?? null) : null,
            ]
        );
    }
}
