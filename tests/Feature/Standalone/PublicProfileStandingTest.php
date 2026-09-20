<?php

use App\Models\Politician;
use App\Models\StateElectionDate;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    Cache::flush();
    StateElectionDate::create([
        'state' => 'CA', 'election_year' => 2026, 'stage_name' => 'General',
        'election_date' => now()->addMonths(2)->toDateString(),
    ]);
});

function profileFor(array $attrs)
{
    $politician = Politician::factory()->create(array_merge([
        'page_published' => true,
        'is_active' => true,
        'state' => 'CA',
        'user_id' => null,
    ], $attrs));

    return test()->get('/p/'.$politician->slug);
}

test('a sitting officeholder who is not running is not shown as a candidate', function () {
    $response = profileFor([
        'term_status' => 'seated',
        'is_running_candidate' => false,
        'term_ends_on' => now()->addMonths(4)->toDateString(),
    ]);

    $response->assertOk();
    $response->assertSee('Current Officeholder');
    $response->assertDontSee('General:');
    $response->assertDontSee('follow this candidate');
    $response->assertSee('Term ends');
});

test('a running candidate still sees the election dates', function () {
    $response = profileFor(['term_status' => 'running', 'is_running_candidate' => true]);

    $response->assertOk();
    $response->assertSee('General:');
    $response->assertSee('follow this candidate');
    $response->assertDontSee('Current Officeholder');
});

test('an incumbent seeking re-election is a candidate', function () {
    $response = profileFor(['term_status' => 'seated', 'is_running_candidate' => true]);

    $response->assertOk();
    $response->assertSee('General:');
    $response->assertDontSee('Current Officeholder');
});

test('a former member sees no election dates', function () {
    $response = profileFor(['term_status' => 'retired', 'is_running_candidate' => false]);

    $response->assertOk();
    $response->assertSee('Former Member');
    $response->assertDontSee('General:');
});

test('every profile shows where its data came from and a way to report a problem', function () {
    $response = profileFor(['term_status' => 'seated', 'is_running_candidate' => false, 'verified_official' => false]);

    $response->assertOk();
    $response->assertSee('Source: U9itus public records');
    $response->assertSee('Report a data problem');
});


test('official status does not claim the official verified the profile content', function () {
    $response = profileFor(['term_status' => 'seated', 'is_running_candidate' => false, 'verified_official' => true]);
    $response->assertOk()->assertSee('Source: U9itus public records')
        ->assertDontSee('Verified by the official');
});
