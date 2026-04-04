<?php

use App\Models\CampaignTransaction;
use App\Models\EngagementSurveyResponse;
use App\Models\PoliticalCampaign;
use App\Models\User;
use App\Models\ViewSession;
use App\Models\Voter;
use App\Models\VoterWatchReport;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    if (class_exists(\Spatie\Permission\Models\Role::class)) {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'voter', 'guard_name' => 'web']);
    }
});

function makeAdminForEngagementReport(): User
{
    $admin = User::factory()->create([
        'platform' => 'standalone',
        'user_type' => 'admin',
    ]);

    if (method_exists($admin, 'assignRole')) {
        $admin->assignRole('admin');
    }

    skipOnboarding($admin, 'admin');

    return $admin;
}

function makeModeScopedCampaign(): PoliticalCampaign
{
    $campaign = PoliticalCampaign::factory()->create();

    CampaignTransaction::query()->create([
        'campaign_id' => $campaign->id,
        'politician_id' => $campaign->politician_id,
        'transaction_type' => 'charge',
        'amount' => 25.00,
        'currency' => 'USD',
        'status' => 'succeeded',
        'metadata' => ['payment_mode' => 'test'],
    ]);

    return $campaign;
}

test('admin engagement report shows survey and voter question analytics', function () {
    $admin = makeAdminForEngagementReport();
    $campaign = makeModeScopedCampaign();

    $voterUser = User::factory()->create([
        'platform' => 'standalone',
        'user_type' => 'voter',
    ]);

    $voter = Voter::factory()->create([
        'user_id' => $voterUser->id,
        'email' => $voterUser->email,
    ]);

    $session = ViewSession::factory()->completed()->create([
        'political_campaign_id' => $campaign->id,
        'voter_id' => $voter->id,
        'status' => 'completed',
    ]);

    EngagementSurveyResponse::query()->create([
        'view_session_id' => $session->id,
        'campaign_id' => $campaign->id,
        'voter_id' => $voter->id,
        'response_value' => 'support',
        'response_text' => 'I support this policy.',
        'responded_at' => now(),
    ]);

    VoterWatchReport::query()->create([
        'voter_id' => $voter->id,
        'campaign_id' => $campaign->id,
        'type' => 'message',
        'body' => 'How will this affect housing costs?',
        'status' => 'open',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.reports.engagement'))
        ->assertOk()
        ->assertSee('Question Queue Status')
        ->assertSee('Survey Option Distribution')
        ->assertSee('How will this affect housing costs?')
        ->assertSee('support');
});

test('admin engagement report filters voter questions by status', function () {
    $admin = makeAdminForEngagementReport();
    $campaign = makeModeScopedCampaign();

    $voter = Voter::factory()->create();

    VoterWatchReport::query()->create([
        'voter_id' => $voter->id,
        'campaign_id' => $campaign->id,
        'type' => 'message',
        'body' => 'Open question should be hidden.',
        'status' => 'open',
    ]);

    VoterWatchReport::query()->create([
        'voter_id' => $voter->id,
        'campaign_id' => $campaign->id,
        'type' => 'message',
        'body' => 'Resolved question should appear.',
        'status' => 'resolved',
        'admin_notes' => 'Answered publicly.',
        'resolved_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.reports.engagement', ['question_status' => 'resolved']))
        ->assertOk()
        ->assertSee('Resolved question should appear.')
        ->assertDontSee('Open question should be hidden.');
});

test('engagement report route is forbidden for non-admin users', function () {
    $user = User::factory()->create([
        'platform' => 'standalone',
        'user_type' => 'voter',
    ]);

    if (method_exists($user, 'assignRole')) {
        $user->assignRole('voter');
    }

    skipOnboarding($user, 'voter');

    $this->actingAs($user)
        ->get(route('admin.reports.engagement'))
        ->assertForbidden();
});

test('engagement report falls back to safe defaults for invalid filters', function () {
    $admin = makeAdminForEngagementReport();
    $campaign = makeModeScopedCampaign();
    $voter = Voter::factory()->create();

    VoterWatchReport::query()->create([
        'voter_id' => $voter->id,
        'campaign_id' => $campaign->id,
        'type' => 'message',
        'body' => 'Open queue item',
        'status' => 'open',
    ]);

    VoterWatchReport::query()->create([
        'voter_id' => $voter->id,
        'campaign_id' => $campaign->id,
        'type' => 'message',
        'body' => 'Resolved queue item',
        'status' => 'resolved',
        'admin_notes' => 'Resolved response',
        'resolved_at' => now(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.reports.engagement', [
            'question_status' => 'invalid-status',
            'days' => 999,
        ]))
        ->assertOk()
        ->assertSee('Open queue item')
        ->assertSee('Resolved queue item')
        ->assertSee('30-day window');
});

test('admin can approve and reject public visibility for voter questions', function () {
    $admin = makeAdminForEngagementReport();
    $campaign = makeModeScopedCampaign();
    $voter = Voter::factory()->create();

    $report = VoterWatchReport::query()->create([
        'voter_id' => $voter->id,
        'campaign_id' => $campaign->id,
        'type' => 'message',
        'body' => 'Moderate this question',
        'status' => 'open',
        'public_visibility' => 'pending',
        'is_public_board' => false,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.reports.engagement.questions.moderate', $report), [
            'visibility_action' => 'approve',
        ])
        ->assertRedirect();

    expect($report->fresh()->public_visibility)->toBe('approved')
        ->and($report->fresh()->is_public_board)->toBeTrue()
        ->and($report->fresh()->published_by)->toBe($admin->id);

    $this->actingAs($admin)
        ->post(route('admin.reports.engagement.questions.moderate', $report), [
            'visibility_action' => 'reject',
        ])
        ->assertRedirect();

    expect($report->fresh()->public_visibility)->toBe('rejected')
        ->and($report->fresh()->is_public_board)->toBeFalse();
});

test('engagement report filters voter questions by public visibility', function () {
    $admin = makeAdminForEngagementReport();
    $campaign = makeModeScopedCampaign();
    $voter = Voter::factory()->create();

    VoterWatchReport::query()->create([
        'voter_id' => $voter->id,
        'campaign_id' => $campaign->id,
        'type' => 'message',
        'body' => 'Pending moderation item',
        'status' => 'open',
        'public_visibility' => 'pending',
        'is_public_board' => false,
    ]);

    VoterWatchReport::query()->create([
        'voter_id' => $voter->id,
        'campaign_id' => $campaign->id,
        'type' => 'message',
        'body' => 'Approved board item',
        'status' => 'resolved',
        'public_visibility' => 'approved',
        'is_public_board' => true,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.reports.engagement', ['public_visibility' => 'approved']))
        ->assertOk()
        ->assertSee('Approved board item')
        ->assertDontSee('Pending moderation item');
});
