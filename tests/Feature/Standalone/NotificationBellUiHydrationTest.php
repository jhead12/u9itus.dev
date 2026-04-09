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
    $response->assertSee("fetch('/api/v1/notifications'", false);

    // Read-state actions are wired to API routes used by the bell.
    $response->assertSee("fetch('/api/v1/notifications/mark-all-as-read'", false);
    $response->assertSee("'/api/v1/notifications/' + notification.id + '/mark-as-read'", false);
});

test('unpublished politician sees bell reminder linking to public page editor', function () {
    $user = politicianForBellUiTest();

    $response = $this->actingAs($user)->get(route('politician.dashboard'));

    $response->assertOk();
    $response->assertSee('Your public profile is not published yet. Publish it so voters can find you.');
    $response->assertSee('politician-unpublished-profile-reminder');
    $response->assertSee('Open Public Page');
});

test('published politician does not see unpublished profile reminder', function () {
    $user = politicianForBellUiTest();
    $user->politician->update(['page_published' => true]);

    $response = $this->actingAs($user)->get(route('politician.dashboard'));

    $response->assertOk();
    $response->assertDontSee('Your public profile is not published yet. Publish it so voters can find you.');
    $response->assertDontSee('politician-unpublished-profile-reminder');
    $response->assertDontSee('Open Public Page');
});
