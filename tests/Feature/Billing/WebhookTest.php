<?php

namespace Tests\Feature\Billing;

use App\Models\CampaignTransaction;
use App\Models\Politician;
use App\Models\PoliticianCredit;
use App\Services\CampaignBillingService;
use App\Services\StripePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build a minimal Stripe webhook payload for a given event type and PaymentIntent id.
     */
    private function stripePayload(string $type, string $piId, float $amountCents = 6000): array
    {
        return [
            'id' => 'evt_test_' . uniqid(),
            'type' => $type,
            'data' => [
                'object' => [
                    'id' => $piId,
                    'object' => 'payment_intent',
                    'amount' => (int) $amountCents,
                    'currency' => 'usd',
                    'status' => $type === 'payment_intent.succeeded' ? 'succeeded' : 'requires_payment_method',
                    'charges' => [
                        'object' => 'list',
                        'data' => [
                            ['id' => 'ch_test_' . uniqid()],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Helper: post a webhook payload to /api/stripe/webhooks with mocked
     * StripePaymentService so no real signature is needed.
     */
    private function postWebhook(array $payload)
    {
        // Mock StripePaymentService::parseWebhook to return the payload directly
        $mock = Mockery::mock(StripePaymentService::class);
        $mock->shouldReceive('parseWebhook')
            ->once()
            ->andReturn($payload);

        $this->app->instance(StripePaymentService::class, $mock);

        return $this->withoutMiddleware()
            ->postJson('/api/stripe/webhooks', [], [
                'Stripe-Signature' => 'test_sig',
                'Content-Type' => 'application/json',
            ]);
    }


    public function test_payment_intent_succeeded_marks_transaction_succeeded_and_credits_politician()
    {
        $politician = Politician::factory()->create();
        $piId = 'pi_test_success_' . uniqid();

        // Seed a pending transaction
        CampaignTransaction::create([
            'politician_id' => $politician->id,
            'campaign_id' => null,
            'transaction_type' => 'charge',
            'amount' => 60.00,
            'currency' => 'USD',
            'stripe_payment_intent_id' => $piId,
            'status' => 'pending',
            'description' => 'Credit purchase',
        ]);

        $payload = $this->stripePayload('payment_intent.succeeded', $piId, 6000);

        $response = $this->postWebhook($payload);

        $response->assertStatus(200)
            ->assertJson(['status' => 'ok']);

        // Transaction should be marked succeeded
        $this->assertDatabaseHas('campaign_transactions', [
            'stripe_payment_intent_id' => $piId,
            'status' => 'succeeded',
        ]);

        // Politician should have a credit entry
        $this->assertDatabaseHas('politician_credits', [
            'politician_id' => $politician->id,
            'transaction_type' => 'purchase',
        ]);

        // Credit balance should be 60.00
        $balance = PoliticianCredit::where('politician_id', $politician->id)
            ->orderBy('created_at', 'desc')
            ->value('balance_after');

        $this->assertEquals('60.00', number_format((float) $balance, 2));
    }


    public function test_payment_intent_payment_failed_marks_transaction_failed_and_no_credit_added()
    {
        $politician = Politician::factory()->create();
        $piId = 'pi_test_failed_' . uniqid();

        CampaignTransaction::create([
            'politician_id' => $politician->id,
            'campaign_id' => null,
            'transaction_type' => 'charge',
            'amount' => 60.00,
            'currency' => 'USD',
            'stripe_payment_intent_id' => $piId,
            'status' => 'pending',
            'description' => 'Credit purchase',
        ]);

        $payload = $this->stripePayload('payment_intent.payment_failed', $piId, 6000);

        $response = $this->postWebhook($payload);

        $response->assertStatus(200)
            ->assertJson(['status' => 'ok']);

        // Transaction should be marked failed
        $this->assertDatabaseHas('campaign_transactions', [
            'stripe_payment_intent_id' => $piId,
            'status' => 'failed',
        ]);

        // No credits should have been added for this politician
        $this->assertDatabaseMissing('politician_credits', [
            'politician_id' => $politician->id,
        ]);
    }


    public function test_duplicate_webhook_for_already_finalized_transaction_is_idempotent()
    {
        $politician = Politician::factory()->create();
        $piId = 'pi_test_idem_' . uniqid();

        CampaignTransaction::create([
            'politician_id' => $politician->id,
            'campaign_id' => null,
            'transaction_type' => 'charge',
            'amount' => 60.00,
            'currency' => 'USD',
            'stripe_payment_intent_id' => $piId,
            'status' => 'succeeded', // already finalized
            'description' => 'Credit purchase',
        ]);

        $payload = $this->stripePayload('payment_intent.succeeded', $piId, 6000);

        $response = $this->postWebhook($payload);

        $response->assertStatus(200);

        // Should not add a second credit entry
        $creditCount = PoliticianCredit::where('politician_id', $politician->id)->count();
        $this->assertEquals(0, $creditCount, 'Duplicate webhook should not double-credit politician');
    }


    public function test_webhook_for_unknown_payment_intent_returns_ok_and_logs_warning()
    {
        $payload = $this->stripePayload('payment_intent.succeeded', 'pi_unknown_xyz', 6000);

        $response = $this->postWebhook($payload);

        // Should still return 200 (Stripe requires 200 to avoid retries on unknown intents)
        $response->assertStatus(200)
            ->assertJson(['status' => 'ok']);
    }


    public function test_unhandled_webhook_event_type_returns_ok()
    {
        $mock = Mockery::mock(StripePaymentService::class);
        $mock->shouldReceive('parseWebhook')
            ->once()
            ->andReturn(['type' => 'customer.created', 'data' => ['object' => []]]);

        $this->app->instance(StripePaymentService::class, $mock);

        $response = $this->withoutMiddleware()
            ->postJson('/api/stripe/webhooks', [], ['Stripe-Signature' => 'test_sig']);

        $response->assertStatus(200)
            ->assertJson(['status' => 'ok']);
    }


    public function test_invalid_webhook_signature_returns_400()
    {
        $mock = Mockery::mock(StripePaymentService::class);
        $mock->shouldReceive('parseWebhook')
            ->once()
            ->andThrow(new \Exception('Signature mismatch'));

        $this->app->instance(StripePaymentService::class, $mock);

        $response = $this->withoutMiddleware()
            ->postJson('/api/stripe/webhooks', [], ['Stripe-Signature' => 'bad_sig']);

        $response->assertStatus(400)
            ->assertJson(['error' => 'Invalid webhook']);
    }

    public function test_missing_webhook_secret_throws_in_non_local_environment()
    {
        // Simulate production environment with no secret configured
        $this->app['config']->set('app.env', 'production');
        $this->app['config']->set('services.stripe.webhook_secret', null);

        // Bind real (non-mocked) StripePaymentService — it should throw
        $service = $this->app->make(\App\Services\StripePaymentService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/webhook secret is not configured/i');

        $service->parseWebhook('{}', 'any_sig');
    }

    public function test_forged_payload_without_secret_does_not_credit_politician_in_production()
    {
        // Arrange: production-like environment, no secret, real service throws
        $this->app['config']->set('app.env', 'production');
        $this->app['config']->set('services.stripe.webhook_secret', null);

        $politician = Politician::factory()->create();
        $piId = 'pi_forged_' . uniqid();

        CampaignTransaction::create([
            'politician_id' => $politician->id,
            'campaign_id' => null,
            'transaction_type' => 'charge',
            'amount' => 10000.00,
            'currency' => 'USD',
            'stripe_payment_intent_id' => $piId,
            'status' => 'pending',
            'description' => 'Credit purchase',
        ]);

        // Post raw forged payload directly (no mock — the real service should reject it)
        $response = $this->withoutMiddleware()
            ->postJson('/api/stripe/webhooks', [], [
                'Stripe-Signature' => 'forged',
                'Content-Type' => 'application/json',
            ]);

        // Should NOT be 200 OK
        $response->assertStatus(400);

        // Politician must not receive any credits
        $this->assertDatabaseMissing('politician_credits', [
            'politician_id' => $politician->id,
        ]);
    }
}
