<?php

use App\Models\User;
use App\Models\Politician;
use App\Models\PoliticalCampaign;
use App\Enums\PaymentStatus;
use App\Enums\CampaignStatus;
use App\Enums\ApprovalStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed roles so hasRole() works
    if (class_exists(\Spatie\Permission\Models\Role::class)) {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'politician', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'voter', 'guard_name' => 'web']);
    }
});

/**
 * Create a User + linked Politician record (mirrors AuthController::register flow).
 * Returns the User with ->politician already loaded.
 */
function makePolitician(): User
{
    $user = User::factory()->create(['platform' => 'standalone']);

    if (method_exists($user, 'assignRole')) {
        $user->assignRole('politician');
    }

    Politician::factory()->create(['user_id' => $user->id]);

    return $user->load('politician');
}

/**
 * Create a PoliticalCampaign directly linked to a Politician record.
 */
function makeCampaign(Politician $politician, array $attrs = []): PoliticalCampaign
{
    return PoliticalCampaign::factory()->create(array_merge(
        ['politician_id' => $politician->id],
        $attrs,
    ));
}

// ---------------------------------------------------------------------------
// Campaign index
// ---------------------------------------------------------------------------

test('politician can view campaigns list', function () {
    $politician = makePolitician();

    $response = $this->actingAs($politician)
        ->get(route('politician.campaigns.index'));

    $response->assertOk()
             ->assertViewIs('standalone.politician.campaigns.index');
});

test('guest is redirected from campaigns list', function () {
    $this->get(route('politician.campaigns.index'))
         ->assertRedirect(route('login'));
});

// ---------------------------------------------------------------------------
// Create / Store campaign
// ---------------------------------------------------------------------------

test('politician can view the create campaign form', function () {
    $politician = makePolitician();

    $this->actingAs($politician)
         ->get(route('politician.campaigns.create'))
         ->assertOk()
         ->assertViewIs('standalone.politician.campaigns.create')
         ->assertViewHasAll(['revenuePerView', 'governanceLevels']);
});

test('politician can create a campaign', function () {
    $politician = makePolitician();

    $payload = [
        'title'                  => 'Test Campaign',
        'campaign_type'          => 'video',
        'total_views_requested'  => 100,
        'total_budget'           => 60.00,
        'message_summary'        => 'A short description.',
        'media_url'              => 'https://cdn.example.com/video.mp4',
        'media_duration'         => 15, // within env limits (MIN=10, MAX=20)
    ];

    $response = $this->actingAs($politician)
        ->post(route('politician.campaigns.store'), $payload);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('political_campaigns', [
        'title'                 => 'Test Campaign',
        'total_views_requested' => 100,
        'total_budget'          => 60.00,
    ]);
});

test('campaign store requires title', function () {
    $politician = makePolitician();

    $this->actingAs($politician)
         ->post(route('politician.campaigns.store'), [
             'campaign_type'         => 'video',
             'total_views_requested' => 100,
             'total_budget'          => 60,
         ])
         ->assertSessionHasErrors('title');
});

test('campaign store requires at least 10 views', function () {
    $politician = makePolitician();

    $this->actingAs($politician)
         ->post(route('politician.campaigns.store'), [
             'title'                 => 'Too Few Views',
             'campaign_type'         => 'video',
             'total_views_requested' => 5,
             'total_budget'          => 10,
         ])
         ->assertSessionHasErrors('total_views_requested');
});

test('campaign store requires minimum budget of 6 dollars', function () {
    $politician = makePolitician();

    $this->actingAs($politician)
         ->post(route('politician.campaigns.store'), [
             'title'                 => 'Too Low Budget',
             'campaign_type'         => 'video',
             'total_views_requested' => 10,
             'total_budget'          => 2,
         ])
         ->assertSessionHasErrors('total_budget');
});

test('live feed campaign requires live_feed_url', function () {
    $politician = makePolitician();

    $this->actingAs($politician)
         ->post(route('politician.campaigns.store'), [
             'title'                 => 'Live Campaign',
             'campaign_type'         => 'live_feed',
             'total_views_requested' => 50,
             'total_budget'          => 30,
             // missing live_feed_url
         ])
         ->assertSessionHasErrors('live_feed_url');
});

// ---------------------------------------------------------------------------
// Ownership
// ---------------------------------------------------------------------------

