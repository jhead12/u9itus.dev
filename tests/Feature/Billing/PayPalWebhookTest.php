<?php

namespace Tests\Feature\Billing;

use App\Models\PayPalWebhookEvent;
use App\Services\PayPalPayoutReconciliationService;
use App\Services\PayPalPayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class PayPalWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_paypal_item_webhook_is_processed_and_replay_is_idempotent(): void
    {
        $payload = [
            'id' => 'WH-PAYPAL-123',
            'event_type' => 'PAYMENT.PAYOUTS-ITEM.SUCCEEDED',
            'resource' => [
                'payout_batch_id' => 'PAYPAL-BATCH-ABC',
                'payout_item_id' => 'PAYOUT-ITEM-1',
            ],
        ];

        $paypalServiceMock = Mockery::mock(PayPalPayoutService::class);
        $paypalServiceMock->shouldReceive('verifyWebhookSignature')->andReturn(true);
        $this->app->instance(PayPalPayoutService::class, $paypalServiceMock);

        $reconciliationMock = Mockery::mock(PayPalPayoutReconciliationService::class);
        $reconciliationMock
            ->shouldReceive('reconcileSingleItemEvent')
            ->once()
            ->with('PAYPAL-BATCH-ABC', 'SUCCEEDED', 'PAYOUT-ITEM-1')
            ->andReturn(['updated' => 1, 'paid' => 1, 'rejected' => 0, 'pending' => 0]);
        $this->app->instance(PayPalPayoutReconciliationService::class, $reconciliationMock);

        $first = $this->postJson('/api/paypal/webhooks', $payload);
        $first->assertOk()->assertJsonPath('status', 'ok');

        $second = $this->postJson('/api/paypal/webhooks', $payload);
        $second->assertOk()->assertJsonPath('status', 'duplicate');

        $this->assertEquals(1, PayPalWebhookEvent::count());
    }
}
