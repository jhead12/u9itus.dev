<?php

use App\Models\CandidateNewsArticle;
use App\Models\Politician;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Covers GET /api/v1/mcp/candidate-news — the cross-candidate "what's the
 * latest news" feed that links each story to its source and its candidate.
 */
function mcpNewsPolitician(array $attrs = []): Politician
{
    return Politician::factory()->create(array_merge([
        'page_published' => true,
        'is_active' => true,
        'slug' => 'slug-'.fake()->unique()->numerify('######'),
        'state' => 'CT',
    ], $attrs));
}

it('returns verified candidate news newest-first with story and profile links', function () {
    $lamont = mcpNewsPolitician(['full_name' => 'Ned Lamont', 'state' => 'CT']);
    $hinson = mcpNewsPolitician(['full_name' => 'Ashley Hinson', 'state' => 'IA']);

    CandidateNewsArticle::factory()->create([
        'politician_id' => $hinson->id,
        'headline' => 'Hinson leads in new poll',
        'source_name' => 'The Hill',
        'source_url' => 'https://thehill.com/hinson-poll',
        'published_at' => now()->subHours(6),
    ]);
    CandidateNewsArticle::factory()->create([
        'politician_id' => $lamont->id,
        'headline' => 'Lamont defends license policy',
        'source_name' => 'New Haven Register',
        'source_url' => 'https://nhregister.com/lamont',
        'published_at' => now()->subMinutes(30),
    ]);

    $res = $this->getJson('/api/v1/mcp/candidate-news')->assertOk();

    $res->assertJsonPath('count', 2)
        ->assertJsonPath('results.0.headline', 'Lamont defends license policy')
        ->assertJsonPath('results.0.source_url', 'https://nhregister.com/lamont')
        ->assertJsonPath('results.0.candidate.full_name', 'Ned Lamont')
        ->assertJsonPath('results.1.candidate.full_name', 'Ashley Hinson');

    expect($res->json('results.0.candidate.profile_url'))->toContain('/p/'.$lamont->slug);
});

it('excludes news attached to unpublished or placeholder profiles', function () {
    $draft = mcpNewsPolitician(['full_name' => 'Breaking News', 'page_published' => false]);
    $live = mcpNewsPolitician(['full_name' => 'Erin Stewart']);

    CandidateNewsArticle::factory()->create([
        'politician_id' => $draft->id,
        'headline' => 'Placeholder blast',
        'published_at' => now(),
    ]);
    CandidateNewsArticle::factory()->create([
        'politician_id' => $live->id,
        'headline' => 'Stewart town hall',
        'published_at' => now()->subDay(),
    ]);

    $this->getJson('/api/v1/mcp/candidate-news')
        ->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('results.0.candidate.full_name', 'Erin Stewart');
});

it('filters candidate news by state and drops unverified stories', function () {
    $ct = mcpNewsPolitician(['full_name' => 'CT Person', 'state' => 'CT']);
    $ia = mcpNewsPolitician(['full_name' => 'IA Person', 'state' => 'IA']);

    CandidateNewsArticle::factory()->create(['politician_id' => $ct->id, 'published_at' => now()]);
    CandidateNewsArticle::factory()->create(['politician_id' => $ia->id, 'published_at' => now()]);
    CandidateNewsArticle::factory()->create([
        'politician_id' => $ct->id,
        'verification_status' => 'pending',
        'published_at' => now(),
    ]);

    $this->getJson('/api/v1/mcp/candidate-news?state=ct')
        ->assertOk()
        ->assertJsonPath('count', 1)
        ->assertJsonPath('results.0.candidate.state', 'CT');
});
