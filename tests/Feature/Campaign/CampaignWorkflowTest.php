<?php

use App\Models\Politician;
use App\Models\PoliticalCampaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    if (class_exists(\Spatie\Permission\Models\Role::class)) {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'politician', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'voter', 'guard_name' => 'web']);
    }
});

function makeWorkflowPolitician(): User
{
    $user = User::factory()->create(['platform' => 'standalone']);

    if (method_exists($user, 'assignRole')) {
        $user->assignRole('politician');
    }

    Politician::factory()->create(['user_id' => $user->id]);
    skipOnboarding($user, 'politician');

    return $user->load('politician');
}

function makeUploadWorkflowPolitician(): User
{
    $user = User::factory()->create(['platform' => 'standalone']);

    if (method_exists($user, 'assignRole')) {
        $user->assignRole('politician');
    }

    Politician::factory()->create(['user_id' => $user->id]);
    skipOnboarding($user, 'politician');

    return $user->load('politician');
}

test('create campaign workflow creates draft then submits for review', function () {
    $user = makeWorkflowPolitician();

    $requestedViews = 100;
    $expectedBudget = round(
        $requestedViews * (float) config('u9itus.revenue_per_view', 1.00),
        2
    );

    $storeResponse = $this->actingAs($user)
        ->post(route('politician.campaigns.store'), [
            'title' => 'Workflow Test Campaign',
            'campaign_type' => 'video',
            'total_views_requested' => $requestedViews,
            'total_budget' => 10000.00,
            'message_summary' => 'Create campaign workflow regression test',
            'media_url' => 'https://cdn.example.com/workflow-video.mp4',
            'media_duration' => config('u9itus.min_video_duration', 30),
        ]);

    $storeResponse->assertRedirect();
    $storeResponse->assertSessionHasNoErrors();

    $campaign = PoliticalCampaign::query()
        ->where('title', 'Workflow Test Campaign')
        ->first();

    expect($campaign)->not->toBeNull();
    expect($campaign->status->value ?? $campaign->status)->toBe('draft');
    expect((float) $campaign->total_budget)->toBe($expectedBudget);

    $user->politician->update(['credit_balance' => 500.00]);

    $submitResponse = $this->actingAs($user)
        ->post(route('politician.campaigns.submit-review', $campaign));

    $submitResponse->assertRedirect();

    expect($campaign->fresh()->status->value ?? $campaign->fresh()->status)
        ->toBe('pending_approval');
});

test('create campaign then upload video then submit for review', function () {
    Storage::fake('public');
    config()->set('filesystems.default', 'public');

    $user = makeUploadWorkflowPolitician();

    $storeResponse = $this->actingAs($user)
        ->post(route('politician.campaigns.store'), [
            'title' => 'Workflow Upload Campaign',
            'campaign_type' => 'video',
            'total_views_requested' => 100,
            'total_budget' => 10000.00,
            'message_summary' => 'Workflow regression for upload endpoint',
            'media_url' => 'https://cdn.example.com/placeholder-video.mp4',
            'media_duration' => config('u9itus.min_video_duration', 30),
        ]);

    $storeResponse->assertRedirect();
    $storeResponse->assertSessionHasNoErrors();

    $campaign = PoliticalCampaign::query()
        ->where('title', 'Workflow Upload Campaign')
        ->first();

    expect($campaign)->not->toBeNull();
    expect($campaign->status->value ?? $campaign->status)->toBe('draft');

    $uploadResponse = $this->actingAs($user)
        ->post(route('politician.campaigns.upload-video', $campaign), [
            'video' => UploadedFile::fake()->create('workflow-upload.mp4', 5, 'video/mp4'),
        ]);

    $uploadResponse->assertRedirect();
    $uploadResponse->assertSessionHasNoErrors();

    $freshCampaign = $campaign->fresh();
    expect((string) $freshCampaign->media_url)->toContain('/storage/campaigns/' . $campaign->id . '/video/');

    $path = ltrim((string) parse_url((string) $freshCampaign->media_url, PHP_URL_PATH), '/');
    $path = preg_replace('#^storage/#', '', $path) ?? $path;
    Storage::disk('public')->assertExists($path);

    $user->politician->update(['credit_balance' => 500.00]);

    $submitResponse = $this->actingAs($user)
        ->post(route('politician.campaigns.submit-review', $campaign));

    $submitResponse->assertRedirect();

    expect($campaign->fresh()->status->value ?? $campaign->fresh()->status)
        ->toBe('pending_approval');
});
