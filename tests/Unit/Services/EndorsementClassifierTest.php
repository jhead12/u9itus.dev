<?php

use App\Services\EndorsementClassifier;

it('detects a governor endorsement and captures the endorser name', function () {
    $classifier = new EndorsementClassifier();

    $result = $classifier->classify(
        'Governor Newsom endorses Jane Smith for state Senate',
        ''
    );

    expect($result)->toHaveCount(1);
    expect($result[0]['group'])->toBe('governor');
    expect($result[0]['label'])->toBe('Governor');
    expect($result[0]['endorser_name'])->toBe('Newsom');
    expect($result[0]['confidence'])->toBeGreaterThan(0.8);
});

it('detects a president endorsement without a proper name to capture', function () {
    $classifier = new EndorsementClassifier();

    $result = $classifier->classify(
        'President endorses local candidate ahead of city council race',
        ''
    );

    expect($result)->toHaveCount(1);
    expect($result[0]['group'])->toBe('president');
    expect($result[0]['endorser_name'])->toBeNull();
});

it('detects multiple groups mentioned in the same headline', function () {
    $classifier = new EndorsementClassifier();

    $result = $classifier->classify(
        'Governor Newsom and Senator Warren both endorse the candidate for Congress',
        ''
    );

    $groups = collect($result)->pluck('group')->all();
    expect($groups)->toContain('governor');
    expect($groups)->toContain('us_senator');
});

it('does not fire when the title keyword appears without any endorsement verb nearby', function () {
    $classifier = new EndorsementClassifier();

    $result = $classifier->classify(
        'Governor visits the county fair and tours local farms',
        ''
    );

    expect($result)->toBe([]);
});

it('returns an empty array for empty input', function () {
    $classifier = new EndorsementClassifier();

    expect($classifier->classify('', ''))->toBe([]);
});

it('hasGroup detects a matched group key', function () {
    $classifier = new EndorsementClassifier();

    $matches = $classifier->classify('Mayor backs the incumbent candidate for reelection', '');

    expect($classifier->hasGroup($matches, 'mayor'))->toBeTrue();
    expect($classifier->hasGroup($matches, 'governor'))->toBeFalse();
});

it('captures the full name after the title and stops at the verb, even in a Title-Case headline', function () {
    $classifier = new EndorsementClassifier();

    $named = fn (string $headline) => collect($classifier->classify($headline, ''))->pluck('endorser_name', 'group')->all();

    expect($named('Gov. Gavin Newsom Endorses Steve Hilton for Governor'))->toBe(['governor' => 'Gavin Newsom']);
    expect($named('President Donald Trump endorses Hilton'))->toBe(['president' => 'Donald Trump']);
    expect($named('Sen. Elizabeth Warren D-Mass. backs Smith'))->toBe(['us_senator' => 'Elizabeth Warren']);
    expect($named("Gov. Newsom's endorsement of Hilton draws fire"))->toBe(['governor' => 'Newsom']);
});

it('does not take a verb or party word for the endorser name', function () {
    $classifier = new EndorsementClassifier();

    expect($classifier->classify('Governor Endorses Hilton', '')[0]['endorser_name'])->toBeNull();
    expect($classifier->classify('Governor Republican Nominee backs Hilton', '')[0]['endorser_name'])->toBeNull();
});

it('reads "endorsed by the Governor" as the governor endorsing', function () {
    $classifier = new EndorsementClassifier();

    $result = $classifier->classify('Hilton, endorsed by Governor Gavin Newsom, leads poll', '');

    expect($result)->toHaveCount(1);
    expect($result[0]['endorser_name'])->toBe('Gavin Newsom');
});

it('does not treat a titleholder who is being endorsed as an endorser', function () {
    $classifier = new EndorsementClassifier();

    expect($classifier->classify("Caucus Endorses Congresswoman Veronica Escobar's Dignity Act", ''))->toBe([]);
    expect($classifier->classify('Backed by the governor, Hilton surges', ''))->toHaveCount(1);
});

it('ignores "Trump-backed" as an endorsement verb', function () {
    $classifier = new EndorsementClassifier();

    expect($classifier->classify('Collins joins 2026 governor Republican primary against Trump-backed rival', ''))->toBe([]);
});

it('does not read "back in the crosshairs" as an endorsement', function () {
    $classifier = new EndorsementClassifier();

    $headline = "Trump's new Cuba campaign puts Mayor Karen Bass back in the crosshairs";

    expect($classifier->classify($headline, ''))->toBe([]);
    expect($classifier->classify($headline, '', 'Karen Bass'))->toBe([]);
});

it('ignores back-verbs that mean retreat or movement', function (string $headline) {
    expect((new EndorsementClassifier())->classify($headline, '', 'Jane Smith'))->toBe([]);
})->with([
    'Governor backs off plan to cut Jane Smith budget',
    'Governor backed into a corner over Jane Smith scandal',
    'President is back on the trail with Jane Smith',
]);

it('does not treat a possessive title as the endorser unless it is "endorsement of"', function () {
    $classifier = new EndorsementClassifier();

    expect($classifier->classify("Trump's tariffs back Jane Smith into a corner", '', 'Jane Smith'))->toBe([]);
    expect($classifier->classify("Trump's endorsement of Jane Smith shakes up the primary", '', 'Jane Smith'))->toHaveCount(1);
});

it('requires the candidate to come after the verb when the endorser acts', function () {
    $classifier = new EndorsementClassifier();

    expect($classifier->classify('Trump backs Karen Bass', '', 'Karen Bass'))->toHaveCount(1);
    expect($classifier->classify('Karen Bass slams Trump, who backs Steve Hilton', '', 'Karen Bass'))->toBe([]);
    expect($classifier->classify('Karen Bass endorsed by the governor', '', 'Karen Bass'))->toHaveCount(1);
});

it('still detects Trump endorsements written in the active voice', function () {
    $result = (new EndorsementClassifier())->classify('Trump endorses Steve Hilton for governor', '', 'Steve Hilton');

    expect($result)->toHaveCount(1);
    expect($result[0]['endorser_name'])->toBe('Donald Trump');
});

it('does not read "back-to-back" as an endorsement', function () {
    $classifier = new EndorsementClassifier();
    $headline = 'Trump, Cruz hold back-to-back rallies ahead of South Carolina primary';

    expect($classifier->classify($headline, ''))->toBe([]);
    expect($classifier->classify($headline, '', 'Ted Cruz'))->toBe([]);
    expect($classifier->classify('Trump holds back-channel talks with Jane Smith', '', 'Jane Smith'))->toBe([]);
});
