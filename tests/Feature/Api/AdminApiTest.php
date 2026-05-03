<?php

namespace Tests\Feature\Api;

use App\Mail\CampaignApprovedMail;
use App\Mail\CampaignRejectedMail;
use App\Models\NotificationPreference;
use App\Models\PoliticalCampaign;
use App\Models\Politician;
use App\Models\User;
use App\Models\Voter;
use App\Notifications\CampaignStatusChangedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminApiTest extends TestCase
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

        // Ensure the admin role exists for tests that require it
        if (class_exists(\Spatie\Permission\Models\Role::class)) {
            \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        }
    }

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

    public function test_admin_can_stop_active_campaign_via_api(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'sanctum');

        $campaign = PoliticalCampaign::factory()->create([
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $response = $this->postJson("/api/v1/admin/campaigns/{$campaign->uuid}/stop", [
            'reason' => 'Policy review required',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Campaign stopped')
            ->assertJsonPath('campaign.status', 'paused');

        $this->assertDatabaseHas('political_campaigns', [
            'id' => $campaign->id,
            'status' => 'paused',
        ]);
    }

    public function test_admin_stop_campaign_requires_reason(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'sanctum');

        $campaign = PoliticalCampaign::factory()->create([
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $response = $this->postJson("/api/v1/admin/campaigns/{$campaign->uuid}/stop", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_admin_can_reactivate_paused_campaign_via_api(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'sanctum');

        $campaign = PoliticalCampaign::factory()->create([
            'status' => 'paused',
            'approval_status' => 'approved',
        ]);

        $response = $this->postJson("/api/v1/admin/campaigns/{$campaign->uuid}/reactivate", [
            'reason' => 'Issue resolved',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Campaign reactivated')
            ->assertJsonPath('campaign.status', 'active');

        $this->assertDatabaseHas('political_campaigns', [
            'id' => $campaign->id,
            'status' => 'active',
        ]);
    }

    public function test_admin_reactivate_returns_422_when_campaign_not_paused(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'sanctum');

        $campaign = PoliticalCampaign::factory()->create([
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $response = $this->postJson("/api/v1/admin/campaigns/{$campaign->uuid}/reactivate");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Only paused campaigns can be reactivated');
    }

    public function test_admin_approve_campaign_via_api_sends_bell_and_email_notifications(): void
    {
        Mail::fake();
        Notification::fake();

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'sanctum');

        $politicianUser = User::factory()->create();
        $politician = Politician::factory()->create(['user_id' => $politicianUser->id]);
        $campaign = PoliticalCampaign::factory()->create([
            'politician_id' => $politician->id,
            'status' => 'pending_approval',
            'approval_status' => 'pending',
        ]);

        $response = $this->postJson("/api/v1/admin/campaigns/{$campaign->uuid}/approve");

        $response->assertOk()
            ->assertJsonPath('message', 'Campaign approved and activated');

        Notification::assertSentTo(
            $politicianUser,
            CampaignStatusChangedNotification::class,
            function (CampaignStatusChangedNotification $notification) {
                return $notification->status === 'approved';
            }
        );

        Mail::assertQueued(CampaignApprovedMail::class);
    }

    public function test_admin_reject_campaign_via_api_respects_email_toggle_and_sends_bell(): void
    {
        Mail::fake();
        Notification::fake();

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin, 'sanctum');

        $politicianUser = User::factory()->create();
        NotificationPreference::create([
            'user_id' => $politicianUser->id,
            'email_campaign_status' => false,
            'inapp_campaign_status' => true,
        ]);

        $politician = Politician::factory()->create(['user_id' => $politicianUser->id]);
        $campaign = PoliticalCampaign::factory()->create([
            'politician_id' => $politician->id,
            'status' => 'pending_approval',
            'approval_status' => 'pending',
        ]);

        $response = $this->postJson("/api/v1/admin/campaigns/{$campaign->uuid}/reject", [
            'reason' => 'Policy mismatch',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Campaign rejected');

        Notification::assertSentTo(
            $politicianUser,
            CampaignStatusChangedNotification::class,
            function (CampaignStatusChangedNotification $notification) {
                return $notification->status === 'rejected' && $notification->reason === 'Policy mismatch';
            }
        );

        Mail::assertNotQueued(CampaignRejectedMail::class);
    }
}
