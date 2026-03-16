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
    $response->assertDontSee('Hidden Candidate');
    $response->assertSee('Public directory is view-only', false);
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

    $response = $this->get(route('politician.public.show', ['slug' => $politician->slug]));

    $response->assertOk();
    $response->assertSee('Guest preview mode');
    $response->assertSee('Public Preview');
    $response->assertSee('Create account to watch on U9itus');
    $response->assertDontSee('Earn $0.25');
    $response->assertDontSee('Start Earning');
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
