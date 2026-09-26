<?php

use App\Models\DistrictLookupSearch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->withoutVite();
    Http::preventStrayRequests();
});

function areaCodeRow(string $areaCode, string $city, string $district, string $state = 'CA'): array
{
    return ['area_code' => $areaCode, 'state' => $state, 'city' => $city, 'district_number' => $district, 'land_area' => 1, 'congress' => 119];
}

it('lists every city for an area code with its districts and the small-town notice', function () {
    DB::table('area_code_districts')->insert([
        areaCodeRow('760', 'Escondido', '50'),
        areaCodeRow('760', 'Escondido', '48'),
        areaCodeRow('760', 'Carlsbad', '49'),
        areaCodeRow('916', 'Sacramento', '7'),
    ]);

    $response = $this->get('/district-lookup?address='.urlencode('(760)'));

    $response->assertOk()->assertSee('Cities in area code 760')
        ->assertSeeInOrder(['Carlsbad, CA', 'CA-49', 'Escondido, CA', 'Spans 2 districts', 'CA-48', 'CA-50'])
        ->assertSee("Don't see your town?", false)
        ->assertDontSee('Sacramento, CA')
        ->assertDontSee('could not load districts');
    expect($response->viewData('areaCodeCities'))->toHaveCount(2)
        ->and(DistrictLookupSearch::latest('id')->first()->source)->toBe('area_code');
    Http::assertNothingSent();
});

it('points to ZIP or address search when an area code has no city listings', function () {
    $this->get('/district-lookup?address=999')->assertOk()
        ->assertSee('have city listings for area code 999 yet', false)
        ->assertDontSee('Cities in area code');
    Http::assertNothingSent();
});

it('does not treat invalid area codes or ZIPs as area codes', function () {
    DB::table('area_code_districts')->insert(areaCodeRow('213', 'Los Angeles', '34'));
    config(['services.google.civic_api_key' => null]);

    // Area codes never start with 0 or 1.
    $this->get('/district-lookup?address=113')->assertOk()->assertDontSee('Cities in area code');
    $this->get('/district-lookup?address=21301')->assertOk()->assertDontSee('Cities in area code');
});
