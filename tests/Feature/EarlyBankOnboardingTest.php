<?php

use App\Http\Controllers\Api\EarlyBankController;
use App\Http\Middleware\CaptureEarlyBankReferral;
use App\Mail\EarlyBankReferralEnrolledMail;
use App\Models\Citizen;
use App\Models\Politician;
use App\Models\User;
use App\Models\Voter;
use App\Services\EarlyBankWebhookService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

// ---------------------------------------------------------------------------
// Helpers (prefixed to avoid collisions with other test files)
// ---------------------------------------------------------------------------

function eb_makeVoterUser(string $email = 'voter@example.com'): User
{
    Role::findOrCreate('voter', 'web');
    $user = User::factory()->create([
        'email'     => $email,
        'user_type' => 'voter',
    ]);
    $user->assignRole('voter');
    return $user;
}

function eb_makeVoter(User $user): Voter
{
    return Voter::factory()->create([
        'user_id' => $user->id,
        'email'   => $user->email,
    ]);
}

// ---------------------------------------------------------------------------
// Gap #1 – Web registration captures Early-bank cookie
// ---------------------------------------------------------------------------

test('web voter registration captures earlybank_ref cookie and fires webhook', function () {
    Role::findOrCreate('voter', 'web');

    $memberId = Str::uuid()->toString();

    config([
        'services.earlybank.enabled'        => true,
        'services.earlybank.webhook_url'    => 'https://early-bank.test/webhook',
        'services.earlybank.webhook_secret' => 'test-secret',
    ]);

    Http::fake();  // Capture outbound HTTP calls.
    Mail::fake();  // Capture queued mail.

    $response = $this->withCookies([CaptureEarlyBankReferral::COOKIE_NAME => $memberId])
        ->post('/register/voter', [
            'first_name'            => 'Cookie',
            'last_name'             => 'Test',
            'email'                 => 'cookie-voter@example.com',
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
            'terms'                 => '1',
            'zip_code'              => '90210',
        ]);

    // Should redirect (phone.verify or verification.notice).
    $response->assertRedirect();

    $voter = Voter::where('email', 'cookie-voter@example.com')->first();
    expect($voter)->not->toBeNull();
    expect($voter->earlybank_member_id)->toBe($memberId);
    expect($voter->earlybank_linked_at)->not->toBeNull();

    // voter.registered webhook should have been dispatched.
    Http::assertSent(function ($request) use ($memberId) {
        $body = json_decode($request->body(), true);
        return ($body['event'] ?? '') === 'voter.registered'
            && ($body['data']['earlybank_member_id'] ?? '') === $memberId;
    });

    // Enrollment email should be queued.
    Mail::assertQueued(EarlyBankReferralEnrolledMail::class, function ($mail) use ($memberId) {
        return $mail->earlybankMemberId === $memberId;
    });

    // Cookie should be cleared in the redirect response.
    $response->assertCookieExpired(CaptureEarlyBankReferral::COOKIE_NAME);
});

test('web voter registration without earlybank cookie does not set earlybank_member_id', function () {
    Role::findOrCreate('voter', 'web');
    Http::fake();
    Mail::fake();

    $this->post('/register/voter', [
        'first_name'            => 'No',
        'last_name'             => 'Cookie',
        'email'                 => 'no-cookie@example.com',
        'password'              => 'Password123!',
        'password_confirmation' => 'Password123!',
        'terms'                 => '1',
        'zip_code'              => '90210',
    ]);

    $voter = Voter::where('email', 'no-cookie@example.com')->first();
    expect($voter?->earlybank_member_id)->toBeNull();

    // No EarlyBank webhook should fire without a referral cookie. Registration
    // does make an unrelated district-lookup HTTP call (RegistrationController
    // → DistrictLookupService), so this asserts the webhook specifically
    // rather than a blanket "no HTTP calls at all".
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'early-bank'));
    Mail::assertNothingQueued();
});

test('web voter registration does not overwrite existing earlybank attribution on adopted row', function () {
    Role::findOrCreate('voter', 'web');

    $originalMemberId = Str::uuid()->toString();
    $newMemberId      = Str::uuid()->toString();

    // Pre-existing voter row already attributed to a member.
    Voter::factory()->create([
        'email'               => 'adopted@example.com',
        'earlybank_member_id' => $originalMemberId,
        'earlybank_linked_at' => now()->subDays(3),
    ]);

    Http::fake();
    Mail::fake();

    $this->withCookies([CaptureEarlyBankReferral::COOKIE_NAME => $newMemberId])
        ->post('/register/voter', [
            'first_name'            => 'Adopted',
            'last_name'             => 'Voter',
            'email'                 => 'adopted@example.com',
            'password'              => 'Password123!',
            'password_confirmation' => 'Password123!',
            'terms'                 => '1',
            'zip_code'              => '90210',
        ]);

    $voter = Voter::where('email', 'adopted@example.com')->first();
    // Original attribution must be preserved.
    expect($voter->earlybank_member_id)->toBe($originalMemberId);

    // Webhook must NOT fire with new member ID (registration's unrelated
    // district-lookup HTTP call is expected and ignored here).
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'early-bank'));
});

