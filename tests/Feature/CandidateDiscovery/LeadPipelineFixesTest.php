<?php

use App\Models\CandidateLead;
use App\Models\CandidateRoster;
use App\Models\ElectionCandidateRecord;
use App\Models\Politician;
use App\Services\CandidateDiscovery\CandidateCorroboration;
use App\Services\CandidateDiscovery\CandidateLeadPromoter;
use App\Services\CandidateDiscovery\CandidateVerificationRegistry;
use App\Services\CandidateDiscovery\LlmCandidateLeadVerifier;
use App\Support\CandidateNameCanonicalizer;
use App\Support\ElectionCycle;
use App\Support\MapCandidateHygiene;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(fn () => Carbon::setTestNow('2026-09-20 12:00:00'));
afterEach(fn () => Carbon::setTestNow());

function txSenateLead(string $name, array $over = []): CandidateLead
{
    return CandidateLead::create(array_merge([
        'source_key' => 'rss_google_news', 'full_name' => $name, 'state' => 'TX', 'office_hint' => 'U.S. Senator',
        'source_url' => 'https://news.example/'.fake()->unique()->slug(), 'discovery_context' => 'Talarico and Paxton head to November',
        'discovered_at' => now(), 'status' => CandidateLead::STATUS_PENDING,
    ], $over));
}

function txSenateFiler(string $name): CandidateRoster
{
    return CandidateRoster::create([
        'source' => 'fec', 'source_id' => 'S6TX'.fake()->unique()->numerify('#####'), 'full_name' => $name,
        'identity_key' => MapCandidateHygiene::identityKey($name), 'state' => 'TX', 'office' => 'S', 'district' => null, 'election_year' => 2026,
    ]);
}

/** Registry stand-in: every lead "verifies" at the given confidence. */
function fakeVerifierReturning(float $confidence, array $payload = []): void
{
    app()->instance(CandidateVerificationRegistry::class, new class($confidence, $payload) extends CandidateVerificationRegistry
    {
        public function __construct(private float $confidence, private array $payload) {}

        public function verifyTiered(CandidateLead $lead, bool $allowAi = true): ?array
        {
            return [
                'status' => 'running', 'confidence' => $this->confidence, 'reason' => 'wikipedia summary confirms running',
                'verified_payload' => array_merge(['political_office' => 'U.S. Senator', 'governance_level' => 'Federal', 'state' => $lead->state], $this->payload),
                'verifier_key' => 'wikipedia',
            ];
        }
    });
}

it('cleans headline decoration off a candidate name', function (string $raw, string $expected) {
    expect(CandidateNameCanonicalizer::canonicalize($raw))->toBe($expected);
})->with([
    ['Texas Rep. James Talarico', 'James Talarico'],
    ['Rep. James Talarico', 'James Talarico'],
    ['Read James Talarico\'s', 'James Talarico'],
    ['James Talarico First', 'James Talarico'],
    ['Ken Paxton College', 'Ken Paxton'],
    ['Ken Paxton’s', 'Ken Paxton'],
    ['New York Sen. Kirsten Gillibrand', 'Kirsten Gillibrand'],
    ['Ken Paxton', 'Ken Paxton'],
    ['Katie Porter', 'Katie Porter'],
    ['Texas Senate', 'Texas Senate'], // never trimmed below two words
]);

it('knows which election cycle it is', function () {
    expect(ElectionCycle::year(Carbon::parse('2026-09-20')))->toBe(2026)
        ->and(ElectionCycle::year(Carbon::parse('2026-12-01')))->toBe(2028)
        ->and(ElectionCycle::year(Carbon::parse('2027-03-01')))->toBe(2028)
        ->and(ElectionCycle::generalElectionDate(2026))->toBe('2026-11-03')
        ->and(ElectionCycle::isCurrentOrFuture('2024-11-05'))->toBeFalse()
        ->and(ElectionCycle::isCurrentOrFuture('2026-11-03'))->toBeTrue();
});

