<?php

use App\Models\DistrictLookupSearch;
use App\Models\Politician;
use App\Models\ProfileAddress;
use App\Services\ZipDistrictLookupService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->withoutVite();
    Http::preventStrayRequests();
});

it('shows all saved ZIP matches without external requests or private address details', function () {
    foreach (['41', 'CA-42', '41'] as $district) {
        DistrictLookupSearch::create([
            'query_address' => '123 Private Street, Moreno Valley, CA 92555',
            'matched_address' => '123 PRIVATE STREET, MORENO VALLEY, CA 92555-1234',
            'state' => 'CA', 'district_number' => $district,
            'resolved' => true, 'source' => 'census_geocoder',
        ]);
    }
    $response = $this->get('/district-lookup?address=92555-9999');
    $response->assertOk()->assertSee('Related districts for ZIP 92555')
        ->assertSee('CA-41')->assertSee('CA-42')->assertSee('Source: U9itus records')
        ->assertDontSee('PRIVATE STREET')->assertDontSee('could not load districts');
    expect($response->viewData('zipDistricts'))->toHaveCount(2);
    Http::assertNothingSent();
});

it('uses verified public district offices and excludes mailing and unpublished records', function () {
    foreach ([['district', true, 'CA-41'], ['mailing', true, 'CA-42'], ['district', false, 'CA-43']] as [$kind, $published, $district]) {
        $politician = Politician::factory()->create([
            'state' => 'CA', 'district' => $district, 'governance_level' => 'federal',
            'political_office' => 'U.S. Representative', 'is_active' => true, 'page_published' => $published,
        ]);
        ProfileAddress::create([
            'profilable_type' => $politician->getMorphClass(), 'profilable_id' => $politician->id,
            'address_kind' => $kind, 'state' => 'CA', 'postal_code' => '92555', 'is_verified' => true,
            'line1' => '100 Civic Plaza', 'city' => 'Moreno Valley',
            'full_address' => '100 Civic Plaza, Moreno Valley, CA 92555',
            'source_url' => 'https://example.test/district-office',
        ]);
    }
    $this->get('/district-lookup?address=92555')->assertOk()
        ->assertSee('CA-41')->assertDontSee('CA-42')->assertDontSee('CA-43');
    Http::assertNothingSent();
});

it('does not treat a street number or a stale saved lookup as a ZIP match', function () {
    DistrictLookupSearch::create([
        'query_address' => '92555 Main Street, Los Angeles, CA 90001',
        'state' => 'CA', 'district_number' => '43', 'resolved' => true, 'source' => 'census_geocoder',
    ]);
    $stale = DistrictLookupSearch::create([
        'query_address' => '92555', 'state' => 'CA', 'district_number' => '44',
        'resolved' => true, 'source' => 'google_civic',
    ]);
    $stale->forceFill(['created_at' => now()->subDays(100)])->save();
    config(['services.google.civic_api_key' => null]);
    expect(app(ZipDistrictLookupService::class)->districtsForZip('92555'))->toBe([]);
    Http::assertNothingSent();
});

it('uses the existing Civic lookup only when local matches are unavailable and saves every result', function () {
    config(['services.google.civic_api_key' => 'test-key']);
    Http::fake(['*divisionsByAddress*' => Http::response(['divisions' => [
        'ocd-division/country:us/state:ca/cd:41' => [],
        'ocd-division/country:us/state:ca/cd:42' => [],
    ]])]);
    $this->get('/district-lookup?address=92555')->assertOk()->assertSee('CA-41')->assertSee('CA-42');
    Http::assertSentCount(1);
    Http::fake();
    $this->get('/district-lookup?address=92555')->assertOk()->assertSee('Source: U9itus records')
        ->assertSee('CA-41')->assertSee('CA-42');
    Http::assertNothingSent();
});

it('normalizes leading-zero ZIPs and at-large districts in saved records', function () {
    DistrictLookupSearch::create([
        'query_address' => '05401', 'state' => 'VT', 'district_number' => '00',
        'resolved' => true, 'source' => 'google_civic',
    ]);
    $this->get('/district-lookup?address=05401')->assertOk()->assertSee('VT-AL');
    Http::assertNothingSent();
});
