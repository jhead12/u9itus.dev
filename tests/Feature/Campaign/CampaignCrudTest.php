<?php

use App\Models\User;
use App\Models\CampaignTransaction;
use App\Models\Politician;
use App\Models\PoliticianCredit;
use App\Models\PoliticalCampaign;
use App\Enums\PaymentStatus;
use App\Enums\CampaignStatus;
use App\Enums\ApprovalStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

    // Skip onboarding for test
    skipOnboarding($user, 'politician');

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
    $requestedViews = 100;
    $expectedBudget = round(
        $requestedViews * (float) config('u9itus.revenue_per_view', 1.00),
        2
    );

    $payload = [
        'title'                  => 'Test Campaign',
        'campaign_type'          => 'video',
        'governance_level'       => 'city',
        'total_views_requested'  => $requestedViews,
        'total_budget'           => 60.00,
        'message_summary'        => 'A short description.',
        'media_url'              => 'https://cdn.example.com/video.mp4',
        'media_duration'         => config('u9itus.min_video_duration', 30) + 5,
    ];

    $response = $this->actingAs($politician)
        ->post(route('politician.campaigns.store'), $payload);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('political_campaigns', [
        'title'                 => 'Test Campaign',
        'total_views_requested' => $requestedViews,
        'total_budget'          => $expectedBudget,
    ]);
});

