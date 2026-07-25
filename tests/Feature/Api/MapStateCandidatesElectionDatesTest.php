<?php

namespace Tests\Feature\Api;

use App\Models\StateElectionDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MapStateCandidatesElectionDatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_response_includes_upcoming_election_dates_for_the_state(): void
    {
        StateElectionDate::create([
            'state' => 'CA', 'election_year' => 2026, 'stage_name' => 'Primary',
            'election_date' => now()->addMonth()->toDateString(),
            'filing_deadline' => now()->addDays(5)->toDateString(),
        ]);
        StateElectionDate::create([
            'state' => 'TX', 'election_year' => 2026, 'stage_name' => 'Primary',
            'election_date' => now()->addMonth()->toDateString(),
        ]);

        $response = $this->getJson('/api/v1/map/state-candidates?state=CA');

        $response->assertOk();
        $dates = $response->json('election_dates');

        $this->assertCount(1, $dates);
        $this->assertSame('Primary', $dates[0]['stage_name']);
        $this->assertArrayHasKey('election_date_formatted', $dates[0]);
    }

    public function test_response_has_empty_election_dates_when_none_synced(): void
    {
        $response = $this->getJson('/api/v1/map/state-candidates?state=WY');

        $response->assertOk();
        $this->assertSame([], $response->json('election_dates'));
    }
}
