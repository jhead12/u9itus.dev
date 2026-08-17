<?php

namespace Tests\Feature\Api;

use App\Models\BallotMeasure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the `ballot_measures` block of /api/v1/map/state-candidates,
 * which powers the click-to-expand ballot measure cards in the map's
 * state panel (resources/js/map/ui/panel-state.js).
 */
class MapStateCandidatesBallotMeasuresTest extends TestCase
{
    use RefreshDatabase;

    public function test_ballot_measures_include_a_link_to_the_dedicated_page(): void
    {
        $measure = BallotMeasure::create([
            'state' => 'CA',
            'title' => 'Bond for Schools',
            'summary' => 'Authorizes a bond for school construction.',
            'yes_meaning' => 'You support the bond.',
            'no_meaning' => 'You oppose the bond.',
            'election_date' => now()->addMonths(2)->toDateString(),
            'status' => 'upcoming',
        ]);

        $response = $this->getJson('/api/v1/map/state-candidates?state=CA');

        $response->assertOk();

        $row = collect($response->json('ballot_measures'))->firstWhere('title', 'Bond for Schools');

        $this->assertNotNull($row);
        $this->assertSame(
            route('voter.ballot-measures.show', $measure->id),
            $row['detail_url']
        );
    }

    public function test_past_ballot_measures_are_excluded(): void
    {
        BallotMeasure::create([
            'state' => 'CA',
            'title' => 'Old Measure',
            'election_date' => now()->subYear()->toDateString(),
            'status' => 'passed',
        ]);

        $response = $this->getJson('/api/v1/map/state-candidates?state=CA');

        $response->assertOk();
        $this->assertSame([], $response->json('ballot_measures'));
    }
}
