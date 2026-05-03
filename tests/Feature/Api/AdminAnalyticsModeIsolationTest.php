<?php

namespace Tests\Feature\Api;

use App\Models\CampaignTransaction;
use App\Models\PoliticalCampaign;
use App\Models\Politician;
use App\Models\ReferralEarning;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AdminAnalyticsModeIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('auth.guards.sanctum', [
            'driver' => 'session',
            'provider' => 'users',
        ]);

        if (class_exists(\Spatie\Permission\Models\Role::class)) {
            \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        }
    }

    public function test_admin_analytics_referral_totals_are_scoped_to_active_payment_mode(): void
    {
        config()->set('services.stripe.secret', 'sk_live_fake_analytics_mode_test');

        $admin = User::factory()->create();
        if (method_exists($admin, 'assignRole')) {
            $admin->assignRole('admin');
        }

        $politician = Politician::factory()->create();
        $campaign = PoliticalCampaign::factory()->create([
            'politician_id' => $politician->id,
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        CampaignTransaction::query()->create([
            'campaign_id' => $campaign->id,
            'politician_id' => $politician->id,
            'transaction_type' => 'charge',
            'amount' => 100.00,
            'currency' => 'USD',
            'status' => 'succeeded',
            'metadata' => ['payment_mode' => 'live'],
        ]);

        ReferralEarning::query()->create([
            'referrer_voter_id' => null,
            'referrer_politician_id' => null,
            'referred_voter_id' => null,
            'view_session_id' => null,
            'commission_amount' => 1.50,
            'payment_mode' => ReferralEarning::PAYMENT_MODE_LIVE,
            'referral_type' => ReferralEarning::TYPE_VOTER_VIEW,
            'politician_id' => $politician->id,
            'paid' => false,
            'paid_at' => null,
        ]);

        ReferralEarning::query()->create([
            'referrer_voter_id' => null,
            'referrer_politician_id' => null,
            'referred_voter_id' => null,
            'view_session_id' => null,
            'commission_amount' => 9.99,
            'payment_mode' => ReferralEarning::PAYMENT_MODE_TEST,
            'referral_type' => ReferralEarning::TYPE_VOTER_VIEW,
            'politician_id' => $politician->id,
            'paid' => false,
            'paid_at' => null,
        ]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/analytics');

        $response->assertOk();
        $response->assertJsonPath('overview.payment_mode', 'live');
        $response->assertJsonPath('overview.total_referral_commissions', 1.5);
    }
}
