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
