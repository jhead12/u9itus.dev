<?php

namespace App\Services;

use App\Models\DistrictLookupSearch;

class ZipVotingLocationService
{
    /** Return public location details only, never the searched home address. */
    public function forZip(string $input, ?array $freshVoterInfo = null): array
    {
        if (! preg_match('/^\d{5}(?:-\d{4})?$/', trim($input))) {
            return [];
        }
        $zip = substr(trim($input), 0, 5);
        $locations = [];
        if ($freshVoterInfo) {
            $this->collectLocations($locations, $freshVoterInfo, now()->toDateString(), 'Google Civic');
        }

        // Freshness is tied to the original saved lookup, not today's ZIP
        // browsing request. Do not persist these aggregated results as fresh data.
        $searches = DistrictLookupSearch::query()
            ->where('created_at', '>=', now()->subDays(7))
            ->whereNotNull('payload->voter_info')
            ->where(function ($query) use ($zip) {
                $query->where('payload->voter_info->normalized_input->zip', $zip)
                    ->orWhere('payload->voter_info->normalized_input->zip', 'like', $zip.'-____');
            })
            ->orderByDesc('created_at')->orderByDesc('id')
            ->get(['payload', 'created_at']);

        foreach ($searches as $search) {
            $info = data_get($search->payload, 'voter_info');
            if (is_array($info) && ($info['source'] ?? null) === 'google_civic_voterinfo') {
                $this->collectLocations($locations, $info, $search->created_at->toDateString(), 'U9itus records · Google Civic');
            }
        }

        return array_values($locations);
    }

    private function collectLocations(array &$locations, array $info, string $checked, string $source): void
    {
        $election = (array) ($info['election'] ?? []);
        $day = $election['election_day'] ?? null;
        if (! $this->validDate($day) || $day < today()->toDateString()) {
            return;
        }

        foreach (['polling_locations' => 'Nearby polling location', 'early_vote_sites' => 'Early voting', 'drop_off_locations' => 'Ballot drop-off'] as $key => $label) {
            foreach ((array) ($info[$key] ?? []) as $location) {
                if (! is_array($location)) {
                    continue;
                }
                $end = $location['end_date'] ?? null;
                if ($end && (! $this->validDate($end) || $end < today()->toDateString())) {
                    continue;
                }
                $address = (array) ($location['address'] ?? []);
                $addressLine = collect(['line1', 'line2', 'line3', 'city', 'state', 'zip'])
                    ->map(fn ($field) => is_string($address[$field] ?? null) ? trim($address[$field]) : '')
                    ->filter()->implode(', ');
                if (empty($address['line1']) || $addressLine === '') {
                    continue;
                }
                $identity = $key.'|'.($election['id'] ?? $election['name'] ?? '').'|'.$day.'|'.strtolower($addressLine);
                $locations[$identity] ??= [
                    'label' => $label,
                    'name' => $location['name'] ?? $address['location_name'] ?? 'Voting location',
                    'address' => $addressLine,
                    'hours' => $location['polling_hours'] ?? null,
                    'start_date' => $this->validDate($location['start_date'] ?? null) ? $location['start_date'] : null,
                    'end_date' => $end,
                    'election_name' => $election['name'] ?? 'Election',
                    'election_day' => $day,
                    'checked' => $checked,
                    'source' => $source,
                ];
            }
        }
    }

    private function validDate(mixed $date): bool
    {
        if (! is_string($date) || ! preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts)) {
            return false;
        }

        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
    }
}
