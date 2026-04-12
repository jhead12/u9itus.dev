<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function makeVerifiedVoterForIdmeTests(): User
{
    Role::firstOrCreate(['name' => 'voter', 'guard_name' => 'web']);

    $user = User::factory()->create([
        'user_type' => 'voter',
        'email_verified_at' => now(),
        'kyc_status' => 'pending',
    ]);

    $user->assignRole('voter');
    skipOnboarding($user, 'voter');

    return $user;
}

beforeEach(function (): void {
    config()->set('services.idme.client_id', 'test-client-id');
    config()->set('services.idme.client_secret', 'test-client-secret');
    config()->set('services.idme.redirect_uri', 'http://localhost/verification/idme/callback');
    config()->set('services.idme.auth_url', 'https://api.id.me/oauth/authorize');
    config()->set('services.idme.token_url', 'https://api.id.me/oauth/token');
    config()->set('services.idme.attributes_url', 'https://api.id.me/api/public/v3/attributes.json');
    config()->set('services.idme.scopes', ['identity', 'email']);
});

test('authenticated voter can start idme verification redirect', function () {
    $user = makeVerifiedVoterForIdmeTests();

    $response = $this->actingAs($user)->get(route('verification.idme.redirect'));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('https://api.id.me/oauth/authorize');
    expect(session('idme_oauth_state'))->not->toBeNull();
    expect((int) session('idme_oauth_user_id'))->toBe($user->id);
});

test('idme callback marks user as verified and approves kyc', function () {
    Http::fake([
        'https://api.id.me/oauth/token' => Http::response([
            'access_token' => 'idme-access-token',
            'token_type' => 'bearer',
        ], 200),
        'https://api.id.me/api/public/v3/attributes.json' => Http::response([
            'uuid' => 'idme-user-123',
            'email' => 'person@example.org',
        ], 200),
    ]);

    $user = makeVerifiedVoterForIdmeTests();

    $this->actingAs($user)->get(route('verification.idme.redirect'));

    $state = (string) session('idme_oauth_state');

    $this->actingAs($user)
        ->get(route('verification.idme.callback', [
            'code' => 'oauth-code-123',
            'state' => $state,
        ]))
        ->assertRedirect(route('voter.profile'));

    $user->refresh();

    expect($user->idme_uuid)->toBe('idme-user-123');
    expect($user->idme_verified_at)->not->toBeNull();
    expect($user->kyc_status)->toBe('approved');
    expect($user->kyc_reviewed_at)->not->toBeNull();
});
