<?php

use App\Models\CampaignTransaction;
use App\Models\Politician;
use App\Models\PoliticianCredit;
use App\Models\ReferralEarning;
use App\Models\Voter;
use App\Services\CampaignBillingService;
use App\Services\StripePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function makeBillingService(): CampaignBillingService
{
    $stripe = Mockery::mock(StripePaymentService::class);
    return new CampaignBillingService($stripe);
}

function politicianWithBalance(float $balance = 0.00): Politician
{
    $politician = Politician::factory()->create(['credit_balance' => $balance]);
    return $politician;
}

// ── recordTransaction() ───────────────────────────────────────────────────────

test('recordTransaction creates a campaign transaction row', function () {
    $politician = politicianWithBalance();
    $svc        = makeBillingService();

    $tx = $svc->recordTransaction([
        'politician_id'     => $politician->id,
        'transaction_type'  => 'charge',
        'amount'            => 60.00,
        'currency'          => 'USD',
        'status'            => 'pending',
        'description'       => 'Test charge',
    ]);

    expect($tx)->toBeInstanceOf(CampaignTransaction::class)
        ->and($tx->politician_id)->toBe($politician->id)
        ->and((float) $tx->amount)->toBe(60.00)
        ->and($tx->status)->toBe('pending');

    $this->assertDatabaseHas('campaign_transactions', [
        'politician_id'    => $politician->id,
        'transaction_type' => 'charge',
    ]);
});

// ── addCredits() ──────────────────────────────────────────────────────────────

test('addCredits creates a credit ledger entry with correct balance', function () {
    $politician = politicianWithBalance(0.00);
    $svc        = makeBillingService();

    $credit = $svc->addCredits($politician, 100.00, [
        'transaction_type' => 'topup',
        'description'      => 'Manual top-up',
    ]);

    expect($credit)->toBeInstanceOf(PoliticianCredit::class)
        ->and((float) $credit->amount)->toBe(100.00)
        ->and((float) $credit->balance_after)->toBe(100.00);

    $this->assertDatabaseHas('politician_credits', [
        'politician_id' => $politician->id,
        'amount'        => 100.00,
        'balance_after' => 100.00,
    ]);
});

test('addCredits accumulates balance across multiple entries', function () {
    $politician = politicianWithBalance(0.00);
    $svc        = makeBillingService();

    // Seed first credit with an older timestamp so ordering is stable.
    $svc->addCredits($politician, 50.00, ['transaction_type' => 'topup']);

    // Advance the clock so the second entry has a strictly later created_at.
    $this->travel(1)->seconds();
    $svc->addCredits($politician, 25.00, ['transaction_type' => 'topup']);

    $this->travel(1)->seconds();
    $credit = $svc->addCredits($politician, 10.00, ['transaction_type' => 'topup']);

    expect((float) $credit->balance_after)->toBe(85.00);
});

test('addCredits with negative amount reduces balance', function () {
    $politician = politicianWithBalance(0.00);
    $svc        = makeBillingService();

    $svc->addCredits($politician, 100.00, ['transaction_type' => 'purchase']);
    $debit = $svc->addCredits($politician, -20.00, ['transaction_type' => 'usage']);

    expect((float) $debit->balance_after)->toBe(80.00);
});

// ── Procurement commission ─────────────────────────────────────────────────

test('addCredits dispatches politician.purchased webhook to Early-bank for referred politician', function () {
    config(['u9itus.procurement_commission_percent' => 10]);

    \Illuminate\Support\Facades\Http::fake();
    Config::set('services.earlybank.enabled', true);
    Config::set('services.earlybank.webhook_url', 'https://early-bank.test/webhook');
    Config::set('services.earlybank.webhook_secret', 'eb-webhook-secret');

    $memberUuid = \Illuminate\Support\Str::uuid()->toString();
    $referrer   = Voter::factory()->create([
        'pending_earnings'          => 0.00,
        'earlybank_own_member_uuid' => $memberUuid,
    ]);
    $politician = Politician::factory()->create([
        'credit_balance'       => 0.00,
        'referred_by_voter_id' => $referrer->id,
    ]);
    $svc = makeBillingService();

    $svc->addCredits($politician, 100.00, ['transaction_type' => 'purchase']);

    // No internal ReferralEarning row should be created.
    $this->assertDatabaseMissing('referral_earnings', [
        'referrer_voter_id' => $referrer->id,
        'politician_id'     => $politician->id,
        'referral_type'     => ReferralEarning::TYPE_POLITICIAN_PROCUREMENT,
    ]);

    // Referrer's pending_earnings must remain zero.
    $referrer->refresh();
    expect((float) $referrer->pending_earnings)->toBe(0.00);

    // Outbound log should exist.
    $this->assertDatabaseHas('earlybank_webhook_logs', [
        'event_type' => 'politician.purchased',
        'earlybank_member_id' => $memberUuid,
    ]);

    // Early-bank should receive the politician.purchased outbound webhook.
    \Illuminate\Support\Facades\Http::assertSent(function ($request) use ($politician, $memberUuid) {
        $body = json_decode($request->body(), true);

        return ($body['event'] ?? '') === 'politician.purchased'
            && ($body['data']['politician_uuid'] ?? '') === (string) $politician->uuid
            && ($body['data']['earlybank_member_id'] ?? '') === $memberUuid;
    });
});

