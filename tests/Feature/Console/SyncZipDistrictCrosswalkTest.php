<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

function zipCrosswalkRow(string $geoid, string $zip, int $land): string
{
    return "1|{$geoid}|Congressional District|1|1|G5200|N|2|{$zip}|ZCTA5 {$zip}|1|1|G6350|B5|S|{$land}|0\n";
}

function zipCrosswalkFile(string $extraRows = '', int $fillerZips = 30100): string
{
    $body = "\xEF\xBB\xBFOID_CD119_20|GEOID_CD119_20|NAMELSAD_CD119_20|AREALAND_CD119_20|AREAWATER_CD119_20|MTFCC_CD119_20|FUNCSTAT_CD119_20|OID_ZCTA5_20|GEOID_ZCTA5_20|NAMELSAD_ZCTA5_20|AREALAND_ZCTA5_20|AREAWATER_ZCTA5_20|MTFCC_ZCTA5_20|CLASSFP_ZCTA5_20|FUNCSTAT_ZCTA5_20|AREALAND_PART|AREAWATER_PART\n";
    for ($i = 0; $i < $fillerZips; $i++) {
        $body .= zipCrosswalkRow('4801', sprintf('%05d', 10000 + $i), 10);
    }

    return $body.$extraRows;
}

it('loads ZIP to district pairs, skipping totals, undefined districts, territories and water-only parts', function () {
    Http::fake(['www2.census.gov/*' => Http::response(zipCrosswalkFile(
        zipCrosswalkRow('3903', '43215', 500)
        .zipCrosswalkRow('3915', '43215', 40)
        .zipCrosswalkRow('5600', '82001', 900)   // at-large state
        .zipCrosswalkRow('1198', '05001', 300)   // DC delegate seat
        .zipCrosswalkRow('09ZZ', '06001', 100)   // districts not defined
        .zipCrosswalkRow('7298', '00601', 100)   // Puerto Rico: no map layer
        .zipCrosswalkRow('3903', '43299', 0)     // water only
        ."1|3903|Congressional District 3|1|1|G5200|N|||||||||500|0\n", // district total row
    ), 200)]);
    DB::table('zip_district_crosswalk')->insert(['zip' => '99999', 'state' => 'AK', 'district_number' => 'AL', 'land_area' => 1, 'congress' => 119]);

    $this->artisan('geo:sync-zip-districts')->assertSuccessful();

    $pairs = fn (string $zip) => DB::table('zip_district_crosswalk')->where('zip', $zip)->orderBy('district_number')
        ->get()->map(fn ($r) => "{$r->state}-{$r->district_number}")->all();
    expect($pairs('43215'))->toBe(['OH-15', 'OH-3'])
        ->and($pairs('82001'))->toBe(['WY-AL'])
        ->and($pairs('05001'))->toBe(['DC-AL'])
        ->and($pairs('10000'))->toBe(['TX-1'])
        ->and($pairs('06001'))->toBe([])
        ->and($pairs('00601'))->toBe([])
        ->and($pairs('43299'))->toBe([])
        ->and($pairs('99999'))->toBe([]); // replaced, not appended
});

it('leaves the existing crosswalk alone when the file is too small or the download fails', function () {
    DB::table('zip_district_crosswalk')->insert(['zip' => '99999', 'state' => 'AK', 'district_number' => 'AL', 'land_area' => 1, 'congress' => 119]);

    Http::fake(['www2.census.gov/*' => Http::response(zipCrosswalkFile('', 5), 200)]);
    $this->artisan('geo:sync-zip-districts')->assertFailed();

    Http::fake(['www2.census.gov/*' => Http::response('', 500)]);
    $this->artisan('geo:sync-zip-districts')->assertFailed();

    expect(DB::table('zip_district_crosswalk')->count())->toBe(1);
});
