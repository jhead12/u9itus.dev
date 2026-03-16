<?php

use App\Models\User;
use App\Models\Politician;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function politicianForBellUiTest(): User
{
    Role::firstOrCreate(['name' => 'politician', 'guard_name' => 'web']);

    $user = User::factory()->create();
    $user->assignRole('politician');

    skipOnboarding($user, 'politician');

    Politician::factory()->create([
        'user_id' => $user->id,
        'is_active' => true,
        'kyc_status' => 'approved',
    ]);

    return $user;
}

test('notification bell open action is wired to API hydration on dashboard', function () {
    $user = politicianForBellUiTest();

    $response = $this->actingAs($user)->get(route('politician.dashboard'));

    $response->assertOk();

    // Bell component exists in rendered dashboard layout.
    $response->assertSee('x-data="notificationBell()"', false);

    // Opening bell uses toggle() that triggers loadFromServer().
    $response->assertSee('@click="toggle()"', false);
    $response->assertSee('if (this.open) this.loadFromServer();', false);

    // Hydration path calls notifications API endpoint.
    $response->assertSee("fetch('/api/notifications'", false);

    // Read-state actions are wired to API routes used by the bell.
    $response->assertSee("fetch('/api/notifications/mark-all-as-read'", false);
    $response->assertSee("'/api/notifications/' + notification.id + '/mark-as-read'", false);
});
