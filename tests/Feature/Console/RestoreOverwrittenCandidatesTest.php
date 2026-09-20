<?php

use App\Models\Politician;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

function restoreCandidatesDamagedRow(): Politician
{
    $row = Politician::factory()->create([
        'slug' => '758c8-governor-austin-gina-hinojosa',
        'full_name' => 'Greg Abbott',
        'political_office' => 'Governor',
        'state' => 'TX',
        'party_affiliation' => 'Republican',
        'ballotpedia_id' => 'Governor_of_Texas',
        'term_status' => 'seated',
        'is_running_candidate' => false,
        'verified_official' => false,
        'profile_photo_url' => 'https://example.test/flag.svg',
    ]);
    // The command targets fixed production ids; move the row onto the first one.
    Politician::whereKey($row->id)->update(['id' => 18837]);

    return Politician::findOrFail(18837);
}

test('dry run reports the repair without writing', function () {
    restoreCandidatesDamagedRow();

    artisan('politicians:restore-overwritten-candidates')->assertSuccessful();

    expect(Politician::find(18837)->full_name)->toBe('Greg Abbott');
});

test('apply restores the candidate name, party and flags but keeps the slug', function () {
    restoreCandidatesDamagedRow();

    artisan('politicians:restore-overwritten-candidates', ['--apply' => true])->assertSuccessful();

    $row = Politician::find(18837);
    expect($row->full_name)->toBe('Gina Hinojosa')
        ->and($row->party_affiliation)->toBe('Democratic')
        ->and($row->ballotpedia_id)->toBeNull()
        ->and($row->term_status)->toBe('running')
        ->and($row->is_running_candidate)->toBeTrue()
        ->and($row->profile_photo_url)->toBeNull()
        ->and($row->slug)->toBe('758c8-governor-austin-gina-hinojosa');
});

test('a row whose slug no longer matches is left alone', function () {
    $row = restoreCandidatesDamagedRow();
    $row->update(['slug' => 'something-else']);

    artisan('politicians:restore-overwritten-candidates', ['--apply' => true])->assertSuccessful();

    expect(Politician::find(18837)->full_name)->toBe('Greg Abbott');
});
