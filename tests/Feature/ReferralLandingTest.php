<?php

use App\Models\Politician;
use App\Models\ReferralVisit;
use App\Models\User;
use App\Models\Voter;
use Spatie\Permission\Models\Role;

const TEST_PASSWORD = 'Password123!';
const REF_QUERY_PREFIX = '/?ref=';

test('home page shows referral program message for valid referral code', function () {
    $referrer = Voter::factory()->create([
        'referral_code' => 'INVITE123',
    ]);

    $response = $this->get(REF_QUERY_PREFIX.$referrer->referral_code);

    $response->assertOk();
    $response->assertSee('You were invited by a U9itus member.');
    $response->assertSee($referrer->referral_code);
    $response->assertSessionHas('referral.code', $referrer->referral_code);

    $this->assertDatabaseHas('referral_visits', [
        'referral_code' => $referrer->referral_code,
        'referrer_voter_id' => $referrer->id,
    ]);
});

test('registration chooser preserves referral links', function () {
    $referrer = Voter::factory()->create([
        'referral_code' => 'VOTER777',
    ]);

    $this->get(REF_QUERY_PREFIX.$referrer->referral_code)->assertOk();

    $response = $this->get('/register');

    $response->assertOk();
    $response->assertSee(route('register.voter', ['ref' => $referrer->referral_code], false), false);
    $response->assertSee(route('register.politician', ['ref' => $referrer->referral_code], false), false);
});

test('voter registration can use stored referral context when form field is empty', function () {
    Role::findOrCreate('voter', 'web');

    $referrer = Voter::factory()->create([
        'referral_code' => 'SAVEVOTE',
    ]);

    $email = 'ref-voter@example.com';

    $response = $this->withSession(['referral.code' => $referrer->referral_code])->post('/register/voter', [
        'first_name' => 'Ref',
        'last_name' => 'Voter',
        'email' => $email,
        'password' => TEST_PASSWORD,
        'password_confirmation' => TEST_PASSWORD,
        'terms' => '1',
        'zip_code' => '90210',
    ]);

    $response->assertRedirect(route('verification.notice', absolute: false));

    $this->assertDatabaseHas('voters', [
        'email' => $email,
        'referred_by_voter_id' => $referrer->id,
    ]);
});

test('politician registration can use stored referral context when query is missing', function () {
    Role::findOrCreate('politician', 'web');

    $referrer = Politician::factory()->create([
        'referral_code' => 'POLI8888',
    ]);

    $email = 'ref-politician@example.com';

    $response = $this->withSession(['referral.code' => $referrer->referral_code])->post('/register/politician', [
        'first_name' => 'Ref',
        'last_name' => 'Politician',
        'email' => $email,
        'password' => TEST_PASSWORD,
        'password_confirmation' => TEST_PASSWORD,
        'phone' => '5555551212',
        'political_office' => 'Mayor',
        'party' => 'Independent',
        'governance_level' => 'city',
        'state' => 'TX',
        'city' => 'Austin',
        'terms' => '1',
    ]);

    $response->assertRedirect(route('verification.notice', absolute: false));

    $user = User::where('email', $email)->first();

    expect($user)->not->toBeNull();

    $this->assertDatabaseHas('politicians', [
        'user_id' => $user->id,
        'referred_by_politician_id' => $referrer->id,
    ]);
});

test('invalid referral code does not activate referral program state', function () {
    $response = $this->get(REF_QUERY_PREFIX.'NOTFOUND1');

    $response->assertOk();
    $response->assertDontSee('You were invited by a U9itus member.');
    $response->assertSessionMissing('referral.code');
    expect(ReferralVisit::count())->toBe(0);
});

test('voter registration marks referral visit as converted', function () {
    Role::findOrCreate('voter', 'web');

    $referrer = Voter::factory()->create([
        'referral_code' => 'TRACK123',
    ]);

    $this->get(REF_QUERY_PREFIX.$referrer->referral_code)->assertOk();

    $email = 'conversion-voter@example.com';

    $response = $this->post('/register/voter', [
        'first_name' => 'Track',
        'last_name' => 'Conversion',
        'email' => $email,
        'password' => TEST_PASSWORD,
        'password_confirmation' => TEST_PASSWORD,
        'terms' => '1',
        'zip_code' => '90210',
    ]);

    $response->assertRedirect(route('verification.notice', absolute: false));

    $user = User::where('email', $email)->first();

    expect($user)->not->toBeNull();

    $this->assertDatabaseHas('referral_visits', [
        'referral_code' => $referrer->referral_code,
        'converted_user_id' => $user->id,
        'converted_user_type' => 'voter',
    ]);
});
