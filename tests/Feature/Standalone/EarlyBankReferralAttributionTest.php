<?php

use App\Http\Middleware\CaptureEarlyBankReferral;
use App\Models\Citizen;
use App\Models\Politician;
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

test('registering as a citizen via the EB member UUID link attributes referred_by_voter_id and earlybank_member_id', function () {
    Role::firstOrCreate(['name' => 'citizen', 'guard_name' => 'web']);

    $ebMemberUuid = (string) Str::uuid();
    $referrer = Voter::factory()->create(['earlybank_own_member_uuid' => $ebMemberUuid]);

    $this->withCookie(CaptureEarlyBankReferral::COOKIE_NAME, $ebMemberUuid)
        ->post(route('register.citizen.submit'), [
            'first_name' => 'New',
            'last_name' => 'Citizen',
            'email' => 'new-eb-citizen@example.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'phone' => '555-222-3333',
            'state' => 'CA',
            'city' => 'Fresno',
            'address_line_1' => '123 Main St',
            'zip' => '93701',
            'terms' => '1',
        ])->assertRedirect();

    $citizen = Citizen::where('email', 'new-eb-citizen@example.test')->first()
        ?? Citizen::whereHas('user', fn ($q) => $q->where('email', 'new-eb-citizen@example.test'))->firstOrFail();

    expect($citizen->referred_by_voter_id)->toBe($referrer->id);
    expect($citizen->earlybank_member_id)->toBe($ebMemberUuid);
});

test('registering as a politician via the EB member UUID link attributes referred_by_voter_id and earlybank_member_id', function () {
    Role::firstOrCreate(['name' => 'politician', 'guard_name' => 'web']);

    $ebMemberUuid = (string) Str::uuid();
    $referrer = Voter::factory()->create(['earlybank_own_member_uuid' => $ebMemberUuid]);

    $this->withCookie(CaptureEarlyBankReferral::COOKIE_NAME, $ebMemberUuid)
        ->post(route('register.politician.submit'), [
            'first_name' => 'New',
            'last_name' => 'Politician',
            'email' => 'new-eb-politician@example.test',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'phone' => '555-111-2222',
            'political_office' => 'U.S. Senator',
            'party' => 'Democratic',
            'governance_level' => 'Federal',
            'state' => 'CA',
            'city' => 'Los Angeles',
            'terms' => '1',
        ])->assertRedirect();

    $politician = Politician::whereHas('user', fn ($q) => $q->where('email', 'new-eb-politician@example.test'))->firstOrFail();

    expect($politician->referred_by_voter_id)->toBe($referrer->id);
    expect($politician->earlybank_member_id)->toBe($ebMemberUuid);
});