// ---------------------------------------------------------------------------
// Gap #2/#3 – memberEnrolled 409 conflict guard
// ---------------------------------------------------------------------------

test('memberEnrolled returns 409 when a different member uuid is already linked', function () {
    $user  = eb_makeVoterUser('conflict@example.com');
    $voter = eb_makeVoter($user);
    $voter->forceFill([
        'earlybank_own_member_uuid' => Str::uuid()->toString(),
        'earlybank_own_linked_at'   => now(),
    ])->save();

    $differentUuid = Str::uuid()->toString();

    // Authenticate as a machine token.
    config(['services.earlybank.api_token' => 'test-eb-token']);

    $response = $this->withHeaders(['Authorization' => 'Bearer test-eb-token'])
        ->postJson('/api/v1/earlybank/member-enrolled', [
            'uuid'        => $voter->uuid,
            'member_uuid' => $differentUuid,
            'u9itus_role' => 'voter',
        ]);

    $response->assertStatus(409)
        ->assertJson(['error' => 'already_enrolled_other_member']);

    // Ensure the original UUID was not overwritten.
    $voter->refresh();
    expect($voter->earlybank_own_member_uuid)->not->toBe($differentUuid);
});

test('memberEnrolled is idempotent for same member uuid', function () {
    $user  = eb_makeVoterUser('idempotent@example.com');
    $voter = eb_makeVoter($user);
    $uuid  = Str::uuid()->toString();

    $voter->forceFill([
        'earlybank_own_member_uuid' => $uuid,
        'earlybank_own_linked_at'   => now(),
    ])->save();

    config(['services.earlybank.api_token' => 'test-eb-token']);

    $response = $this->withHeaders(['Authorization' => 'Bearer test-eb-token'])
        ->postJson('/api/v1/earlybank/member-enrolled', [
            'uuid'        => $voter->uuid,
            'member_uuid' => $uuid,
            'u9itus_role' => 'voter',
        ]);

    $response->assertOk()
        ->assertJson(['status' => 'already_enrolled']);
});

// ---------------------------------------------------------------------------
// EarlyBankWebhookService::handleVoterRegistered
// ---------------------------------------------------------------------------

test('handleVoterRegistered dispatches voter.registered webhook and queues mail', function () {
    Http::fake();
    Mail::fake();

    config([
        'services.earlybank.enabled'        => true,
        'services.earlybank.webhook_url'    => 'https://early-bank.test/webhook',
        'services.earlybank.webhook_secret' => 'test-secret',
    ]);

    $user  = eb_makeVoterUser('wh-test@example.com');
    $voter = eb_makeVoter($user);
    $memberId = Str::uuid()->toString();

    app(EarlyBankWebhookService::class)->handleVoterRegistered($voter, $memberId);

    Http::assertSent(function ($request) use ($memberId) {
        $body = json_decode($request->body(), true);
        return ($body['event'] ?? '') === 'voter.registered'
            && ($body['data']['earlybank_member_id'] ?? '') === $memberId;
    });

    Mail::assertQueued(EarlyBankReferralEnrolledMail::class);
});

test('handleVoterRegistered is a no-op when earlybank is disabled', function () {
    Http::fake();
    Mail::fake();

    config(['services.earlybank.enabled' => false]);

    $user  = eb_makeVoterUser('disabled@example.com');
    $voter = eb_makeVoter($user);

    app(EarlyBankWebhookService::class)->handleVoterRegistered($voter, Str::uuid()->toString());

    Http::assertNothingSent();
    Mail::assertNothingQueued();
});

// ---------------------------------------------------------------------------
// member-enrolled: first-time write + new Stripe Connect / payouts fields
// ---------------------------------------------------------------------------

function eb_authedHeaders(): array
{
    config(['services.earlybank.api_token' => 'test-eb-token']);
    return ['Authorization' => 'Bearer test-eb-token'];
}

test('memberEnrolled first-time write persists member uuid and stripe state for a voter', function () {
    $user  = eb_makeVoterUser('fresh-voter@example.com');
    $voter = eb_makeVoter($user);
    $memberUuid = Str::uuid()->toString();
    $stripeAccount = 'acct_1PM_stripe_connect';

    $response = $this->withHeaders(eb_authedHeaders())
        ->postJson('/api/v1/earlybank/member-enrolled', [
            'uuid'                                    => $voter->uuid,
            'member_uuid'                             => $memberUuid,
            'u9itus_role'                             => 'voter',
            'payouts_enabled'                         => false,
            'stripe_connect_account_id'              => $stripeAccount,
            'stripe_connect_onboarding_complete'      => false,
        ]);

    $response->assertStatus(201)
        ->assertJson(['status' => 'enrolled', 'member_uuid' => $memberUuid]);

    $voter->refresh();
    expect($voter->earlybank_own_member_uuid)->toBe($memberUuid)
        ->and($voter->earlybank_own_linked_at)->not->toBeNull()
        ->and($voter->earlybank_stripe_connect_account_id)->toBe($stripeAccount)
        ->and($voter->earlybank_payouts_enabled)->toBeFalse()
        ->and($voter->earlybank_stripe_connect_onboarding_complete)->toBeFalse();
});

