<?php

use App\Models\CandidateNewsArticle;
use App\Models\Politician;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Rejected articles failed the name/context relevance gate and are kept only
// for audit. The profile page used to show them while the /news archive hid
// them, so the two pages disagreed about the same politician.

function politicianWithMixedNews(): Politician
{
    $politician = Politician::factory()->create([
        'full_name' => 'Nicholas W. Brown',
        'state' => 'WA',
        'political_office' => 'Attorney General',
        'page_published' => true,
        'is_active' => true,
    ]);

    CandidateNewsArticle::factory()->create([
        'politician_id' => $politician->id,
        'candidate_name' => $politician->full_name,
        'headline' => 'Nicholas W. Brown files suit over a federal rule',
        'verification_status' => 'verified',
    ]);
    CandidateNewsArticle::factory()->create([
        'politician_id' => $politician->id,
        'candidate_name' => $politician->full_name,
        'headline' => 'Unrelated story that only matched the search query',
        'verification_status' => 'rejected',
    ]);

    return $politician;
}

it('shows only verified articles on the public profile', function () {
    $politician = politicianWithMixedNews();

    $this->get(route('politician.public.show', $politician->slug))
        ->assertOk()
        ->assertSee('Nicholas W. Brown files suit over a federal rule')
        ->assertDontSee('Unrelated story that only matched the search query');
});

it('does not fall back to rejected articles in the map overview', function () {
    $politician = politicianWithMixedNews();
    CandidateNewsArticle::query()->where('verification_status', 'verified')->delete();

    $this->getJson('/api/v1/map/candidate-overview?slug='.$politician->slug)
        ->assertOk()
        ->assertJsonMissing(['headline' => 'Unrelated story that only matched the search query']);
});
