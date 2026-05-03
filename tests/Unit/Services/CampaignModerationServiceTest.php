<?php

use App\Enums\ApprovalStatus;
use App\Enums\CampaignStatus;
use App\Models\CampaignAuditLog;
use App\Models\PoliticalCampaign;
use App\Models\User;
use App\Services\CampaignModerationService;
use App\Services\CampaignStatusNotifier;
use App\Services\PoliticalPaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

function makeModerationService(): CampaignModerationService
{
    $paymentService  = Mockery::mock(PoliticalPaymentService::class);
    $statusNotifier  = Mockery::mock(CampaignStatusNotifier::class);

    $paymentService->shouldReceive('chargeCampaign')->andReturn('')->byDefault();
    $statusNotifier->shouldReceive('notifyStatusChanged')->andReturnNull()->byDefault();

    return new CampaignModerationService($paymentService, $statusNotifier);
}

function pendingCampaign(array $overrides = []): PoliticalCampaign
{
    return PoliticalCampaign::factory()->create(array_merge([
        'status'          => CampaignStatus::PendingApproval->value,
        'approval_status' => ApprovalStatus::Pending->value,
        'scheduled_start_at' => null,
    ], $overrides));
}

// ── approve() — immediate activation ─────────────────────────────────────────

test('approve transitions campaign to Active when no scheduled_start_at', function () {
    $campaign = pendingCampaign();
    $svc      = makeModerationService();

    $result = $svc->approve($campaign);

    expect($result['new_status'])->toBe(CampaignStatus::Active);

    $campaign->refresh();
    expect($campaign->approval_status)->toBe(ApprovalStatus::Approved)
        ->and($campaign->status)->toBe(CampaignStatus::Active)
        ->and($campaign->approved_at)->not->toBeNull()
        ->and($campaign->started_at)->not->toBeNull();
});

test('approve stamps approved_at and started_at for immediate activation', function () {
    $campaign = pendingCampaign();
    $svc      = makeModerationService();

    $before = now()->subSecond();
    $svc->approve($campaign);
    $after  = now()->addSecond();

    $campaign->refresh();
    expect($campaign->approved_at->between($before, $after))->toBeTrue()
        ->and($campaign->started_at->between($before, $after))->toBeTrue();
});

test('approve calls chargeCampaign on the payment service', function () {
    $campaign       = pendingCampaign();
    $paymentService = Mockery::mock(PoliticalPaymentService::class);
    $statusNotifier = Mockery::mock(CampaignStatusNotifier::class);

    $paymentService->shouldReceive('chargeCampaign')->once()->with(Mockery::type(PoliticalCampaign::class))->andReturn('');
    $statusNotifier->shouldReceive('notifyStatusChanged')->andReturnNull();

    $svc = new CampaignModerationService($paymentService, $statusNotifier);
    $svc->approve($campaign);
});

test('approve calls notifyStatusChanged with approved', function () {
    $campaign       = pendingCampaign();
    $paymentService = Mockery::mock(PoliticalPaymentService::class);
    $statusNotifier = Mockery::mock(CampaignStatusNotifier::class);

    $paymentService->shouldReceive('chargeCampaign')->andReturn('');
    $statusNotifier->shouldReceive('notifyStatusChanged')
        ->once()
        ->with(Mockery::type(PoliticalCampaign::class), 'approved');

    $svc = new CampaignModerationService($paymentService, $statusNotifier);
    $svc->approve($campaign);
});

test('approve creates CampaignAuditLog when adminId is provided', function () {
    $campaign = pendingCampaign();
    $admin    = User::factory()->create();
    $svc      = makeModerationService();

    $svc->approve($campaign, $admin->id);

    $this->assertDatabaseHas('campaign_audit_logs', [
        'campaign_id' => $campaign->id,
        'admin_id'    => $admin->id,
        'action'      => 'approved',
    ]);
});

test('approve does not create CampaignAuditLog when adminId is null', function () {
    $campaign = pendingCampaign();
    $svc      = makeModerationService();

    $svc->approve($campaign, null);

    expect(CampaignAuditLog::where('campaign_id', $campaign->id)->count())->toBe(0);
});

// ── approve() — scheduled activation ─────────────────────────────────────────

test('approve transitions campaign to Scheduled when scheduled_start_at is in the future', function () {
    $campaign = pendingCampaign(['scheduled_start_at' => now()->addDays(3)]);
    $svc      = makeModerationService();

    $result = $svc->approve($campaign);

    expect($result['new_status'])->toBe(CampaignStatus::Scheduled);

    $campaign->refresh();
    expect($campaign->status)->toBe(CampaignStatus::Scheduled)
        ->and($campaign->started_at)->toBeNull();
});

test('approve label includes formatted date when campaign is scheduled', function () {
    $future   = now()->addDays(5);
    $campaign = pendingCampaign(['scheduled_start_at' => $future]);
    $svc      = makeModerationService();

    $result = $svc->approve($campaign);

    expect($result['label'])->toContain('approved and scheduled');
});

test('approve treats past scheduled_start_at as immediate activation', function () {
    $campaign = pendingCampaign(['scheduled_start_at' => now()->subDay()]);
    $svc      = makeModerationService();

    $result = $svc->approve($campaign);

    expect($result['new_status'])->toBe(CampaignStatus::Active);
});

// ── reject() ─────────────────────────────────────────────────────────────────

test('reject transitions campaign to Draft with Rejected approval_status', function () {
    $campaign = pendingCampaign();
    $svc      = makeModerationService();

    $svc->reject($campaign, 'Does not meet content guidelines.');

    $campaign->refresh();
    expect($campaign->approval_status)->toBe(ApprovalStatus::Rejected)
        ->and($campaign->status)->toBe(CampaignStatus::Draft)
        ->and($campaign->rejection_reason)->toBe('Does not meet content guidelines.');
});

test('reject calls notifyStatusChanged with rejected and the reason', function () {
    $campaign       = pendingCampaign();
    $paymentService = Mockery::mock(PoliticalPaymentService::class);
    $statusNotifier = Mockery::mock(CampaignStatusNotifier::class);

    $paymentService->shouldReceive('chargeCampaign')->andReturn('');
    $statusNotifier->shouldReceive('notifyStatusChanged')
        ->once()
        ->with(Mockery::type(PoliticalCampaign::class), 'rejected', 'Offensive content.');

    $svc = new CampaignModerationService($paymentService, $statusNotifier);
    $svc->reject($campaign, 'Offensive content.');
});

test('reject creates CampaignAuditLog when adminId is provided', function () {
    $campaign = pendingCampaign();
    $admin    = User::factory()->create();
    $svc      = makeModerationService();

    $svc->reject($campaign, 'Missing disclosures.', $admin->id);

    $this->assertDatabaseHas('campaign_audit_logs', [
        'campaign_id' => $campaign->id,
        'admin_id'    => $admin->id,
        'action'      => 'rejected',
        'reason'      => 'Missing disclosures.',
    ]);
});

test('reject does not create CampaignAuditLog when adminId is null', function () {
    $campaign = pendingCampaign();
    $svc      = makeModerationService();

    $svc->reject($campaign, 'Test reason', null);

    expect(CampaignAuditLog::where('campaign_id', $campaign->id)->count())->toBe(0);
});
