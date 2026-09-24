<?php

use App\Models\ElectionCandidateRecord;
use App\Models\PoliticalCampaign;
use App\Models\Politician;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

test('imports unclaimed U.S. politicians for selected states using current fetcher', function () {
    Queue::fake();

    Http::fake([
        'https://example.test/legislators-current.json' => Http::response([
            [
                'id' => ['bioguide' => 'G000001'],
                'name' => ['official_full' => 'Grace Hopper'],
                'terms' => [
                    [
                        'type' => 'rep',
                        'state' => 'NY',
                        'district' => 3,
                        'party' => 'Independent',
                        'start' => '2025-01-03',
                        'end' => '2027-01-03',
                        'url' => 'https://hopper.house.gov',
                        'address' => '123 Main St, Albany, NY 12207',
                    ],
                ],
            ],
            [
                'id' => ['bioguide' => 'T000001'],
                'name' => ['official_full' => 'Tommy Douglas'],
                'terms' => [
                    [
                        'type' => 'sen',
                        'state' => 'TX',
                        'party' => 'Democrat',
                        'start' => '2023-01-03',
                        'end' => '2029-01-03',
                    ],
                ],
            ],
            [
                'id' => ['bioguide' => 'C000001'],
                'name' => ['official_full' => 'Casey Test'],
                'terms' => [
                    [
                        'type' => 'rep',
                        'state' => 'CA',
                        'district' => 9,
                        'party' => 'Democrat',
                        'start' => '2025-01-03',
                        'end' => '2027-01-03',
                    ],
                ],
            ],
        ], 200),
    ]);

    $this->artisan('politicians:import-unclaimed-us', [
        '--fetcher' => 'current',
        '--state' => ['NY', 'TX'],
        '--current-url' => 'https://example.test/legislators-current.json',
        '--with-campaigns' => true,
    ])->assertExitCode(0);

    $this->assertDatabaseCount('politicians', 2);

    $ny = Politician::query()->where('full_name', 'Grace Hopper')->first();
    expect($ny)->not->toBeNull();
    expect($ny->state)->toBe('NY');
    expect($ny->district)->toBe('NY-03');

    $tx = Politician::query()->where('full_name', 'Tommy Douglas')->first();
    expect($tx)->not->toBeNull();
    expect($tx->state)->toBe('TX');
    expect($tx->district)->toBeNull();

    $this->assertDatabaseMissing('politicians', [
        'full_name' => 'Casey Test',
        'state' => 'CA',
    ]);

    $this->assertDatabaseHas('election_candidate_records', [
        'source' => 'congress_legislators_current',
        'external_candidate_id' => 'G000001',
        'state' => 'NY',
    ]);

    expect(PoliticalCampaign::query()->count())->toBe(2);
});

test('historical fetcher imports former officials only when include-former is enabled', function () {
    Queue::fake();

    Http::fake([
        'https://example.test/legislators-historical.json' => Http::response([
            [
                'id' => ['bioguide' => 'H000001'],
                'name' => ['official_full' => 'Historic Official'],
                'terms' => [
                    [
                        'type' => 'rep',
                        'state' => 'OH',
                        'district' => 7,
                        'party' => 'Whig',
                        'start' => '1991-01-03',
                        'end' => '1995-01-03',
                    ],
                ],
            ],
        ], 200),
    ]);

    $this->artisan('politicians:import-unclaimed-us', [
        '--fetcher' => 'historical',
        '--historical-url' => 'https://example.test/legislators-historical.json',
    ])->assertExitCode(0);

    $this->assertDatabaseCount('politicians', 0);
    $this->assertDatabaseCount('election_candidate_records', 0);

    $this->artisan('politicians:import-unclaimed-us', [
        '--fetcher' => 'historical',
        '--include-former' => true,
        '--historical-url' => 'https://example.test/legislators-historical.json',
    ])->assertExitCode(0);

    $this->assertDatabaseHas('politicians', [
        'full_name' => 'Historic Official',
        'state' => 'OH',
    ]);

    $this->assertDatabaseHas('election_candidate_records', [
        'source' => 'congress_legislators_historical',
        'external_candidate_id' => 'H000001',
    ]);
});

