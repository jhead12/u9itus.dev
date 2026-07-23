<?php

use App\Http\Middleware\CaptureEarlyBankReferral;
use App\Models\Voter;
use Illuminate\Support\Str;

// ---------------------------------------------------------------------------
// Both CaptureReferralContext and CaptureEarlyBankReferral read the same
// ?ref= query parameter. Internal U9itus codes are short alphanumeric strings
// (e.g. VOTER123); Early-bank links use a UUID. Before this fix,
// CaptureReferralContext treated any UUID as an invalid internal code and
// wiped the session/cookie, silently dropping attribution for a visitor who
// had already landed via a U9itus referral link.
// ---------------------------------------------------------------------------

test('landing with an internal referral code stores the session as usual', function () {
    $referrer = Voter::factory()->create(['referral_code' => 'VOTER123']);

    $response = $this->get('/?ref=' . $referrer->referral_code);

    $response->assertOk();
    $response->assertSessionHas('referral.code', $referrer->referral_code);
});

test('a later Early-bank UUID link does not clear an existing internal referral session', function () {
    $referrer = Voter::factory()->create(['referral_code' => 'VOTER123']);
    $ebMemberUuid = (string) Str::uuid();

    // First: a visitor arrives via a U9itus internal referral link.
    $this->get('/?ref=' . $referrer->referral_code)->assertOk();

    // Then: the same visitor clicks an Early-bank invite link.
    $response = $this->get('/?ref=' . $ebMemberUuid);

    $response->assertOk();
    $response->assertSessionHas('referral.code', $referrer->referral_code);
    $response->assertCookie(CaptureEarlyBankReferral::COOKIE_NAME, $ebMemberUuid);
});

test('landing with only an Early-bank UUID sets no internal referral session', function () {
    $ebMemberUuid = (string) Str::uuid();

    $response = $this->get('/?ref=' . $ebMemberUuid);

    $response->assertOk();
    $response->assertSessionMissing('referral.code');
    $response->assertCookie(CaptureEarlyBankReferral::COOKIE_NAME, $ebMemberUuid);
});

test('an invalid internal-looking code still clears any existing internal referral session', function () {
    $referrer = Voter::factory()->create(['referral_code' => 'VOTER123']);

    $this->get('/?ref=' . $referrer->referral_code)->assertOk();

    // A garbage code (not a UUID, not a real referral code) must still clear
    // the session — only UUID-shaped values are exempted for Early-bank.
    $response = $this->get('/?ref=NOTFOUND1');

    $response->assertOk();
    $response->assertSessionMissing('referral.code');
});
