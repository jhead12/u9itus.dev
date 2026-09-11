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
    // "Christian" is a common given name, not just the leading-qualifier
    // adjective ("Christian conservative candidate...") it was added to
    // catch — it must never be stripped off a real name (regression: was
    // mangling "Christian Hurd"/"Christian Ahmed"/etc. into a bare surname).
    'Christian Hurd',
    'Christian Ahmed',
]);

it('flags as unrepairable when nothing sensible is left after stripping', function (string $junk) {
    $result = PoliticianNameRepairer::repair($junk);

    expect($result['changed'])->toBeFalse()
        ->and($result['unrepairable'])->toBeTrue()
        ->and($result['name'])->toBe($junk);
})->with([
    'Former California',
    // A single leftover word is never a full name — regression coverage for
    // "Current Lieutenant Eleni" over-stripping to just "Eleni", and for
    // "Democratic Party"/"Republican Party" (garbage placeholder rows)
    // collapsing to the bare word "Party" instead of being left alone /
    // flagged for review.
    'Current Lieutenant Eleni',
    'Democratic Party',
    'Republican Party',
    // A leading-qualifier strip can leave a dangling preposition behind —
    // "Mayor of Evanston" must not become the fragment "of Evanston".
    'Mayor of Evanston',
]);

it('handles null/empty input without stripping anything', function () {
    expect(PoliticianNameRepairer::repair(null))
        ->toBe(['name' => '', 'changed' => false, 'unrepairable' => false]);
});
