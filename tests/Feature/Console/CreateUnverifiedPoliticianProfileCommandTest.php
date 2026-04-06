<?php

use App\Models\ElectionCandidateRecord;
use App\Models\Politician;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const JACKSON_WEBSITE = 'https://jackson.asmdc.org/';
const JACKSON_NAME = 'Dr. Corey A. Jackson';
const JACKSON_CITY = 'Moreno Valley';

test('creates unverified unclaimed politician profile and candidate record from official website', function () {
    $this->artisan('politicians:create-unverified-profile', [
        '--website' => JACKSON_WEBSITE,
        '--name' => JACKSON_NAME,
        '--office' => 'Assemblymember',
        '--level' => 'State',
        '--state' => 'CA',
        '--district' => 'AD-60',
        '--party' => 'Democratic',
        '--city' => JACKSON_CITY,
    ])->assertExitCode(0);

    $politician = Politician::query()->where('full_name', JACKSON_NAME)->first();

    expect($politician)->not->toBeNull();
    expect($politician->user_id)->toBeNull();
    expect($politician->website_url)->toBe(JACKSON_WEBSITE);
    expect($politician->verified_official)->toBeFalse();
    expect($politician->verification_status)->toBe('unverified');
    expect($politician->page_published)->toBeTrue();

    $candidate = ElectionCandidateRecord::query()
        ->where('source', 'official_state_website')
        ->where('full_name', JACKSON_NAME)
        ->first();

    expect($candidate)->not->toBeNull();
    expect($candidate->state)->toBe('CA');
    expect($candidate->district)->toBe('AD-60');
});

test('updates existing unclaimed profile instead of creating duplicate', function () {
    $existing = Politician::factory()->create([
        'user_id' => null,
        'full_name' => JACKSON_NAME,
        'political_office' => 'Assemblymember',
        'state' => 'CA',
        'city' => 'Old City',
        'website_url' => 'https://old.example.com',
        'verified_official' => true,
        'verification_status' => 'verified',
        'page_published' => false,
    ]);

    $this->artisan('politicians:create-unverified-profile', [
        '--website' => JACKSON_WEBSITE,
        '--name' => JACKSON_NAME,
        '--office' => 'Assemblymember',
        '--state' => 'CA',
        '--city' => JACKSON_CITY,
    ])->assertExitCode(0);

    expect(Politician::query()->count())->toBe(1);

    $updated = $existing->fresh();
    expect($updated->website_url)->toBe(JACKSON_WEBSITE);
    expect($updated->city)->toBe(JACKSON_CITY);
    expect($updated->verified_official)->toBeFalse();
    expect($updated->verification_status)->toBe('unverified');
    expect($updated->page_published)->toBeTrue();
});
