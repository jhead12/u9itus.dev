<?php

use App\Models\CandidateNewsArticle;
use App\Models\PoliticianEndorsement;
use App\Models\Politician;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
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

test('two different endorsers of the same office are kept as separate rows', function () {
    $jane = seedEndorsementPolitician('Jane Smith');
    seedEndorsementArticle($jane, 'Sen. Elizabeth Warren endorses Jane Smith for state Senate');
    seedEndorsementArticle($jane, 'Sen. Bernie Sanders backs Jane Smith in state Senate race');

    Artisan::call('candidates:detect-endorsements', ['--limit' => 10]);

    expect(PoliticianEndorsement::where('politician_id', $jane->id)->pluck('endorser_name')->sort()->values()->all())
        ->toBe(['Bernie Sanders', 'Elizabeth Warren']);
});

test('the same endorser across articles is one row, and the fuller name wins', function () {
    $jane = seedEndorsementPolitician('Jane Smith');
    seedEndorsementArticle($jane, 'Gov. Newsom endorses Jane Smith for state Senate');
    seedEndorsementArticle($jane, 'Governor Gavin Newsom backs Jane Smith again');

    Artisan::call('candidates:detect-endorsements', ['--limit' => 10]);

    $rows = PoliticianEndorsement::where('politician_id', $jane->id)->get();
    expect($rows)->toHaveCount(1);
    expect($rows->first()->endorser_name)->toBe('Gavin Newsom');
    expect($rows->first()->match_count)->toBe(2);
});

test('a nameless mention joins the named endorser instead of adding a second chip', function () {
    $jane = seedEndorsementPolitician('Jane Smith');
    seedEndorsementArticle($jane, 'Governor Gavin Newsom endorses Jane Smith');
    seedEndorsementArticle($jane, 'Governor endorses Jane Smith in surprise move');

    Artisan::call('candidates:detect-endorsements', ['--limit' => 10]);

    $rows = PoliticianEndorsement::where('politician_id', $jane->id)->get();
    expect($rows)->toHaveCount(1);
    expect($rows->first()->endorser_name)->toBe('Gavin Newsom');
});

test('a surname alone resolves to the sitting official of that office in the state', function () {
    $jane = seedEndorsementPolitician('Jane Smith');
    Politician::create([
        'uuid' => Str::uuid(), 'full_name' => 'Gavin Newsom', 'state' => 'CA', 'political_office' => 'Governor',
        'governance_level' => 'State', 'term_status' => 'seated', 'is_active' => true, 'page_published' => true,
        'verified_official' => true, 'slug' => 'gavin-newsom-'.Str::random(6),
    ]);
    seedEndorsementArticle($jane, 'Gov. Newsom endorses Jane Smith for state Senate');

    Artisan::call('candidates:detect-endorsements', ['--limit' => 10]);

    expect(PoliticianEndorsement::where('politician_id', $jane->id)->value('endorser_name'))->toBe('Gavin Newsom');
});

test('the profile page lists each endorser by name with a link to the article', function () {
    $jane = seedEndorsementPolitician('Jane Smith');
    $article = seedEndorsementArticle($jane, 'Governor Gavin Newsom endorses Jane Smith');
    $article->update(['source_name' => 'CalMatters']);

    Artisan::call('candidates:detect-endorsements', ['--limit' => 10]);

    $this->get('/p/'.$jane->slug)
        ->assertOk()
        ->assertSee('Endorsements')
        ->assertSee('Gavin Newsom')
        ->assertSee('Read on CalMatters')
        ->assertDontSee('Governor Endorsed');
});

