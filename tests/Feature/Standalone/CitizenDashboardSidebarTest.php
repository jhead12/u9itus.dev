<?php

use App\Models\Citizen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function dashboardSidebarCitizen(): User
{
    Role::firstOrCreate(['name' => 'citizen', 'guard_name' => 'web']);

    $user = User::factory()->create(['user_type' => 'citizen', 'platform' => 'standalone']);
    $user->assignRole('citizen');
    skipOnboarding($user, 'citizen');

    Citizen::factory()->create([
        'user_id' => $user->id,
        'address_line_1' => '100 Test St',
        'city' => 'Fresno',
        'state' => 'CA',
        'zip' => '93701',
    ]);

    return $user;
}

it('shows a Posts section and a Two-Factor Auth link in the citizen dashboard sidebar', function () {
    $user = dashboardSidebarCitizen();

    $this->actingAs($user)->get(route('citizen.dashboard'))
        ->assertOk()
        ->assertSee('Posts')
        ->assertSee('My Posts')
        ->assertSee(route('citizen.posts.index'), false)
        ->assertSee('New Post')
        ->assertSee(route('citizen.posts.create'), false)
        ->assertSee('Two-Factor Auth')
        ->assertSee(route('2fa.setup'), false);
});

it('every quick-action tile on the citizen dashboard has a matching sidebar link', function () {
    $user = dashboardSidebarCitizen();

    $response = $this->actingAs($user)->get(route('citizen.dashboard'))->assertOk();

    foreach ([
        route('citizen.campaigns.create'),
        route('citizen.campaigns.index'),
        route('citizen.posts.create'),
        route('citizen.posts.index'),
        route('citizen.events.create'),
        route('citizen.events.index'),
        route('citizen.billing'),
        route('2fa.setup'),
    ] as $tileRoute) {
        // Every dashboard tile route must also appear as a persistent
        // sidebar link, not just as a one-off tile on the dashboard body.
        expect(substr_count($response->getContent(), $tileRoute))->toBeGreaterThanOrEqual(2);
    }
});
