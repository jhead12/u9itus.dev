<?php

use App\Models\BallotMeasure;
use App\Support\BallotMeasureWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('enriches an existing measure matched by number when the title differs', function () {
    // A hand-curated row with a short title and no yes_meaning.
    BallotMeasure::create([
        'state' => 'CA', 'measure_number' => '1', 'title' => 'Proposition 1: Housing Bond',
        'election_date' => '2026-11-03', 'source' => 'ca_sos',
    ]);

    // A second source with the fuller official title + a Yes-vote description.
    $attrs = BallotMeasureWriter::normalize(
        [
            'title' => 'Proposition 1: California Veteran and Housing Assistance Programs Bond Measure',
            'measure_number' => '1',
            'yes_meaning' => 'Issue $11.25 billion in housing bonds.',
        ],
        state: 'CA', county: null, electionDate: '2026-11-03', source: 'wikipedia',
    );

    $result = (new BallotMeasureWriter)->upsert($attrs);

    expect($result)->toBe('updated');
    expect(BallotMeasure::count())->toBe(1);

    $row = BallotMeasure::first();
    expect($row->yes_meaning)->toBe('Issue $11.25 billion in housing bonds.') // filled
        ->and($row->title)->toBe('Proposition 1: Housing Bond')                 // kept (blank-fill only)
        ->and($row->source)->toBe('ca_sos');                                    // provenance untouched
});

it('creates a new row when neither title nor number matches', function () {
    BallotMeasure::create(['state' => 'CA', 'measure_number' => '1', 'title' => 'Prop 1', 'election_date' => '2026-11-03', 'source' => 'x']);

    $attrs = BallotMeasureWriter::normalize(
        ['title' => 'Proposition 2: A different measure', 'measure_number' => '2'],
        state: 'CA', county: null, electionDate: '2026-11-03', source: 'wikipedia',
    );
    (new BallotMeasureWriter)->upsert($attrs);

    expect(BallotMeasure::count())->toBe(2);
});

it('passes through status and refuses an out-of-range value', function () {
    $ok = BallotMeasureWriter::normalize(['title' => 'M A', 'status' => 'passed'], 'CA', null, null, 'wikipedia');
    $bad = BallotMeasureWriter::normalize(['title' => 'M B', 'status' => 'garbage'], 'CA', null, null, 'wikipedia');

    expect($ok['status'])->toBe('passed')
        ->and($bad['status'])->toBe('upcoming');
});
