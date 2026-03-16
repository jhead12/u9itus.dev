<?php

use App\Models\ElectionCandidateRecord;
use App\Models\PoliticalCampaign;
use App\Models\Politician;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('imports california unclaimed politicians from remote json and creates preview campaigns', function () {
    Http::fake([
        'https://example.test/legislators-current.json' => Http::response([
            [
                'id' => ['bioguide' => 'P000197'],
                'name' => [
                    'official_full' => 'Nancy Pelosi',
                    'first' => 'Nancy',
                    'last' => 'Pelosi',
                ],
                'terms' => [
                    [
                        'type' => 'rep',
                        'state' => 'CA',
                        'district' => 11,
                        'party' => 'Democrat',
                        'start' => '2023-01-03',
                        'end' => '2027-01-03',
                        'url' => 'https://pelosi.house.gov',
                    ],
                ],
            ],
            [
                'id' => ['bioguide' => 'S000033'],
                'name' => [
                    'official_full' => 'Chuck Schumer',
                ],
                'terms' => [
                    [
                        'type' => 'sen',
                        'state' => 'NY',
                        'party' => 'Democrat',
                        'start' => '2023-01-03',
                        'end' => '2029-01-03',
                    ],
                ],
            ],
        ], 200),
    ]);

    $this->artisan('politicians:import-unclaimed-ca', [
        '--source-url' => 'https://example.test/legislators-current.json',
        '--with-campaigns' => true,
    ])->assertExitCode(0);

    $politician = Politician::where('full_name', 'Nancy Pelosi')->first();

    expect($politician)->not->toBeNull();
    expect($politician->user_id)->toBeNull();
    expect($politician->state)->toBe('CA');
    expect($politician->profile_photo_url)->toContain('P000197');
    expect($politician->page_published)->toBeTrue();

    $this->assertDatabaseHas('election_candidate_records', [
        'source' => 'congress_legislators',
        'external_candidate_id' => 'P000197',
        'full_name' => 'Nancy Pelosi',
        'state' => 'CA',
    ]);

    $this->assertDatabaseCount('politicians', 1);

    expect(PoliticalCampaign::where('politician_id', $politician->id)->count())->toBe(1);
});
