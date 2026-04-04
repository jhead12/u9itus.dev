<?php

use App\Models\User;
use App\Models\Politician;
use App\Models\PoliticalCampaign;
use App\Models\Voter;
use App\Models\VoterWatchReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function makePoliticianUserForQuestionReply(string $name = 'politician'): array
{
    Role::firstOrCreate(['name' => 'politician', 'guard_name' => 'web']);

    $user = User::factory()->create([
        'platform' => 'standalone',
        'user_type' => 'politician',
    ]);
    $user->assignRole('politician');
    skipOnboarding($user, 'politician');

    $politician = Politician::factory()->create([
        'user_id' => $user->id,
        'full_name' => ucfirst($name) . ' User',
    ]);

    return [$user, $politician];
}

test('campaign owner can post official reply to voter question', function () {
    [$user, $politician] = makePoliticianUserForQuestionReply('owner');

    $campaign = PoliticalCampaign::factory()->create([
        'politician_id' => $politician->id,
    ]);

    $voter = Voter::factory()->create();

    $report = VoterWatchReport::query()->create([
        'voter_id' => $voter->id,
        'campaign_id' => $campaign->id,
        'type' => 'message',
        'body' => 'What is your plan?',
        'status' => 'open',
        'public_visibility' => 'pending',
    ]);

    $this->actingAs($user)
        ->post(route('politician.campaigns.questions.reply', [$campaign, $report]), [
            'campaign_reply' => 'We will publish a full transportation plan next month.',
        ])
        ->assertRedirect();

    expect($report->fresh()->campaign_reply)->toBe('We will publish a full transportation plan next month.')
        ->and($report->fresh()->campaign_replied_by)->toBe($user->id)
        ->and($report->fresh()->status)->toBe('resolved');
});

test('non-owner politician cannot reply to another campaign question', function () {
    [$ownerUser, $ownerPolitician] = makePoliticianUserForQuestionReply('owner');
    unset($ownerUser);

    [$otherUser] = makePoliticianUserForQuestionReply('other');

    $campaign = PoliticalCampaign::factory()->create([
        'politician_id' => $ownerPolitician->id,
    ]);

    $voter = Voter::factory()->create();

    $report = VoterWatchReport::query()->create([
        'voter_id' => $voter->id,
        'campaign_id' => $campaign->id,
        'type' => 'message',
        'body' => 'Can you clarify your policy?',
        'status' => 'open',
        'public_visibility' => 'pending',
    ]);

    $this->actingAs($otherUser)
        ->post(route('politician.campaigns.questions.reply', [$campaign, $report]), [
            'campaign_reply' => 'Unauthorized reply attempt.',
        ])
        ->assertForbidden();
});

test('politician reply fails when report does not belong to campaign route parameter', function () {
    [$user, $politician] = makePoliticianUserForQuestionReply('owner');

    $campaignA = PoliticalCampaign::factory()->create([
        'politician_id' => $politician->id,
    ]);

    $campaignB = PoliticalCampaign::factory()->create([
        'politician_id' => $politician->id,
    ]);

    $voter = Voter::factory()->create();

    $reportForCampaignB = VoterWatchReport::query()->create([
        'voter_id' => $voter->id,
        'campaign_id' => $campaignB->id,
        'type' => 'message',
        'body' => 'Question tied to campaign B',
        'status' => 'open',
        'public_visibility' => 'pending',
    ]);

    $this->actingAs($user)
        ->post(route('politician.campaigns.questions.reply', [$campaignA, $reportForCampaignB]), [
            'campaign_reply' => 'This should be rejected.',
        ])
        ->assertForbidden();
});
