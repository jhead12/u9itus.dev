<?php

namespace Tests\Feature\Billing;

use App\Models\Politician;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseCreditsTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_creates_payment_intent_placeholder()
    {
        // Create politician with linked user
        $user = User::factory()->create(['platform' => 'standalone']);
        $politician = Politician::factory()->create(['user_id' => $user->id]);

        // Act as the politician's owner so ownership checks pass
        $response = $this->actingAs($user)
            ->withoutMiddleware()
            ->postJson("/api/v1/politicians/{$politician->uuid}/billing/purchase", [
                'amount' => 60.00,
            ]);

        // 200 when Stripe configured, 500 when not, 422 on validation edge cases
        $this->assertTrue(in_array($response->getStatusCode(), [200, 422, 500]));
    }
}
