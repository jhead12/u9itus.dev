<?php

use App\Enums\ApprovalStatus;
use App\Enums\CampaignStatus;
use App\Enums\CitizenAdType;
use App\Models\Citizen;
use App\Models\CitizenCampaign;
use App\Models\CitizenCampaignMessage;
use App\Models\CitizenCredit;
use App\Models\CitizenViewSession;
use App\Models\User;
use App\Models\Voter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    if (class_exists(\Spatie\Permission\Models\Role::class)) {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'voter', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'citizen', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
    }
});

if (! function_exists('makeVoterForCitizenWatchInteraction')) {
    function makeVoterForCitizenWatchInteraction(): User
    {
        $user = User::factory()->create(['platform' => 'standalone']);
        $user->assignRole('voter');
        Voter::factory()->create([
            'user_id'     => $user->id,
            'email'       => $user->email,
            'is_verified' => true,
            'is_active'   => true,
        ]);
        skipOnboarding($user, 'voter');
        return $user->load('voter');
    }
}

if (! function_exists('makeAdminForCitizenWatchInteraction')) {
    function makeAdminForCitizenWatchInteraction(): User
    {
        $user = User::factory()->create(['platform' => 'standalone']);
        $user->assignRole('admin');
        skipOnboarding($user, 'admin');
        return $user;
    }
}

if (! function_exists('makeApprovedCitizenCampaignForInteraction')) {
    function makeApprovedCitizenCampaignForInteraction(array $overrides = []): CitizenCampaign
    {
        $citizen = Citizen::factory()->create([
            'receipt_email' => 'sponsor@example.com',
        ]);

        CitizenCredit::factory()->create([
            'citizen_id'       => $citizen->id,
            'transaction_type' => 'purchase',
            'amount'           => 30.00,
            'balance_after'    => 30.00,
            'description'      => 'Opening balance',
        ]);
        $citizen->syncCreditBalance();

        $campaign = CitizenCampaign::factory()->create(array_merge([
            'citizen_id'             => $citizen->id,
            'citizen_ad_type'        => CitizenAdType::LocalBusiness->value,
            'status'                 => CampaignStatus::PendingApproval->value,
            'approval_status'        => ApprovalStatus::Pending->value,
            'total_budget'           => 30.00,
            'total_views_requested'  => 10,
            'media_duration'         => 60,
            'min_watch_time_percent' => 80,
            'allow_repeat_views'     => true,
            'max_views_per_voter'    => 10,
            'repeat_view_cooldown_hours' => 0,
            'media_url'              => 'https://www.youtube.com/watch?v=abc',
        ], $overrides));

        $admin = makeAdminForCitizenWatchInteraction();

        test()->actingAs($admin)
            ->post(route('admin.citizen-campaigns.approve', $campaign))
            ->assertSessionHas('success');

        $campaign->refresh();
        return $campaign;
    }
}

// ── Watch page rendering ────────────────────────────────────────────────────

test('watch page renders report and ask buttons for an eligible voter', function () {
    $user     = makeVoterForCitizenWatchInteraction();
    $campaign = makeApprovedCitizenCampaignForInteraction();

    $this->actingAs($user)
        ->get(route('voter.citizen-campaigns.watch', $campaign))
        ->assertOk()
        ->assertSee('Report Issue')
        ->assertSee('Ask the Creator')
        ->assertDontSee('Take Action'); // no CTA set
});

test('watch page renders the CTA button when a call to action is set', function () {
    $user     = makeVoterForCitizenWatchInteraction();
    $campaign = makeApprovedCitizenCampaignForInteraction([
        'call_to_action_url'   => 'https://example.com/offer',
        'call_to_action_label' => 'Shop Now',
    ]);

    $this->actingAs($user)
        ->get(route('voter.citizen-campaigns.watch', $campaign))
        ->assertOk()
        ->assertSee('Take Action')
        ->assertSee('Shop Now')
        ->assertSee('https://example.com/offer');
});

// ── Report issue ────────────────────────────────────────────────────────────

