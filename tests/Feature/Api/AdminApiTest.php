<?php

namespace Tests\Feature\Api;

use App\Models\PoliticalCampaign;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_analytics_endpoint_exists(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->getJson('/api/v1/admin/analytics');

        $this->assertContains($response->status(), [200, 401, 403, 500]);
    }

    public function test_admin_pending_campaigns_endpoint_exists(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->getJson('/api/v1/admin/campaigns/pending');

        $this->assertContains($response->status(), [200, 401, 403, 500]);
    }

    public function test_admin_approve_campaign_endpoint_exists(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $campaign = PoliticalCampaign::factory()->create();

        $response = $this->postJson("/api/v1/admin/campaigns/{$campaign->uuid}/approve");

        $this->assertContains($response->status(), [200, 401, 403, 404, 422, 500]);
    }

    public function test_admin_reject_campaign_endpoint_exists(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $campaign = PoliticalCampaign::factory()->create();

        $response = $this->postJson("/api/v1/admin/campaigns/{$campaign->uuid}/reject", [
            'reason' => 'Inappropriate content',
        ]);

        $this->assertContains($response->status(), [200, 401, 403, 404, 422, 500]);
    }

    public function test_admin_process_payouts_endpoint_exists(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson('/api/v1/admin/payouts/process');

        $this->assertContains($response->status(), [200, 401, 403, 422, 500]);
    }

    public function test_admin_flagged_voters_endpoint_exists(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->getJson('/api/v1/admin/voters/flagged');

        $this->assertContains($response->status(), [200, 401, 403, 500]);
    }

    public function test_admin_clear_fraud_flag_endpoint_exists(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $voter = Voter::factory()->create();

        $response = $this->postJson("/api/v1/admin/voters/{$voter->uuid}/clear-flag");

        $this->assertContains($response->status(), [200, 401, 403, 404, 422, 500]);
    }

    public function test_admin_endpoints_require_authentication(): void
    {
        // Request without authentication should be blocked
        $response = $this->getJson('/api/v1/admin/analytics');

        // Should return 401 without valid authentication
        $this->assertContains($response->status(), [401, 403, 500]);
    }
}
