<?php

use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create([
        'platform' => 'standalone',
        'user_type' => 'voter',
        'email_verified_at' => now(),
    ]);

    skipOnboarding($this->user, 'voter');
});

test('notification preferences page renders for an authenticated user', function () {
    $this->actingAs($this->user)
        ->get(route('notification-preferences.edit'))
        ->assertOk()
        ->assertSee('Notification Preferences')
        ->assertSee('Email Notifications')
        ->assertSee('In-App Notifications')
        ->assertSee('Save Preferences');
});

test('notification preferences page auto-creates a preference row for a new user', function () {
    expect(NotificationPreference::count())->toBe(0);

    $this->actingAs($this->user)
        ->get(route('notification-preferences.edit'))
        ->assertOk();

    expect(NotificationPreference::where('user_id', $this->user->id)->exists())->toBeTrue();
});

test('notification preferences can be updated and unchecked boxes default to false', function () {
    $this->actingAs($this->user)
        ->put(route('notification-preferences.update'), [
            'email_campaign_status' => 1,
            'email_low_balance' => 1,
        ])
        ->assertRedirect();

    $prefs = NotificationPreference::where('user_id', $this->user->id)->first();

    expect($prefs)->email_campaign_status->toBeTrue()
        ->and($prefs)->email_low_balance->toBeTrue()
        ->and($prefs)->email_payout_processed->toBeFalse();
});