test('a voter can report an issue on a citizen campaign', function () {
    // Mail::fake() silences the best-effort admin notification (MailFake::raw
    // is a no-op); the testable behaviour is the persisted issue record.
    Mail::fake();

    $user     = makeVoterForCitizenWatchInteraction();
    $campaign = makeApprovedCitizenCampaignForInteraction();

    $this->actingAs($user)
        ->postJson(route('voter.citizen-campaigns.report-issue', $campaign), [
            'issue_category' => 'video_not_playing',
            'body'           => 'Video kept buffering',
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(CitizenCampaignMessage::count())->toBe(1);
    $message = CitizenCampaignMessage::first();
    expect($message->type)->toBe('issue')
        ->and($message->issue_category)->toBe('video_not_playing')
        ->and($message->voter_id)->toBe($user->voter->id)
        ->and($message->citizen_campaign_id)->toBe($campaign->id)
        ->and($message->body)->toBe('Video kept buffering');
});

test('report issue rejects an invalid category', function () {
    $user     = makeVoterForCitizenWatchInteraction();
    $campaign = makeApprovedCitizenCampaignForInteraction();

    $this->actingAs($user)
        ->postJson(route('voter.citizen-campaigns.report-issue', $campaign), [
            'issue_category' => 'bogus',
        ])
        ->assertStatus(422);

    expect(CitizenCampaignMessage::count())->toBe(0);
});

// ── Ask question ────────────────────────────────────────────────────────────

test('a voter can ask the creator a question and the sponsor has a reachable email', function () {
    // Mail::fake() silences the best-effort sponsor notification (MailFake::raw
    // is a no-op); we assert the question is persisted and that the sponsor
    // exposes an email so the controller's recipient branch is satisfiable.
    Mail::fake();

    $user     = makeVoterForCitizenWatchInteraction();
    $campaign = makeApprovedCitizenCampaignForInteraction();

    $sponsorEmail = $campaign->citizen->user->email ?? $campaign->citizen->receipt_email;

    $this->actingAs($user)
        ->postJson(route('voter.citizen-campaigns.ask-question', $campaign), [
            'body' => 'Is this offer still valid next week?',
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(CitizenCampaignMessage::count())->toBe(1);
    $message = CitizenCampaignMessage::first();
    expect($message->type)->toBe('message')
        ->and($message->body)->toBe('Is this offer still valid next week?')
        ->and($message->issue_category)->toBeNull()
        ->and($message->voter_id)->toBe($user->voter->id);

    expect($sponsorEmail)->not->toBeEmpty();
});

test('ask question requires a body', function () {
    $user     = makeVoterForCitizenWatchInteraction();
    $campaign = makeApprovedCitizenCampaignForInteraction();

    $this->actingAs($user)
        ->postJson(route('voter.citizen-campaigns.ask-question', $campaign), [
            'body' => '',
        ])
        ->assertStatus(422);

    expect(CitizenCampaignMessage::count())->toBe(0);
});

// ── Repeat viewing (free re-watches) ────────────────────────────────────────

test('watch page renders a Watch Again button only when repeat viewing is enabled', function () {
    // Helper defaults to allow_repeat_views = true.
    $user     = makeVoterForCitizenWatchInteraction();
    $campaign = makeApprovedCitizenCampaignForInteraction();

    $this->actingAs($user)
        ->get(route('voter.citizen-campaigns.watch', $campaign))
        ->assertOk()
        ->assertSee('Watch Again');

    $campaign->update(['allow_repeat_views' => false]);

    $this->actingAs($user)
        ->get(route('voter.citizen-campaigns.watch', $campaign))
        ->assertOk()
        ->assertDontSee('Watch Again');
});

test('a repeat completed view is recorded but pays nothing and charges no budget', function () {
    $user     = makeVoterForCitizenWatchInteraction();
    $campaign = makeApprovedCitizenCampaignForInteraction(); // repeat enabled, cooldown 0, max 10

    // First view: paid.
    $this->actingAs($user)
        ->postJson(route('voter.citizen-campaigns.complete', $campaign), [
            'total_seconds_watched'  => 60,
            'media_duration_seconds' => 60,
        ])
        ->assertOk()
        ->assertJsonPath('qualified', true)
        ->assertJsonPath('is_repeat', false)
        ->assertJsonPath('payout_earned', 0.50);

    $campaign->refresh();
    expect((float) $campaign->amount_spent)->toBe(0.75)
        ->and($campaign->views_completed)->toBe(1)
        ->and((float) $user->voter->fresh()->pending_earnings)->toBe(0.50)
        ->and(CitizenViewSession::count())->toBe(1);

    // Second view (re-watch): recorded, but no payout and no spend.
    $this->actingAs($user)
        ->postJson(route('voter.citizen-campaigns.complete', $campaign), [
            'total_seconds_watched'  => 60,
            'media_duration_seconds' => 60,
        ])
        ->assertOk()
        ->assertJsonPath('is_repeat', true)
        ->assertJsonPath('qualified', false)
        ->assertJsonPath('payout_earned', 0);

    $campaign->refresh();
    expect((float) $campaign->amount_spent)->toBe(0.75)        // unchanged
        ->and($campaign->views_completed)->toBe(1)             // only paid views count
        ->and((float) $user->voter->fresh()->pending_earnings)->toBe(0.50) // unchanged
        ->and(CitizenViewSession::count())->toBe(2);           // both sessions recorded
});