test('addCredits skips politician.purchased webhook when referrer has no EB member UUID', function () {
    config(['u9itus.procurement_commission_percent' => 10]);

    \Illuminate\Support\Facades\Http::fake();
    Config::set('services.earlybank.enabled', true);
    Config::set('services.earlybank.webhook_url', 'https://early-bank.test/webhook');
    Config::set('services.earlybank.webhook_secret', 'eb-webhook-secret');

    $referrer   = Voter::factory()->create(['pending_earnings' => 0.00]);
    $politician = Politician::factory()->create([
        'credit_balance'       => 0.00,
        'referred_by_voter_id' => $referrer->id,
    ]);
    $svc = makeBillingService();

    $svc->addCredits($politician, 100.00, ['transaction_type' => 'purchase']);

    \Illuminate\Support\Facades\Http::assertNothingSent();
});

test('addCredits dispatches politician.purchased webhook only once per politician', function () {
    config(['u9itus.procurement_commission_percent' => 10]);

    \Illuminate\Support\Facades\Http::fake();
    Config::set('services.earlybank.enabled', true);
    Config::set('services.earlybank.webhook_url', 'https://early-bank.test/webhook');
    Config::set('services.earlybank.webhook_secret', 'eb-webhook-secret');

    $memberUuid = \Illuminate\Support\Str::uuid()->toString();
    $referrer   = Voter::factory()->create([
        'pending_earnings'          => 0.00,
        'earlybank_own_member_uuid' => $memberUuid,
    ]);
    $politician = Politician::factory()->create([
        'credit_balance'       => 0.00,
        'referred_by_voter_id' => $referrer->id,
    ]);
    $svc = makeBillingService();

    $svc->addCredits($politician, 100.00, ['transaction_type' => 'purchase']);
    $svc->addCredits($politician, 50.00, ['transaction_type' => 'purchase']);

    \Illuminate\Support\Facades\Http::assertSentCount(1);
});

// ── finalizePaymentIntent() ───────────────────────────────────────────────────

test('finalizePaymentIntent returns null when no matching transaction exists', function () {
    $svc    = makeBillingService();
    $result = $svc->finalizePaymentIntent('pi_nonexistent');

    expect($result)->toBeNull();
});

test('finalizePaymentIntent marks transaction as succeeded and credits politician', function () {
    $politician = politicianWithBalance(0.00);
    $svc        = makeBillingService();

    // Seed a pending transaction manually
    $tx = CampaignTransaction::create([
        'politician_id'            => $politician->id,
        'transaction_type'         => 'charge',
        'amount'                   => 60.00,
        'currency'                 => 'USD',
        'stripe_payment_intent_id' => 'pi_test123',
        'status'                   => 'pending',
        'description'              => 'Credit purchase',
    ]);

    $result = $svc->finalizePaymentIntent('pi_test123');

    expect($result->status)->toBe('succeeded');

    // Politician's balance should have been credited
    $credit = PoliticianCredit::where('politician_id', $politician->id)
        ->where('transaction_type', 'purchase')
        ->first();

    expect($credit)->not->toBeNull()
        ->and((float) $credit->amount)->toBe(60.00);
});

test('finalizePaymentIntent is idempotent for already-succeeded transactions', function () {
    $politician = politicianWithBalance(0.00);
    $svc        = makeBillingService();

    CampaignTransaction::create([
        'politician_id'            => $politician->id,
        'transaction_type'         => 'charge',
        'amount'                   => 30.00,
        'currency'                 => 'USD',
        'stripe_payment_intent_id' => 'pi_idempotent',
        'status'                   => 'succeeded',
    ]);

    $svc->finalizePaymentIntent('pi_idempotent');
    $svc->finalizePaymentIntent('pi_idempotent');

    // Only one credit entry should exist (idempotency guard)
    $count = PoliticianCredit::where('politician_id', $politician->id)->count();
    expect($count)->toBe(0);  // no credits added for already-succeeded tx
});

