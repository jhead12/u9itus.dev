<?php

use App\Models\DistrictLookupSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('admin can view district search insights page', function () {
    $zip = '92555';

    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $admin = User::factory()->create([
        'user_type' => 'admin',
    ]);
    $admin->assignRole('admin');
    skipOnboarding($admin, 'admin');

    DistrictLookupSearch::create([
        'query_address' => $zip,
        'matched_address' => 'Moreno Valley, CA ' . $zip,
        'state' => 'CA',
        'district_number' => '18',
        'district_code' => 'CA-18',
        'resolved' => true,
        'source' => 'google_civic',
        'discovered_officials_count' => 2,
        'payload' => ['example' => true],
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.district-searches.index'));

    $response->assertOk();
    $response->assertSee('District Search Insights');
    $response->assertSee($zip);
    $response->assertSee('CA-18');
    $response->assertSee('Google Civic');
});

test('district search insights filter by state', function () {
    $caZip = '92556';

    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $admin = User::factory()->create([
        'user_type' => 'admin',
    ]);
    $admin->assignRole('admin');
    skipOnboarding($admin, 'admin');

    DistrictLookupSearch::create([
        'query_address' => $caZip,
        'state' => 'CA',
        'district_code' => 'CA-18',
        'resolved' => true,
    ]);

    DistrictLookupSearch::create([
        'query_address' => '10001',
        'state' => 'NY',
        'district_code' => 'NY-10',
        'resolved' => true,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.district-searches.index', ['state' => 'CA']));

    $response->assertOk();
    $response->assertSee($caZip);
    $response->assertDontSee('10001');
});

test('admin can export district searches csv with source labels', function () {
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $admin = User::factory()->create([
        'user_type' => 'admin',
    ]);
    $admin->assignRole('admin');
    skipOnboarding($admin, 'admin');

    DistrictLookupSearch::create([
        'query_address' => '92557',
        'state' => 'CA',
        'district_code' => 'CA-39',
        'resolved' => true,
        'source' => 'google_civic',
        'discovered_officials_count' => 3,
    ]);

    DistrictLookupSearch::create([
        'query_address' => '10001',
        'state' => 'NY',
        'district_code' => 'NY-10',
        'resolved' => true,
        'source' => 'census_geocoder',
        'discovered_officials_count' => 1,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.district-searches.export', ['state' => 'CA']));

    $response->assertOk();

    $csv = $response->streamedContent();
    expect($csv)->toContain('Source Label');
    expect($csv)->toContain('Google Civic');
    expect($csv)->toContain('92557');
    expect($csv)->not->toContain('10001');
});
