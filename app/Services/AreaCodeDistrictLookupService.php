<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class AreaCodeDistrictLookupService extends DistrictLookupService
{
    /**
     * Cities an area code serves, each with the congressional district(s) it
     * falls in, so the voter can pick their city. Backed by
     * area_code_districts (geo:sync-area-code-districts).
     *
     * @return list<array{city:string, state:string, districts:list<array<string,string>>}>
     */
    public function citiesForAreaCode(string $input): array
    {
        $areaCode = self::normalizeAreaCode($input);
        if ($areaCode === null) {
            return [];
        }

        $rows = DB::table('area_code_districts')->where('area_code', $areaCode)
            ->orderBy('city')->orderBy('state')->get(['state', 'city', 'district_number']);

        $cities = [];
        foreach ($rows as $row) {
            $key = "{$row->city}|{$row->state}";
            $cities[$key] ??= ['city' => $row->city, 'state' => $row->state, 'districts' => []];
            $cities[$key]['districts'][] = [
                'state' => $row->state, 'district_number' => $row->district_number,
                'district_code' => $this->buildDistrictCode($row->state, $row->district_number),
                'district_label' => $this->buildDistrictLabel($row->state, $row->district_number),
            ];
        }

        foreach ($cities as &$city) {
            usort($city['districts'], fn ($a, $b) => (int) $a['district_number'] <=> (int) $b['district_number']);
        }

        return array_values($cities);
    }

    /** "213", "(213)" or "213-" → "213"; anything else → null. */
    public static function normalizeAreaCode(string $input): ?string
    {
        return preg_match('/^\(?([2-9]\d{2})\)?-?$/', trim($input), $m) === 1 ? $m[1] : null;
    }
}
