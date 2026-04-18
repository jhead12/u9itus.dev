<?php

use App\Enums\ApprovalStatus;
use App\Enums\CampaignStatus;
use App\Mail\CampaignQuestionDigestMail;
use App\Models\PoliticalCampaign;
use App\Models\User;
use App\Models\Voter;
use App\Models\VoterWatchReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('scheduled campaign ending queues voter question digest for the campaign owner', function () {
    Mail::fake();

    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $admin = User::factory()->create([
        'user_type' => 'admin',
        'email' => 'admin@example.test',
    ]);
    $admin->assignRole('admin');

    $campaignOwner = User::factory()->create([
        'user_type' => 'politician',
        'email' => 'candidate@example.test',
    ]);

    $politician = \App\Models\Politician::factory()->create([
        'user_id' => $campaignOwner->id,
    ]);

    $campaign = PoliticalCampaign::factory()->create([
        'politician_id' => $politician->id,
        'status' => CampaignStatus::Active->value,
        'approval_status' => ApprovalStatus::Approved->value,
        'scheduled_end_at' => now()->subMinute(),
    ]);

    $voter = Voter::factory()->create();

    VoterWatchReport::create([
        'voter_id' => $voter->id,
        'campaign_id' => $campaign->id,
        'type' => 'message',
        'body' => 'What will you do about housing costs?',
        'status' => 'open',
        'public_visibility' => 'pending',
        'is_public_board' => false,
    ]);

    VoterWatchReport::create([
        'voter_id' => $voter->id,
        'campaign_id' => $campaign->id,
        'type' => 'message',
        'body' => 'Will you publish a transit plan?',
        'status' => 'resolved',
        'public_visibility' => 'approved',
        'is_public_board' => true,
        'campaign_reply' => 'Yes, we will publish a corridor plan next quarter.',
        'campaign_replied_at' => now()->subDay(),
        'published_at' => now()->subHours(12),
    ]);

    $this->artisan('campaigns:apply-schedule')
        ->assertExitCode(0);

    expect($campaign->fresh()->status)->toBe(CampaignStatus::Paused);

    Mail::assertQueued(CampaignQuestionDigestMail::class, function (CampaignQuestionDigestMail $mail) use ($campaign) {
        return $mail->campaign->is($campaign)
            && $mail->questions->count() === 2;
    });
});

test('scheduled campaign ending skips digest when there are no voter questions', function () {
    Mail::fake();

    Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

    $admin = User::factory()->create([
        'user_type' => 'admin',
        'email' => 'admin@example.test',
    ]);
    $admin->assignRole('admin');

    $campaignOwner = User::factory()->create([
        'user_type' => 'politician',
        'email' => 'candidate@example.test',
    ]);

    $politician = \App\Models\Politician::factory()->create([
        'user_id' => $campaignOwner->id,
    ]);

    PoliticalCampaign::factory()->create([
        'politician_id' => $politician->id,
        'status' => CampaignStatus::Active->value,
        'approval_status' => ApprovalStatus::Approved->value,
        'scheduled_end_at' => now()->subMinute(),
    ]);

    $this->artisan('campaigns:apply-schedule')
        ->assertExitCode(0);

    Mail::assertNothingQueued();
});
