<?php

use App\Models\ElectionCandidateRecord;
use App\Models\GovernorRaceCandidateCount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

it('compares the governors the map lists as running against the race article', function () {
    Cache::flush();
    GovernorRaceCandidateCount::create(['state' => 'CA', 'election_year' => 2026, 'expected_count' => 2, 'source' => 'test']);

    Http::fake([
        'en.wikipedia.org/w/api.php*' => Http::response(['parse' => ['wikitext' => "====Advanced to general====\n* [[Xavier Becerra]], former AG\n* [[Steve Hilton]], commentator\n====Withdrawn====\n* [[Eric Swalwell]], former representative\n"]]),
    ]);

    foreach (['Xavier Becerra', 'Eric Swalwell'] as $name) {
        ElectionCandidateRecord::factory()->create([
            'source' => 'candidate_discovery', 'full_name' => $name, 'governance_level' => 'State', 'political_office' => 'Governor',
            'state' => 'CA', 'election_date' => '2026-11-03', 'party_affiliation' => 'Democratic',
            'payload' => ['primary_result' => 'running', 'state' => 'CA', 'election_date' => '2026-11-03', 'political_office' => 'Governor'],
        ]);
    }

    $this->artisan('politicians:audit-race-counts', ['--state' => 'CA', '--wikipedia' => true])
        ->expectsOutputToContain('missing from map')
        ->expectsOutputToContain('Eric Swalwell (withdrawn per Wikipedia)');
});
