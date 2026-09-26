<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

function censusRelFile(string $layer, array $rows): string
{
    $body = "\xEF\xBB\xBFOID_CD119_20|GEOID_CD119_20|NAMELSAD_CD119_20|AREALAND_CD119_20|AREAWATER_CD119_20|MTFCC_CD119_20|FUNCSTAT_CD119_20|OID_{$layer}_20|GEOID_{$layer}_20|NAMELSAD_{$layer}_20|AREALAND_{$layer}_20|AREAWATER_{$layer}_20|MTFCC_{$layer}_20|CLASSFP_{$layer}_20|FUNCSTAT_{$layer}_20|AREALAND_PART|AREAWATER_PART\n";
    foreach ($rows as [$geoid, $name, $land]) {
        $body .= "1|{$geoid}|Congressional District|1|1|G5200|N|2|0000000|{$name}|1|1|G4110|C1|A|{$land}|0\n";
    }

    return $body;
}

function fakeAreaCodeSources(array $phoneLines, array $places, array $cousubs, int $filler = 8100): void
{
    $phone = "# Copyright (C) 2011 The Libphonenumber Authors\n";
    for ($i = 0; $i < $filler; $i++) {
        $phone .= "1936{$i}|Filler{$i}, TX\n";
        $places[] = ['4801', "Filler{$i} city", 10];
    }
    Http::fake([
        'raw.githubusercontent.com/*' => Http::response($phone.implode("\n", $phoneLines)."\n", 200),
        'www2.census.gov/*place20*' => Http::response(censusRelFile('PLACE', $places), 200),
        'www2.census.gov/*cousub20*' => Http::response(censusRelFile('COUSUB', $cousubs), 200),
    ]);
}

it('matches phone cities to Census places and county subdivisions', function () {
    fakeAreaCodeSources([
        '1760324|Palm Desert, CA',
        '1760329|Desert Hot Spgs, CA',      // abbreviation
        '1760480|Escondido, CA',
        '1718230|Brooklyn, NY',             // county subdivision, not a place
        '1213|Los Angeles, CA',
        '1310200|California',               // state only: no city
        '1416200|Toronto, ON',              // Canada
        '1760999|Nowhereville, CA',         // unmatched small town
    ], [
        ['0625', 'Palm Desert city', 500],
        ['0625', 'Desert Hot Springs city', 400],
        ['0648', 'Escondido city', 600],
        ['0650', 'Escondido city', 300],
        ['0634', 'Los Angeles city', 900000],
        ['0628', 'Los Angeles city', 5],     // boundary sliver
        ['0625', '', 1000],                  // district total row
    ], [
        ['3607', 'Brooklyn borough', 200],
        ['3608', 'Brooklyn borough', 300],
    ]);
    DB::table('area_code_districts')->insert(['area_code' => '999', 'state' => 'AK', 'city' => 'Old', 'district_number' => 'AL', 'land_area' => 1, 'congress' => 119]);

    $this->artisan('geo:sync-area-code-districts')->assertSuccessful();

    $districts = fn (string $areaCode, string $city) => DB::table('area_code_districts')
        ->where('area_code', $areaCode)->where('city', $city)->orderBy('district_number')
        ->get()->map(fn ($r) => "{$r->state}-{$r->district_number}")->all();
    expect($districts('760', 'Palm Desert'))->toBe(['CA-25'])
        ->and($districts('760', 'Desert Hot Springs'))->toBe(['CA-25'])
        ->and($districts('760', 'Escondido'))->toBe(['CA-48', 'CA-50'])
        ->and($districts('718', 'Brooklyn'))->toBe(['NY-7', 'NY-8'])
        ->and($districts('213', 'Los Angeles'))->toBe(['CA-34'])
        ->and(DB::table('area_code_districts')->whereIn('area_code', ['310', '416', '999'])->count())->toBe(0)
        ->and(DB::table('area_code_districts')->where('city', 'Nowhereville')->count())->toBe(0);
});

it('leaves the existing table alone when the build is too small or a download fails', function () {
    DB::table('area_code_districts')->insert(['area_code' => '999', 'state' => 'AK', 'city' => 'Old', 'district_number' => 'AL', 'land_area' => 1, 'congress' => 119]);

    fakeAreaCodeSources([], [], [], 5);
    $this->artisan('geo:sync-area-code-districts')->assertFailed();

    Http::fake(['raw.githubusercontent.com/*' => Http::response('', 500), '*' => Http::response('', 200)]);
    $this->artisan('geo:sync-area-code-districts')->assertFailed();

    expect(DB::table('area_code_districts')->count())->toBe(1);
});
