<?php

use App\Support\PoliticianDataRules;

/**
 * Covers headlineFragmentViolation() — the strict name check applied to
 * candidate-discovery / RSS-headline extractions (ElectionCandidateRecord
 * write-guard, CandidateLeadPromoter, politicians:prune-junk-ecrs).
 */
dataset('real names', [
    'Gavin Newsom',
    'Eleni Kounalakis',
    'Xavier Becerra',
    'Antonio Villaraigosa',
    'Katie Porter',
    'Chad Bianco',
    'Steve Hilton',
    'Toni Atkins',
    'Betty Yee',
    'Ian Calderon',
    'Rob Bonta',
    'Eric Swalwell',
    'Tom Steyer',
    'Caitlyn Jenner',
    'Fiona Ma',
    'Anna May',       // "May" is a real surname — must not trip trailing-verb rule
    'Theresa May',
    'Young Kim',
    'Al Green',
    'Mark Green',
]);

dataset('headline fragments', [
    'Former California',
    'California Gavin Newsom',
    'New Gavin Newsom',
    'Current Lieutenant Eleni',
    'Former L.A. Mayor Antonio',
    'Billionaire Tom Steyer',
    'Millennial Ian Calderon',
    'Riverside County Sheriff Chad',
    'Sheriff Chad Bianco',
    'Job Creator',
    'Consumer Protection Attorney',
    'Reality TV',
    'Indian American',
    'California Governor\'s Race',
    'California GOP',
    'After Trolling Trump',
    'Becerra Advances',
    'Nancy Mace Run',
    'Nevada Joe Lombardo',
    'Tennessee Sen. Blackburn',
    'Steve Hilton Candidacy',
    'Steve Hilton Edges Out',
    'Eric Swalwell Won More',
    'Trump-Backed Steve Hilton Is',
    'Toni Atkins Outraises Other',
    'Treasurer Fiona Ma',
    'Steven Bradford Exits Lt.',
    'San José Mayor Matt',
    'ADA BRICEÑO LAUNCHES CAMPAIGN',
    'Fiery Katie Porter',
    'California Attorney General',
    'Caitlyn Jenner Run',
    'Former Fox News Contributor',
    // Trailing headline-fragment leaks that produced real duplicate rows on
    // the public map ("Eric Swalwell" already exists as a clean name, but
    // the RSS pipeline also promoted these mangled variants of the same
    // person as if they were different candidates).
    'Eric Swalwell Officially',
    'Steve Hilton Dinner',
    'Steve Hilton Chances',
]);

it('accepts a real first-last name', function (string $name) {
    expect(PoliticianDataRules::headlineFragmentViolation($name))->toBeNull();
})->with('real names');

it('rejects a news-headline fragment', function (string $name) {
    expect(PoliticianDataRules::headlineFragmentViolation($name))->not->toBeNull();
})->with('headline fragments');

it('leaves the lenient nameViolation() unchanged for headline-shaped fixtures', function () {
    // The Politician model hook uses nameViolation(), not the strict variant —
    // synthetic fixtures like these must still pass it.
    expect(PoliticianDataRules::nameViolation('Governor Jane Smith'))->toBeNull();
    expect(PoliticianDataRules::nameViolation('New Name'))->toBeNull();
    expect(PoliticianDataRules::nameViolation('Jane Advances'))->toBeNull();

    // ...but the sentence / artifact rules it always had still fire.
    expect(PoliticianDataRules::nameViolation('The outcome is unknown'))->not->toBeNull();
    expect(PoliticianDataRules::nameViolation('TBD'))->not->toBeNull();
});
