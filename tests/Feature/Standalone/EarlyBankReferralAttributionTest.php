<?php

use App\Http\Middleware\CaptureEarlyBankReferral;
use App\Models\Voter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

// ---------------------------------------------------------------------------
// The only referral links shown on /voter/referrals carry an Early-bank
// member UUID (?ref=<uuid>), not the voter's internal referral_code. Before
// this fix, registering through those links set earlybank_member_id but
// never referred_by_voter_id, so a referrer's "Referred Voters" list stayed
// empty forever regardless of how many people signed up through their links.
// ---------------------------------------------------------------------------

uses(RefreshDatabase::class);

test('registering via the EB member UUID link attributes referred_by_voter_id to that member', function () {
    Role::firstOrCreate(['name' => 'voter', 'guard_name' => 'web']);

    $ebMemberUuid = (string) Str::uuid();
    $referrer = Voter::factory()->create([
        'referral_code' => 'VOTERSHR',
        'earlybank_own_member_uuid' => $ebMemberUuid,
        'is_verified' => true,
        'is_active' => true,
    ]);

    $this->withCookie(CaptureEarlyBankReferral::COOKIE_NAME, $ebMemberUuid)
        ->post(route('register.voter.submit'), [
            'first_name' => 'New',
            'last_name' => 'Signup',
            'email' => 'new-eb-signup@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'zip_code' => '30301',
            'terms' => '1',
        ])->assertRedirect();

    $newVoter = Voter::where('email', 'new-eb-signup@example.test')->firstOrFail();

    expect($newVoter->referred_by_voter_id)->toBe($referrer->id);
    expect($newVoter->earlybank_member_id)->toBe($ebMemberUuid);

    // The referrer's list now reflects the signup driven by their own link.
    $referrer->refresh();
    expect($referrer->referrals()->count())->toBe(1);
});

test('an internal referral_code still wins attribution over an EB cookie when both are present', function () {
    Role::firstOrCreate(['name' => 'voter', 'guard_name' => 'web']);

    $internalReferrer = Voter::factory()->create(['referral_code' => 'INTERNAL1']);
    $ebMemberUuid = (string) Str::uuid();
    $ebReferrer = Voter::factory()->create(['earlybank_own_member_uuid' => $ebMemberUuid]);

    $this->withCookie(CaptureEarlyBankReferral::COOKIE_NAME, $ebMemberUuid)
        ->post(route('register.voter.submit'), [
            'first_name' => 'New',
            'last_name' => 'Signup',
            'email' => 'new-internal-signup@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'zip_code' => '30301',
            'referral_code' => 'INTERNAL1',
            'terms' => '1',
        ])->assertRedirect();

    $newVoter = Voter::where('email', 'new-internal-signup@example.test')->firstOrFail();

    expect($newVoter->referred_by_voter_id)->toBe($internalReferrer->id);
});
