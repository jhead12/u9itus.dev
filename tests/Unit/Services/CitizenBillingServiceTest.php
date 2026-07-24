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
