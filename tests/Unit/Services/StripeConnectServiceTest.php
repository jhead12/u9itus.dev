<?php

use App\Exceptions\StripeConnectException;
use App\Models\Voter;
use App\Services\StripeConnectService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Builds a real Stripe\StripeClient (no network calls in its constructor) whose
 * ->accounts service is swapped for a Mockery double. Declaring `public $accounts`
 * on the anonymous subclass shadows StripeClient's magic __get(), so reading
 * ->accounts returns our mock directly instead of hitting Stripe's service factory.
 */
function fakeStripeClient(object $accountsMock): \Stripe\StripeClient
{
    $client = new class ('sk_test_fake_key_for_unit_tests') extends \Stripe\StripeClient {
        public $accounts;
    };

    $client->accounts = $accountsMock;

    return $client;
}

/**
 * StripeConnectService builds its real client from config in the constructor.
 * There's no setter, so tests reach into the protected property to swap in the
 * fake client built above.
 */
function injectStripeClient(StripeConnectService $service, \Stripe\StripeClient $client): void
{
    $property = new ReflectionProperty(StripeConnectService::class, 'client');
    $property->setAccessible(true);
    $property->setValue($service, $client);
}

function makeStripeConnectService(object $accountsMock): StripeConnectService
{
    config(['services.stripe.secret' => 'sk_test_fake_key_for_unit_tests']);

    $service = new StripeConnectService();
    injectStripeClient($service, fakeStripeClient($accountsMock));

    return $service;
}

// ── ensureExpressAccount() ──────────────────────────────────────────────────

test('ensureExpressAccount returns the existing stripe_account_id without calling Stripe', function () {
    $voter = Voter::factory()->create(['stripe_account_id' => 'acct_existing']);

    $accounts = Mockery::mock(\Stripe\Service\AccountService::class);
    $accounts->shouldNotReceive('create');

    $service = makeStripeConnectService($accounts);

    expect($service->ensureExpressAccount($voter))->toBe('acct_existing');

    Mockery::close();
});

test('ensureExpressAccount does not create a second Stripe account when two requests race for the same voter', function () {
    // Two separate model instances of the same DB row, simulating two concurrent
    // web requests that both loaded the voter before either had a stripe_account_id.
    $voter1 = Voter::factory()->create(['stripe_account_id' => null, 'email' => 'race@example.com']);
    $voter2 = Voter::find($voter1->id);

    $accounts = Mockery::mock(\Stripe\Service\AccountService::class);
    $accounts->shouldReceive('create')
        ->once() // the whole point: a second racing request must not call Stripe again
        ->andReturn((object) ['id' => 'acct_race_winner']);

    $service = makeStripeConnectService($accounts);

    $firstId = $service->ensureExpressAccount($voter1);
    $secondId = $service->ensureExpressAccount($voter2);

    expect($firstId)->toBe('acct_race_winner')
        ->and($secondId)->toBe('acct_race_winner')
        ->and(Voter::find($voter1->id)->stripe_account_id)->toBe('acct_race_winner');

    Mockery::close();
});

test('ensureExpressAccount surfaces a classified error when Stripe rejects the transfers-only capability request', function () {
    // Reproduces the "Payout setup is temporarily unavailable while platform
    // capabilities are being configured" banner: Stripe rejects accounts->create()
    // because the platform isn't approved to request `transfers` without
    // `card_payments`. The account is never created — nothing to de-duplicate.
    $voter = Voter::factory()->create(['stripe_account_id' => null]);

    $stripeError = \Stripe\Exception\InvalidRequestException::factory(
        "Your platform's Stripe Connect settings do not allow you to request the transfers capability without also requesting card_payments. Please contact Stripe support.",
        400,
        null,
        ['error' => [
            'type' => 'invalid_request_error',
            'param' => 'requested_capabilities',
            'message' => 'platform needs approval to request this capability',
        ]],
        null,
        null
    );

    $accounts = Mockery::mock(\Stripe\Service\AccountService::class);
    $accounts->shouldReceive('create')->once()->andThrow($stripeError);

    $service = makeStripeConnectService($accounts);

    try {
        $service->ensureExpressAccount($voter);
        $this->fail('Expected StripeConnectException was not thrown.');
    } catch (StripeConnectException $e) {
        expect($e->getMessage())->toBe(
            'Payout setup is temporarily unavailable while platform capabilities are being configured. Please contact support.'
        );
    }

    // The failed create() must not leave a half-written stripe_account_id behind —
    // the DB transaction rolls back, so the voter can retry cleanly once Stripe
    // approves the capability.
    expect(Voter::find($voter->id)->stripe_account_id)->toBeNull();

    Mockery::close();
});
