<?php

use App\Models\CampaignTransaction;
use App\Models\PoliticalCampaign;
use App\Models\ReferralEarning;
use App\Models\User;
use App\Models\ViewSession;
use App\Models\Voter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    if (class_exists(\Spatie\Permission\Models\Role::class)) {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'voter', 'guard_name' => 'web']);
    }
});

function makeAdminForAnalyticsExports(): User
{
    $admin = User::factory()->create([
        'platform' => 'standalone',
        'user_type' => 'admin',
    ]);

    if (method_exists($admin, 'assignRole')) {
        $admin->assignRole('admin');
    }

    skipOnboarding($admin, 'admin');

    return $admin;
}

test('campaign accounting export is scoped to active payment mode and includes monthly rollup', function () {
    $admin = makeAdminForAnalyticsExports();

    $testCampaign = PoliticalCampaign::factory()->create(['title' => 'Test Mode Campaign']);
    $liveCampaign = PoliticalCampaign::factory()->create(['title' => 'Live Mode Campaign']);

    CampaignTransaction::query()->create([
        'campaign_id' => $testCampaign->id,
        'politician_id' => $testCampaign->politician_id,
        'transaction_type' => 'charge',
        'amount' => 50.00,
        'currency' => 'USD',
        'status' => 'succeeded',
        'metadata' => ['payment_mode' => 'test'],
    ]);

    CampaignTransaction::query()->create([
        'campaign_id' => $liveCampaign->id,
        'politician_id' => $liveCampaign->politician_id,
        'transaction_type' => 'charge',
        'amount' => 65.00,
        'currency' => 'USD',
        'status' => 'succeeded',
        'metadata' => ['payment_mode' => 'live'],
    ]);

    $voter = Voter::factory()->create();

    ViewSession::factory()->completed()->create([
        'political_campaign_id' => $testCampaign->id,
        'voter_id' => $voter->id,
        'status' => 'completed',
        'payment_status' => 'paid',
        'platform_revenue' => 0.60,
        'voter_payout_amount' => 0.25,
        'referral_commission' => 0.05,
    ]);

    ViewSession::factory()->completed()->create([
        'political_campaign_id' => $liveCampaign->id,
        'voter_id' => $voter->id,
        'status' => 'completed',
        'payment_status' => 'paid',
        'platform_revenue' => 0.60,
        'voter_payout_amount' => 0.25,
        'referral_commission' => 0.05,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.analytics.export.campaign-accounting'));

    $response->assertOk();

    $csv = $response->streamedContent();

    expect($csv)->toContain('"Payment Mode",test');
    expect($csv)->toContain('Monthly Rollup');
    expect($csv)->toContain('Test Mode Campaign');
    expect($csv)->not->toContain('Live Mode Campaign');
});

test('voter accounting export includes session and referral rows with monthly rollup', function () {
    $admin = makeAdminForAnalyticsExports();
    $campaign = PoliticalCampaign::factory()->create(['title' => 'Referral Accounting Campaign']);

    CampaignTransaction::query()->create([
        'campaign_id' => $campaign->id,
        'politician_id' => $campaign->politician_id,
        'transaction_type' => 'charge',
        'amount' => 40.00,
        'currency' => 'USD',
        'status' => 'succeeded',
        'metadata' => ['payment_mode' => 'test'],
    ]);

    $referredVoter = Voter::factory()->create([
        'full_name' => 'Referred Voter',
        'payment_method' => 'paypal',
        'paypal_email' => 'referred@example.com',
    ]);

    $referrerVoter = Voter::factory()->create([
        'full_name' => 'Referrer Voter',
        'payment_method' => 'paypal',
        'paypal_email' => 'referrer@example.com',
    ]);

    $session = ViewSession::factory()->completed()->create([
        'political_campaign_id' => $campaign->id,
        'voter_id' => $referredVoter->id,
        'status' => 'completed',
        'payment_status' => 'paid',
        'platform_revenue' => 0.60,
        'voter_payout_amount' => 0.25,
        'referral_commission' => 0.05,
    ]);

    ReferralEarning::query()->create([
        'referrer_voter_id' => $referrerVoter->id,
        'referred_voter_id' => $referredVoter->id,
        'view_session_id' => $session->id,
        'commission_amount' => 0.05,
        'referral_type' => ReferralEarning::TYPE_VOTER_VIEW,
        'paid' => true,
        'paid_at' => now(),
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.analytics.export.voter-accounting'));

    $response->assertOk();

    $csv = $response->streamedContent();

    expect($csv)->toContain('view_session');
    expect($csv)->toContain('referral_earning');
    expect($csv)->toContain('Referral Accounting Campaign');
    expect($csv)->toContain('Monthly Rollup');
});

test('admin analytics page shows gross revenue referral commissions and platform net for active payment mode', function () {
    config()->set('services.stripe.secret', 'sk_live_fake_admin_analytics');

    $admin = makeAdminForAnalyticsExports();

    $liveCampaign = PoliticalCampaign::factory()->create(['title' => 'Live Analytics Campaign']);
    $testCampaign = PoliticalCampaign::factory()->create(['title' => 'Test Analytics Campaign']);

    CampaignTransaction::query()->create([
        'campaign_id' => $liveCampaign->id,
        'politician_id' => $liveCampaign->politician_id,
        'transaction_type' => 'charge',
        'amount' => 100.00,
        'currency' => 'USD',
        'status' => 'succeeded',
        'metadata' => ['payment_mode' => 'live'],
    ]);

    CampaignTransaction::query()->create([
        'campaign_id' => $testCampaign->id,
        'politician_id' => $testCampaign->politician_id,
        'transaction_type' => 'charge',
        'amount' => 100.00,
        'currency' => 'USD',
        'status' => 'succeeded',
        'metadata' => ['payment_mode' => 'test'],
    ]);

    $voter = Voter::factory()->create();

    ViewSession::factory()->completed()->create([
        'political_campaign_id' => $liveCampaign->id,
        'voter_id' => $voter->id,
        'status' => 'completed',
        'payment_status' => 'paid',
        'platform_revenue' => 0.30,
        'voter_payout_amount' => 0.25,
        'referral_commission' => 0.05,
    ]);

    ViewSession::factory()->completed()->create([
        'political_campaign_id' => $testCampaign->id,
        'voter_id' => $voter->id,
        'status' => 'completed',
        'payment_status' => 'paid',
        'platform_revenue' => 9.30,
        'voter_payout_amount' => 0.25,
        'referral_commission' => 0.05,
    ]);

    $response = $this->actingAs($admin)->get(route('admin.analytics'));

    $response->assertOk();
    $response->assertSee('Referral Commissions');
    $response->assertSee('Platform Net');
    $response->assertViewHas('stats', function (array $stats) {
        return round((float) $stats['gross_revenue'], 2) === 0.60
            && round((float) $stats['net_revenue'], 2) === 0.30
            && round((float) $stats['total_referrals'], 2) === 0.05
            && round((float) $stats['avg_revenue_per_view'], 2) === 0.60
            && round((float) $stats['avg_profit_per_view'], 2) === 0.30
            && round((float) $stats['margin_percent'], 1) === 50.0;
    });
});
