<?php

use App\Models\CandidateNewsArticle;
use App\Models\PoliticianEndorsement;
use App\Models\Politician;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function seedEndorsementPolitician(string $name): Politician
{
    return Politician::create([
        'uuid' => Str::uuid(),
        'full_name' => $name,
        'state' => 'CA',
        'political_office' => 'State Senate',
        'governance_level' => 'State',
        'is_running_candidate' => true,
        'term_status' => 'candidate',
        'is_active' => true,
        'page_published' => true,
        'verified_official' => false,
        'user_id' => null,
        'slug' => Str::slug($name) . '-' . Str::random(6),
    ]);
}

function seedEndorsementArticle(Politician $politician, string $headline, string $verificationStatus = 'verified'): CandidateNewsArticle
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
        'verification_status' => $verificationStatus,
    ]);
}

test('detects an endorsement in a verified stored article and creates a badge row', function () {
    $politician = seedEndorsementPolitician('Jane Smith');
    seedEndorsementArticle($politician, 'Governor Newsom endorses Jane Smith for state Senate');

    Artisan::call('candidates:detect-endorsements', ['--limit' => 10]);

    $endorsement = PoliticianEndorsement::where('politician_id', $politician->id)->first();

    expect($endorsement)->not->toBeNull();
    expect($endorsement->group_key)->toBe('governor');
    expect($endorsement->label)->toBe('Governor');
    expect($endorsement->status)->toBe('detected');
});

test('does not create a row for an article with no endorsement language', function () {
    $politician = seedEndorsementPolitician('Jane Smith');
    seedEndorsementArticle($politician, 'Jane Smith holds a town hall in Sacramento');

    Artisan::call('candidates:detect-endorsements', ['--limit' => 10]);

    expect(PoliticianEndorsement::where('politician_id', $politician->id)->exists())->toBeFalse();
});

test('does not create a row for an unverified article', function () {
    $politician = seedEndorsementPolitician('Jane Smith');
    seedEndorsementArticle($politician, 'Governor Newsom endorses Jane Smith for state Senate', 'rejected');

    Artisan::call('candidates:detect-endorsements', ['--limit' => 10]);

    expect(PoliticianEndorsement::where('politician_id', $politician->id)->exists())->toBeFalse();
});

test('--politician-id scopes detection to a single politician', function () {
    $jane = seedEndorsementPolitician('Jane Smith');
    $john = seedEndorsementPolitician('John Doe');
    seedEndorsementArticle($jane, 'Governor Newsom endorses Jane Smith for state Senate');
    seedEndorsementArticle($john, 'Mayor backs John Doe for reelection');

    Artisan::call('candidates:detect-endorsements', ['--politician-id' => $jane->id, '--limit' => 10]);

    expect(PoliticianEndorsement::where('politician_id', $jane->id)->exists())->toBeTrue();
    expect(PoliticianEndorsement::where('politician_id', $john->id)->exists())->toBeFalse();
});
