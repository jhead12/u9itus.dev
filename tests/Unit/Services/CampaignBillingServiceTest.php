<?php

use App\Models\CampaignTransaction;
use App\Models\Politician;
use App\Models\PoliticianCredit;
use App\Models\ReferralEarning;
use App\Models\Voter;
use App\Services\CampaignBillingService;
use App\Services\StripePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

// ── Procurement commission ────────────────────────────────────────────────────

test('addCredits fires procurement commission on first purchase when voter referred politician', function () {
    config(['u9itus.procurement_commission_percent' => 10]);

    $referrer   = Voter::factory()->create(['pending_earnings' => 0.00]);
    $politician = Politician::factory()->create([
        'credit_balance'        => 0.00,
        'referred_by_voter_id'  => $referrer->id,
    ]);
    $svc = makeBillingService();

    $svc->addCredits($politician, 100.00, ['transaction_type' => 'purchase']);

    $this->assertDatabaseHas('referral_earnings', [
        'referrer_voter_id' => $referrer->id,
        'politician_id'     => $politician->id,
        'commission_amount' => 10.00,
        'referral_type'     => ReferralEarning::TYPE_POLITICIAN_PROCUREMENT,
    ]);

    $referrer->refresh();
    expect((float) $referrer->pending_earnings)->toBe(10.00);
});

test('procurement commission fires only once per politician', function () {
    config(['u9itus.procurement_commission_percent' => 10]);

    $referrer   = Voter::factory()->create(['pending_earnings' => 0.00]);
    $politician = Politician::factory()->create([
        'credit_balance'       => 0.00,
        'referred_by_voter_id' => $referrer->id,
    ]);
    $svc = makeBillingService();

    $svc->addCredits($politician, 100.00, ['transaction_type' => 'purchase']);
    $svc->addCredits($politician, 100.00, ['transaction_type' => 'purchase']);

    $count = ReferralEarning::where('politician_id', $politician->id)
        ->where('referral_type', ReferralEarning::TYPE_POLITICIAN_PROCUREMENT)
        ->count();

    expect($count)->toBe(1);
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
