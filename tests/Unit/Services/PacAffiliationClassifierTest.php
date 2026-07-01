<?php

use App\Services\PacAffiliationClassifier;

it('flags AIPAC and pro-Israel PAC names among top contributors', function () {
    $classifier = new PacAffiliationClassifier();

    $result = $classifier->classify([
        ['name' => 'AIPAC', 'total' => '$50,000'],
        ['name' => 'Pro-Israel America PAC', 'total' => '$12,000'],
        ['name' => 'NORPAC', 'total' => '$8,000'],
        ['name' => 'National Association of Realtors', 'total' => '$20,000'],
    ]);

    expect($result)->toHaveCount(3);
    expect(collect($result)->pluck('matched_name')->all())
        ->toEqual(['AIPAC', 'Pro-Israel America PAC', 'NORPAC']);
    expect(collect($result)->every(fn ($m) => $m['group'] === 'aipac_pro_israel'))->toBeTrue();
});

it('returns an empty array when there are no matches', function () {
    $classifier = new PacAffiliationClassifier();

    $result = $classifier->classify([
        ['name' => 'National Association of Realtors', 'total' => '$20,000'],
    ]);

    expect($result)->toBe([]);
});

it('returns an empty array for empty input', function () {
    $classifier = new PacAffiliationClassifier();

    expect($classifier->classify([]))->toBe([]);
});

it('hasGroup detects a matched group key', function () {
    $classifier = new PacAffiliationClassifier();

    $matches = $classifier->classify([
        ['name' => 'AIPAC', 'total' => '$50,000'],
    ]);

    expect($classifier->hasGroup($matches, 'aipac_pro_israel'))->toBeTrue();
    expect($classifier->hasGroup($matches, 'some_other_group'))->toBeFalse();
});
