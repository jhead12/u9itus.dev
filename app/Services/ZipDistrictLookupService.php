<?php

namespace App\Services;

use App\Models\DistrictLookupSearch;
use App\Models\Politician;
use App\Models\ProfileAddress;
use Illuminate\Support\Facades\DB;

class ZipDistrictLookupService extends DistrictLookupService
{
    public function districtsForZip(string $input): array
    {
        if (! preg_match('/^\d{5}(?:-\d{4})?$/', trim($input))) {
            return [];
        }
        $zip = substr(trim($input), 0, 5);

        // The Census ZIP crosswalk is a complete answer, so it wins over the
        // partial sources below.
        $crosswalk = $this->crosswalkDistricts($zip);
        if ($crosswalk !== []) {
            return $crosswalk;
        }

        $districts = [];

        // Reuse recent resolved lookups without exposing the underlying home
        // addresses. These are known matches, not a complete ZIP crosswalk.
        $searches = DistrictLookupSearch::query()->where('resolved', true)
            ->whereIn('source', ['census_geocoder', 'google_civic'])
            ->where('created_at', '>=', now()->subDays(90))
            ->where(function ($query) use ($zip) {
                $query->where('query_address', $zip)
                    ->orWhere('query_address', 'like', '%'.$zip.'%')
                    ->orWhere('matched_address', 'like', '%'.$zip.'%');
            })->get(['query_address', 'matched_address', 'state', 'district_number', 'district_code', 'payload']);

        foreach ($searches as $search) {
            // Match a trailing ZIP (or ZIP+4), never a street number or a
            // substring of another ZIP. A matched address takes precedence.
            $address = trim($search->matched_address ?: $search->query_address);
            if (! preg_match('/(?:^|[\s,])'.preg_quote($zip, '/').'(?:-\d{4})?$/', $address)) {
                continue;
            }
            $this->addDistrict($districts, $search->state, $search->district_number ?? $search->district_code);
            foreach (data_get($search->payload, 'lookup_result.districts', []) as $district) {
                $this->addDistrict($districts, $district['state'] ?? null, $district['district_number'] ?? null);
            }
        }

        // District office addresses are public records. Do not infer a district
        // from voter accounts, campaign mailing addresses, or a DC office ZIP.
        $offices = ProfileAddress::query()->where('profilable_type', (new Politician)->getMorphClass())
            ->where('address_kind', 'district')->where('is_verified', true)
            ->where(fn ($query) => $query->where('postal_code', $zip)->orWhere('postal_code', 'like', $zip.'-____'))
            ->with('profilable')->get();
        foreach ($offices as $office) {
            $politician = $office->profilable;
            if (! $politician instanceof Politician || ! $politician->is_active || ! $politician->page_published
                || strtolower((string) $politician->governance_level) !== 'federal'
                || ! preg_match('/representative|u\.s\. house/i', (string) $politician->political_office)
                || strtoupper((string) $office->state) !== strtoupper((string) $politician->state)) {
                continue;
            }
            $this->addDistrict($districts, $politician->state, $politician->district);
        }

        if ($districts !== []) {
            ksort($districts);

            return array_values($districts);
        }

        return array_map(fn ($district) => $district + ['source' => 'google_civic'],
            app(GoogleCivicService::class)->districtsForZip($zip));
    }

    /** @return array<int, array<string, string>> */
    private function crosswalkDistricts(string $zip): array
    {
        $rows = DB::table('zip_district_crosswalk')->where('zip', $zip)->get(['state', 'district_number']);

        $districts = [];
        foreach ($rows as $row) {
            $code = $this->buildDistrictCode($row->state, $row->district_number);
            $districts[$code] = [
                'state' => $row->state, 'district_number' => $row->district_number, 'district_code' => $code,
                'district_label' => $this->buildDistrictLabel($row->state, $row->district_number), 'source' => 'census_zcta',
            ];
        }
        ksort($districts);

        return array_values($districts);
    }

    private function addDistrict(array &$districts, ?string $state, ?string $raw): void
    {
        $state = strtoupper(trim((string) $state));
        if (! is_string(config('u9itus.us_states.'.$state))) {
            return;
        }
        $raw = strtoupper(trim((string) $raw));
        if (! preg_match('/^(?:'.preg_quote($state, '/').'[- ]|DISTRICT\s+)?(\d{1,2}|AL|AT[- ]LARGE)$/', $raw, $match)) {
            return;
        }
        $number = is_numeric($match[1]) && (int) $match[1] !== 0 ? (string) (int) $match[1] : 'AL';
        $code = $this->buildDistrictCode($state, $number);
        $districts[$code] = [
            'state' => $state, 'district_number' => $number, 'district_code' => $code,
            'district_label' => $this->buildDistrictLabel($state, $number), 'source' => 'u9itus_records',
        ];
    }
}