test('getUnusedRefundSummary uses cents-accurate totals', function () {
    $politician = politicianWithBalance(0.00);
    $svc        = makeBillingService();

    $svc->addCredits($politician, 60.00, ['transaction_type' => 'purchase']);
    $this->travel(1)->seconds();
    $svc->addCredits($politician, -39.99, ['transaction_type' => 'usage']);

    $purchaseTx = CampaignTransaction::create([
        'politician_id'            => $politician->id,
        'transaction_type'         => 'charge',
        'amount'                   => 61.54,
        'currency'                 => 'USD',
        'stripe_payment_intent_id' => 'pi_summary_precision',
        'status'                   => 'succeeded',
        'metadata'                 => [
            'credits_amount' => 60.00,
            'payment_mode' => 'test',
        ],
    ]);

    CampaignTransaction::create([
        'politician_id'            => $politician->id,
        'transaction_type'         => 'refund',
        'amount'                   => 20.50,
        'currency'                 => 'USD',
        'stripe_payment_intent_id' => 'pi_summary_refund_1',
        'status'                   => 'succeeded',
        'metadata'                 => [
            'original_transaction_id' => $purchaseTx->id,
            'refunded_credits_amount' => 19.99,
        ],
    ]);

    CampaignTransaction::create([
        'politician_id'            => $politician->id,
        'transaction_type'         => 'refund',
        'amount'                   => 20.51,
        'currency'                 => 'USD',
        'stripe_payment_intent_id' => 'pi_summary_refund_2',
        'status'                   => 'pending',
        'metadata'                 => [
            'original_transaction_id' => $purchaseTx->id,
            'refunded_credits_amount' => 20.00,
        ],
    ]);

    $summary = $svc->getUnusedRefundSummary($purchaseTx);

    expect($summary['credits_purchased'])->toBe(60.00)
        ->and($summary['current_balance'])->toBe(20.01)
        ->and($summary['already_refunded_credits'])->toBe(39.99)
        ->and($summary['already_refunded_gross'])->toBe(41.01)
        ->and($summary['remaining_by_purchase'])->toBe(20.01)
        ->and($summary['refundable_credits_now'])->toBe(20.01);
});

test('refundUnusedCredits computes gross refund in cents and respects remaining cap', function () {
    $stripe = Mockery::mock(StripePaymentService::class);
    $svc    = new CampaignBillingService($stripe);

    $politician = politicianWithBalance(0.00);
    $svc->addCredits($politician, 60.00, ['transaction_type' => 'purchase']);
    $this->travel(1)->seconds();
    $svc->addCredits($politician, -39.99, ['transaction_type' => 'usage']);

    $purchaseTx = CampaignTransaction::create([
        'politician_id'            => $politician->id,
        'transaction_type'         => 'charge',
        'amount'                   => 61.54,
        'currency'                 => 'USD',
        'stripe_payment_intent_id' => 'pi_refund_precision',
        'status'                   => 'succeeded',
        'metadata'                 => [
            'credits_amount' => 60.00,
            'payment_mode' => 'test',
        ],
    ]);

    CampaignTransaction::create([
        'politician_id'            => $politician->id,
        'transaction_type'         => 'refund',
        'amount'                   => 41.01,
        'currency'                 => 'USD',
        'stripe_payment_intent_id' => 'pi_refund_seed_1',
        'status'                   => 'succeeded',
        'metadata'                 => [
            'original_transaction_id' => $purchaseTx->id,
            'refunded_credits_amount' => 39.99,
        ],
    ]);

    $stripe->shouldReceive('createRefundForPaymentIntent')
        ->once()
        ->with(
            'pi_refund_precision',
            20.52,
            Mockery::on(function (array $metadata) use ($purchaseTx): bool {
                return $metadata['source'] === 'admin_unused_credits_refund'
                    && $metadata['original_transaction_id'] === (string) $purchaseTx->id
                    && $metadata['admin_id'] === '42';
            })
        )
        ->andReturn((object) [
            'id' => 're_precision',
            'status' => 'pending',
        ]);

    $refundTx = $svc->refundUnusedCredits($purchaseTx, 42, null, 'precision test');

    expect((float) $refundTx->amount)->toBe(20.52)
        ->and((float) ($refundTx->metadata['refunded_credits_amount'] ?? 0))->toBe(20.01)
        ->and((float) ($refundTx->metadata['refunded_gross_amount'] ?? 0))->toBe(20.52);

    $refundCredit = PoliticianCredit::query()
        ->where('politician_id', $politician->id)
        ->where('transaction_type', 'refund')
        ->where('related_transaction_id', $refundTx->id)
        ->first();

    expect($refundCredit)->not->toBeNull()
        ->and((float) $refundCredit->amount)->toBe(-20.01);

    $postSummary = $svc->getUnusedRefundSummary($purchaseTx);
    expect($postSummary['refundable_credits_now'])->toBe(0.00);
});
