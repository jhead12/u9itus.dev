<?php

namespace Tests\Feature\Payout;

use App\Enums\CampaignStatus;
use App\Enums\ApprovalStatus;
use App\Enums\ViewPaymentStatus;
use App\Enums\ViewSessionStatus;
use App\Jobs\ProcessBatchPayoutsJob;
use App\Models\Citizen;
use App\Models\CitizenCampaign;
use App\Models\CitizenViewSession;
use App\Models\PayoutAttempt;
use App\Models\PayoutRun;
use App\Models\User;
use App\Models\ViewSession;
use App\Models\Voter;
use App\Services\CashAppPayoutService;
use App\Services\PayPalPayoutService;
use App\Services\PoliticalPaymentService;
use App\Services\ReverbBroadcastService;
use App\Services\StripeConnectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

/**
 * ARCH-CITIZEN: citizen-campaign voter payouts now flow through the same
 * settlement path as political payouts. A voter can accrue earnings from
 * BOTH campaign systems; requestPayout + processBatchPayoutsForRun must mark
 * both ViewSession and CitizenViewSession rows Paid and decrement the single
 * pending_earnings balance by the combined approved sum — exactly once.
 *
 * Previously citizen earnings were stranded: credited to pending_earnings but
 * never queued (requestPayout touched only ViewSession) and never swept
 * (processBatchPayoutsForRun queried only view_sessions).
 */
class CitizenPayoutSettlementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        DB::table('cache')->truncate();
        DB::table('platform_settings')->truncate();
        config(['u9itus.min_payout_amount' => 5.00, 'u9itus.fraud_payout_hold_hours' => 48]);
    }

    private function bindService(): PoliticalPaymentService
    {
        $broadcast = Mockery::mock(ReverbBroadcastService::class);
        $broadcast->shouldReceive('payoutProcessed')->andReturn(null)->byDefault();
        $broadcast->shouldReceive('payoutDispatched')->andReturn(null)->byDefault();

        $stripe = Mockery::mock(StripeConnectService::class);
        $pp     = tap(Mockery::mock(PayPalPayoutService::class), fn ($m) => $m->shouldReceive('isConfigured')->andReturn(false)->byDefault());
        $ca     = tap(Mockery::mock(CashAppPayoutService::class), fn ($m) => $m->shouldReceive('isConfigured')->andReturn(false)->byDefault());

        $service = new PoliticalPaymentService($stripe, $pp, $ca, $broadcast);
        $this->app->instance(PoliticalPaymentService::class, $service);

        return $service;
    }

    private function makePoliticalSessions(Voter $voter, int $count, float $amount = 2.00): void
    {
        $holdCutoff = now()->subHours(49);
        for ($i = 0; $i < $count; $i++) {
            ViewSession::factory()->create([
                'voter_id'            => $voter->id,
                'status'              => ViewSessionStatus::Completed,
                'payment_status'      => ViewPaymentStatus::Approved,
                'voter_payout_amount' => $amount,
                'completed_at'        => $holdCutoff->copy()->subMinutes($i),
            ]);
        }
    }

    private function makeCitizenSessions(Voter $voter, int $count, float $amount = 2.00): void
    {
        $campaign = CitizenCampaign::factory()->active()->create([
            'citizen_id' => Citizen::factory()->create()->id,
        ]);
        $holdCutoff = now()->subHours(49);
        for ($i = 0; $i < $count; $i++) {
            CitizenViewSession::create([
                'citizen_campaign_id' => $campaign->id,
                'voter_id'             => $voter->id,
                'status'               => ViewSessionStatus::Completed,
                'completed_at'         => $holdCutoff->copy()->subMinutes($i),
                'payment_status'       => ViewPaymentStatus::Approved,
                'voter_payout_amount' => $amount,
            ]);
        }
    }

    public function test_wallet_payout_settles_both_political_and_citizen_sessions(): void
    {
        // Voter accrues $4 from political views + $4 from citizen views = $8.
        $voter = Voter::factory()->create([
            'payment_method'   => 'wallet',
            'pending_earnings' => 8.00,
            'total_earned'     => 0.00,
            'wallet_balance'   => 0.00,
        ]);
        $this->makePoliticalSessions($voter, 2, 2.00);
        $this->makeCitizenSessions($voter, 2, 2.00);

        $this->bindService();
        $run = (app(PoliticalPaymentService::class))->createPayoutRun(
            triggeredByAdminId: User::factory()->create()->id,
            triggerSource: 'admin',
        );

        ProcessBatchPayoutsJob::dispatch($run);

        $run->refresh();
        $voter->refresh();

        $this->assertSame('completed', $run->status);
        $this->assertSame(1, (int) $run->processed_count);
        $this->assertSame(0, (int) $run->skipped_count);
        $this->assertSame(8.00, (float) $run->total_paid);

        // Both session types are marked Paid with the wallet processor.
        $this->assertSame(
            2,
            ViewSession::where('voter_id', $voter->id)->where('payment_status', ViewPaymentStatus::Paid)->count(),
        );
        $this->assertSame(
            2,
            CitizenViewSession::where('voter_id', $voter->id)->where('payment_status', ViewPaymentStatus::Paid)->count(),
        );
        $this->assertTrue(CitizenViewSession::where('voter_id', $voter->id)->whereNotNull('paid_at')->exists());
        $this->assertSame(
            'wallet',
            CitizenViewSession::where('voter_id', $voter->id)->value('processor_executed'),
        );

        // The single pending_earnings balance is decremented by the COMBINED sum.
        $this->assertSame(0.00, (float) $voter->pending_earnings);
        $this->assertSame(8.00, (float) $voter->total_earned);
        $this->assertSame(8.00, (float) $voter->wallet_balance);

        // One attempt records both session types in its idempotency set.
        $attempt = PayoutAttempt::first();
        $this->assertNotNull($attempt);
        $this->assertSame('paid', $attempt->status);
        $this->assertSame(8.00, (float) $attempt->amount);
        $sessionIds = $attempt->session_ids ?? [];
        $this->assertCount(4, $sessionIds);
        // Citizen ids are prefixed 'c' to stay distinct from political ids.
        $this->assertSame(2, count(array_filter($sessionIds, fn ($id) => is_string($id) && str_starts_with($id, 'c'))));

        Mockery::close();
    }

    public function test_voter_with_only_citizen_earnings_still_settles(): void
    {
        // Regression guard: before the fix, a voter whose earnings came ONLY
        // from citizen campaigns was invisible to the batch sweep.
        $voter = Voter::factory()->create([
            'payment_method'   => 'wallet',
            'pending_earnings' => 6.00,
            'total_earned'     => 0.00,
        ]);
        $this->makeCitizenSessions($voter, 3, 2.00);

        $this->bindService();
        $run = (app(PoliticalPaymentService::class))->createPayoutRun();

        ProcessBatchPayoutsJob::dispatch($run);

        $run->refresh();
        $voter->refresh();

        $this->assertSame('completed', $run->status);
        $this->assertSame(1, (int) $run->processed_count);
        $this->assertSame(6.00, (float) $run->total_paid);
        $this->assertSame(
            3,
            CitizenViewSession::where('voter_id', $voter->id)->where('payment_status', ViewPaymentStatus::Paid)->count(),
        );
        $this->assertSame(0.00, (float) $voter->pending_earnings);
        $this->assertSame(6.00, (float) $voter->total_earned);

        Mockery::close();
    }

    public function test_redispatching_a_run_does_not_double_pay_citizen_earnings(): void
    {
        $voter = Voter::factory()->create([
            'payment_method'   => 'wallet',
            'pending_earnings' => 8.00,
        ]);
        $this->makePoliticalSessions($voter, 2, 2.00);
        $this->makeCitizenSessions($voter, 2, 2.00);

        $this->bindService();

        $run1 = (app(PoliticalPaymentService::class))->createPayoutRun();
        ProcessBatchPayoutsJob::dispatch($run1);

        $run2 = (app(PoliticalPaymentService::class))->createPayoutRun();
        ProcessBatchPayoutsJob::dispatch($run2);

        $voter->refresh();

        // Second run finds nothing Approve-eligible (all already Paid) and
        // decrements nothing — pending_earnings stays at 0, not -8.
        $this->assertSame(0.00, (float) $voter->pending_earnings);
        $this->assertSame(8.00, (float) $voter->total_earned);
        $this->assertSame(1, PayoutAttempt::count());
        $this->assertSame(
            2,
            CitizenViewSession::where('voter_id', $voter->id)->where('payment_status', ViewPaymentStatus::Paid)->count(),
        );

        Mockery::close();
    }
}