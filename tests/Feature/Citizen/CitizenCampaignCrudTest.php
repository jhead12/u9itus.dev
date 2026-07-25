<?php

use App\Enums\ApprovalStatus;
use App\Enums\CampaignStatus;
use App\Enums\CitizenAdType;
use App\Models\Citizen;
use App\Models\CitizenCampaign;
use App\Models\User;
use Database\Seeders\CitizenTierPricingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────────

beforeEach(function () {
    if (class_exists(\Spatie\Permission\Models\Role::class)) {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'citizen',   'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'politician', 'guard_name' => 'web']);
    }
});

function makeCitizen(): User
{
    $user = User::factory()->create(['platform' => 'standalone']);
    $user->assignRole('citizen');
    Citizen::factory()->create(['user_id' => $user->id]);
    skipOnboarding($user, 'citizen');
    return $user->load('citizen');
}

function makeCitizenCampaign(Citizen $citizen, array $attrs = []): CitizenCampaign
{
    return CitizenCampaign::factory()->create(array_merge(
        ['citizen_id' => $citizen->id],
        $attrs
    ));
}

// ── Auth guards ───────────────────────────────────────────────────────────────

test('unauthenticated user is redirected from campaigns index', function () {
    $this->get(route('citizen.campaigns.index'))
        ->assertRedirect();
});

test('politician cannot access citizen campaign routes', function () {
    \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'politician', 'guard_name' => 'web']);
    $user = User::factory()->create(['platform' => 'standalone']);
    $user->assignRole('politician');
    \App\Models\Politician::factory()->create(['user_id' => $user->id]);
    skipOnboarding($user, 'politician');

    $this->actingAs($user)
        ->get(route('citizen.campaigns.index'))
        ->assertForbidden();
});

// ── Campaign index ────────────────────────────────────────────────────────────

test('citizen sees empty state on campaign list', function () {
    $user = makeCitizen();
    $this->actingAs($user)
        ->get(route('citizen.campaigns.index'))
        ->assertOk()
        ->assertSee('No campaigns yet');
});

test('citizen sees their campaigns listed', function () {
    $user = makeCitizen();
    makeCitizenCampaign($user->citizen, ['title' => 'My Test Ad']);

    $this->actingAs($user)
        ->get(route('citizen.campaigns.index'))
        ->assertOk()
        ->assertSee('My Test Ad');
});

// ── Campaign create ───────────────────────────────────────────────────────────

test('citizen can view create campaign form', function () {
    $user = makeCitizen();
    $this->actingAs($user)
        ->get(route('citizen.campaigns.create'))
        ->assertOk()
        ->assertSee('New Campaign');
});

test('citizen can create a local_business campaign at $0.75 rate', function () {
    $this->seed(CitizenTierPricingSeeder::class);
    $user = makeCitizen();

    $this->actingAs($user)
        ->post(route('citizen.campaigns.store'), [
            'title'                 => 'Maple Bakery Grand Opening',
            'citizen_ad_type'       => CitizenAdType::LocalBusiness->value,
            'campaign_type'         => 'video',
            'media_url'             => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'total_views_requested' => 20,
            'total_budget'          => 15.00,
            'target_zip'            => '90210',
            'target_zip_radius'     => 10,
        ])
        ->assertRedirect();

    $campaign = CitizenCampaign::where('title', 'Maple Bakery Grand Opening')->first();
    expect($campaign)->not->toBeNull();
    expect((float) $campaign->revenue_per_view)->toBe(0.75);
    expect($campaign->status->value)->toBe('draft');
    expect((float) $campaign->total_budget)->toBe(15.00); // 20 × 0.75
});