it('matches a nickname to the FEC name that carries it as a middle name', function () {
    txSenateFiler('Warren Kenneth Paxton Jr.');

    $check = (new CandidateCorroboration)->checkIdentity('Ken Paxton', 'TX', 'U.S. Senator');

    expect($check['corroborated'])->toBeTrue()->and($check['source'])->toBe('FEC');
    // …but not for a different chamber, and not for a different surname.
    expect((new CandidateCorroboration)->checkIdentity('Ken Paxton', 'TX', 'U.S. Representative')['corroborated'])->toBeFalse();
    expect((new CandidateCorroboration)->checkIdentity('Ken Smith', 'TX', 'U.S. Senator')['corroborated'])->toBeFalse();
});

it('tells the AI verifier today\'s date and cycle, and drops a past-cycle date it guesses anyway', function () {
    config(['services.anthropic.api_key' => 'test-key']);
    Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['text' => json_encode([
        'is_real_candidate' => true, 'office' => 'U.S. Senator', 'party_affiliation' => 'Democratic',
        'election_date_guess' => '2024-11-05', 'status' => 'running', 'confidence' => 0.9, 'reason' => 'ok',
    ])]]])]);

    $result = (new LlmCandidateLeadVerifier)->verify(txSenateLead('James Talarico'));

    Http::assertSent(fn ($request) => str_contains($request['system'], '2026-09-20')
        && str_contains($request['system'], 'cycle in question is 2026')
        && str_contains($request['system'], '2026-11-03'));
    expect($result['status'])->toBe('running')->and($result['verified_payload']['election_date'])->toBeNull();
});

it('never writes a past-cycle election date, so the prune job does not delete the record', function () {
    $lead = txSenateLead('James Talarico', [
        'status' => CandidateLead::STATUS_VERIFIED, 'confidence' => 0.9,
        'verified_payload' => ['political_office' => 'U.S. Senator', 'election_date' => '2024-11-05'],
    ]);

    $record = app(CandidateLeadPromoter::class)->promote($lead);

    expect($record->election_date->toDateString())->toBe('2026-11-03');
});

it('promotes a 0.80 lead the FEC roster corroborates, and leaves an uncorroborated one below the line', function () {
    txSenateFiler('James Talarico');
    fakeVerifierReturning(0.8);

    $real = txSenateLead('Texas Rep. James Talarico');
    $noise = txSenateLead('Some Headline Words');

    $this->artisan('candidates:verify-leads', ['--state' => 'TX', '--skip-ai' => true])->assertSuccessful();

    expect($real->fresh()->status)->toBe(CandidateLead::STATUS_PROMOTED)
        ->and($real->fresh()->election_candidate_record_id)->not->toBeNull()
        ->and($noise->fresh()->status)->toBe(CandidateLead::STATUS_VERIFIED);
    $this->assertDatabaseHas('election_candidate_records', ['full_name' => 'James Talarico', 'state' => 'TX']);
    $this->assertDatabaseMissing('election_candidate_records', ['full_name' => 'Some Headline Words']);
});

it('does not treat a sitting official as corroboration for a candidacy', function () {
    Politician::factory()->create([
        'full_name' => 'Ken Paxton', 'state' => 'TX', 'user_id' => null, 'term_status' => 'seated', 'is_active' => true,
        'political_office' => 'Attorney General', 'slug' => 'ken-paxton-attorney-general',
    ]);
    fakeVerifierReturning(0.8);
    $lead = txSenateLead('Ken Paxton');

    $this->artisan('candidates:verify-leads', ['--state' => 'TX', '--skip-ai' => true])->assertSuccessful();

    expect($lead->fresh()->status)->toBe(CandidateLead::STATUS_VERIFIED);
});

it('re-checks leads stuck below the threshold or wrongly rejected by the AI, only with --recheck', function () {
    txSenateFiler('James Talarico');
    fakeVerifierReturning(0.8);

    $stuck = txSenateLead('James Talarico', ['status' => CandidateLead::STATUS_VERIFIED, 'confidence' => 0.8]);
    $wrong = txSenateLead('James Talarico', ['status' => CandidateLead::STATUS_REJECTED, 'verifier_key' => 'llm_anthropic']);
    $kept = txSenateLead('James Talarico', ['status' => CandidateLead::STATUS_REJECTED, 'verifier_key' => 'wikipedia']);

    $this->artisan('candidates:verify-leads', ['--state' => 'TX', '--skip-ai' => true])->assertSuccessful();
    expect($stuck->fresh()->status)->toBe(CandidateLead::STATUS_VERIFIED);

    $this->artisan('candidates:verify-leads', ['--state' => 'TX', '--skip-ai' => true, '--recheck' => true])->assertSuccessful();
    expect($stuck->fresh()->status)->toBe(CandidateLead::STATUS_PROMOTED)
        ->and($wrong->fresh()->status)->toBe(CandidateLead::STATUS_PROMOTED)
        ->and($kept->fresh()->status)->toBe(CandidateLead::STATUS_REJECTED);
});

