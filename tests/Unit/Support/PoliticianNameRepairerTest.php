<?php

use App\Support\PoliticianNameRepairer;

/**
 * Covers PoliticianNameRepairer::repair() — the leading-qualifier strip pass
 * that fixes (rather than only rejecting) junk full_name rows like the ones
 * that leaked onto the public map: "Former California", "California Gavin
 * Newsom", "Independent Michael Shellenberger", "Lt. Gov. Eleni Kounalakis".
 */
it('strips a leading qualifier and keeps the real name', function (string $junk, string $expected) {
    $result = PoliticianNameRepairer::repair($junk);

    expect($result['changed'])->toBeTrue()
        ->and($result['unrepairable'])->toBeFalse()
        ->and($result['name'])->toBe($expected);
})->with([
    ['California Gavin Newsom', 'Gavin Newsom'],
    ['New Gavin Newsom', 'Gavin Newsom'],
    ['Independent Michael Shellenberger', 'Michael Shellenberger'],
    ['Lt. Gov. Eleni Kounalakis', 'Eleni Kounalakis'],
    ['Current Lieutenant Eleni', 'Eleni'],
    ['Former Xavier Becerra', 'Xavier Becerra'],
]);

it('leaves a name with no leading qualifier untouched', function (string $name) {
    $result = PoliticianNameRepairer::repair($name);

    expect($result['changed'])->toBeFalse()
        ->and($result['unrepairable'])->toBeFalse()
        ->and($result['name'])->toBe($name);
})->with([
    'Gavin Newsom',
    'Eleni Kounalakis',
    'Xavier Becerra',
    'Steve Hilton',
]);

it('flags as unrepairable when nothing sensible is left after stripping', function () {
    $result = PoliticianNameRepairer::repair('Former California');

    expect($result['changed'])->toBeFalse()
        ->and($result['unrepairable'])->toBeTrue()
        ->and($result['name'])->toBe('Former California');
});

it('handles null/empty input without stripping anything', function () {
    expect(PoliticianNameRepairer::repair(null))
        ->toBe(['name' => '', 'changed' => false, 'unrepairable' => false]);
});