test('citizen can create a ballot_issue campaign at $1.00 rate with pac_registration_id', function () {
    $this->seed(CitizenTierPricingSeeder::class);
    $user = makeCitizen();

    $this->actingAs($user)
        ->post(route('citizen.campaigns.store'), [
            'title'                 => 'Vote Yes on Measure B',
            'citizen_ad_type'       => CitizenAdType::BallotIssue->value,
            'campaign_type'         => 'video',
            'media_url'             => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'total_views_requested' => 20,
            'total_budget'          => 20.00,
            'target_zip'            => '90210',
            'pac_registration_id'   => 'C00123456',
        ])
        ->assertRedirect();

    $campaign = CitizenCampaign::where('title', 'Vote Yes on Measure B')->first();
    expect($campaign)->not->toBeNull();
    expect((float) $campaign->revenue_per_view)->toBe(1.00);
    expect($campaign->pac_registration_id)->toBe('C00123456');
    expect($campaign->daily_view_cap)->toBeNull();
    expect($campaign->approval_status->value)->toBe('pending');
});

test('ballot_issue campaign requires pac_registration_id', function () {
    $user = makeCitizen();

    $this->actingAs($user)
        ->post(route('citizen.campaigns.store'), [
            'title'                 => 'Missing PAC',
            'citizen_ad_type'       => CitizenAdType::BallotIssue->value,
            'campaign_type'         => 'video',
            'media_url'             => 'https://www.youtube.com/watch?v=abc',
            'total_views_requested' => 10,
            'total_budget'          => 10.00,
            'target_zip'            => '90210',
            // pac_registration_id omitted
        ])
        ->assertSessionHasErrors(['pac_registration_id']);
});

test('campaign_type q_and_a is rejected for citizens', function () {
    $user = makeCitizen();

    $this->actingAs($user)
        ->post(route('citizen.campaigns.store'), [
            'title'                 => 'QA Test',
            'citizen_ad_type'       => CitizenAdType::LocalBusiness->value,
            'campaign_type'         => 'q_and_a',
            'media_url'             => 'https://www.youtube.com/watch?v=abc',
            'total_views_requested' => 10,
            'total_budget'          => 7.50,
            'target_zip'            => '90210',
        ])
        ->assertSessionHasErrors(['campaign_type']);
});

test('minimum budget validation for citizen rate', function () {
    $user = makeCitizen();

    // $0.75 × 10 views = $7.50 minimum. Sending $1 should fail.
    $this->actingAs($user)
        ->post(route('citizen.campaigns.store'), [
            'title'                 => 'Underfunded',
            'citizen_ad_type'       => CitizenAdType::LocalBusiness->value,
            'campaign_type'         => 'video',
            'media_url'             => 'https://www.youtube.com/watch?v=abc',
            'total_views_requested' => 10,
            'total_budget'          => 1.00,
            'target_zip'            => '90210',
        ])
        ->assertSessionHasErrors(['total_budget']);
});

test('target_zip must be exactly 5 digits', function () {
    $user = makeCitizen();

    $this->actingAs($user)
        ->post(route('citizen.campaigns.store'), [
            'title'                 => 'Bad ZIP',
            'citizen_ad_type'       => CitizenAdType::LocalBusiness->value,
            'campaign_type'         => 'video',
            'media_url'             => 'https://www.youtube.com/watch?v=abc',
            'total_views_requested' => 10,
            'total_budget'          => 7.50,
            'target_zip'            => 'ABCDE',
        ])
        ->assertSessionHasErrors(['target_zip']);
});

// ── Show / Edit / Delete ──────────────────────────────────────────────────────

test('citizen can view their own campaign', function () {
    $user     = makeCitizen();
    $campaign = makeCitizenCampaign($user->citizen, ['title' => 'My Campaign']);

    $this->actingAs($user)
        ->get(route('citizen.campaigns.show', $campaign))
        ->assertOk()
        ->assertSee('My Campaign');
});

test('citizen cannot view another citizens campaign', function () {
    $userA = makeCitizen();
    $userB = makeCitizen();
    $campaign = makeCitizenCampaign($userA->citizen);

    $this->actingAs($userB)
        ->get(route('citizen.campaigns.show', $campaign))
        ->assertForbidden();
});