test('a stale row from an older detector is replaced, not left beside the correct endorser', function () {
    $hilton = seedEndorsementPolitician('Steve Hilton');
    $article = seedEndorsementArticle($hilton, 'President Trump endorses Steve Hilton for governor');
    PoliticianEndorsement::create([
        'politician_id' => $hilton->id, 'group_key' => 'governor', 'label' => 'Governor', 'endorser_key' => '',
        'endorser_name' => null, 'matched_phrase' => 'governor', 'confidence' => 0.85, 'source_article_id' => $article->id,
        'source_url' => $article->source_url, 'detected_article_ids' => [$article->id], 'match_count' => 1,
    ]);

    Artisan::call('candidates:detect-endorsements', ['--limit' => 10]);

    $rows = PoliticianEndorsement::where('politician_id', $hilton->id)->get();
    expect($rows)->toHaveCount(1);
    expect($rows->first()->group_key)->toBe('president');
    expect($rows->first()->endorser_name)->toBe('Donald Trump');
});

test('rebuilding keeps rows an admin already dismissed', function () {
    $jane = seedEndorsementPolitician('Jane Smith');
    $article = seedEndorsementArticle($jane, 'Governor Gavin Newsom endorses Jane Smith');
    PoliticianEndorsement::create([
        'politician_id' => $jane->id, 'group_key' => 'mayor', 'label' => 'Mayor', 'endorser_key' => '',
        'matched_phrase' => 'mayor', 'confidence' => 0.7, 'source_article_id' => $article->id, 'detected_article_ids' => [$article->id],
        'match_count' => 1, 'status' => 'dismissed',
    ]);

    Artisan::call('candidates:detect-endorsements', ['--limit' => 10]);

    expect(PoliticianEndorsement::where('politician_id', $jane->id)->where('status', 'dismissed')->count())->toBe(1);
    expect(PoliticianEndorsement::where('politician_id', $jane->id)->active()->value('group_key'))->toBe('governor');
});

test('a stale row whose article is no longer verified is removed by the re-scan', function () {
    $hilton = seedEndorsementPolitician('Steve Hilton');
    $article = seedEndorsementArticle($hilton, 'Governor endorses Steve Hilton', 'rejected');
    seedEndorsementArticle($hilton, 'Steve Hilton holds a rally in Fresno');
    PoliticianEndorsement::create([
        'politician_id' => $hilton->id, 'group_key' => 'governor', 'label' => 'Governor', 'endorser_key' => '',
        'matched_phrase' => 'governor', 'confidence' => 0.85, 'source_article_id' => $article->id,
        'detected_article_ids' => [$article->id], 'match_count' => 1,
    ]);

    Artisan::call('candidates:detect-endorsements', ['--limit' => 1]);

    expect(PoliticianEndorsement::where('politician_id', $hilton->id)->exists())->toBeFalse();
});

test('the re-scan clears the cached guest copy of the profile page', function () {
    $jane = seedEndorsementPolitician('Jane Smith');
    seedEndorsementArticle($jane, 'Governor Gavin Newsom endorses Jane Smith');
    Cache::put("profile.page.seo-v2.{$jane->id}", '<html>stale</html>', 600);

    Artisan::call('candidates:detect-endorsements', ['--limit' => 10]);

    expect(Cache::has("profile.page.seo-v2.{$jane->id}"))->toBeFalse();
});

test('a headline that names Trump without a title lists Donald Trump as the endorser', function () {
    $hilton = seedEndorsementPolitician('Steve Hilton');
    seedEndorsementArticle($hilton, 'Trump endorses Steve Hilton for California governor');

    Artisan::call('candidates:detect-endorsements', ['--limit' => 10]);

    $row = PoliticianEndorsement::where('politician_id', $hilton->id)->sole();
    expect($row->group_key)->toBe('president');
    expect($row->endorser_name)->toBe('Donald Trump');
});

test('Trump relatives, Trump administration and endorsing Trump are not endorsements by Trump', function (string $headline) {
    $hilton = seedEndorsementPolitician('Steve Hilton');
    seedEndorsementArticle($hilton, $headline);

    Artisan::call('candidates:detect-endorsements', ['--limit' => 10]);

    expect(PoliticianEndorsement::where('politician_id', $hilton->id)->exists())->toBeFalse();
})->with([
    'Eric Trump endorses Hilton',
    'Donald Trump Jr. backs Hilton',
    'Trump administration backs Hilton on tariffs',
    'Steve Hilton endorses Trump for president',
]);
