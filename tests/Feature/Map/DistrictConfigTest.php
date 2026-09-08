<?php

use App\Models\Politician;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/*
|--------------------------------------------------------------------------
| District config — TIGERweb layer resolution
|--------------------------------------------------------------------------
|
| The 3D map resolves congressional-district boundaries from the Census
| TIGERweb "Legislative/MapServer" service. The Census rolls that service
| forward for each new Congress, which SHIFTS every layer index — when the
| 120th was published, the 119th moved from layer 0 to layer 4. These tests
| pin the layer/field contract so a stale index fails loudly here instead of
| silently rendering an empty map.
|
*/

beforeEach(function () {
    Cache::forget('district_config');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('returns the 119th-Congress fallback when no config row exists', function () {
    DB::table('district_config')->truncate();

    $this->getJson('/api/v1/map/district-config')
        ->assertOk()
        ->assertJson([
            'congress_number' => 119,
            'tigerweb_layer' => 4, // layer 0 is the 120th; the 119th lives at layer 4
            'cd_field' => 'CD119',
        ]);
});

it('syncs the 119th Congress to TIGERweb layer 4', function () {
    Carbon::setTestNow('2026-09-08');
    DB::table('district_config')->truncate();

    $this->artisan('geo:sync-district-config')->assertSuccessful();

    $row = DB::table('district_config')->latest('synced_at')->first();
    expect($row->congress_number)->toBe(119)
        ->and((int) $row->tigerweb_layer)->toBe(4)
        ->and($row->cd_field)->toBe('CD119');
});

it('syncs the 120th Congress to TIGERweb layer 0 once it is seated', function () {
    Carbon::setTestNow('2027-06-01');
    DB::table('district_config')->truncate();

    $this->artisan('geo:sync-district-config')->assertSuccessful();

    $row = DB::table('district_config')->latest('synced_at')->first();
    expect($row->congress_number)->toBe(120)
        ->and((int) $row->tigerweb_layer)->toBe(0)
        ->and($row->cd_field)->toBe('CD120');
});

it('exposes the synced layer through the map endpoint', function () {
    Carbon::setTestNow('2026-09-08');
    DB::table('district_config')->truncate();
    $this->artisan('geo:sync-district-config')->assertSuccessful();

    $this->getJson('/api/v1/map/district-config')
        ->assertOk()
        ->assertJson(['tigerweb_layer' => 4, 'cd_field' => 'CD119']);
});

it('builds the district → party map from seated federal House members', function () {
    Carbon::setTestNow('2026-09-08');
    DB::table('district_config')->truncate();

    Politician::factory()->create([
        'governance_level' => 'federal',
        'political_office' => 'U.S. House Representative',
        'term_status' => 'seated',
        'state' => 'UT',
        'district' => 'UT-1',
        'party_affiliation' => 'Republican',
    ]);

    $this->artisan('geo:sync-district-config')->assertSuccessful();

    $partyMap = json_decode(DB::table('district_config')->latest('synced_at')->first()->party_map, true);
    expect($partyMap)->toHaveKey('UT-1', 'R');
});

/*
| Live contract check — hits the real Census service, so it is opt-in.
| Run with:  TIGERWEB_LIVE_TESTS=1 php artisan test --filter=live_tigerweb
*/
it('live_tigerweb layer actually serves 119th-Congress districts', function () {
    $layer = 4;
    $meta = Http::acceptJson()
        ->get("https://tigerweb.geo.census.gov/arcgis/rest/services/TIGERweb/Legislative/MapServer/{$layer}", ['f' => 'json'])
        ->json();

    expect($meta['name'] ?? '')->toContain('119th')
        ->and(collect($meta['fields'] ?? [])->pluck('name'))->toContain('CD119');

    $features = Http::acceptJson()
        ->get("https://tigerweb.geo.census.gov/arcgis/rest/services/TIGERweb/Legislative/MapServer/{$layer}/query", [
            'where' => "STATE='49'", // Utah
            'outFields' => 'STATE,CD119,NAME,GEOID',
            'returnGeometry' => 'false',
            'f' => 'json',
        ])
        ->json();

    expect($features['features'] ?? [])->toHaveCount(4); // Utah has 4 districts
})->skip(fn () => ! env('TIGERWEB_LIVE_TESTS'), 'Set TIGERWEB_LIVE_TESTS=1 to run the live Census contract check.');