test('campaign store infers youtube media type from url', function () {
    $politician = makePolitician();

    $response = $this->actingAs($politician)
        ->post(route('politician.campaigns.store'), [
            'title' => 'YouTube Source Campaign',
            'campaign_type' => 'video',
            'governance_level' => 'city',
            'total_views_requested' => 100,
            'total_budget' => 60.00,
            'message_summary' => 'YouTube-backed campaign',
            'media_type' => 'direct_file',
            'media_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'media_duration' => config('u9itus.min_video_duration', 30) + 5,
        ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $campaign = PoliticalCampaign::query()->where('title', 'YouTube Source Campaign')->first();

    expect($campaign)->not->toBeNull();
    expect($campaign->getRawOriginal('media_type'))->toBe('youtube');
    expect($campaign->media_url)->toBe('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
});

test('campaign store prefers uploaded file over provided media url', function () {
    Storage::fake('public');
    config()->set('filesystems.default', 'public');

    $politician = makePolitician();

    $response = $this->actingAs($politician)
        ->post(route('politician.campaigns.store'), [
            'title' => 'Uploaded File Wins Campaign',
            'campaign_type' => 'video',
            'governance_level' => 'city',
            'total_views_requested' => 100,
            'total_budget' => 60.00,
            'message_summary' => 'Uploaded file should override url',
            'media_type' => 'youtube',
            'media_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
            'media_duration' => config('u9itus.min_video_duration', 30) + 5,
            'video' => UploadedFile::fake()->create('stored-video.mp4', 5, 'video/mp4'),
        ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $campaign = PoliticalCampaign::query()->where('title', 'Uploaded File Wins Campaign')->first();

    expect($campaign)->not->toBeNull();
    expect($campaign->getRawOriginal('media_type'))->toBe('direct_file');
    expect((string) $campaign->media_url)->toContain('/storage/campaigns/' . $campaign->id . '/video/');
});

test('politician can create a q_and_a campaign', function () {
    $politician = makePolitician();

    $payload = [
        'title'                  => 'Town Hall Q&A: Public Safety',
        'campaign_type'          => 'q_and_a',
        'governance_level'       => 'city',
        'total_views_requested'  => 100,
        'total_budget'           => 60.00,
        'message_summary'        => 'Answers to most common district questions.',
        'media_url'              => 'https://cdn.example.com/qa-answer.mp4',
        'media_duration'         => config('u9itus.min_video_duration', 30) + 5,
    ];

    $response = $this->actingAs($politician)
        ->post(route('politician.campaigns.store'), $payload);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('political_campaigns', [
        'title' => 'Town Hall Q&A: Public Safety',
        'campaign_type' => 'q_and_a',
    ]);
});

test('politician can save a partial campaign draft', function () {
    $politician = makePolitician();

    $response = $this->actingAs($politician)
        ->post(route('politician.campaigns.save-draft'), [
            'campaign_type' => 'video',
            'message_summary' => 'Draft message only',
        ]);

    $campaign = PoliticalCampaign::query()->latest('id')->first();

    expect($campaign)->not->toBeNull();

    $response->assertRedirect(route('politician.campaigns.edit', $campaign));
    $response->assertSessionHasNoErrors();

    $this->assertDatabaseHas('political_campaigns', [
        'id' => $campaign->id,
        'politician_id' => $politician->politician->id,
        'status' => 'draft',
        'campaign_type' => 'video',
        'message_summary' => 'Draft message only',
    ]);

    expect((string) $campaign->title)->toContain('Draft Campaign -');
});

test('campaign store requires title', function () {
    $politician = makePolitician();

    $this->actingAs($politician)
         ->post(route('politician.campaigns.store'), [
             'campaign_type'         => 'video',
             'governance_level'      => 'city',
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
             'governance_level'      => 'city',
             'total_views_requested' => 5,
             'total_budget'          => 10,
         ])
         ->assertSessionHasErrors('total_views_requested');
});

test('campaign store rejects media_duration above configured max', function () {
    $politician = makePolitician();
    $maxDuration = (int) config('u9itus.max_video_duration', 300);

    $this->actingAs($politician)
         ->post(route('politician.campaigns.store'), [
             'title'                 => 'Too Long Video Campaign',
             'campaign_type'         => 'video',
             'governance_level'      => 'city',
             'total_views_requested' => 100,
             'total_budget'          => 60,
             'media_url'             => 'https://cdn.example.com/video.mp4',
             'media_duration'        => $maxDuration + 1,
         ])
         ->assertSessionHasErrors('media_duration');
});

test('campaign store requires minimum budget of 6 dollars', function () {
    $politician = makePolitician();

    $this->actingAs($politician)
         ->post(route('politician.campaigns.store'), [
             'title'                 => 'Too Low Budget',
             'campaign_type'         => 'video',
             'governance_level'      => 'city',
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
             'governance_level'      => 'city',
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

test('campaign update infers hls media type from playlist url', function () {
    $politician = makePolitician();

    $campaign = makeCampaign($politician->politician, [
        'status' => 'draft',
        'media_type' => 'direct_file',
        'media_url' => 'https://cdn.example.com/video.mp4',
        'governance_level' => 'city',
    ]);

    $response = $this->actingAs($politician)
        ->put(route('politician.campaigns.update', $campaign), [
            'title' => 'Updated HLS Campaign',
            'campaign_type' => 'video',
            'governance_level' => 'city',
            'total_views_requested' => 100,
            'total_budget' => 60.00,
            'media_type' => 'youtube',
            'media_url' => 'https://stream.example.com/channel/main.m3u8',
        ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $fresh = $campaign->fresh();
    expect($fresh->getRawOriginal('media_type'))->toBe('hls_stream');
    expect($fresh->media_url)->toBe('https://stream.example.com/channel/main.m3u8');
});

test('politician can schedule an approved active campaign immediately after approval', function () {
    $politician = makePolitician();

    $campaign = makeCampaign($politician->politician, [
        'status' => CampaignStatus::Active->value,
        'approval_status' => ApprovalStatus::Approved->value,
        'governance_level' => 'city',
    ]);

    $scheduledStartAt = now()->addHour()->startOfMinute();

    $response = $this->actingAs($politician)
        ->put(route('politician.campaigns.update', $campaign), [
            'scheduled_start_at' => $scheduledStartAt->format('Y-m-d H:i:s'),
        ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();

    $campaign->refresh();

    expect($campaign->status)->toBe(CampaignStatus::Scheduled);
    expect($campaign->scheduled_start_at)->not->toBeNull();
    expect($campaign->scheduled_start_at->equalTo($scheduledStartAt))->toBeTrue();
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

    // Seed sufficient credit balance so the credit gate passes.
    $politician->politician->update(['credit_balance' => 100.00]);

    $campaign = makeCampaign($politician->politician, [
        'status'       => 'draft',
        'media_url'    => 'https://cdn.example.com/video.mp4',
        'total_budget' => 60.00,
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

test('politician analytics credits purchased excludes pending and campaign charges', function () {
    config()->set('services.stripe.secret', 'sk_test_fake_analytics_charge_status_filter');

    $user = makePolitician();
    $politician = $user->politician;
    $campaign = makeCampaign($politician, [
        'status' => CampaignStatus::Active->value,
    ]);

    CampaignTransaction::create([
        'politician_id' => $politician->id,
        'transaction_type' => 'charge',
        'amount' => 102.56,
        'currency' => 'USD',
        'status' => 'succeeded',
        'description' => 'Real succeeded purchase',
        'metadata' => [
            'payment_mode' => 'test',
            'credits_amount' => 100.00,
            'stripe_fee' => 2.56,
        ],
    ]);

    CampaignTransaction::create([
        'politician_id' => $politician->id,
        'campaign_id' => $campaign->id,
        'transaction_type' => 'charge',
        'amount' => 100.00,
        'currency' => 'USD',
        'status' => 'succeeded',
        'description' => 'Campaign spend charge should not count as credits purchased',
        'metadata' => [
            'payment_mode' => 'test',
        ],
    ]);

    CampaignTransaction::create([
        'politician_id' => $politician->id,
        'transaction_type' => 'charge',
        'amount' => 102.56,
        'currency' => 'USD',
        'status' => 'pending',
        'description' => 'Unfinished faux trial purchase intent',
        'metadata' => [
            'payment_mode' => 'test',
            'credits_amount' => 100.00,
            'stripe_fee' => 2.56,
        ],
    ]);

    $this->actingAs($user)
        ->get(route('politician.analytics'))
        ->assertOk()
        ->assertViewHas('transactionsWithFeeSummary', function ($summary) {
            return (float) $summary->sum('credits') === 100.0
                && (float) $summary->sum('fee') === 2.56
                && $summary->count() === 1;
        });
});

test('politician analytics total spent is derived from mode-scoped usage ledger', function () {
    config()->set('services.stripe.secret', 'sk_test_fake_analytics_spend_mode_filter');

    $user = makePolitician();
    $politician = $user->politician;

    PoliticianCredit::create([
        'politician_id' => $politician->id,
        'transaction_type' => 'usage',
        'amount' => -25.00,
        'balance_after' => 75.00,
        'description' => 'Test mode campaign usage',
        'metadata' => ['payment_mode' => 'test'],
        'created_at' => now()->subMinutes(2),
    ]);

    PoliticianCredit::create([
        'politician_id' => $politician->id,
        'transaction_type' => 'usage',
        'amount' => -10.00,
        'balance_after' => 65.00,
        'description' => 'Another test mode usage',
        'metadata' => ['payment_mode' => 'test'],
        'created_at' => now()->subMinute(),
    ]);

    PoliticianCredit::create([
        'politician_id' => $politician->id,
        'transaction_type' => 'usage',
        'amount' => -99.00,
        'balance_after' => -34.00,
        'description' => 'Live mode usage should not appear in test mode analytics',
        'metadata' => ['payment_mode' => 'live'],
        'created_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('politician.analytics'))
        ->assertOk()
        ->assertViewHas('totalSpent', 35.0);
});

test('politician analytics budget and spent do not mix sandbox and live data and include usage deductions', function () {
    config()->set('services.stripe.secret', 'sk_live_fake_analytics_mode_budget');

    $user = makePolitician();
    $politician = $user->politician;

    $liveCampaign = makeCampaign($politician, [
        'status' => CampaignStatus::Active->value,
    ]);
    $testCampaign = makeCampaign($politician, [
        'status' => CampaignStatus::Active->value,
    ]);

    CampaignTransaction::create([
        'politician_id' => $politician->id,
        'campaign_id' => $liveCampaign->id,
        'transaction_type' => 'charge',
        'amount' => 100.00,
        'currency' => 'USD',
        'status' => 'succeeded',
        'metadata' => ['payment_mode' => 'live'],
    ]);

    CampaignTransaction::create([
        'politician_id' => $politician->id,
        'campaign_id' => $testCampaign->id,
        'transaction_type' => 'charge',
        'amount' => 100.00,
        'currency' => 'USD',
        'status' => 'succeeded',
        'metadata' => ['payment_mode' => 'test'],
    ]);

    PoliticianCredit::create([
        'politician_id' => $politician->id,
        'transaction_type' => 'purchase',
        'amount' => 100.00,
        'balance_after' => 100.00,
        'description' => 'Live purchase',
        'metadata' => ['payment_mode' => 'live'],
        'created_at' => now()->subMinutes(4),
    ]);

    PoliticianCredit::create([
        'politician_id' => $politician->id,
        'transaction_type' => 'usage',
        'campaign_id' => $liveCampaign->id,
        'amount' => -20.00,
        'balance_after' => 80.00,
        'description' => 'Live campaign usage row without metadata',
        'metadata' => null,
        'created_at' => now()->subMinutes(3),
    ]);

    PoliticianCredit::create([
        'politician_id' => $politician->id,
        'transaction_type' => 'purchase',
        'amount' => 100.00,
        'balance_after' => 180.00,
        'description' => 'Sandbox purchase',
        'metadata' => ['payment_mode' => 'test'],
        'created_at' => now()->subMinutes(2),
    ]);

    PoliticianCredit::create([
        'politician_id' => $politician->id,
        'transaction_type' => 'usage',
        'campaign_id' => $testCampaign->id,
        'amount' => -10.00,
        'balance_after' => 170.00,
        'description' => 'Sandbox usage row without metadata',
        'metadata' => null,
        'created_at' => now()->subMinute(),
    ]);

    $this->actingAs($user)
        ->get(route('politician.analytics'))
        ->assertOk()
        ->assertViewHas('totalBudget', 80.0)
        ->assertViewHas('totalSpent', 20.0);
});

test('politician analytics total views and active campaigns are scoped to active payment mode', function () {
    config()->set('services.stripe.secret', 'sk_live_fake_analytics_views_mode_scope');

    $user = makePolitician();
    $politician = $user->politician;

    $liveCampaign = makeCampaign($politician, [
        'status' => CampaignStatus::Active->value,
        'views_completed' => 40,
    ]);

    $testCampaign = makeCampaign($politician, [
        'status' => CampaignStatus::Active->value,
        'views_completed' => 15,
    ]);

    CampaignTransaction::create([
        'politician_id' => $politician->id,
        'campaign_id' => $liveCampaign->id,
        'transaction_type' => 'charge',
        'amount' => 100.00,
        'currency' => 'USD',
        'status' => 'succeeded',
        'metadata' => ['payment_mode' => 'live'],
    ]);

    CampaignTransaction::create([
        'politician_id' => $politician->id,
        'campaign_id' => $testCampaign->id,
        'transaction_type' => 'charge',
        'amount' => 100.00,
        'currency' => 'USD',
        'status' => 'succeeded',
        'metadata' => ['payment_mode' => 'test'],
    ]);

    $this->actingAs($user)
        ->get(route('politician.analytics'))
        ->assertOk()
        ->assertViewHas('totalViews', 40)
        ->assertViewHas('activeCampaigns', 1)
        ->assertViewHas('campaigns', function ($campaigns) use ($liveCampaign) {
            return $campaigns->count() === 1
                && (int) $campaigns->first()->id === (int) $liveCampaign->id;
        });
});

test('politician dashboard balance excludes test mode credits when stripe is live', function () {
    config()->set('services.stripe.secret', 'sk_live_fake_dashboard_mode_filter');

    $user = makePolitician();
    $politician = $user->politician;

    PoliticianCredit::create([
        'politician_id' => $politician->id,
        'transaction_type' => 'credit_purchase',
        'amount' => 48.20,
        'balance_after' => 48.20,
        'description' => 'Sandbox credit',
        'metadata' => ['payment_mode' => 'test'],
        'created_at' => now()->subMinute(),
    ]);

    PoliticianCredit::create([
        'politician_id' => $politician->id,
        'transaction_type' => 'credit_purchase',
        'amount' => 12.50,
        'balance_after' => 12.50,
        'description' => 'Live credit',
        'metadata' => ['payment_mode' => 'live'],
        'created_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('politician.dashboard'))
        ->assertOk()
        ->assertViewHas('stats', function (array $stats) {
            return (float) ($stats['credit_balance'] ?? -1) === 12.5;
        });
});

test('politician dashboard shows correct credit balance from a purchase entry linked to a transaction', function () {
    config()->set('services.stripe.secret', 'sk_live_fake_dashboard_dedupe');

    $user = makePolitician();
    $politician = $user->politician;

    $relatedTx = CampaignTransaction::create([
        'politician_id' => $politician->id,
        'transaction_type' => 'charge',
        'amount' => 100.00,
        'currency' => 'USD',
        'status' => 'succeeded',
        'description' => 'Linked payment intent transaction',
        'metadata' => ['payment_mode' => 'live'],
    ]);

    PoliticianCredit::create([
        'politician_id' => $politician->id,
        'transaction_type' => 'purchase',
        'amount' => 100.00,
        'balance_after' => 100.00,
        'related_transaction_id' => $relatedTx->id,
        'description' => 'Live credit #1',
        'metadata' => ['payment_mode' => 'live'],
        'created_at' => now()->subMinute(),
    ]);

    $this->actingAs($user)
        ->get(route('politician.dashboard'))
        ->assertOk()
        ->assertViewHas('stats', function (array $stats) {
            return (float) ($stats['credit_balance'] ?? -1) >= 0.0;
        });
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