test('combined fetcher imports current and historical in one execution', function () {
    Queue::fake();

    Http::fake([
        'https://example.test/legislators-current.json' => Http::response([
            [
                'id' => ['bioguide' => 'N000001'],
                'name' => ['official_full' => 'Now Serving'],
                'terms' => [
                    [
                        'type' => 'rep',
                        'state' => 'WA',
                        'district' => 2,
                        'party' => 'Democrat',
                        'start' => '2025-01-03',
                        'end' => '2027-01-03',
                    ],
                ],
            ],
        ], 200),
        'https://example.test/legislators-historical.json' => Http::response([
            [
                'id' => ['bioguide' => 'O000001'],
                'name' => ['official_full' => 'Old Serving'],
                'terms' => [
                    [
                        'type' => 'sen',
                        'state' => 'WA',
                        'party' => 'Republican',
                        'start' => '1981-01-03',
                        'end' => '1987-01-03',
                    ],
                ],
            ],
        ], 200),
    ]);

    $this->artisan('politicians:import-unclaimed-us', [
        '--fetcher' => 'combined',
        '--state' => ['WA'],
        '--include-former' => true,
        '--current-url' => 'https://example.test/legislators-current.json',
        '--historical-url' => 'https://example.test/legislators-historical.json',
    ])->assertExitCode(0);

    $this->assertDatabaseHas('politicians', [
        'full_name' => 'Now Serving',
        'state' => 'WA',
    ]);

    $this->assertDatabaseHas('politicians', [
        'full_name' => 'Old Serving',
        'state' => 'WA',
    ]);

    $this->assertDatabaseHas('election_candidate_records', [
        'source' => 'congress_legislators_current',
        'external_candidate_id' => 'N000001',
    ]);

    $this->assertDatabaseHas('election_candidate_records', [
        'source' => 'congress_legislators_historical',
        'external_candidate_id' => 'O000001',
    ]);
});

// The nightly import used to reset every sitting member to seated / not
// running, erasing re-election runs recorded by the primary-result sync.
test('re-importing an existing member keeps running status and curated fields but refreshes seat facts', function () {
    Queue::fake();

    Http::fake([
        'https://example.test/legislators-current.json' => Http::response([[
            'id' => ['bioguide' => 'O000001'],
            'name' => ['official_full' => 'Jay Obernolte'],
            'terms' => [[
                'type' => 'rep', 'state' => 'CA', 'district' => 23, 'party' => 'Republican',
                'start' => '2025-01-03', 'end' => '2027-01-03', 'url' => 'https://obernolte.house.gov',
            ]],
        ]], 200),
    ]);

    $this->artisan('politicians:import-unclaimed-us', ['--state' => ['CA'], '--current-url' => 'https://example.test/legislators-current.json'])->assertExitCode(0);

    $member = Politician::query()->where('full_name', 'Jay Obernolte')->firstOrFail();
    $member->update([
        'is_running_candidate' => true,
        'term_status' => 'running',
        'bio' => 'Edited by staff.',
        'profile_photo_url' => 'https://example.test/claimed.jpg',
        'party_affiliation' => 'Stale',
    ]);

    $this->artisan('politicians:import-unclaimed-us', ['--state' => ['CA'], '--current-url' => 'https://example.test/legislators-current.json'])->assertExitCode(0);

    $member->refresh();
    expect(Politician::query()->where('full_name', 'Jay Obernolte')->count())->toBe(1)
        ->and($member->is_running_candidate)->toBeTrue()
        ->and($member->term_status)->toBe('running')
        ->and($member->bio)->toBe('Edited by staff.')
        ->and($member->profile_photo_url)->toBe('https://example.test/claimed.jpg')
        ->and($member->party_affiliation)->not->toBe('Stale');
});
