<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Advertiser;
use App\Models\LoyaltyViewer;
use App\Models\Campaign;
use App\Models\AdAssignment;
use App\Services\AdminAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed roles
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
    }

    public function test_admin_can_access_assignment_dashboard(): void
    {
        $admin = User::factory()->create(['user_type' => 'admin']);
        $admin->assignRole('admin');

        $response = $this->actingAs($admin)->get(route('admin.assignments.index'));

        $response->assertStatus(200);
    }

    public function test_viewer_can_only_have_one_active_assignment(): void
    {
        // Create admin
        $admin = User::factory()->create(['user_type' => 'admin']);
        $admin->assignRole('admin');

        // Create advertiser with campaign
        $advertiserUser = User::factory()->create(['user_type' => 'advertiser']);
        $advertiserUser->assignRole('advertiser');
        $advertiser = Advertiser::factory()->create(['user_id' => $advertiserUser->id]);
        $campaign = Campaign::factory()->create([
            'advertiser_id' => $advertiser->id,
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        // Create viewer
        $viewer = User::factory()->create([
            'user_type' => 'viewer',
            'is_verified' => true,
            'is_available_for_assignment' => true,
        ]);
        $viewer->assignRole('viewer');
        LoyaltyViewer::factory()->create(['user_id' => $viewer->id]);

        // Assign first ad
        $service = new AdminAssignmentService();
        $assignment = $service->assignAdToViewer($campaign, $viewer, $admin);

        $this->assertNotNull($assignment);
        $this->assertEquals('assigned', $assignment->status);
        
        // Verify viewer is no longer available
        $viewer->refresh();
        $this->assertFalse($viewer->is_available_for_assignment);
        $this->assertEquals($assignment->id, $viewer->current_assignment_id);

        // Try to assign another ad (should fail)
        $campaign2 = Campaign::factory()->create([
            'advertiser_id' => $advertiser->id,
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $this->expectException(\Exception::class);
        $service->assignAdToViewer($campaign2, $viewer, $admin);
    }

    public function test_assignment_expires_after_24_hours(): void
    {
        $admin = User::factory()->create(['user_type' => 'admin']);
        $admin->assignRole('admin');

        $advertiserUser = User::factory()->create(['user_type' => 'advertiser']);
        $advertiserUser->assignRole('advertiser');
        $advertiser = Advertiser::factory()->create(['user_id' => $advertiserUser->id]);
        $campaign = Campaign::factory()->create([
            'advertiser_id' => $advertiser->id,
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $viewer = User::factory()->create([
            'user_type' => 'viewer',
            'is_verified' => true,
            'is_available_for_assignment' => true,
        ]);
        $viewer->assignRole('viewer');
        LoyaltyViewer::factory()->create(['user_id' => $viewer->id]);

        $service = new AdminAssignmentService();
        $assignment = $service->assignAdToViewer($campaign, $viewer, $admin);

        // Verify assignment has correct expiration
        $expectedExpiry = now()->addHours(config('dial4dough.assignment_expiry_hours', 24));
        $this->assertEquals(
            $expectedExpiry->format('Y-m-d H:i'),
            $assignment->expires_at->format('Y-m-d H:i')
        );
    }

    public function test_viewer_cannot_watch_same_campaign_twice(): void
    {
        $admin = User::factory()->create(['user_type' => 'admin']);
        $admin->assignRole('admin');

        $advertiserUser = User::factory()->create(['user_type' => 'advertiser']);
        $advertiserUser->assignRole('advertiser');
        $advertiser = Advertiser::factory()->create(['user_id' => $advertiserUser->id]);
        $campaign = Campaign::factory()->create([
            'advertiser_id' => $advertiser->id,
            'status' => 'active',
            'approval_status' => 'approved',
        ]);

        $viewer = User::factory()->create([
            'user_type' => 'viewer',
            'is_verified' => true,
            'is_available_for_assignment' => true,
        ]);
        $viewer->assignRole('viewer');
        LoyaltyViewer::factory()->create(['user_id' => $viewer->id]);

        // First assignment
        $service = new AdminAssignmentService();
        $assignment = $service->assignAdToViewer($campaign, $viewer, $admin);
        
        // Complete the assignment
        $assignment->markCompleted($campaign->media_duration);
        
        // Verify viewer is now available again
        $viewer->refresh();
        $this->assertTrue($viewer->is_available_for_assignment);

        // Try to assign same campaign again (should fail)
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Viewer has already watched this campaign');
        $service->assignAdToViewer($campaign, $viewer, $admin);
    }
}

