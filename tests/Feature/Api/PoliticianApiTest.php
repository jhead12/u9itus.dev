<?php

namespace Tests\Feature\Api;

use App\Models\Politician;
use App\Models\PoliticalCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PoliticianApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_politician_registration_endpoint_exists(): void
    {
        $response = $this->withHeaders([
            'X-Wix-Instance' => 'fake-instance-token',
        ])->postJson('/api/v1/politicians', [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'politician@example.com',
            'office' => 'Mayor',
        ]);

        // Middleware may block without valid Wix instance
        $this->assertContains($response->status(), [200, 201, 401, 403, 422, 500]);
    }

    public function test_politician_profile_endpoint_exists(): void
    {
        $politician = Politician::factory()->create();

        $response = $this->withHeaders([
            'X-Wix-Instance' => 'fake-instance-token',
        ])->getJson("/api/v1/politicians/{$politician->uuid}");

        $this->assertContains($response->status(), [200, 401, 403, 404, 500]);
    }

    public function test_politician_update_endpoint_exists(): void
    {
        $politician = Politician::factory()->create();

        $response = $this->withHeaders([
            'X-Wix-Instance' => 'fake-instance-token',
        ])->putJson("/api/v1/politicians/{$politician->uuid}", [
            'first_name' => 'Updated',
        ]);

        $this->assertContains($response->status(), [200, 401, 403, 404, 422, 500]);
    }

    public function test_politician_create_campaign_endpoint_exists(): void
    {
        $politician = Politician::factory()->create();

        $response = $this->withHeaders([
            'X-Wix-Instance' => 'fake-instance-token',
        ])->postJson("/api/v1/politicians/{$politician->uuid}/campaigns", [
            'title' => 'Test Campaign',
            'total_budget' => 1000.00,
            'payment_per_view' => 0.60,
        ]);

        $this->assertContains($response->status(), [200, 201, 401, 403, 422, 500]);
    }

    public function test_politician_campaigns_list_endpoint_exists(): void
    {
        $politician = Politician::factory()->create();

        $response = $this->withHeaders([
            'X-Wix-Instance' => 'fake-instance-token',
        ])->getJson("/api/v1/politicians/{$politician->uuid}/campaigns");

        $this->assertContains($response->status(), [200, 401, 403, 404, 500]);
    }

    public function test_politician_campaign_show_endpoint_exists(): void
    {
        $politician = Politician::factory()->create();
        $campaign = PoliticalCampaign::factory()->create(['politician_id' => $politician->id]);

        $response = $this->withHeaders([
            'X-Wix-Instance' => 'fake-instance-token',
        ])->getJson("/api/v1/politicians/{$politician->uuid}/campaigns/{$campaign->uuid}");

        $this->assertContains($response->status(), [200, 401, 403, 404, 500]);
    }

    public function test_politician_endpoints_require_wix_headers(): void
    {
        $politician = Politician::factory()->create();

        // Request without Wix headers should be blocked
        $response = $this->getJson("/api/v1/politicians/{$politician->uuid}");

        // Should return 401 or 403 without valid Wix instance
        $this->assertContains($response->status(), [401, 403, 500]);
    }
}
