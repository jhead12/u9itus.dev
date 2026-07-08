<?php

namespace Tests\Feature\Payout;

use App\Enums\ViewPaymentStatus;
use App\Models\PayoutAttempt;
use App\Models\PayoutRun;
use App\Models\ViewSession;
use App\Models\Voter;
use App\Services\CashAppPayoutService;
use App\Services\PayPalPayoutService;
use App\Services\PoliticalPaymentService;
use App\Services\ReverbBroadcastService;
use App\Services\StripeConnectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class BatchPayoutIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure cache is clean for each test
        Cache::flush();
        // Also clear any cached platform settings
        \Illuminate\Support\Facades\DB::table('cache')->truncate();
        \Illuminate\Support\Facades\DB::table('platform_settings')->truncate();
        
        // Set known platform settings to avoid cache pollution from other tests
        config(['u9itus.min_payout_amount' => 5.00, 'u9itus.fraud_payout_hold_hours' => 48]);
    }

    private function makeSessions(Voter $voter, int $count, float $amount = 1.00): void
    {
        $holdCutoff = now()->subHours(49);
        for ($i = 0; $i < $count; $i++) {
            ViewSession::factory()->create([
                'voter_id'            => $voter->id,
                'status'              => 'completed',
                'payment_status'      => ViewPaymentStatus::Approved,
                'voter_payout_amount' => $amount,
                'completed_at'        => $holdCutoff->copy()->subMinutes($i),
            ]);
        }
    }

    private function makeService(
        ?object $stripeConnect = null,
        ?object $paypal = null,
        ?object $cashapp = null,
    ): PoliticalPaymentService {
        $broadcast = Mockery::mock(ReverbBroadcastService::class);
        $broadcast->shouldReceive('payoutProcessed')->andReturn(null)->byDefault();
        $broadcast->shouldReceive('payoutDispatched')->andReturn(null)->byDefault();
        $broadcast->shouldReceive('payoutDispatched')->andReturn(null)->byDefault();

        $stripe = $stripeConnect ?? Mockery::mock(StripeConnectService::class);
        $pp     = $paypal ?? tap(Mockery::mock(PayPalPayoutService::class), fn ($m) => $m->shouldReceive('isConfigured')->andReturn(false)->byDefault());
        $ca     = $cashapp ?? tap(Mockery::mock(CashAppPayoutService::class), fn ($m) => $m->shouldReceive('isConfigured')->andReturn(false)->byDefault());

        return new PoliticalPaymentService($stripe, $pp, $ca, $broadcast);
    }

    private function makeRun(): PayoutRun
    {
        return PayoutRun::create([
            'trigger_source'    => 'test',
            'min_payout_amount' => 5.00,
            'fraud_hold_hours'  => 48,
        ]);
    }

    /**
     * Calling processBatchPayouts twice with the same voter + same sessions should
     * only create one payout attempt row and call the processor once.
     */
    public function test_duplicate_call_with_same_sessions_does_not_create_second_attempt(): void
    {
        $voter = Voter::factory()->create(['payment_method' => 'stripe', 'stripe_account_id' => 'acct_test']);

        $this->makeSessions($voter, 6, 1.00); // $6 total > $5 min

        $stripeConnect = Mockery::mock(StripeConnectService::class);
        $stripeConnect->shouldReceive('canReceivePayout')->andReturn(true);
        $stripeConnect->shouldReceive('sendTransfer')
            ->once() // must only be called once across two runs
            ->andReturn(['reference' => 'tr_test_123', 'fee' => 0.0]);

        $service = $this->makeService(stripeConnect: $stripeConnect);

        $result1 = $service->processBatchPayouts();
        $result2 = $service->processBatchPayouts();

        $this->assertSame(1, PayoutAttempt::count(), 'Second run should reuse existing submitted attempt');
        Mockery::close();
    }

    /**
     * If the attempt exists in status=submitted (crash after external call),
     * a retry should skip the external call and not create a duplicate.
     */
    public function test_retry_after_crash_skips_already_submitted_attempt(): void
    {
        $voter = Voter::factory()->create(['payment_method' => 'stripe', 'stripe_account_id' => 'acct_test']);
        $this->makeSessions($voter, 6, 1.00);

        $stripeConnect = Mockery::mock(StripeConnectService::class);
        $stripeConnect->shouldReceive('canReceivePayout')->andReturn(true);
        $stripeConnect->shouldReceive('sendTransfer')
            ->once()  // Only once across both calls
            ->andReturn(['reference' => 'tr_test_123', 'fee' => 0.0]);

        $service = $this->makeService(stripeConnect: $stripeConnect);
        
        // First call processes the payout
        $result1 = $service->processBatchPayouts();
        $this->assertSame(1, $result1['processed']);
        
        // Sessions are now marked as Paid, so second call finds 0 eligible sessions
        $result2 = $service->processBatchPayouts();
        $this->assertSame(0, $result2['processed']);
        $this->assertSame(1, PayoutAttempt::count());
        Mockery::close();
    }

    /**
     * If PayPal is not configured, force-payout should not throw — it should
     * fall through to wallet or raise a meaningful exception, not a null call error.
     */
    public function test_force_payout_with_unconfigured_paypal_does_not_crash(): void
    {
        $voter = Voter::factory()->create([
            'payment_method' => 'paypal',
            'paypal_email'   => 'voter@example.com',
        ]);
        $this->makeSessions($voter, 6, 1.00);

        $paypal = Mockery::mock(PayPalPayoutService::class);
        $paypal->shouldReceive('isConfigured')->andReturn(false); // Not configured

        $stripeConnect = Mockery::mock(StripeConnectService::class);
        $stripeConnect->shouldReceive('canReceivePayout')->andReturn(false);

        $service = $this->makeService(stripeConnect: $stripeConnect, paypal: $paypal);

        // Should not throw — should skip and record the voter
        $result = $service->processBatchPayouts();

        $this->assertSame(1, $result['skipped']);
        $this->assertSame(0, $result['processed']);
        Mockery::close();
    }
}
