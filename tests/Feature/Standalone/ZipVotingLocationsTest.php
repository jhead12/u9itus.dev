<?php

use App\Models\DistrictLookupSearch;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Carbon::setTestNow('2026-10-25 12:00:00');
    $this->withoutVite();
    Http::preventStrayRequests();
    config(['services.google.civic_api_key' => null]);
});

afterEach(fn () => Carbon::setTestNow());

function saveZipVotingInfo(array $changes = [], int $age = 1): DistrictLookupSearch
{
    $location = [
        'name' => 'Community Center', 'polling_hours' => '7 AM–8 PM',
        'address' => ['line1' => '100 Civic Plaza', 'city' => 'Moreno Valley', 'state' => 'CA', 'zip' => '92555'],
    ];
    $info = array_replace_recursive([
        'source' => 'google_civic_voterinfo',
        'normalized_input' => ['line1' => '123 Private Home', 'zip' => '92555'],
        'election' => ['id' => '9000', 'name' => 'General Election', 'election_day' => '2026-11-03'],
        'polling_locations' => [$location],
        'early_vote_sites' => [], 'drop_off_locations' => [],
    ], $changes);
    $search = DistrictLookupSearch::create([
        'query_address' => '123 Private Home, Moreno Valley, CA 92555',
        'matched_address' => '123 Private Home, Moreno Valley, CA 92555',
        'state' => 'CA', 'district_number' => '41', 'resolved' => true,
        'source' => 'census_geocoder', 'payload' => ['voter_info' => $info],
    ]);
    $search->forceFill(['created_at' => now()->subDays($age)])->save();

    return $search;
}

it('shows saved polling locations on ZIP district results with dates hours and directions', function () {
    saveZipVotingInfo();
    $this->get('/district-lookup?address=92555')->assertOk()
        ->assertSee('CA-41')->assertSee('Where to Vote')->assertSee('Nearby polling location')
        ->assertSee('Community Center')->assertSee('7 AM–8 PM')->assertSee('2026-11-03')
        ->assertSee('Get directions')->assertSee('not confirmed as your assigned polling place')
        ->assertDontSee('Your Polling Place')->assertDontSee('123 Private Home');
    Http::assertNothingSent();
});

it('deduplicates locations and includes early voting and drop-off dates', function () {
    $extra = ['name' => 'County Library', 'address' => ['line1' => '200 Main St', 'city' => 'Moreno Valley', 'state' => 'CA', 'zip' => '92555'], 'start_date' => '2026-10-26', 'end_date' => '2026-11-02'];
    saveZipVotingInfo(['early_vote_sites' => [$extra], 'drop_off_locations' => [$extra]]);
    saveZipVotingInfo();
    $response = $this->get('/district-lookup?address=92555-1234')->assertOk()
        ->assertSee('Early voting')->assertSee('Ballot drop-off')->assertSee('2026-10-26');
    expect($response->viewData('zipVotingLocations'))->toHaveCount(3);
    Http::assertNothingSent();
});

it('excludes old missing-date past-election and closed-location records', function () {
    saveZipVotingInfo([], 8);
    saveZipVotingInfo(['election' => ['election_day' => null]]);
    saveZipVotingInfo(['election' => ['election_day' => '2026-02-30']]);
    saveZipVotingInfo(['election' => ['election_day' => '2026-10-24']]);
    saveZipVotingInfo(['polling_locations' => [['end_date' => '2026-10-24']]]);
    $this->get('/district-lookup?address=92555')->assertOk()
        ->assertDontSee('Community Center')->assertSee('No recent polling-location information')
        ->assertSee('https://www.usa.gov/find-polling-place', false);
});

it('does not show saved locations from another ZIP or refresh them by browsing', function () {
    saveZipVotingInfo(['normalized_input' => ['zip' => '92553']]);
    $record = saveZipVotingInfo([], 6);
    $this->get('/district-lookup?address=92555')->assertOk()->assertSee('Community Center');
    expect($record->fresh()->created_at->toDateString())->toBe('2026-10-19');
    Carbon::setTestNow('2026-10-27 12:00:00');
    $this->get('/district-lookup?address=92555')->assertOk()->assertDontSee('Community Center');
    Http::assertNothingSent();
});

it('keeps the initial lookup page free of an empty polling section', function () {
    $this->get('/district-lookup')->assertOk()->assertDontSee('Where to Vote');
    Http::assertNothingSent();
});
