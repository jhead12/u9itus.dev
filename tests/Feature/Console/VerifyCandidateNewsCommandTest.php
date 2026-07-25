<?php

use App\Models\CandidateNewsArticle;
use App\Models\Politician;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function seedVerifyNewsPolitician(string $state, string $name): Politician
{
    return Politician::create([
        'uuid' => Str::uuid(),
        'full_name' => $name,
        'state' => $state,
        'political_office' => 'Governor',
        'governance_level' => 'State',
        'is_running_candidate' => false,
        'term_status' => 'seated',
        'is_active' => true,
        'page_published' => true,
        'verified_official' => false,
        'user_id' => null,
        'slug' => Str::slug($name) . '-' . Str::random(6),
    ]);
}

function seedNewsArticle(Politician $politician, string $headline): CandidateNewsArticle
{
    return CandidateNewsArticle::create([
        'politician_id' => $politician->id,
        'candidate_name' => $politician->full_name,
        'headline' => $headline,
        'source_url' => 'https://example.test/' . Str::random(10),
        'source_hash' => hash('sha256', Str::random(20)),
        'provider' => 'google_rss',
        'published_at' => now(),
        'scraped_at' => now(),
    ]);
}

// verification_status defaults to 'verified' at the DB level, so it can't
// signal whether a row was actually touched by this run — verification_reason
// is null until the service processes a row, so it's used as the "touched" marker.
test('--state limits verification to articles for that state\'s candidates', function () {
    $ca = seedVerifyNewsPolitician('CA', 'Jane Doe');
    $tx = seedVerifyNewsPolitician('TX', 'John Smith');

    $caArticle = seedNewsArticle($ca, 'Jane Doe wins CA primary');
    $txArticle = seedNewsArticle($tx, 'John Smith campaign update');

    Artisan::call('candidates:verify-news', ['--state' => 'CA', '--limit' => 10]);

    expect($caArticle->fresh()->verification_reason)->not->toBeNull();
    expect($txArticle->fresh()->verification_reason)->toBeNull();
});

test('omitting --state verifies articles from every state', function () {
    $ca = seedVerifyNewsPolitician('CA', 'Jane Doe');
    $tx = seedVerifyNewsPolitician('TX', 'John Smith');

    $caArticle = seedNewsArticle($ca, 'Jane Doe wins CA primary');
    $txArticle = seedNewsArticle($tx, 'John Smith campaign update');

    Artisan::call('candidates:verify-news', ['--limit' => 10]);

    expect($caArticle->fresh()->verification_reason)->not->toBeNull();
    expect($txArticle->fresh()->verification_reason)->not->toBeNull();
});
