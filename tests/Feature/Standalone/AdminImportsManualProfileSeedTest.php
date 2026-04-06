<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

const JACKSON_PROFILE_NAME = 'Dr. Corey A. Jackson';
const JACKSON_PROFILE_WEBSITE = 'https://jackson.asmdc.org/';

function adminForManualProfileImportTests(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $admin = User::factory()->create([
        'user_type' => 'admin',
    ]);

    $admin->assignRole('admin');
    skipOnboarding($admin, 'admin');

    return $admin;
}

test('admin can trigger one-off unverified politician profile import from imports dashboard', function () {
    $admin = adminForManualProfileImportTests();

    Artisan::shouldReceive('call')
        ->once()
        ->withArgs(function (string $command, array $arguments): bool {
            return $command === 'politicians:create-unverified-profile'
                && ($arguments['--website'] ?? null) === JACKSON_PROFILE_WEBSITE
                && ($arguments['--name'] ?? null) === JACKSON_PROFILE_NAME
                && ($arguments['--office'] ?? null) === 'Assemblymember'
                && ($arguments['--state'] ?? null) === 'CA'
                && ($arguments['--publish'] ?? null) === '1';
        })
        ->andReturn(0);

    Artisan::shouldReceive('output')
        ->once()
        ->andReturn('Unverified profile updated: ' . JACKSON_PROFILE_NAME . ' (ID 4)');

    $response = $this->actingAs($admin)
        ->post(route('admin.imports.unverified-profile.seed'), [
            'website' => JACKSON_PROFILE_WEBSITE,
            'name' => JACKSON_PROFILE_NAME,
            'office' => 'Assemblymember',
            'level' => 'State',
            'state' => 'CA',
            'district' => 'AD-60',
            'party' => 'Democratic',
            'city' => 'Moreno Valley',
            'source' => 'official_state_website',
            'publish' => '1',
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');
});

test('admin sees validation error when website is missing for one-off import', function () {
    $admin = adminForManualProfileImportTests();

    $response = $this->actingAs($admin)
        ->from(route('admin.imports'))
        ->post(route('admin.imports.unverified-profile.seed'), [
            'website' => '',
            'name' => JACKSON_PROFILE_NAME,
            'office' => 'Assemblymember',
            'state' => 'CA',
        ]);

    $response->assertRedirect(route('admin.imports'));
    $response->assertSessionHasErrors('website');
});
