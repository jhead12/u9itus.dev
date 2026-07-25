<?php

namespace Tests\Feature\Payout;

use App\Enums\ViewPaymentStatus;
use App\Jobs\ProcessBatchPayoutsJob;
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
use Mockery;
use Tests\TestCase;

/**
 * ARCH-1: the batch payout run is dispatched as a queued job. These cover the
 * run status machine (pending → running → completed|failed), per-voter counter
 * increments, and idempotency across re-dispatches. The queue connection is sync
 * in phpunit.xml, so dispatch() runs the job inline.
 */
class ProcessBatchPayoutsJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        \Illuminate\Support\Facades\DB::table('cache')->truncate();
        \Illuminate\Support\Facades\DB::table('platform_settings')->truncate();
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

    private function bindService(
        ?object $stripeConnect = null,
        ?object $paypal = null,
        ?object $cashapp = null,
    ): PoliticalPaymentService {
        $broadcast = Mockery::mock(ReverbBroadcastService::class);
        $broadcast->shouldReceive('payoutProcessed')->andReturn(null)->byDefault();
        $broadcast->shouldReceive('payoutDispatched')->andReturn(null)->byDefault();

        $stripe = $stripeConnect ?? Mockery::mock(StripeConnectService::class);
        $pp     = $paypal ?? tap(Mockery::mock(PayPalPayoutService::class), fn ($m) => $m->shouldReceive('isConfigured')->andReturn(false)->byDefault());
        $ca     = $cashapp ?? tap(Mockery::mock(CashAppPayoutService::class), fn ($m) => $m->shouldReceive('isConfigured')->andReturn(false)->byDefault());

        $service = new PoliticalPaymentService($stripe, $pp, $ca, $broadcast);

        // Bind into the container so the job's method injection picks it up.
        $this->app->instance(PoliticalPaymentService::class, $service);

        return $service;
    }

    public function test_job_transitions_run_to_completed_and_increments_counters(): void
    {
        $voter = Voter::factory()->create(['payment_method' => 'stripe', 'stripe_account_id' => 'acct_test']);
        $this->makeSessions($voter, 6, 1.00); // $6 total > $5 min

        $stripeConnect = Mockery::mock(StripeConnectService::class);
        $stripeConnect->shouldReceive('canReceivePayout')->andReturn(true);
        $stripeConnect->shouldReceive('sendTransfer')
            ->once()
            ->andReturn(['reference' => 'tr_job_1', 'fee' => 0.0]);

        $service = $this->bindService(stripeConnect: $stripeConnect);
        $admin = User::factory()->create();
        $run = $service->createPayoutRun(triggeredByAdminId: $admin->id, triggerSource: 'admin');

        $this->assertSame('pending', $run->status);

        ProcessBatchPayoutsJob::dispatch($run);

        $run->refresh();
        $this->assertSame('completed', $run->status, 'Run should reach completed');
        $this->assertNotNull($run->completed_at);
        $this->assertSame(1, (int) $run->processed_count);
        $this->assertSame(0, (int) $run->skipped_count);
        $this->assertSame(6.00, (float) $run->total_paid);
        $this->assertSame(1, PayoutAttempt::count());
        $this->assertSame('paid', PayoutAttempt::first()->status);

        Mockery::close();
    }

    public function test_re_dispatching_a_new_run_does_not_double_pay(): void
    {
        $voter = Voter::factory()->create(['payment_method' => 'stripe', 'stripe_account_id' => 'acct_test']);
        $this->makeSessions($voter, 6, 1.00);

        $stripeConnect = Mockery::mock(StripeConnectService::class);
        $stripeConnect->shouldReceive('canReceivePayout')->andReturn(true);
        $stripeConnect->shouldReceive('sendTransfer')
            ->once() // only the first run actually pays
            ->andReturn(['reference' => 'tr_job_2', 'fee' => 0.0]);

        $service = $this->bindService(stripeConnect: $stripeConnect);

        $run1 = $service->createPayoutRun();
        ProcessBatchPayoutsJob::dispatch($run1);
        $run2 = $service->createPayoutRun();
        ProcessBatchPayoutsJob::dispatch($run2);

        $run2->refresh();
        $this->assertSame('completed', $run2->status);
        $this->assertSame(0, (int) $run2->processed_count, 'Second run finds sessions already Paid');
        $this->assertSame(1, PayoutAttempt::count(), 'No duplicate attempt row');

        Mockery::close();
    }

    public function test_per_voter_processor_failure_skips_voter_but_completes_run(): void
    {
        $voter = Voter::factory()->create(['payment_method' => 'stripe', 'stripe_account_id' => 'acct_test']);
        $this->makeSessions($voter, 6, 1.00);

        $stripeConnect = Mockery::mock(StripeConnectService::class);
        $stripeConnect->shouldReceive('canReceivePayout')->andReturn(true);
        $stripeConnect->shouldReceive('sendTransfer')
            ->andThrow(new \RuntimeException('Stripe is down'));

        $service = $this->bindService(stripeConnect: $stripeConnect);
        $run = $service->createPayoutRun();

        try {
            ProcessBatchPayoutsJob::dispatch($run);
        } catch (\Throwable $e) {
            // A per-voter processor failure is caught and recorded as a skip —
            // it must NOT propagate or fail the whole run.
            $this->fail('Per-voter processor failure should not propagate: ' . $e->getMessage());
        }

        $run->refresh();
        $this->assertSame('completed', $run->status, 'Run completes even when a voter is skipped');
        $this->assertSame(0, (int) $run->processed_count);
        $this->assertSame(1, (int) $run->skipped_count);

        Mockery::close();
    }

    public function test_failed_hook_marks_run_failed_on_worker_death(): void
    {
        $service = $this->bindService();
        $run = $service->createPayoutRun();
        $this->assertSame('pending', $run->status);

        // Simulate the queue worker calling failed() because the job died before
        // handle() could reach its own catch (e.g. worker killed mid-run).
        (new ProcessBatchPayoutsJob($run))->failed(new \RuntimeException('worker died'));

        $run->refresh();
        $this->assertSame('failed', $run->status, 'failed() hook should mark the run failed');
        $this->assertNotNull($run->failed_at);

        Mockery::close();
    }
}