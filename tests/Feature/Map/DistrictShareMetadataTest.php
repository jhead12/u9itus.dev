<?php

use App\Models\Politician;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->withoutVite();
});

function districtShareLeader(array $attributes = []): Politician
{
    return Politician::factory()->create(array_merge([
        'full_name' => 'District Representative',
        'state' => 'CA',
        'district' => 'CA-43',
        'governance_level' => 'federal',
        'political_office' => 'U.S. Representative',
        'term_status' => 'seated',
        'term_ends_on' => null,
        'is_running_candidate' => false,
        'is_active' => true,
        'page_published' => true,
        'profile_photo_url' => 'https://example.com/representative.jpg',
    ], $attributes));
}

it('serves district and current leader metadata in the initial HTML with a stable canonical URL', function () {
    districtShareLeader();
    $challenger = districtShareLeader(['full_name' => 'District Challenger', 'term_status' => 'active', 'is_running_candidate' => true]);
    DB::table('district_populations')->insert([
        'state' => 'CA', 'district_number' => 43, 'total_population' => 750123,
        'census_year' => 2024, 'census_product' => 'acs/acs5',
    ]);

    $this->get('/map?state=CA&district=43&slug='.$challenger->slug.'&ref=friend')
        ->assertOk()
        ->assertSee('<title>California Congressional District 43 – District Representative | U9itus</title>', false)
        ->assertSee('Population: 750,123 (2024 Census data).')
        ->assertSee('Represented by District Representative.')
        ->assertSee('<meta property="og:image"       content="https://example.com/representative.jpg">', false)
        ->assertSee('<meta name="twitter:image"       content="https://example.com/representative.jpg">', false)
        ->assertSee('<meta property="og:url"         content="'.e(url('/map').'?state=CA&district=43').'">', false)
        ->assertDontSee('Represented by District Challenger.');
});

it('normalizes padded districts and at-large districts and makes local photos absolute', function () {
    districtShareLeader(['district' => 'CA-03', 'profile_photo_url' => '/storage/leader.jpg']);
    $this->get('/map?state=ca&district=03')->assertOk()
        ->assertSee('Represented by District Representative.')
        ->assertSee(e(url('/storage/leader.jpg')), false);

    districtShareLeader(['state' => 'AK', 'district' => 'AK-AL', 'full_name' => 'Alaska Representative']);
    foreach (['AL', '0', '00'] as $district) {
        $this->get('/map?state=AK&district='.$district)->assertOk()
            ->assertSee('Alaska At-Large Congressional District')
            ->assertSee('Represented by Alaska Representative.');
    }
});

it('does not expose a former, unpublished, state-level, or another district representative', function () {
    districtShareLeader(['term_status' => 'former']);
    districtShareLeader(['page_published' => false]);
    districtShareLeader(['governance_level' => 'state']);
    districtShareLeader(['district' => 'CA-42']);
    districtShareLeader(['term_ends_on' => now()->subDay()]);

    $this->get('/map?state=CA&district=43')->assertOk()
        ->assertSee('California Congressional District 43')
        ->assertDontSee('Represented by')
        ->assertSee(e(asset('images/og-default.png')), false);
});

it('keeps leader text when the photo is missing', function () {
    districtShareLeader(['profile_photo_url' => null]);
    $this->get('/map?state=CA&district=43')->assertOk()
        ->assertSee('Represented by District Representative.')
        ->assertSee(e(asset('images/og-default.png')), false);
});

it('preserves the generic map for absent or malformed district inputs', function (string $query) {
    $this->get('/map'.$query)->assertOk()
        ->assertSee('<title>U.S. Regional Map – U9itus</title>', false);
})->with(['', '?state=CA', '?state=ZZ&district=1', '?state[]=CA&district=1', '?state=CA&district[]=43', '?state=CA&district=bad']);
