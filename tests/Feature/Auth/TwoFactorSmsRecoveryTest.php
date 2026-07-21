<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('voter', 'web');
    Role::findOrCreate('admin', 'web');

    config([
        'services.twilio.account_sid' => 'test-sid',
        'services.twilio.auth_token' => 'test-token',
        'services.twilio.from_number' => '+15551234567',
    ]);
});

function makeVoterWithTwoFactor(array $overrides = []): User
{
    $voter = User::factory()->create(array_merge([
        'platform' => 'standalone',
        'user_type' => 'voter',
        'email_verified_at' => now(),
        'phone' => '+15559876543',
        'phone_verified_at' => now(),
        'two_factor_secret' => str_repeat('A', 32),
        'two_factor_confirmed_at' => now(),
        'two_factor_recovery_codes' => ['ABCD-EFGH', 'JKLM-NPQR'],
    ], $overrides));

    $voter->assignRole('voter');
    skipOnboarding($voter, 'voter');

    return $voter;
}

function fakeTwilioSuccess(): void
{
    Http::fake([
        'api.twilio.com/*' => Http::response(['sid' => 'SM_test_sid'], 200),
    ]);
}

/**
 * Pull the plaintext recovery code out of the faked Twilio request body
 * rather than brute-forcing the bcrypt hash (which is prohibitively slow
 * over a 6-digit keyspace).
 */
function lastSentRecoveryCode(): string
{
    $request = Http::recorded()->last()[0];

    preg_match('/is: (\d{6})\./', $request->data()['Body'] ?? '', $matches);

    return $matches[1];
}

test('recovery page shows unavailable state without a verified phone', function () {
    $voter = makeVoterWithTwoFactor(['phone' => null, 'phone_verified_at' => null]);

    $response = $this->actingAs($voter)->get(route('2fa.recovery-sms'));

    $response->assertOk();
    $response->assertSee("doesn't have a verified phone number", false);
});

test('sending a recovery code fails server-side without a verified phone even if requested directly', function () {
    $voter = makeVoterWithTwoFactor(['phone' => null, 'phone_verified_at' => null]);

    $response = $this->actingAs($voter)->post(route('2fa.recovery-sms.send'));

    $response->assertSessionHasErrors('recovery');
});

test('happy path: correct sms code disables 2fa and redirects to dashboard', function () {
    fakeTwilioSuccess();
    $voter = makeVoterWithTwoFactor();

    $this->actingAs($voter)
        ->post(route('2fa.recovery-sms.send'))
        ->assertRedirect(route('2fa.recovery-sms.verify'));

    $this->assertDatabaseHas('two_factor_recovery_sms_codes', [
        'user_id' => $voter->id,
    ]);

    $code = lastSentRecoveryCode();

    $this->actingAs($voter)
        ->post(route('2fa.recovery-sms.verify.submit'), ['code' => $code])
        ->assertRedirect(route('voter.dashboard'));

    $voter->refresh();

    expect($voter->two_factor_secret)->toBeNull();
    expect($voter->two_factor_confirmed_at)->toBeNull();
    expect($voter->two_factor_recovery_codes)->toBeNull();
});

test('wrong code does not disable 2fa and increments attempts', function () {
    fakeTwilioSuccess();
    $voter = makeVoterWithTwoFactor();

    $this->actingAs($voter)->post(route('2fa.recovery-sms.send'));

    $response = $this->actingAs($voter)
        ->post(route('2fa.recovery-sms.verify.submit'), ['code' => '000000']);

    $response->assertSessionHasErrors('code');

    $voter->refresh();
    expect($voter->hasTwoFactorEnabled())->toBeTrue();

    $record = DB::table('two_factor_recovery_sms_codes')->where('user_id', $voter->id)->first();
    expect($record->attempts)->toBe(1);
});

test('exceeding max attempts locks out the code even with the correct value', function () {
    fakeTwilioSuccess();
    $voter = makeVoterWithTwoFactor();

    $this->actingAs($voter)->post(route('2fa.recovery-sms.send'));

    $code = lastSentRecoveryCode();

    for ($i = 0; $i < 5; $i++) {
        $this->actingAs($voter)->post(route('2fa.recovery-sms.verify.submit'), ['code' => '999999']);
    }

    $response = $this->actingAs($voter)
        ->post(route('2fa.recovery-sms.verify.submit'), ['code' => $code]);

    $response->assertSessionHasErrors('code');

    $voter->refresh();
    expect($voter->hasTwoFactorEnabled())->toBeTrue();
});

test('expired code is rejected even if correct', function () {
    fakeTwilioSuccess();
    $voter = makeVoterWithTwoFactor();

    $this->actingAs($voter)->post(route('2fa.recovery-sms.send'));

    $code = lastSentRecoveryCode();

    DB::table('two_factor_recovery_sms_codes')
        ->where('user_id', $voter->id)
        ->update(['expires_at' => now()->subMinute()]);

    $response = $this->actingAs($voter)
        ->post(route('2fa.recovery-sms.verify.submit'), ['code' => $code]);

    $response->assertSessionHasErrors('code');
});

test('resend cooldown blocks an immediate second send', function () {
    fakeTwilioSuccess();
    $voter = makeVoterWithTwoFactor();

    $this->actingAs($voter)->post(route('2fa.recovery-sms.send'));
    $countAfterFirst = DB::table('two_factor_recovery_sms_codes')->where('user_id', $voter->id)->count();

    $this->actingAs($voter)->post(route('2fa.recovery-sms.send'));
    $countAfterSecond = DB::table('two_factor_recovery_sms_codes')->where('user_id', $voter->id)->count();

    expect($countAfterFirst)->toBe(1);
    expect($countAfterSecond)->toBe(1);
});

test('admin users are forbidden from the generic sms recovery routes', function () {
    $admin = User::factory()->create([
        'platform' => 'standalone',
        'user_type' => 'admin',
        'email_verified_at' => now(),
    ]);
    $admin->assignRole('admin');
    skipOnboarding($admin, 'admin');

    $this->actingAs($admin)->get(route('2fa.recovery-sms'))->assertForbidden();
    $this->actingAs($admin)->post(route('2fa.recovery-sms.send'))->assertForbidden();
    $this->actingAs($admin)->get(route('2fa.recovery-sms.verify'))->assertForbidden();
    $this->actingAs($admin)->post(route('2fa.recovery-sms.verify.submit'), ['code' => '123456'])->assertForbidden();
});

test('a locked-out user reaching recovery routes is not bounced back to the challenge', function () {
    $voter = makeVoterWithTwoFactor();

    // No 2fa_verified_user_id/2fa_verified_at session markers set — this user
    // is exactly the "stuck" case EnsureTwoFactorVerified would otherwise
    // redirect to /2fa/challenge.
    $response = $this->actingAs($voter)->get(route('2fa.recovery-sms'));
    $response->assertOk();

    $response = $this->actingAs($voter)->get(route('2fa.recovery-sms.verify'));
    $response->assertOk();
});
