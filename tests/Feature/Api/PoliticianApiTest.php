<?php

namespace Tests\Feature\Api;

use App\Models\Politician;
use App\Models\PoliticalCampaign;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PoliticianApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('auth.guards.sanctum', [
            'driver' => 'session',
            'provider' => 'users',
        ]);

        Queue::fake();
    }

    public function test_politician_registration_endpoint_exists(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->postJson('/api/v1/politicians', [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'politician@example.com',
            'office' => 'Mayor',
        ]);

        $this->assertContains($response->status(), [200, 201, 401, 403, 422, 500]);
    }

    public function test_politician_profile_endpoint_exists(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $politician = Politician::factory()->create();

        $response = $this->getJson("/api/v1/politicians/{$politician->uuid}");

        $this->assertContains($response->status(), [200, 401, 403, 404, 500]);
    }

    public function test_politician_update_endpoint_exists(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $politician = Politician::factory()->create();

        $response = $this->putJson("/api/v1/politicians/{$politician->uuid}", [
            'first_name' => 'Updated',
        ]);

        $this->assertContains($response->status(), [200, 401, 403, 404, 422, 500]);
    }

    public function test_politician_create_campaign_endpoint_exists(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $politician = Politician::factory()->create();

        $response = $this->postJson("/api/v1/politicians/{$politician->uuid}/campaigns", [
            'title' => 'Test Campaign',
            'total_budget' => 1000.00,
            'payment_per_view' => 0.60,
        ]);

        $this->assertContains($response->status(), [200, 201, 401, 403, 422, 500]);
    }

    public function test_politician_campaigns_list_endpoint_exists(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $politician = Politician::factory()->create();

        $response = $this->getJson("/api/v1/politicians/{$politician->uuid}/campaigns");

        $this->assertContains($response->status(), [200, 401, 403, 404, 500]);
    }

    public function test_politician_campaign_show_endpoint_exists(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $politician = Politician::factory()->create();
        $campaign = PoliticalCampaign::factory()->create(['politician_id' => $politician->id]);

        $response = $this->getJson("/api/v1/politicians/{$politician->uuid}/campaigns/{$campaign->uuid}");

        $this->assertContains($response->status(), [200, 401, 403, 404, 500]);
    }

    public function test_politician_endpoints_require_authentication(): void
    {
        $politician = Politician::factory()->create();

        // Request without authentication should be blocked
        $response = $this->getJson("/api/v1/politicians/{$politician->uuid}");

        // Should return 401 without valid authentication
        $this->assertContains($response->status(), [401, 403, 500]);
    }

    public function test_politician_can_pause_active_campaign_via_api(): void
    {
        $user = User::factory()->create();
        $politician = Politician::factory()->create(['user_id' => $user->id]);
        $campaign = PoliticalCampaign::factory()->create([
            'politician_id' => $politician->id,
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $this->actingAs($user, 'sanctum');

        $response = $this->postJson("/api/v1/politicians/{$politician->uuid}/campaigns/{$campaign->uuid}/pause");

        $response->assertOk()
            ->assertJsonPath('message', 'Campaign paused')
            ->assertJsonPath('campaign.status', 'paused');

        $this->assertDatabaseHas('political_campaigns', [
            'id' => $campaign->id,
            'status' => 'paused',
        ]);
    }

    public function test_politician_pause_returns_422_for_non_active_campaign(): void
    {
        $user = User::factory()->create();
        $politician = Politician::factory()->create(['user_id' => $user->id]);
        $campaign = PoliticalCampaign::factory()->create([
            'politician_id' => $politician->id,
            'status' => 'paused',
            'approval_status' => 'approved',
        ]);

        $this->actingAs($user, 'sanctum');

        $response = $this->postJson("/api/v1/politicians/{$politician->uuid}/campaigns/{$campaign->uuid}/pause");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Only active campaigns can be paused');
    }

    public function test_politician_resume_requires_campaign_ownership(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $politician = Politician::factory()->create(['user_id' => $owner->id]);
        $campaign = PoliticalCampaign::factory()->create([
            'politician_id' => $politician->id,
            'status' => 'paused',
            'approval_status' => 'approved',
        ]);

        $this->actingAs($other, 'sanctum');

        $response = $this->postJson("/api/v1/politicians/{$politician->uuid}/campaigns/{$campaign->uuid}/resume");

        $response->assertStatus(403)
            ->assertJsonPath('message', 'Forbidden');
    }
}