it('re-creates the record for an orphaned promoted lead, only when something corroborates the name', function () {
    txSenateFiler('James Talarico');
    fakeVerifierReturning(0.8);

    $orphan = txSenateLead('James Talarico', ['status' => CandidateLead::STATUS_PROMOTED, 'verified_payload' => ['political_office' => 'U.S. Senator', 'election_date' => '2024-11-05']]);
    $junk = txSenateLead('Headline Fragment Words', ['status' => CandidateLead::STATUS_PROMOTED, 'verified_payload' => ['political_office' => 'U.S. Senator']]);
    $eliminated = txSenateLead('James Talarico', ['status' => CandidateLead::STATUS_PROMOTED, 'verified_payload' => ['primary_result' => 'eliminated']]);

    $this->artisan('candidates:verify-leads', ['--state' => 'TX', '--relink' => true, '--skip-ai' => true])->assertSuccessful();

    $record = ElectionCandidateRecord::where('full_name', 'James Talarico')->first();
    expect($record)->not->toBeNull()
        ->and($record->election_date->toDateString())->toBe('2026-11-03')
        ->and($orphan->fresh()->election_candidate_record_id)->toBe($record->id)
        ->and($junk->fresh()->election_candidate_record_id)->toBeNull()
        ->and($eliminated->fresh()->election_candidate_record_id)->toBeNull();
});

it('says a junk-named row is already hidden instead of listing it as an open problem', function () {
    $junk = Politician::factory()->create([
        'full_name' => 'Jane Placeholder', 'state' => 'TX', 'user_id' => null, 'is_active' => false, 'page_published' => false,
        'term_status' => 'lost', 'is_running_candidate' => false, 'political_office' => 'City Council Member', 'governance_level' => 'City', 'slug' => 'election-results-tx',
    ]);
    // The model guard refuses this name on save; the scraped rows got in around it.
    Politician::query()->whereKey($junk->id)->update(['full_name' => 'Election results']);

    $this->artisan('politicians:audit-data-integrity', ['--state' => 'TX', '--fix' => true])
        ->expectsOutputToContain('1 junk-named rows already hidden')
        ->doesntExpectOutputToContain('#')
        ->assertSuccessful();
});

it('relinks only the named person when --name is given', function () {
    txSenateFiler('James Talarico');
    txSenateFiler('Jasmine Crockett');
    fakeVerifierReturning(0.8);

    $pick = txSenateLead('James Talarico', ['status' => CandidateLead::STATUS_PROMOTED, 'verified_payload' => ['political_office' => 'U.S. Senator']]);
    $other = txSenateLead('Jasmine Crockett', ['status' => CandidateLead::STATUS_PROMOTED, 'verified_payload' => ['political_office' => 'U.S. Senator']]);

    $this->artisan('candidates:verify-leads', ['--state' => 'TX', '--relink' => true, '--name' => 'Talarico', '--skip-ai' => true])->assertSuccessful();

    expect($pick->fresh()->election_candidate_record_id)->not->toBeNull()
        ->and($other->fresh()->election_candidate_record_id)->toBeNull();
});

it('cleans a place plus title in front of a name', function (string $raw, string $expected) {
    expect(CandidateNameCanonicalizer::canonicalize($raw))->toBe($expected);
})->with([
    ['Detroit Mayor Mike Duggan', 'Mike Duggan'],
    ['Detroit’s Mayor Mike Duggan', 'Mike Duggan'],
    ['Mayor Mike Duggan', 'Mike Duggan'],
    ['Michigan Sen. Gary Peters', 'Gary Peters'],
    ['Mike Duggan', 'Mike Duggan'],
]);

