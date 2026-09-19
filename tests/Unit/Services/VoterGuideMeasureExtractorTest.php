<?php

use App\Services\OcrCandidateImportService;
use App\Services\VoterGuideMeasureExtractor;

function guideExtractor(): VoterGuideMeasureExtractor
{
    return new VoterGuideMeasureExtractor(new OcrCandidateImportService);
}

it('extracts measures with titles, summaries and yes/no meanings', function () {
    $text = <<<'TXT'
    Measure A
    School Facilities Bond
    Shall the Lakeside Unified School District issue $80 million in bonds to repair classrooms?
    A YES vote means: The district could borrow $80 million to repair schools.
    A NO vote means: The district could not issue the bonds.

    Measure B: Cannabis Business Tax
    An ordinance to tax cannabis retailers at 4% of gross receipts.
    TXT;

    $measures = guideExtractor()->parse($text);

    expect($measures)->toHaveCount(2)
        ->and($measures[0]['title'])->toBe('Measure A: School Facilities Bond')
        ->and($measures[0]['measure_number'])->toBe('A')
        ->and($measures[0]['summary'])->toContain('issue $80 million')
        ->and($measures[0]['summary'])->not->toContain('YES vote')
        ->and($measures[0]['yes_meaning'])->toBe('The district could borrow $80 million to repair schools.')
        ->and($measures[0]['no_meaning'])->toBe('The district could not issue the bonds.')
        ->and($measures[1]['title'])->toBe('Measure B: Cannabis Business Tax')
        ->and($measures[1]['summary'])->toContain('4% of gross receipts');
});

it('keeps the fullest entry when a measure appears in the contents and in the body', function () {
    $text = <<<'TXT'
    CONTENTS
    Measure A ........ 12
    Measure B ........ 14

    Measure A: Library Parcel Tax
    Renews a $39 parcel tax for county libraries for ten years.
    TXT;

    $measures = guideExtractor()->parse($text);
    $a = collect($measures)->firstWhere('measure_number', 'A');

    expect(collect($measures)->pluck('measure_number')->all())->toBe(['A', 'B'])
        ->and(collect($measures)->firstWhere('measure_number', 'B')['title'])->toBe('Measure B')
        ->and($a['title'])->toBe('Measure A: Library Parcel Tax')
        ->and($a['summary'])->toContain('parcel tax');
});

it('does not treat a sentence about a measure as a new measure', function () {
    $text = "Measure C: Road Repair Sales Tax\nMeasure C would raise the sales tax by half a cent.\nIt is on the November ballot.";

    $measures = guideExtractor()->parse($text);

    expect($measures)->toHaveCount(1)
        ->and($measures[0]['summary'])->toContain('would raise the sales tax');
});

it('returns nothing for text with no measures', function () {
    expect(guideExtractor()->parse("Candidate statements\nJane Smith for Mayor"))->toBe([]);
});

it('reads a plain-text guide file', function () {
    $path = tempnam(sys_get_temp_dir(), 'guide').'.txt';
    file_put_contents($path, "Question 2: Charter Amendment\nAllows the city council to set its own salaries.");

    $measures = guideExtractor()->fromFile($path);
    @unlink($path);

    expect($measures)->toHaveCount(1)
        ->and($measures[0]['title'])->toBe('Question 2: Charter Amendment');
});

it('reads a guide from a link, and refuses non-public addresses', function () {
    \Illuminate\Support\Facades\Http::fake([
        '93.184.216.34/*' => \Illuminate\Support\Facades\Http::response(
            '<html><body><script>var x=1</script><h2>Measure D</h2><p>Parks Maintenance Levy</p><p>Funds park upkeep.</p></body></html>',
            200,
            ['Content-Type' => 'text/html'],
        ),
    ]);

    $measures = guideExtractor()->fromUrl('https://93.184.216.34/guide');

    expect($measures)->toHaveCount(1)
        ->and($measures[0]['title'])->toBe('Measure D: Parks Maintenance Levy');

    expect(fn () => guideExtractor()->fromUrl('http://127.0.0.1/admin'))->toThrow(\App\Exceptions\OcrCandidateImportException::class)
        ->and(fn () => guideExtractor()->fromUrl('file:///etc/passwd'))->toThrow(\App\Exceptions\OcrCandidateImportException::class);
});