test('politician cannot view another politicians campaign', function () {
    $ownerUser = makePolitician();
    $otherUser = makePolitician();

    $campaign = makeCampaign($ownerUser->politician);

    $this->actingAs($otherUser)
         ->get(route('politician.campaigns.show', $campaign))
         ->assertForbidden();
});

test('politician cannot edit another politicians campaign', function () {
    $ownerUser = makePolitician();
    $otherUser = makePolitician();

    $campaign = makeCampaign($ownerUser->politician);

    $this->actingAs($otherUser)
         ->put(route('politician.campaigns.update', $campaign), ['title' => 'Hacked'])
         ->assertForbidden();
});

// ---------------------------------------------------------------------------
// Delete
// ---------------------------------------------------------------------------

test('politician can delete a draft campaign', function () {
    $politician = makePolitician();

    $campaign = makeCampaign($politician->politician, [
        'status' => 'draft',
    ]);

    $this->actingAs($politician)
         ->delete(route('politician.campaigns.destroy', $campaign))
         ->assertRedirect(route('politician.campaigns.index'));

    $this->assertDatabaseMissing('political_campaigns', ['id' => $campaign->id]);
});

test('politician cannot delete an active campaign', function () {
    $politician = makePolitician();

    $campaign = makeCampaign($politician->politician, [
        'status'         => CampaignStatus::Active->value,
        'approval_status'=> ApprovalStatus::Approved->value,
        'payment_status' => PaymentStatus::Captured->value,
    ]);

    $this->actingAs($politician)
         ->delete(route('politician.campaigns.destroy', $campaign))
         ->assertForbidden();

    $this->assertDatabaseHas('political_campaigns', ['id' => $campaign->id]);
});

// ---------------------------------------------------------------------------
// Submit for review
// ---------------------------------------------------------------------------

test('politician can submit a draft campaign with video for review', function () {
    $politician = makePolitician();

    $campaign = makeCampaign($politician->politician, [
        'status'    => 'draft',
        'media_url' => 'https://cdn.example.com/video.mp4',
    ]);

    $this->actingAs($politician)
         ->post(route('politician.campaigns.submit-review', $campaign))
         ->assertRedirect();

    expect($campaign->fresh()->status->value ?? $campaign->fresh()->status)
        ->toBe('pending_approval');
});

test('politician cannot submit a draft campaign without video for review', function () {
    $politician = makePolitician();

    $campaign = makeCampaign($politician->politician, [
        'status'    => 'draft',
        'media_url' => null,
    ]);

    $this->actingAs($politician)
         ->post(route('politician.campaigns.submit-review', $campaign))
         ->assertStatus(422);
});

test('politician cannot submit an already active campaign for review', function () {
    $politician = makePolitician();

    $campaign = makeCampaign($politician->politician, [
        'status'    => CampaignStatus::Active->value,
        'media_url' => 'https://cdn.example.com/video.mp4',
    ]);

    $this->actingAs($politician)
         ->post(route('politician.campaigns.submit-review', $campaign))
         ->assertStatus(422);
});

// ---------------------------------------------------------------------------
// Pages: analytics, billing, profile
// ---------------------------------------------------------------------------

test('politician can view the analytics page', function () {
    $politician = makePolitician();

    $this->actingAs($politician)
         ->get(route('politician.analytics'))
         ->assertOk()
         ->assertViewIs('standalone.politician.analytics');
});

test('politician can view the billing page', function () {
    $politician = makePolitician();

    $this->actingAs($politician)
         ->get(route('politician.billing'))
         ->assertOk()
         ->assertViewIs('standalone.politician.billing');
});

test('politician can view the profile page', function () {
    $politician = makePolitician();

    $this->actingAs($politician)
         ->get(route('politician.profile'))
         ->assertOk()
         ->assertViewIs('standalone.politician.profile');
});

test('politician can update their profile', function () {
    $politician = makePolitician();

    $this->actingAs($politician)
         ->put(route('politician.profile.update'), [
             'full_name'        => 'Jane Smith',
             'political_office' => 'Mayor',
             'state'            => 'CA',
         ])
         ->assertRedirect();

    expect($politician->politician->fresh()->full_name)->toBe('Jane Smith');
});

test('politician can view per-campaign analytics', function () {
    $politician = makePolitician();
    $campaign   = makeCampaign($politician->politician);

    $this->actingAs($politician)
         ->get(route('politician.analytics.campaign', $campaign))
         ->assertOk()
         ->assertViewIs('standalone.politician.analytics.campaign');
});
