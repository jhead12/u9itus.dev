<?php

use App\Models\Politician;
use App\Models\PoliticalCampaign;
use App\Models\CampaignTransaction;
use App\Models\ReferralEarning;
use App\Models\User;
use App\Models\Voter;
use App\Models\ViewSession;
use App\Enums\ApprovalStatus;
use App\Enums\CampaignStatus;
use App\Enums\PaymentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use App\Models\EmailTemplate;

uses(RefreshDatabase::class);

function politicianReferralUser(array $politicianOverrides = []): array
{
    Role::firstOrCreate(['name' => 'politician', 'guard_name' => 'web']);

    $user = User::factory()->create([
        'user_type' => 'politician',
    ]);
    $user->assignRole('politician');

    skipOnboarding($user, 'politician');

    $politician = Politician::factory()->create(array_merge([
        'user_id' => $user->id,
        'referral_code' => 'POLSHARE',
        'is_active' => true,
    ], $politicianOverrides));

    return [$user, $politician];
}

test('voter referral page renders email and social share links', function () {
    Role::firstOrCreate(['name' => 'voter', 'guard_name' => 'web']);

    $user = User::factory()->create([
        'user_type' => 'voter',
    ]);
    $user->assignRole('voter');

    skipOnboarding($user, 'voter');

    Voter::factory()->create([
        'user_id' => $user->id,
        'referral_code' => 'VOTERSHR',
        'is_verified' => true,
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)->get(route('voter.referrals'));

    $response->assertOk();
    $response->assertSee('Email Draft');
    $response->assertSee('https://twitter.com/intent/tweet?text=Join%20U9itus%20as%20a%20voter%20using%20my%20referral%20link', false);
    $response->assertSee('https://twitter.com/intent/tweet?text=Join%20U9itus%20as%20a%20politician%20using%20my%20referral%20link', false);
    $response->assertSee('https://api.whatsapp.com/send?text=Join%20U9itus%20as%20a%20voter%20using%20my%20referral%20link', false);
    $response->assertSee('https://t.me/share/url?url=', false);
    $response->assertSee('mailto:?subject=Join%20U9itus%20as%20a%20voter%20with%20my%20referral%20link', false);
});

test('politician referral page renders email and social share links', function () {
    [$user] = politicianReferralUser();

    $response = $this->actingAs($user)->get(route('politician.referrals'));

    $response->assertOk();
    $response->assertSee('Email Draft');
    $response->assertSee('https://twitter.com/intent/tweet?text=Join%20U9itus%20as%20a%20voter%20using%20my%20referral%20link', false);
    $response->assertSee('https://twitter.com/intent/tweet?text=Join%20U9itus%20as%20a%20politician%20using%20my%20referral%20link', false);
    $response->assertSee('https://www.facebook.com/sharer/sharer.php?u=', false);
    $response->assertSee('https://api.whatsapp.com/send?text=Join%20U9itus%20as%20a%20politician%20using%20my%20referral%20link', false);
    $response->assertSee('mailto:?subject=Join%20U9itus%20as%20a%20politician%20with%20my%20referral%20link', false);
});

test('voter referral page reflects admin-overridden share message', function () {
    Role::firstOrCreate(['name' => 'voter', 'guard_name' => 'web']);

    $user = User::factory()->create(['user_type' => 'voter']);
    $user->assignRole('voter');
    skipOnboarding($user, 'voter');
    Voter::factory()->create([
        'user_id' => $user->id,
        'referral_code' => 'OVRTEST',
        'is_verified' => true,
        'is_active' => true,
    ]);

    EmailTemplate::updateOrCreate(['key' => 'referral_voter_share'], [
        'name'                => 'Referral: Voter Signup Share',
        'category'            => 'referral',
        'subject_override'    => 'Custom voter title',
        'body_override'       => 'Custom voter share message from admin.',
        'available_variables' => ['{{referral_link}}'],
        'is_active'           => true,
    ]);

    $response = $this->actingAs($user)->get(route('voter.referrals'));

    $response->assertOk();
    $response->assertSee('Custom voter share message from admin.');
    $response->assertSee('mailto:?subject=Custom%20voter%20title', false);
});

test('politician referral page reflects admin-overridden share message', function () {
    [$user] = politicianReferralUser();

    EmailTemplate::updateOrCreate(['key' => 'referral_politician_share'], [
        'name'                => 'Referral: Politician Signup Share',
        'category'            => 'referral',
        'subject_override'    => null,
        'body_override'       => 'Custom politician share message from admin.',
        'available_variables' => ['{{referral_link}}'],
        'is_active'           => true,
    ]);

    $response = $this->actingAs($user)->get(route('politician.referrals'));

    $response->assertOk();
    $response->assertSee('Custom politician share message from admin.');
    $response->assertSee('https://twitter.com/intent/tweet?text=Custom%20politician%20share%20message%20from%20admin.', false);
});

test('inactive referral template falls back to default share message', function () {
    Role::firstOrCreate(['name' => 'voter', 'guard_name' => 'web']);

    $user = User::factory()->create(['user_type' => 'voter']);
    $user->assignRole('voter');
    skipOnboarding($user, 'voter');
    Voter::factory()->create([
        'user_id' => $user->id,
        'referral_code' => 'INACTREF',
        'is_verified' => true,
        'is_active' => true,
    ]);

    // Seed an inactive override — should NOT be used
    EmailTemplate::updateOrCreate(['key' => 'referral_voter_share'], [
        'name'                => 'Referral: Voter Signup Share',
        'category'            => 'referral',
        'subject_override'    => 'Should not appear',
        'body_override'       => 'Should not appear in page output.',
        'available_variables' => [],
        'is_active'           => false,
    ]);

    $response = $this->actingAs($user)->get(route('voter.referrals'));

    $response->assertOk();
    $response->assertDontSee('Should not appear');
    $response->assertSee('Join U9itus as a voter using my referral link');
});

test('voter referral page filters commissions to the active stripe payment mode', function () {
    config()->set('services.stripe.secret', 'sk_live_fake_voter_referral_filter');

    Role::firstOrCreate(['name' => 'voter', 'guard_name' => 'web']);

    $user = User::factory()->create(['user_type' => 'voter']);
    $user->assignRole('voter');
    skipOnboarding($user, 'voter');

    $referrer = Voter::factory()->create([
        'user_id' => $user->id,
        'referral_code' => 'LIVEONLY',
        'is_verified' => true,
        'is_active' => true,
    ]);

    $referredVoter = Voter::factory()->create([
        'referred_by_voter_id' => $referrer->id,
        'is_verified' => true,
        'is_active' => true,
    ]);

    $politician = Politician::factory()->create(['is_active' => true]);

    $liveCampaign = PoliticalCampaign::factory()->create([
        'politician_id' => $politician->id,
        'title' => 'Live Production Campaign',
        'status' => CampaignStatus::Active->value,
        'approval_status' => ApprovalStatus::Approved->value,
        'payment_status' => PaymentStatus::Captured->value,
        'stripe_payment_intent_id' => 'pi_live_referral_campaign',
    ]);

    $testCampaign = PoliticalCampaign::factory()->create([
        'politician_id' => $politician->id,
        'title' => 'Internal Test Campaign',
        'status' => CampaignStatus::Active->value,
        'approval_status' => ApprovalStatus::Approved->value,
        'payment_status' => PaymentStatus::Captured->value,
        'stripe_payment_intent_id' => 'pi_test_referral_campaign',
    ]);

    CampaignTransaction::create([
        'campaign_id' => $liveCampaign->id,
        'politician_id' => $politician->id,
        'transaction_type' => 'charge',
        'amount' => 50.00,
        'currency' => 'USD',
        'status' => 'succeeded',
        'metadata' => ['payment_mode' => 'live'],
    ]);

    CampaignTransaction::create([
        'campaign_id' => $testCampaign->id,
        'politician_id' => $politician->id,
        'transaction_type' => 'charge',
        'amount' => 50.00,
        'currency' => 'USD',
        'status' => 'succeeded',
        'metadata' => ['payment_mode' => 'test'],
    ]);

    $liveSession = ViewSession::factory()->create([
        'voter_id' => $referredVoter->id,
        'political_campaign_id' => $liveCampaign->id,
    ]);

    $testSession = ViewSession::factory()->create([
        'voter_id' => $referredVoter->id,
        'political_campaign_id' => $testCampaign->id,
    ]);

    ReferralEarning::create([
        'referrer_voter_id' => $referrer->id,
        'referred_voter_id' => $referredVoter->id,
        'view_session_id' => $liveSession->id,
        'commission_amount' => 1.50,
        'payment_mode' => 'live',
        'referral_type' => ReferralEarning::TYPE_VOTER_VIEW,
    ]);

    ReferralEarning::create([
        'referrer_voter_id' => $referrer->id,
        'referred_voter_id' => $referredVoter->id,
        'view_session_id' => $testSession->id,
        'commission_amount' => 9.50,
        'payment_mode' => 'test',
        'referral_type' => ReferralEarning::TYPE_VOTER_VIEW,
    ]);

    $liveProcurementPolitician = Politician::factory()->create(['full_name' => 'Live Recruit']);
    $testProcurementPolitician = Politician::factory()->create(['full_name' => 'Test Recruit']);

    ReferralEarning::create([
        'referrer_voter_id' => $referrer->id,
        'referred_voter_id' => null,
        'view_session_id' => null,
        'commission_amount' => 3.25,
        'payment_mode' => 'live',
        'referral_type' => ReferralEarning::TYPE_POLITICIAN_PROCUREMENT,
        'politician_id' => $liveProcurementPolitician->id,
    ]);

    ReferralEarning::create([
        'referrer_voter_id' => $referrer->id,
        'referred_voter_id' => null,
        'view_session_id' => null,
        'commission_amount' => 8.25,
        'payment_mode' => 'test',
        'referral_type' => ReferralEarning::TYPE_POLITICIAN_PROCUREMENT,
        'politician_id' => $testProcurementPolitician->id,
    ]);

    $response = $this->actingAs($user)->get(route('voter.referrals'));

    $response->assertOk();

    // Historical totals are still summed from the DB for backwards-compatibility reporting.
    // New commissions route through Early-bank; internal ReferralEarning rows no longer created.
    $response->assertViewHas('totalReferralEarnings', 1.5);
    $response->assertViewHas('totalProcurementEarnings', 3.25);

    // Collections are now empty — the page no longer renders per-earning detail rows.
    $response->assertViewHas('referralEarnings', fn ($earnings) => $earnings->count() === 0);
    $response->assertViewHas('procurementEarnings', fn ($earnings) => $earnings->count() === 0);
});

test('politician referral page filters commissions to the active stripe payment mode', function () {
    config()->set('services.stripe.secret', 'sk_live_fake_politician_referral_filter');

    [$user, $politician] = politicianReferralUser(['referral_code' => 'POLLIVE']);

    $liveReferredVoter = Voter::factory()->create([
        'full_name' => 'Live Referred Voter',
        'referred_by_politician_id' => $politician->id,
        'is_verified' => true,
        'is_active' => true,
    ]);

    $testReferredVoter = Voter::factory()->create([
        'full_name' => 'Test Referred Voter',
        'referred_by_politician_id' => $politician->id,
        'is_verified' => true,
        'is_active' => true,
    ]);

    ReferralEarning::create([
        'referrer_politician_id' => $politician->id,
        'referred_voter_id' => $liveReferredVoter->id,
        'view_session_id' => null,
        'commission_amount' => 2.75,
        'payment_mode' => 'live',
        'referral_type' => ReferralEarning::TYPE_VOTER_VIEW,
    ]);

    ReferralEarning::create([
        'referrer_politician_id' => $politician->id,
        'referred_voter_id' => $testReferredVoter->id,
        'view_session_id' => null,
        'commission_amount' => 7.75,
        'payment_mode' => 'test',
        'referral_type' => ReferralEarning::TYPE_VOTER_VIEW,
    ]);

    $liveReferredPolitician = Politician::factory()->create(['full_name' => 'Live Recruit Politician']);
    $testReferredPolitician = Politician::factory()->create(['full_name' => 'Test Recruit Politician']);

    ReferralEarning::create([
        'referrer_politician_id' => $politician->id,
        'referred_voter_id' => null,
        'view_session_id' => null,
        'commission_amount' => 4.50,
        'payment_mode' => 'live',
        'referral_type' => ReferralEarning::TYPE_POLITICIAN_PROCUREMENT,
        'politician_id' => $liveReferredPolitician->id,
    ]);

    ReferralEarning::create([
        'referrer_politician_id' => $politician->id,
        'referred_voter_id' => null,
        'view_session_id' => null,
        'commission_amount' => 11.50,
        'payment_mode' => 'test',
        'referral_type' => ReferralEarning::TYPE_POLITICIAN_PROCUREMENT,
        'politician_id' => $testReferredPolitician->id,
    ]);

    $response = $this->actingAs($user)->get(route('politician.referrals'));

    $response->assertOk();
    $response->assertSee('Live Recruit Politician');
    $response->assertDontSee('Test Recruit Politician');
    $response->assertViewHas('totalVoterViewEarnings', 2.75);
    $response->assertViewHas('totalProcurementEarnings', 4.5);
    $response->assertViewHas('voterViewEarnings', fn ($earnings) => $earnings->count() === 1 && $earnings->first()->payment_mode === 'live');
    $response->assertViewHas('procurementEarnings', fn ($earnings) => $earnings->count() === 1 && $earnings->first()->payment_mode === 'live');
});