test('citizen can edit a draft campaign', function () {
    $user     = makeCitizen();
    $campaign = makeCitizenCampaign($user->citizen, [
        'status'           => CampaignStatus::Draft->value,
        'citizen_ad_type'  => CitizenAdType::LocalBusiness->value,
        'target_zip'       => '90210',
    ]);

    $this->actingAs($user)
        ->get(route('citizen.campaigns.edit', $campaign))
        ->assertOk()
        ->assertSee('Edit Campaign');
});

test('citizen cannot edit a pending_approval campaign', function () {
    $user     = makeCitizen();
    $campaign = makeCitizenCampaign($user->citizen, [
        'status' => CampaignStatus::PendingApproval->value,
    ]);

    $this->actingAs($user)
        ->get(route('citizen.campaigns.edit', $campaign))
        ->assertForbidden();
});

test('citizen can delete a draft campaign', function () {
    $user     = makeCitizen();
    $campaign = makeCitizenCampaign($user->citizen, ['status' => CampaignStatus::Draft->value]);

    $this->actingAs($user)
        ->delete(route('citizen.campaigns.destroy', $campaign))
        ->assertRedirect(route('citizen.campaigns.index'));

    expect(CitizenCampaign::find($campaign->id))->toBeNull();
});

test('citizen cannot delete an active campaign', function () {
    $user     = makeCitizen();
    $campaign = makeCitizenCampaign($user->citizen, ['status' => CampaignStatus::Active->value]);

    $this->actingAs($user)
        ->delete(route('citizen.campaigns.destroy', $campaign))
        ->assertForbidden();

    expect(CitizenCampaign::find($campaign->id))->not->toBeNull();
});

// ── Submit for review ─────────────────────────────────────────────────────────

test('citizen can submit a draft campaign with video for review', function () {
    $user     = makeCitizen();
    $campaign = makeCitizenCampaign($user->citizen, [
        'status'    => CampaignStatus::Draft->value,
        'media_url' => 'https://www.youtube.com/watch?v=abc',
    ]);

    $this->actingAs($user)
        ->post(route('citizen.campaigns.submit-review', $campaign))
        ->assertRedirect();

    $campaign->refresh();
    expect($campaign->status->value)->toBe('pending_approval');
    expect($campaign->approval_status->value)->toBe('pending');
});

test('citizen cannot submit a draft campaign without video', function () {
    $user     = makeCitizen();
    $campaign = makeCitizenCampaign($user->citizen, [
        'status'    => CampaignStatus::Draft->value,
        'media_url' => null,
    ]);

    $this->actingAs($user)
        ->post(route('citizen.campaigns.submit-review', $campaign))
        ->assertStatus(422);
});

test('ballot issue campaign requires pac_registration_id before submission', function () {
    $user     = makeCitizen();
    $campaign = makeCitizenCampaign($user->citizen, [
        'status'              => CampaignStatus::Draft->value,
        'citizen_ad_type'     => CitizenAdType::BallotIssue->value,
        'media_url'           => 'https://www.youtube.com/watch?v=abc',
        'pac_registration_id' => null,
    ]);

    $this->actingAs($user)
        ->post(route('citizen.campaigns.submit-review', $campaign))
        ->assertSessionHasErrors(['pac_registration_id']);
});

// ── Video upload ──────────────────────────────────────────────────────────────

test('citizen can upload a video file to a draft campaign', function () {
    Storage::fake('local');
    $user     = makeCitizen();
    $campaign = makeCitizenCampaign($user->citizen, ['status' => CampaignStatus::Draft->value]);

    $file = UploadedFile::fake()->create('ad.mp4', 512, 'video/mp4');

    $this->actingAs($user)
        ->post(route('citizen.campaigns.upload-video', $campaign), ['video' => $file])
        ->assertRedirect();

    $campaign->refresh();
    expect($campaign->media_url)->not->toBeNull();
    expect($campaign->media_type)->toBe('direct_file');
});

