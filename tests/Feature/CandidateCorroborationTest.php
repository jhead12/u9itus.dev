<?php

use App\Models\CandidateIdentityLink;
use App\Models\CandidateRoster;
use App\Models\ElectionCandidateRecord;
use App\Models\Politician;
use App\Models\PoliticianCleanupReview;
use App\Services\CandidateDiscovery\CandidateCorroboration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    config(['services.fec.api_key' => 'test-key']);
});

function rosterFiler(string $name, array $over = []): CandidateRoster
{
    return CandidateRoster::create(array_merge([
        'source' => 'fec', 'source_id' => 'H6CA'.fake()->unique()->numerify('#####'), 'full_name' => $name,
        'identity_key' => App\Support\MapCandidateHygiene::identityKey($name),
        'state' => 'CA', 'office' => 'H', 'district' => 'CA-43', 'election_year' => 2026,
    ], $over));
}

function newsLead(string $name, array $over = []): ElectionCandidateRecord
{
    return ElectionCandidateRecord::create(array_merge([
        'source' => 'candidate_discovery', 'external_candidate_id' => 'disc:'.fake()->unique()->numerify('######'),
        'full_name' => $name, 'political_office' => 'U.S. Representative', 'governance_level' => 'federal',
        'state' => 'CA', 'district' => 'CA-43', 'election_date' => now()->addMonths(2)->toDateString(),
    ], $over));
}

function official(string $name, array $over = []): Politician
{
    return Politician::factory()->create(array_merge([
        'full_name' => $name, 'state' => 'CA', 'user_id' => null, 'governance_level' => 'federal',
        'political_office' => 'U.S. Representative', 'term_status' => 'seated', 'is_active' => true,
        'slug' => str($name)->slug()->append('-'.fake()->unique()->numerify('####'))->toString(),
    ], $over));
}

it('corroborates a name the FEC roster lists for that chamber, whatever the spelling', function () {
    rosterFiler('Steven Bradford');

    $check = (new CandidateCorroboration)->check(newsLead('Steve Bradford'));

    expect($check['corroborated'])->toBeTrue()->and($check['source'])->toBe('FEC');
});

it('does not corroborate a headline, another state, or another chamber', function () {
    rosterFiler('Steven Bradford');
    $service = new CandidateCorroboration;

    expect($service->check(newsLead('Bradford Agenda'))['corroborated'])->toBeFalse()
        ->and($service->check(newsLead('Steven Bradford', ['state' => 'TX']))['corroborated'])->toBeFalse()
        ->and($service->check(newsLead('Steven Bradford', ['political_office' => 'U.S. Senator', 'district' => 'Statewide']))['corroborated'])->toBeFalse();
});

it('keeps a real person whose district disagrees with the FEC, and reports the FEC district', function () {
    rosterFiler('Steven Bradford'); // FEC: CA-43
    $service = new CandidateCorroboration;

    $stale = $service->check(newsLead('Steven Bradford', ['district' => 'CA-12']));
    $same = $service->check(newsLead('Steven Bradford', ['district' => 'CA-43']));
    $padded = $service->check(newsLead('Steven Bradford', ['district' => 'CA-043']));

    expect($stale['corroborated'])->toBeTrue()->and($stale['district'])->toBe('CA-43')
        ->and($same['district'])->toBeNull()
        ->and($padded['district'])->toBeNull();
});

it('creates the profile with the FEC district, not the news one', function () {
    rosterFiler('Steven Bradford');
    newsLead('Steven Bradford', ['district' => 'CA-12']);

    $this->artisan('politicians:reconcile-missing-profiles')->expectsOutputToContain('[DISTRICT]')->assertExitCode(0);

    expect(Politician::where('full_name', 'Steven Bradford')->value('district'))->toBe('CA-43');
});

it('says what an uncorroborated name is close to', function () {
    rosterFiler('Steven Bradford');

    $check = (new CandidateCorroboration)->check(newsLead('Stevan Bradford'));

    expect($check['corroborated'])->toBeFalse()->and($check['reason'])->toContain('close to "Steven Bradford"');
});

it('accepts a non-news record or a sitting official of that state, but not one from elsewhere', function () {
    ElectionCandidateRecord::create([
        'source' => 'ballotpedia', 'external_candidate_id' => 'bp-1', 'full_name' => 'Maria Lopez',
        'political_office' => 'Governor', 'governance_level' => 'state', 'state' => 'CA',
    ]);
    official('Nancy Pelosi');
    official('Greg Abbott', ['state' => 'TX', 'political_office' => 'Governor', 'governance_level' => 'state']);
    $service = new CandidateCorroboration;

    expect($service->check(newsLead('Maria Lopez', ['political_office' => 'Governor', 'district' => null]))['source'])->toBe('ballotpedia')
        // a sitting House member running for another office is a real person
        ->and($service->check(newsLead('Nancy Pelosi', ['political_office' => 'Governor', 'district' => null]))['source'])->toBe('official')
        // Abbott is real — in Texas
        ->and($service->check(newsLead('Greg Abbott', ['state' => 'NC', 'political_office' => 'Governor', 'district' => null]))['corroborated'])->toBeFalse();
});

