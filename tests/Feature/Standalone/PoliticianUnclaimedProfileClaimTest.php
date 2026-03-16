<?php

use App\Models\Politician;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('politician registration claims matching unclaimed profile', function () {
    Role::firstOrCreate(['name' => 'politician', 'guard_name' => 'web']);

    $existing = Politician::factory()->create([
        'user_id' => null,
        'full_name' => 'Alex Padilla',
        'political_office' => 'U.S. Senator',
        'governance_level' => 'Federal',
        'state' => 'CA',
        'city' => 'Los Angeles',
        'bio' => 'Imported public record profile.',
        'page_published' => true,
        'is_active' => true,
    ]);

    $response = $this->post(route('register.politician.submit'), [
        'first_name' => 'Alex',
        'last_name' => 'Padilla',
        'email' => 'alex.padilla@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'phone' => '555-111-2222',
        'political_office' => 'U.S. Senator',
        'party' => 'Democratic',
        'governance_level' => 'Federal',
        'state' => 'CA',
        'city' => 'Los Angeles',
        'terms' => '1',
    ]);

    $response->assertRedirect(route('verification.notice'));

    $user = User::where('email', 'alex.padilla@example.com')->first();
    expect($user)->not->toBeNull();

    $claimed = $existing->fresh();
    expect($claimed->user_id)->toBe($user->id);
    expect($claimed->party_affiliation)->toBe('Democratic');

    $this->assertDatabaseCount('politicians', 1);
});
