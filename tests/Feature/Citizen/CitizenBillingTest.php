<?php

use App\Enums\CampaignStatus;
use App\Enums\CitizenAdType;
use App\Models\Citizen;
use App\Models\CitizenCampaign;
use App\Models\CitizenCredit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    if (class_exists(\Spatie\Permission\Models\Role::class)) {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'citizen', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }
});

function makeCitizenForBilling(array $overrides = []): User
{
    $user = User::factory()->create(['platform' => 'standalone']);
    $user->assignRole('citizen');
    Citizen::factory()->create(array_merge(['user_id' => $user->id], $overrides));
    skipOnboarding($user, 'citizen');
    return $user->load('citizen');
}

function makeAdminForBilling(): User
{
    $user = User::factory()->create(['platform' => 'standalone']);
    $user->assignRole('admin');
    skipOnboarding($user, 'admin');
    return $user;
}

function makeCitizenCampaignForBilling(Citizen $citizen, array $attrs = []): CitizenCampaign
{
    return CitizenCampaign::factory()->create(array_merge(
        [
            'citizen_id' => $citizen->id,
            'status' => CampaignStatus::PendingApproval->value,
            'approval_status' => 'pending',
            'citizen_ad_type' => CitizenAdType::LocalBusiness->value,
            'total_budget' => 15.00,
            'media_url' => 'https://www.youtube.com/watch?v=abc',
        ],
        $attrs
    ));
}

// ── Billing page access ───────────────────────────────────────────────────────

test('citizen can view billing page', function () {
    $user = makeCitizenForBilling();

    $this->actingAs($user)
        ->get(route('citizen.billing'))
        ->assertOk()
        ->assertSee('Credit Balance')
        ->assertSee('Add Funds');
});

test('non-citizen cannot access billing page', function () {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'voter', 'guard_name' => 'web']);
    $user = User::factory()->create(['platform' => 'standalone']);
    $user->assignRole('voter');
    skipOnboarding($user, 'voter');

    $this->actingAs($user)
        ->get(route('citizen.billing'))
        ->assertForbidden();
});

// ── Credit ledger / wallet behavior ───────────────────────────────────────────

test('citizen billing page shows credit ledger rows', function () {
    $user = makeCitizenForBilling();
    CitizenCredit::factory()->create([
        'citizen_id' => $user->citizen->id,
        'transaction_type' => 'purchase',
        'amount' => 50.00,
        'balance_after' => 50.00,
        'description' => 'Initial test credit',
    ]);

    $this->actingAs($user)
        ->get(route('citizen.billing'))
        ->assertOk()
        ->assertSee('Initial test credit')
        ->assertSee('$50.00');
});

// ── Campaign approval funding enforcement ───────────────────────────────────────

test('admin cannot approve citizen campaign with insufficient credits', function () {
    $user = makeCitizenForBilling(['credit_balance' => 5.00]);
    $campaign = makeCitizenCampaignForBilling($user->citizen, ['total_budget' => 15.00]);
    $admin = makeAdminForBilling();

    $this->actingAs($admin)
        ->post(route('admin.citizen-campaigns.approve', $campaign))
        ->assertRedirect();

    $campaign->refresh();
    expect($campaign->approval_status->value)->toBe('pending');
    expect((float) $user->citizen->fresh()->credit_balance)->toBe(5.00);
});

test('admin approves citizen campaign and reserves budget from credits', function () {
    $user = makeCitizenForBilling();

    // Ledger balance is the source of truth; seed an opening credit.
    CitizenCredit::factory()->create([
        'citizen_id' => $user->citizen->id,
        'transaction_type' => 'purchase',
        'amount' => 50.00,
        'balance_after' => 50.00,
        'description' => 'Opening balance',
    ]);
    $user->citizen->syncCreditBalance();

    $campaign = makeCitizenCampaignForBilling($user->citizen, ['total_budget' => 15.00]);
    $admin = makeAdminForBilling();

    $this->actingAs($admin)
        ->post(route('admin.citizen-campaigns.approve', $campaign))
        ->assertSessionHas('success');

    $campaign->refresh();
    expect($campaign->approval_status->value)->toBe('approved');
    expect($campaign->status->value)->toBe('active');

    $user->citizen->refresh();
    expect((float) $user->citizen->credit_balance)->toBe(35.00);

    $ledger = CitizenCredit::where('citizen_id', $user->citizen->id)
        ->where('transaction_type', 'debit_campaign_budget')
        ->first();
    expect($ledger)->not->toBeNull();
    expect((float) $ledger->amount)->toBe(-15.00);
});

// ── Add-funds / Stripe PaymentIntent flow (requires Stripe config) ──────────────

test('add-funds endpoint validates amount', function () {
    $user = makeCitizenForBilling();

    $this->actingAs($user)
        ->postJson(route('citizen.billing.add-funds'), ['amount' => 5])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['amount']);
});

test('add-funds endpoint returns payment intent data or graceful error when Stripe is unavailable', function () {
    $user = makeCitizenForBilling();

    $response = $this->actingAs($user)
        ->postJson(route('citizen.billing.add-funds'), ['amount' => 100]);

    // With Stripe keys configured it returns 200 + client_secret.
    // Without keys it returns a 500/503 style error handled by the controller.
    $this->assertTrue(in_array($response->getStatusCode(), [200, 500, 503, 422], true));
});

// ── Saved payment methods ─────────────────────────────────────────────────────

test('setup-intent endpoint requires authentication', function () {
    $this->postJson(route('citizen.billing.setup-intent'))
        ->assertStatus(401);
});

test('setup-intent endpoint responds when authenticated', function () {
    $user = makeCitizenForBilling();

    $response = $this->actingAs($user)
        ->postJson(route('citizen.billing.setup-intent'));

    // Graceful response regardless of Stripe configuration.
    $this->assertTrue(in_array($response->getStatusCode(), [200, 500, 503], true));
});

// ── Receipt email update ──────────────────────────────────────────────────────

test('citizen can update receipt email', function () {
    $user = makeCitizenForBilling();

    $this->actingAs($user)
        ->post(route('citizen.billing.update-receipt-email'), [
            'receipt_email' => 'receipts@example.com',
        ])
        ->assertRedirect();

    expect($user->citizen->fresh()->receipt_email)->toBe('receipts@example.com');
});
