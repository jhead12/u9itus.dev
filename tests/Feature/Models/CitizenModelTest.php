<?php

use App\Models\Citizen;

test('citizen boot hooks generate uuid, slug, and referral code', function () {
    $citizen = Citizen::factory()->create([
        'full_name' => 'Jane Doe',
        'business_name' => null,
        'city' => 'Springfield',
    ]);

    expect($citizen->uuid)->not->toBeEmpty();
    expect($citizen->referral_code)->toMatch('/^C[A-Z0-9]{7}$/');
    expect($citizen->slug)->toContain('jane-doe-springfield');
    expect($citizen->getRouteKeyName())->toBe('uuid');
});

test('citizen slug prefers business name over full name', function () {
    $citizen = Citizen::factory()->create([
        'full_name' => 'Jane Doe',
        'business_name' => 'Jane\'s Bakery',
        'city' => 'Springfield',
    ]);

    expect($citizen->slug)->toContain('janes-bakery-springfield');
});

test('citizen is not identity verified by default and becomes verified via factory state', function () {
    $unverified = Citizen::factory()->create();
    $verified = Citizen::factory()->verified()->create();

    expect($unverified->isIdentityVerified())->toBeFalse();
    expect($verified->isIdentityVerified())->toBeTrue();
});
