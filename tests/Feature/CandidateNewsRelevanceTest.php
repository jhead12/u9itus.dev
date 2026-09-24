<?php

use App\Models\CandidateNewsArticle;
use App\Models\Politician;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// The relevance gate verified any exact full-name match and counted the
// outlet's own name (the " - Source" suffix on aggregator headlines) as
// context. FL-11's Daniel Webster ended up with Britannica's entry on the
// 1850s statesman and Politico's "this day in history" pieces as coverage.

function webster(): Politician
{
    return Politician::factory()->create([
        'full_name' => 'Daniel Webster',
        'state' => 'FL',
        'district' => 'FL-11',
        'political_office' => 'United States Representative',
    ]);
}

function websterArticle(Politician $politician, string $headline, array $overrides = []): CandidateNewsArticle
{
    return CandidateNewsArticle::factory()->create(array_merge([
        'politician_id' => $politician->id,
        'candidate_name' => $politician->full_name,
        'headline' => $headline,
        'snippet' => '',
        'source_name' => 'Google News',
        'verification_status' => 'verified',
        'verification_reason' => 'full-name match',
    ], $overrides));
}

it('rejects reference entries, dated history pieces and outlet index pages', function () {
    $politician = webster();
    $britannica = websterArticle($politician, 'Daniel Webster - Whig Leader, Statesman, Orator - Encyclopedia Britannica', ['source_name' => 'Encyclopedia Britannica']);
    $anniversary = websterArticle($politician, 'Daniel Webster resigns from the Senate, July 22, 1850 - politico.com', ['source_name' => 'politico.com']);
    $lifespan = websterArticle($politician, 'Remembering Daniel Webster (1782–1852), the great orator');
    $indexPage = websterArticle($politician, 'Daniel Webster - Breaking News, Photos and Videos | Page 5 - The Hill');

    $this->artisan('candidates:verify-news', ['--politician' => $politician->id])->assertSuccessful();

    foreach ([$britannica, $anniversary, $lifespan, $indexPage] as $article) {
        expect($article->fresh()->verification_status)->toBe('rejected');
    }
    expect($britannica->fresh()->verification_reason)->toBe('reference/encyclopedia source')
        ->and($anniversary->fresh()->verification_reason)->toBe('historical namesake (pre-1900 date)')
        ->and($indexPage->fresh()->verification_reason)->toBe('outlet index page, not an article');
});

it('keeps current coverage, including stories that cite an old year', function () {
    $politician = webster();
    $current = websterArticle($politician, 'U.S. Rep. Daniel Webster will challenge for District 11 congressional seat - Tampa Bay Times');
    $oldLaw = websterArticle($politician, 'Daniel Webster weighs in on the 1864 abortion ban ruling');
    $surname = websterArticle($politician, 'Rep. Webster fundraising off run for speaker - politico.com', ['verification_status' => 'rejected']);

    $this->artisan('candidates:verify-news', ['--politician' => $politician->id])->assertSuccessful();

    expect($current->fresh()->verification_status)->toBe('verified')
        ->and($oldLaw->fresh()->verification_status)->toBe('verified')
        ->and($surname->fresh()->verification_status)->toBe('verified');
});

it('does not count the outlet name or a bare state name as context for a surname', function () {
    $politician = webster();
    $sourceOnly = websterArticle($politician, 'Webster dictionary adds new words - Florida Politics', ['source_name' => 'Florida Politics']);
    $stateOnly = websterArticle($politician, 'Webster Elementary wins Florida science fair');

    $this->artisan('candidates:verify-news', ['--politician' => $politician->id])->assertSuccessful();

    expect($sourceOnly->fresh()->verification_status)->toBe('rejected')
        ->and($stateOnly->fresh()->verification_status)->toBe('rejected');
});

it('re-checks only never-verified rows with --unchecked, and writes nothing on --dry-run', function () {
    $politician = webster();
    $legacy = websterArticle($politician, 'Anchor Alan Wendt leaves Channel 13 - Tampa Bay Times', ['verification_reason' => null]);
    $checked = websterArticle($politician, 'Daniel Webster - Encyclopedia Britannica');

    $this->artisan('candidates:verify-news', ['--unchecked' => true, '--dry-run' => true])
        ->expectsOutputToContain('newly_rejected=1')
        ->assertSuccessful();
    expect($legacy->fresh()->verification_status)->toBe('verified');

    $this->artisan('candidates:verify-news', ['--unchecked' => true])->assertSuccessful();

    expect($legacy->fresh()->verification_status)->toBe('rejected')
        ->and($checked->fresh()->verification_status)->toBe('verified');
});

it('leaves official-site articles verified', function () {
    $politician = webster();
    $release = websterArticle($politician, 'Contact Form', ['provider' => 'official_site', 'verification_reason' => 'official site (site-scoped, name match not required)']);

    $this->artisan('candidates:verify-news', ['--politician' => $politician->id])->assertSuccessful();

    expect($release->fresh()->verification_status)->toBe('verified');
});
