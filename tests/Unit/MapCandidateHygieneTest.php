<?php

use App\Support\MapCandidateHygiene;

it('treats nicknames, suffixes, initials and diacritics as the same person', function (string $a, string $b) {
    expect(MapCandidateHygiene::identityKey($a))->toBe(MapCandidateHygiene::identityKey($b));
})->with([
    'Steve / Steven' => ['Steve Bradford', 'Steven Bradford'],
    'Stephen / Steven' => ['Stephen Bradford', 'Steven Bradford'],
    'suffix' => ['Rudy Yakym III', 'Rudy Yakym'],
    'comma suffix' => ['Gilbert Ray Cisneros, Jr.', 'Gilbert Cisneros'],
    'middle initial' => ['Linda T. Sánchez', 'Linda Sanchez'],
    'title' => ['Rep. Nancy Pelosi', 'Nancy Pelosi'],
    'last, first' => ['Bradford, Steven', 'Steve Bradford'],
]);

it('does not merge different people', function () {
    expect(MapCandidateHygiene::identityKey('Steven Bradford'))
        ->not->toBe(MapCandidateHygiene::identityKey('Steven Bradley'))
        ->and(MapCandidateHygiene::identityKey('Jim Costa'))
        ->not->toBe(MapCandidateHygiene::identityKey('Jim Cooper'));
});

it('flags names that are not people', function (string $name) {
    expect(MapCandidateHygiene::nameProblem($name))->not->toBeNull();
})->with([
    'WI Candidate David',
    "Ballotpedia's Candidate Survey",
    'Send us candidate contact info',
    'Huntington Beach',
    "The State Treasurer's Office",
]);

it('flags a name that matches a known city', function () {
    $places = [MapCandidateHygiene::placeKey('Santa Ana') => true];

    expect(MapCandidateHygiene::nameProblem('Santa Ana', $places))->not->toBeNull()
        ->and(MapCandidateHygiene::nameProblem('Santa Anderson', $places))->toBeNull();
});

it('accepts ordinary and unusual real names', function (string $name) {
    expect(MapCandidateHygiene::nameProblem($name))->toBeNull();
})->with([
    'Steven Bradford', 'Al Green', 'Linda T. Sánchez', 'Nanette Diaz Barragán',
    'Gilbert Ray Cisneros, Jr.', "Beto O'Rourke", 'Alexandria Ocasio-Cortez', 'Young Kim',
]);

it('only hides a junk name that nothing vouches for', function () {
    expect(MapCandidateHygiene::shouldHide(['full_name' => 'WI Candidate David', 'status' => 'running']))->toBeTrue()
        ->and(MapCandidateHygiene::shouldHide(['full_name' => 'WI Candidate David', 'status' => 'seated']))->toBeFalse()
        ->and(MapCandidateHygiene::shouldHide(['full_name' => 'WI Candidate David', 'status' => 'running', 'verified' => true]))->toBeFalse()
        ->and(MapCandidateHygiene::shouldHide(['full_name' => 'Steven Bradford', 'status' => 'running']))->toBeFalse();
});

it('keeps the best record when merging duplicates and fills in what it lacks', function () {
    [$kept, $merged] = MapCandidateHygiene::dedupe([
        ['full_name' => 'Steve Bradford', 'source' => 'scraped', 'status' => 'running', 'photo' => 'https://x/p.jpg', 'party' => 'Democratic'],
        ['full_name' => 'Steven Bradford', 'source' => 'platform', 'status' => 'running', 'slug' => 'steven-bradford', 'photo' => null],
        ['full_name' => 'Someone Else', 'source' => 'platform', 'status' => 'running'],
    ]);

    expect($merged)->toBe(1)
        ->and($kept)->toHaveCount(2)
        ->and($kept[0]['full_name'])->toBe('Steven Bradford')
        ->and($kept[0]['photo'])->toBe('https://x/p.jpg')
        ->and($kept[0]['party'])->toBe('Democratic')
        ->and($kept[1]['full_name'])->toBe('Someone Else');
});

it('merges a record that drops half of a hyphenated surname', function () {
    [$kept, $merged] = MapCandidateHygiene::dedupe([
        ['full_name' => 'Sydney Kamlager-Dove', 'source' => 'platform', 'status' => 'seated', 'slug' => 'kamlager-dove'],
        ['full_name' => 'Sydney Kamlager', 'source' => 'scraped', 'status' => 'running'],
        ['full_name' => 'Sydney Dove-Smith', 'source' => 'scraped', 'status' => 'running'],
        ['full_name' => 'Maria Kamlager', 'source' => 'scraped', 'status' => 'running'],
    ]);

    expect($merged)->toBe(1)
        ->and(array_column($kept, 'full_name'))->toBe(['Sydney Kamlager-Dove', 'Sydney Dove-Smith', 'Maria Kamlager']);
});