it('anchors a decorated name to the person the FEC roster or an official already names', function () {
    $filer = fn (string $name) => CandidateRoster::create([
        'source' => 'fec', 'source_id' => 'S6MI'.fake()->unique()->numerify('#####'), 'full_name' => $name,
        'identity_key' => MapCandidateHygiene::identityKey($name), 'state' => 'MI', 'office' => 'S', 'district' => null, 'election_year' => 2026,
    ]);
    $filer('Abdul El-Sayed');
    Politician::factory()->create(['full_name' => 'Gretchen Whitmer', 'state' => 'MI', 'user_id' => null, 'term_status' => 'seated', 'is_active' => true, 'political_office' => 'Governor', 'slug' => 'gretchen-whitmer']);

    $anchor = new CandidateCorroboration;

    expect($anchor->anchorName('Abdul El-Sayed Billboards', 'MI'))->toBe('Abdul El-Sayed')
        ->and($anchor->anchorName('Progressive Abdul El-Sayed', 'MI'))->toBe('Abdul El-Sayed')
        ->and($anchor->anchorName('Michigan Gretchen Whitmer', 'MI'))->toBe('Gretchen Whitmer')
        ->and($anchor->anchorName('Abdul El-Sayed', 'MI'))->toBe('Abdul El-Sayed')
        ->and($anchor->anchorName('Unknown Person Talks', 'MI'))->toBe('Unknown Person Talks');
});

it('renames decorated discovery records, merges into an existing one, and leaves the rest', function () {
    CandidateRoster::create([
        'source' => 'fec', 'source_id' => 'S6MI00418', 'full_name' => 'Abdul El-Sayed',
        'identity_key' => MapCandidateHygiene::identityKey('Abdul El-Sayed'), 'state' => 'MI', 'office' => 'S', 'district' => null, 'election_year' => 2026,
    ]);
    $make = fn (string $name, string $ext) => ElectionCandidateRecord::create([
        'source' => 'candidate_discovery', 'external_candidate_id' => $ext, 'full_name' => $name, 'state' => 'MI',
        'political_office' => 'U.S. Senator', 'governance_level' => 'Federal', 'election_date' => '2024-11-05', 'payload' => [],
    ]);
    $dirty = $make('Abdul El-Sayed Billboards', 'hash-1');
    $other = $make('Mike Rogers Says', 'hash-2');
    $clean = $make('Mike Rogers', 'disc:mi:u-s-senator:mike-rogers');

    $this->artisan('candidates:clean-discovery-names', ['--state' => ['MI']])->assertSuccessful();
    expect($dirty->fresh()->full_name)->toBe('Abdul El-Sayed Billboards'); // dry run

    $this->artisan('candidates:clean-discovery-names', ['--state' => ['MI'], '--apply' => true])->assertSuccessful();

    expect($dirty->fresh()->full_name)->toBe('Abdul El-Sayed')
        ->and($dirty->fresh()->election_date->toDateString())->toBe('2026-11-03')
        ->and(ElectionCandidateRecord::find($other->id))->toBeNull()
        ->and(ElectionCandidateRecord::find($clean->id))->not->toBeNull();
});

it('moves a discovery record to the U.S. seat the FEC lists, merging into the right-office record', function () {
    CandidateRoster::create([
        'source' => 'fec', 'source_id' => 'S6MI00418', 'full_name' => 'Abdul El-Sayed',
        'identity_key' => MapCandidateHygiene::identityKey('Abdul El-Sayed'), 'state' => 'MI', 'office' => 'S', 'district' => null, 'election_year' => 2026,
    ]);
    $make = fn (string $office, string $ext) => ElectionCandidateRecord::create([
        'source' => 'candidate_discovery', 'external_candidate_id' => $ext, 'full_name' => 'Abdul El-Sayed', 'state' => 'MI',
        'political_office' => $office, 'governance_level' => $office === 'U.S. Senator' ? 'Federal' : 'State', 'election_date' => '2026-11-03', 'payload' => [],
    ]);
    $right = $make('U.S. Senator', 'disc:mi:us-senator:abdul-el-sayed');
    $governor = $make('Governor', 'disc:mi:governor:abdul-el-sayed');
    $legislature = $make('Michigan State Senate', 'hash-x');
    $wrongAlone = ElectionCandidateRecord::create([
        'source' => 'candidate_discovery', 'external_candidate_id' => 'hash-y', 'full_name' => 'Abdul El-Sayed', 'state' => 'MI',
        'political_office' => 'Lieutenant Governor', 'governance_level' => 'State', 'election_date' => '2026-11-03', 'payload' => [],
    ]);
    $wrongAlone->delete(); // only the two wrong-office duplicates matter beside the right one

    $this->artisan('candidates:clean-discovery-names', ['--state' => ['MI'], '--apply' => true])->assertSuccessful();

    expect(ElectionCandidateRecord::where('full_name', 'Abdul El-Sayed')->pluck('political_office')->all())->toBe(['U.S. Senator'])
        ->and(ElectionCandidateRecord::find($right->id))->not->toBeNull()
        ->and(ElectionCandidateRecord::find($governor->id))->toBeNull()
        ->and(ElectionCandidateRecord::find($legislature->id))->toBeNull();
});