test('citizen cannot upload a video to an active campaign', function () {
    Storage::fake('local');
    $user     = makeCitizen();
    $campaign = makeCitizenCampaign($user->citizen, ['status' => CampaignStatus::Active->value]);

    $file = UploadedFile::fake()->create('ad.mp4', 512, 'video/mp4');

    $this->actingAs($user)
        ->post(route('citizen.campaigns.upload-video', $campaign), ['video' => $file])
        ->assertSessionHasErrors(['video']);
});

// ── Review as voter ─────────────────────────────────────────────────────────────

test('citizen can review their draft campaign as a voter', function () {
    $user     = makeCitizen();
    $campaign = makeCitizenCampaign($user->citizen, [
        'status'              => CampaignStatus::Draft->value,
        'media_url'           => 'https://www.youtube.com/watch?v=abc',
        'media_duration'      => 60,
        'voter_payout_per_view' => 0.50,
    ]);

    $this->actingAs($user)
        ->get(route('citizen.campaigns.review', $campaign))
        ->assertOk()
        ->assertSee('Preview Mode')
        ->assertSee($campaign->title)
        ->assertSee('Click to Play Preview');
});

test('citizen cannot review another citizens campaign', function () {
    $userA = makeCitizen();
    $userB = makeCitizen();
    $campaign = makeCitizenCampaign($userA->citizen, [
        'status'    => CampaignStatus::Draft->value,
        'media_url' => 'https://www.youtube.com/watch?v=abc',
    ]);

    $this->actingAs($userB)
        ->get(route('citizen.campaigns.review', $campaign))
        ->assertForbidden();
});

test('citizen cannot review a non-draft campaign', function () {
    $user     = makeCitizen();
    $campaign = makeCitizenCampaign($user->citizen, [
        'status'    => CampaignStatus::PendingApproval->value,
        'media_url' => 'https://www.youtube.com/watch?v=abc',
    ]);

    $this->actingAs($user)
        ->get(route('citizen.campaigns.review', $campaign))
        ->assertForbidden();
});

test('review page redirects when campaign has no video or live feed', function () {
    $user     = makeCitizen();
    $campaign = makeCitizenCampaign($user->citizen, [
        'status'        => CampaignStatus::Draft->value,
        'media_url'     => null,
        'live_feed_url' => null,
    ]);

    $this->actingAs($user)
        ->get(route('citizen.campaigns.review', $campaign))
        ->assertRedirect(route('citizen.campaigns.show', $campaign))
        ->assertSessionHasErrors(['review']);
});

test('review page does not record views or create sessions', function () {
    $user     = makeCitizen();
    $campaign = makeCitizenCampaign($user->citizen, [
        'status'                => CampaignStatus::Draft->value,
        'media_url'               => 'https://www.youtube.com/watch?v=abc',
        'media_duration'          => 60,
        'views_completed'         => 0,
        'total_views_requested'   => 100,
        'voter_payout_per_view'   => 0.50,
    ]);

    $sessionCount = \App\Models\CitizenViewSession::count();

    $this->actingAs($user)
        ->get(route('citizen.campaigns.review', $campaign))
        ->assertOk();

    $campaign->refresh();
    expect($campaign->views_completed)->toBe(0);
    expect(\App\Models\CitizenViewSession::count())->toBe($sessionCount);
});

test('review page shows targeting info and zip eligibility', function () {
    $user     = makeCitizen();
    $user->citizen->update(['zip' => '90210']);

    $campaign = makeCitizenCampaign($user->citizen, [
        'status'            => CampaignStatus::Draft->value,
        'media_url'         => 'https://www.youtube.com/watch?v=abc',
        'target_zip'        => '90210',
        'target_zip_radius' => 10,
    ]);

    $this->actingAs($user)
        ->get(route('citizen.campaigns.review', $campaign))
        ->assertOk()
        ->assertSee('Targeting:')
        ->assertSeeText('Campaign target: 90210')
        ->assertSeeText('Radius: 10 miles');
});
