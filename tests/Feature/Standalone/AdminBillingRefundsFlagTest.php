<?php

use App\Models\CampaignTransaction;
use App\Models\Politician;
use App\Models\PoliticianCredit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('admin', 'web');
});

function makeAdminForBillingRefundsTest(): User
{
    $admin = User::factory()->create([
        'platform' => 'standalone',
        'user_type' => 'admin',
        'email_verified_at' => now(),
    ]);

    $admin->assignRole('admin');
    skipOnboarding($admin, 'admin');

    return $admin;
}

function makePoliticianForBillingRefundsTest(string $name, string $email): Politician
{
    $user = User::factory()->create(['email' => $email]);

    return Politician::factory()->create([
        'user_id' => $user->id,
        'full_name' => $name,
        'credit_balance' => 100.00,
    ]);
}

test('billing refunds page shows a needs-review flag for a payment_mode_mismatch charge instead of a refund button', function () {
    $admin = makeAdminForBillingRefundsTest();
    $politician = makePoliticianForBillingRefundsTest('Flagged Politician', 'flagged@example.com');

    $tx = CampaignTransaction::create([
        'politician_id' => $politician->id,
        'transaction_type' => 'charge',
        'amount' => 102.57,
        'currency' => 'USD',
        'stripe_payment_intent_id' => 'pi_mismatch_flagged',
        'status' => 'succeeded',
        'metadata' => [
            'credits_amount' => 100.00,
            'payment_mode' => 'test',
            'stripe_livemode' => false,
            'payment_mode_mismatch' => true,
            'payment_mode_mismatch_note' => 'Succeeded in test mode while platform was configured for live mode; credits withheld pending manual review.',
        ],
    ]);

    $this->actingAs($admin)
        ->get(route('admin.billing.refunds'))
        ->assertOk()
        ->assertSee('Flagged Politician')
        ->assertSee('payment mode mismatch')
        ->assertSee('Needs review');
});

test('billing refunds page still shows a refund button for a normal unflagged purchase', function () {
    $admin = makeAdminForBillingRefundsTest();
    $politician = makePoliticianForBillingRefundsTest('Normal Politician', 'normal@example.com');

    PoliticianCredit::create([
        'politician_id' => $politician->id,
        'transaction_type' => 'purchase',
        'amount' => 60.00,
        'balance_after' => 60.00,
        'description' => 'Credits added from Stripe payment',
    ]);

    $tx = CampaignTransaction::create([
        'politician_id' => $politician->id,
        'transaction_type' => 'charge',
        'amount' => 61.50,
        'currency' => 'USD',
        'stripe_payment_intent_id' => 'pi_normal_purchase',
        'status' => 'succeeded',
        'metadata' => [
            'credits_amount' => 60.00,
            'payment_mode' => 'test',
            'stripe_livemode' => false,
        ],
    ]);

    $this->actingAs($admin)
        ->get(route('admin.billing.refunds'))
        ->assertOk()
        ->assertSee('Normal Politician')
        ->assertSee('Refund')
        ->assertDontSee('Needs review');
});