it('quarantines uncorroborated news names instead of creating public profiles', function () {
    rosterFiler('Steven Bradford');
    newsLead('Steven Bradford');
    newsLead('Bradford Agenda');
    newsLead('Totally Madeup');

    $this->artisan('politicians:reconcile-missing-profiles')
        ->expectsOutputToContain('[QUARANTINE] Totally Madeup')
        ->assertExitCode(0);

    expect(Politician::pluck('full_name')->all())->toBe(['Steven Bradford']);
});

it('creates them anyway when told to', function () {
    newsLead('Totally Madeup');

    $this->artisan('politicians:reconcile-missing-profiles', ['--allow-uncorroborated' => true])->assertExitCode(0);

    expect(Politician::where('full_name', 'Totally Madeup')->exists())->toBeTrue();
});

it('links a respelled name to the person we already have rather than creating a duplicate', function () {
    rosterFiler('Steven Bradford');
    $existing = official('Steven Bradford', ['term_status' => 'running']);
    $lead = newsLead('Steve Bradford');

    $this->artisan('politicians:reconcile-missing-profiles')->assertExitCode(0);

    expect(Politician::where('full_name', 'like', '%Bradford%')->count())->toBe(1)
        ->and(CandidateIdentityLink::where('election_candidate_record_id', $lead->id)->value('politician_id'))->toBe($existing->id);
});

it('queues an existing news-only profile that nothing corroborates, and leaves corroborated ones alone', function () {
    rosterFiler('Steven Bradford');
    $link = fn (Politician $p, ElectionCandidateRecord $e) => CandidateIdentityLink::create([
        'politician_id' => $p->id, 'election_candidate_record_id' => $e->id, 'match_score' => 1, 'link_source' => 'system', 'linked_at' => now(),
    ]);
    $made = official('Totally Madeup', ['term_status' => 'running']);
    $real = official('Steven Bradford', ['term_status' => 'running']);
    $newsOnly = official('Vaguely Plausible', ['term_status' => 'running']);
    $link($made, newsLead('Totally Madeup'));
    $link($real, newsLead('Steven Bradford'));
    // Vaguely Plausible has a Ballotpedia record too, so it is not news-only.
    $link($newsOnly, newsLead('Vaguely Plausible'));
    $link($newsOnly, ElectionCandidateRecord::create([
        'source' => 'ballotpedia', 'external_candidate_id' => 'bp-vp', 'full_name' => 'Vaguely Plausible',
        'political_office' => 'U.S. Representative', 'governance_level' => 'federal', 'state' => 'CA',
    ]));

    $this->artisan('politicians:flag-suspect-profiles', ['--apply' => true])->assertExitCode(0);

    $review = PoliticianCleanupReview::where('politician_id', $made->id)->sole();
    expect($review->status)->toBe('pending')
        ->and($review->reason)->toContain('news headline')
        ->and($made->refresh()->is_active)->toBeTrue()
        ->and(PoliticianCleanupReview::where('politician_id', $real->id)->exists())->toBeFalse()
        ->and(PoliticianCleanupReview::where('politician_id', $newsOnly->id)->exists())->toBeFalse();
});

it('puts every FEC filer on the roster, but only adds funded ones as candidate records', function () {
    App\Models\StateElectionDate::create(['state' => 'CA', 'election_year' => 2026, 'stage_name' => 'Primary', 'election_date' => now()->addMonth()->toDateString()]);
    $filer = fn (string $id, string $name, string $status) => [
        'candidate_id' => $id, 'name' => $name, 'party' => 'DEM', 'state' => 'CA', 'office' => 'H', 'district' => '43',
        'candidate_status' => $status, 'incumbent_challenge_full' => 'Challenger', 'candidate_inactive' => false,
    ];
    Http::swap(new HttpFactory);
    Http::fake(['api.open.fec.gov/v1/candidates/*' => Http::response(['results' => [
        $filer('H6CA43001', 'RAHMAN, MYLA', 'C'), $filer('H6CA43002', 'CRISTO, EDEN', 'N'),
    ], 'pagination' => ['pages' => 1]])]);

    $this->artisan('politicians:import-fec-candidates', ['--state' => 'CA', '--office' => 'H'])->assertExitCode(0);

    expect(CandidateRoster::pluck('source_id')->sort()->values()->all())->toBe(['H6CA43001', 'H6CA43002'])
        ->and(CandidateRoster::where('source_id', 'H6CA43002')->value('district'))->toBe('CA-43')
        ->and(ElectionCandidateRecord::pluck('external_candidate_id')->all())->toBe(['H6CA43001']);
});
