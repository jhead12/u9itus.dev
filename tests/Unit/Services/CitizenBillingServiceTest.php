<?php

use App\Models\Citizen;
use App\Models\CitizenCredit;
use App\Models\CitizenTransaction;
use App\Services\CitizenBillingService;
use App\Services\StripePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeCitizenBillingService(): CitizenBillingService
{
    $stripe = Mockery::mock(StripePaymentService::class);
    return new CitizenBillingService($stripe);
}

test('finalizePaymentIntent credits the citizen wallet for a first-ever purchase', function () {
    // Regression test for a schema bug: citizen_credits.related_transaction_id was
    // defined as a foreign key to citizen_credits itself instead of citizen_transactions,
    // which made addCredits() fail on every citizen's very first purchase (no row yet
    // existed in citizen_credits to satisfy the wrongly-targeted constraint).
    $citizen = Citizen::factory()->create(['credit_balance' => 0.00]);
    $svc     = makeCitizenBillingService();

    // Seed filler transaction rows first so the real transaction's id is
    // guaranteed not to coincidentally equal the still-empty citizen_credits
    // table's next auto-increment id — that coincidence is exactly what let
    // the wrongly-self-referencing FK go unnoticed in production.
    for ($i = 0; $i < 3; $i++) {
        CitizenTransaction::create([
            'citizen_id'               => $citizen->id,
            'transaction_type'         => 'charge',
            'amount'                   => 1.00,
            'currency'                 => 'USD',
            'stripe_payment_intent_id' => 'pi_filler_' . $i,
            'status'                   => 'failed',
        ]);
    }

    $tx = CitizenTransaction::create([
        'citizen_id'                => $citizen->id,
        'transaction_type'          => 'charge',
        'amount'                    => 102.57,
        'currency'                  => 'USD',
        'stripe_payment_intent_id'  => 'pi_citizen_first_purchase',
        'status'                    => 'pending',
        'metadata'                  => [
            'credits_amount'  => 100.00,
            'payment_mode'    => 'test',
            'stripe_livemode' => false,
        ],
    ]);

    $result = $svc->finalizePaymentIntent('pi_citizen_first_purchase');

    expect($result->status)->toBe('succeeded');

    $credit = CitizenCredit::where('citizen_id', $citizen->id)
        ->where('transaction_type', 'purchase')
        ->first();

    expect($credit)->not->toBeNull()
        ->and((float) $credit->amount)->toBe(100.00)
        ->and($credit->related_transaction_id)->toBe($tx->id);

    expect((float) $citizen->fresh()->credit_balance)->toBe(100.00);
});

// ── refundAllUnusedCredits() ─────────────────────────────────────────────────

test('refundAllUnusedCredits drains the full balance across multiple purchases oldest-first', function () {
    $stripe = Mockery::mock(StripePaymentService::class);
    $svc    = new CitizenBillingService($stripe);

    $citizen = Citizen::factory()->create(['credit_balance' => 0.00]);
    $svc->addCredits($citizen, 30.00, ['transaction_type' => 'purchase']);
    $this->travel(1)->seconds();
    $svc->addCredits($citizen, 25.00, ['transaction_type' => 'purchase']);

    $purchaseTx1 = CitizenTransaction::create([
        'citizen_id'               => $citizen->id,
        'transaction_type'         => 'charge',
        'amount'                   => 30.00,
        'currency'                 => 'USD',
        'stripe_payment_intent_id' => 'pi_citizen_drain_1',
        'status'                   => 'succeeded',
        'metadata'                 => ['credits_amount' => 30.00, 'payment_mode' => 'test'],
    ]);

    $purchaseTx2 = CitizenTransaction::create([
        'citizen_id'               => $citizen->id,
        'transaction_type'         => 'charge',
        'amount'                   => 25.00,
        'currency'                 => 'USD',
        'stripe_payment_intent_id' => 'pi_citizen_drain_2',
        'status'                   => 'succeeded',
        'metadata'                 => ['credits_amount' => 25.00, 'payment_mode' => 'test'],
    ]);

    $stripe->shouldReceive('createRefundForPaymentIntent')
        ->once()
        ->with('pi_citizen_drain_1', 30.00, Mockery::any())
        ->andReturn((object) ['id' => 're_citizen_drain_1', 'status' => 'succeeded']);

    $stripe->shouldReceive('createRefundForPaymentIntent')
        ->once()
        ->with('pi_citizen_drain_2', 25.00, Mockery::any())
        ->andReturn((object) ['id' => 're_citizen_drain_2', 'status' => 'succeeded']);

    $result = $svc->refundAllUnusedCredits($citizen, 42, 'account deletion test');

    expect($result['errors'])->toBeEmpty()
        ->and($result['refunded'])->toHaveCount(2);

    expect((float) $citizen->fresh()->credit_balance)->toBe(0.00)
        ->and((float) $svc->getUnusedRefundSummary($purchaseTx1)['refundable_credits_now'])->toBe(0.00)
        ->and((float) $svc->getUnusedRefundSummary($purchaseTx2)['refundable_credits_now'])->toBe(0.00);
});

test('refundAllUnusedCredits continues past a Stripe error on one purchase', function () {
    $stripe = Mockery::mock(StripePaymentService::class);
    $svc    = new CitizenBillingService($stripe);

    $citizen = Citizen::factory()->create(['credit_balance' => 0.00]);
    $svc->addCredits($citizen, 20.00, ['transaction_type' => 'purchase']);

    $failingTx = CitizenTransaction::create([
        'citizen_id'               => $citizen->id,
        'transaction_type'         => 'charge',
        'amount'                   => 20.00,
        'currency'                 => 'USD',
        'stripe_payment_intent_id' => 'pi_citizen_will_fail',
        'status'                   => 'succeeded',
        'metadata'                 => ['credits_amount' => 20.00, 'payment_mode' => 'test'],
    ]);

    $stripe->shouldReceive('createRefundForPaymentIntent')
        ->once()
        ->with('pi_citizen_will_fail', 20.00, Mockery::any())
        ->andThrow(new \RuntimeException('Stripe unavailable'));

    $result = $svc->refundAllUnusedCredits($citizen, 42, 'account deletion test');

    expect($result['refunded'])->toHaveCount(0)
        ->and($result['errors'])->toHaveCount(1)
        ->and($result['errors'][0])->toContain((string) $failingTx->id);
});