test('memberEnrolled first-time write persists member uuid for a politician', function () {
    $politician = Politician::factory()->create();
    $memberUuid = Str::uuid()->toString();

    $response = $this->withHeaders(eb_authedHeaders())
        ->postJson('/api/v1/earlybank/member-enrolled', [
            'uuid'        => $politician->uuid,
            'member_uuid' => $memberUuid,
            'u9itus_role' => 'politician',
        ]);

    $response->assertStatus(201)->assertJson(['status' => 'enrolled']);

    $politician->refresh();
    expect($politician->earlybank_own_member_uuid)->toBe($memberUuid);
});

test('memberEnrolled first-time write persists member uuid for a citizen', function () {
    // Regression guard: citizens live on their own `citizens` table. Before the
    // fix this routed to Voter and returned 404 voter_not_found.
    $citizen = Citizen::factory()->create();
    $memberUuid = Str::uuid()->toString();

    $response = $this->withHeaders(eb_authedHeaders())
        ->postJson('/api/v1/earlybank/member-enrolled', [
            'uuid'        => $citizen->uuid,
            'member_uuid' => $memberUuid,
            'u9itus_role' => 'citizen',
            'payouts_enabled'                         => true,
            'stripe_connect_onboarding_complete'      => true,
        ]);

    $response->assertStatus(201)->assertJson(['status' => 'enrolled']);

    $citizen->refresh();
    expect($citizen->earlybank_own_member_uuid)->toBe($memberUuid)
        ->and($citizen->earlybank_own_linked_at)->not->toBeNull()
        ->and($citizen->earlybank_payouts_enabled)->toBeTrue()
        ->and($citizen->earlybank_stripe_connect_onboarding_complete)->toBeTrue();
});

test('memberEnrolled idempotent re-enroll syncs stripe onboarding state', function () {
    $user  = eb_makeVoterUser('sync-voter@example.com');
    $voter = eb_makeVoter($user);
    $memberUuid = Str::uuid()->toString();

    // First enrollment: onboarding not yet complete.
    $this->withHeaders(eb_authedHeaders())
        ->postJson('/api/v1/earlybank/member-enrolled', [
            'uuid'                                => $voter->uuid,
            'member_uuid'                          => $memberUuid,
            'u9itus_role'                          => 'voter',
            'stripe_connect_onboarding_complete'   => false,
        ])
        ->assertStatus(201);

    // Replay with the SAME member uuid but onboarding now complete + payouts on.
    $response = $this->withHeaders(eb_authedHeaders())
        ->postJson('/api/v1/earlybank/member-enrolled', [
            'uuid'                                => $voter->uuid,
            'member_uuid'                          => $memberUuid,
            'u9itus_role'                          => 'voter',
            'payouts_enabled'                      => true,
            'stripe_connect_onboarding_complete'   => true,
        ]);

    $response->assertOk()->assertJson(['status' => 'already_enrolled']);

    $voter->refresh();
    expect($voter->earlybank_own_member_uuid)->toBe($memberUuid)
        ->and($voter->earlybank_payouts_enabled)->toBeTrue()
        ->and($voter->earlybank_stripe_connect_onboarding_complete)->toBeTrue();
});

test('memberEnrolled rejects an invalid u9itus_role with 422', function () {
    $user  = eb_makeVoterUser('role-valid@example.com');
    $voter = eb_makeVoter($user);

    $this->withHeaders(eb_authedHeaders())
        ->postJson('/api/v1/earlybank/member-enrolled', [
            'uuid'        => $voter->uuid,
            'member_uuid' => Str::uuid()->toString(),
            'u9itus_role' => 'superuser',
        ])
        ->assertStatus(422)
        ->assertJson(['error' => 'validation_failed']);
});

test('memberEnrolled returns 404 when the uuid does not match the role', function () {
    $citizen = Citizen::factory()->create();

    // A citizen uuid posted under the 'voter' role should not resolve on the
    // voters table.
    $this->withHeaders(eb_authedHeaders())
        ->postJson('/api/v1/earlybank/member-enrolled', [
            'uuid'        => $citizen->uuid,
            'member_uuid' => Str::uuid()->toString(),
            'u9itus_role' => 'voter',
        ])
        ->assertStatus(404)
        ->assertJson(['error' => 'voter_not_found']);
});

test('memberEnrolled requires a bearer token', function () {
    $user  = eb_makeVoterUser('noauth@example.com');
    $voter = eb_makeVoter($user);

    // No Authorization header — earlybank.api middleware must reject.
    config(['services.earlybank.api_token' => 'test-eb-token']);

    $this->postJson('/api/v1/earlybank/member-enrolled', [
        'uuid'        => $voter->uuid,
        'member_uuid' => Str::uuid()->toString(),
        'u9itus_role' => 'voter',
    ])->assertStatus(401);
});