it('lists discovery records the automatic cleanup cannot settle', function () {
    CandidateRoster::create([
        'source' => 'fec', 'source_id' => 'S6MI00418', 'full_name' => 'Abdul El-Sayed',
        'identity_key' => MapCandidateHygiene::identityKey('Abdul El-Sayed'), 'state' => 'MI', 'office' => 'S', 'district' => null, 'election_year' => 2026,
    ]);
    $make = fn (string $name, string $office, string $ext, string $result) => ElectionCandidateRecord::create([
        'source' => 'candidate_discovery', 'external_candidate_id' => $ext, 'full_name' => $name, 'state' => 'MI',
        'political_office' => $office, 'election_date' => '2026-11-03', 'payload' => ['primary_result' => $result],
    ]);
    $make('Abdul El-Sayed', 'U.S. Senator', 'a', 'advanced_to_general');
    $make('Abdul El-Sayed Billboards', 'Michigan State Senate', 'b', 'eliminated');

    $this->artisan('candidates:audit-records', ['--state' => ['MI']])
        ->expectsOutputToContain('records disagree on the primary result')
        ->expectsOutputToContain('FEC lists them for U.S. Senator')
        ->expectsOutputToContain('name should read "Abdul El-Sayed"')
        ->assertSuccessful();
});

it('does not eliminate a candidate because a page mentions an earlier cycle\'s loss', function () {
    Http::fake([
        'ballotpedia.org/*' => Http::response('', 404),
        'en.wikipedia.org/*' => Http::response(['extract' => 'Mike Rogers is a Republican who conceded the 2024 Senate race to Elissa Slotkin. He is running for the U.S. Senate in 2026.']),
    ]);
    $record = ElectionCandidateRecord::create([
        'source' => 'candidate_discovery', 'external_candidate_id' => 'disc:mi:us-senator:mike-rogers', 'full_name' => 'Mike Rogers', 'state' => 'MI',
        'political_office' => 'U.S. Senator', 'governance_level' => 'Federal', 'election_date' => '2026-11-03', 'payload' => [],
    ]);

    $this->artisan('politicians:sync-primary-results', ['--state' => 'MI'])->assertSuccessful();

    expect($record->fresh()->payload['primary_result'] ?? null)->not->toBe('eliminated');
});

it('lets an admin override a wrongly eliminated primary result', function () {
    $record = ElectionCandidateRecord::create([
        'source' => 'candidate_discovery', 'external_candidate_id' => 'disc:mi:us-senator:mike-rogers', 'full_name' => 'Mike Rogers', 'state' => 'MI',
        'political_office' => 'U.S. Senator', 'governance_level' => 'Federal', 'election_date' => '2026-11-03', 'payload' => ['primary_result' => 'eliminated', 'elimination_note' => 'x'],
    ]);

    $args = ['--state' => 'MI', '--name' => 'Mike Rogers', '--office' => 'Senator', '--result' => 'advanced_to_general'];
    $this->artisan('candidates:set-primary-result', $args)->assertSuccessful();
    expect($record->fresh()->payload['primary_result'])->toBe('eliminated'); // dry run

    $this->artisan('candidates:set-primary-result', $args + ['--apply' => true])->assertSuccessful();
    expect($record->fresh()->payload['primary_result'])->toBe('advanced_to_general')
        ->and($record->fresh()->payload)->not->toHaveKey('elimination_note');
});
