<?php

use App\Models\User;
use App\Models\Politician;
use App\Models\PoliticalCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('guest can browse politician directory in view only mode', function () {
    Politician::factory()->create([
        'full_name' => 'Avery Stone',
        'slug' => 'avery-stone',
        'user_id' => null,
        'page_published' => true,
        'is_active' => true,
    ]);

    $claimedUser = User::factory()->create([
        'user_type' => 'politician',
    ]);

    Politician::factory()->create([
        'user_id' => $claimedUser->id,
        'full_name' => 'Claimed Official',
        'slug' => 'claimed-official',
        'page_published' => true,
        'is_active' => true,
    ]);

    Politician::factory()->create([
        'full_name' => 'Hidden Candidate',
        'slug' => 'hidden-candidate',
        'page_published' => false,
        'is_active' => true,
    ]);

    $response = $this->get(route('politicians.directory'));

    $response->assertOk();
    $response->assertSee('Avery Stone');
    $response->assertSee('Claimed Official');
    $response->assertDontSee('Hidden Candidate');
    $response->assertSee('Unclaimed Profile');
    $response->assertSee('Public directory is view-only for earnings.', false);
    $response->assertSee('watch active public campaign videos', false);
    $response->assertSee('commissions are only available after creating a voter account', false);
    $response->assertSee('Create Free Account');
    $response->assertDontSee('Earn Money Watching');
});

test('guest public politician profile stays in preview mode without earning copy', function () {
    $politician = Politician::factory()->create([
        'full_name' => 'Jordan Vale',
        'slug' => 'abcde-jordan-vale',
        'page_published' => true,
        'is_active' => true,
    ]);

    PoliticalCampaign::factory()->active()->create([
        'politician_id' => $politician->id,
        'title' => 'Jordan Vale for Reform',
        'message_summary' => 'A public message for voters.',
        'media_url' => 'https://youtu.be/dQw4w9WgXcQ',
    ]);

    PoliticalCampaign::factory()->create([
        'politician_id' => $politician->id,
        'title' => 'Jordan Vale Town Hall Recap',
        'message_summary' => 'A previous campaign update for public review.',
        'media_url' => 'https://youtu.be/dQw4w9WgXcQ',
        'status' => 'completed',
        'approval_status' => 'approved',
        'completed_at' => now()->subDays(5),
    ]);

    $response = $this->get(route('politician.public.show', ['slug' => $politician->slug]));

    $response->assertOk();
    $response->assertSee('Guest preview mode');
    $response->assertSee('Public Preview');
    $response->assertSee('Unclaimed Profile');
    $response->assertSee('currently unclaimed and generated from public records', false);
    $response->assertSee('Campaign Videos & Updates');
    $response->assertSee('Running Campaigns');
    $response->assertSee('Past Campaigns');
    $response->assertSee('Guests can browse current and past public campaign videos here to learn how this candidate is communicating over time.', false);
    $response->assertSee('Create free account for full access');
    $response->assertSee('Jordan Vale Town Hall Recap');
    $response->assertDontSee('Earn $0.25');
    $response->assertDontSee('Start Earning');
    $response->assertDontSee('Sign up to earn commissions from views');
});

test('claimed politician profile does not show unclaimed badge', function () {
    $user = User::factory()->create([
        'user_type' => 'politician',
    ]);

    $politician = Politician::factory()->create([
        'user_id' => $user->id,
        'full_name' => 'Casey Jordan',
        'slug' => 'casey-jordan',
        'page_published' => true,
        'is_active' => true,
    ]);

    $response = $this->get(route('politician.public.show', ['slug' => $politician->slug]));

    $response->assertOk();
    $response->assertDontSee('Unclaimed Profile');
    $response->assertDontSee('currently unclaimed and generated from public records', false);
});

test('directory unclaimed-only filter returns only unclaimed profiles', function () {
    Politician::factory()->create([
        'user_id' => null,
        'full_name' => 'Unclaimed Candidate',
        'slug' => 'unclaimed-candidate',
        'page_published' => true,
        'is_active' => true,
    ]);

    $claimedUser = User::factory()->create([
        'user_type' => 'politician',
    ]);

    Politician::factory()->create([
        'user_id' => $claimedUser->id,
        'full_name' => 'Claimed Candidate',
        'slug' => 'claimed-candidate',
        'page_published' => true,
        'is_active' => true,
    ]);

    $response = $this->get(route('politicians.directory', ['unclaimed' => 1]));

    $response->assertOk();
    $response->assertSee('Unclaimed Candidate');
    $response->assertDontSee('Claimed Candidate');
});

test('authenticated politician can still preview their unpublished page', function () {
    $user = User::factory()->create([
        'user_type' => 'politician',
    ]);

    $politician = Politician::factory()->create([
        'user_id' => $user->id,
        'full_name' => 'Morgan Reed',
        'slug' => 'vwxyz-morgan-reed',
        'page_published' => false,
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)
        ->get(route('politician.public.show', ['slug' => $politician->slug]));

    $response->assertOk();
    $response->assertSee('Morgan Reed');
    $response->assertSee('Open Dashboard');
});
