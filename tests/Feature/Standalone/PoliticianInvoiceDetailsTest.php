<?php

use App\Models\CampaignTransaction;
use App\Models\Politician;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function makePoliticianUserForInvoiceDetails(): array
{
    Role::firstOrCreate(['name' => 'politician', 'guard_name' => 'web']);

    $user = User::factory()->create([
        'platform' => 'standalone',
        'user_type' => 'politician',
    ]);
    $user->assignRole('politician');
    skipOnboarding($user, 'politician');

    $politician = Politician::factory()->create([
        'user_id' => $user->id,
    ]);

    return [$user, $politician];
}

test('politician can fetch invoice engagement details for owned succeeded charge', function () {
    [$user, $politician] = makePoliticianUserForInvoiceDetails();

    $transaction = CampaignTransaction::query()->create([
        'politician_id' => $politician->id,
        'transaction_type' => 'charge',
        'amount' => 120.00,
        'currency' => 'usd',
        'status' => 'succeeded',
        'metadata' => [
            'payment_mode' => 'test',
            'credits_amount' => 117.00,
            'stripe_fee' => 3.00,
        ],
    ]);

    $this->actingAs($user)
        ->get(route('politician.billing.invoices.details', $transaction))
        ->assertOk()
        ->assertJsonPath('data.invoice.id', $transaction->id)
        ->assertJsonPath('data.attribution.method', 'date_window')
        ->assertJsonPath('data.metrics.replay_tracking_available', false);
});

test('politician cannot fetch invoice engagement details for someone else invoice', function () {
    [$user] = makePoliticianUserForInvoiceDetails();
    [, $otherPolitician] = makePoliticianUserForInvoiceDetails();

    $transaction = CampaignTransaction::query()->create([
        'politician_id' => $otherPolitician->id,
        'transaction_type' => 'charge',
        'amount' => 120.00,
        'currency' => 'usd',
        'status' => 'succeeded',
        'metadata' => ['payment_mode' => 'test'],
    ]);

    $this->actingAs($user)
        ->get(route('politician.billing.invoices.details', $transaction))
        ->assertForbidden();
});

test('invoice details endpoint returns 404 when payment mode does not match active mode', function () {
    [$user, $politician] = makePoliticianUserForInvoiceDetails();

    $transaction = CampaignTransaction::query()->create([
        'politician_id' => $politician->id,
        'transaction_type' => 'charge',
        'amount' => 90.00,
        'currency' => 'usd',
        'status' => 'succeeded',
        'metadata' => ['payment_mode' => 'live'],
    ]);

    $this->actingAs($user)
        ->get(route('politician.billing.invoices.details', $transaction))
        ->assertNotFound();
});
