<?php

use App\Enums\CampaignStatus;
use App\Enums\ApprovalStatus;
use App\Models\CampaignAuditLog;
use App\Models\PoliticalCampaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function makeBulkCampaignAdmin(): User
{
    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $admin = User::factory()->create([
        'user_type' => 'admin',
    ]);

    $admin->assignRole('admin');
    skipOnboarding($admin, 'admin');

    return $admin;
}

test('admin can bulk stop selected campaigns from live campaign monitor', function () {
    $admin = makeBulkCampaignAdmin();
    $reason = 'Bulk compliance hold.';

    $activeCampaign = PoliticalCampaign::factory()->create([
        'status' => CampaignStatus::Active->value,
        'approval_status' => ApprovalStatus::Approved->value,
    ]);

    $alreadyPausedCampaign = PoliticalCampaign::factory()->create([
        'status' => CampaignStatus::Paused->value,
        'approval_status' => ApprovalStatus::Approved->value,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.campaigns.bulk-action'), [
            'action' => 'stop',
            'campaign_ids' => [$activeCampaign->id, $alreadyPausedCampaign->id],
            'reason' => $reason,
        ])
        ->assertRedirect();

    expect($activeCampaign->fresh()->status)->toBe(CampaignStatus::Paused);
    expect($alreadyPausedCampaign->fresh()->status)->toBe(CampaignStatus::Paused);

    $this->assertDatabaseHas('campaign_audit_logs', [
        'campaign_id' => $activeCampaign->id,
        'admin_id' => $admin->id,
        'action' => 'stopped',
        'reason' => $reason,
    ]);

    $this->assertDatabaseMissing('campaign_audit_logs', [
        'campaign_id' => $alreadyPausedCampaign->id,
        'admin_id' => $admin->id,
        'action' => 'stopped',
        'reason' => $reason,
    ]);
});

test('admin can bulk reactivate paused campaigns from live campaign monitor', function () {
    $admin = makeBulkCampaignAdmin();

    $pausedCampaign = PoliticalCampaign::factory()->create([
        'status' => CampaignStatus::Paused->value,
        'approval_status' => ApprovalStatus::Approved->value,
    ]);

    $activeCampaign = PoliticalCampaign::factory()->create([
        'status' => CampaignStatus::Active->value,
        'approval_status' => ApprovalStatus::Approved->value,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.campaigns.bulk-action'), [
            'action' => 'reactivate',
            'campaign_ids' => [$pausedCampaign->id, $activeCampaign->id],
        ])
        ->assertRedirect();

    expect($pausedCampaign->fresh()->status)->toBe(CampaignStatus::Active);
    expect($activeCampaign->fresh()->status)->toBe(CampaignStatus::Active);

    $this->assertDatabaseHas('campaign_audit_logs', [
        'campaign_id' => $pausedCampaign->id,
        'admin_id' => $admin->id,
        'action' => 'reactivated',
    ]);

    $activeCampaignReactivateLogs = CampaignAuditLog::query()
        ->where('campaign_id', $activeCampaign->id)
        ->where('action', 'reactivated')
        ->count();

    expect($activeCampaignReactivateLogs)->toBe(0);
});
