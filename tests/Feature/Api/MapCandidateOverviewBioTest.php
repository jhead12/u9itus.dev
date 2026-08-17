<?php

namespace Tests\Feature\Api;

use App\Models\Politician;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Covers the birth_date/education/profession fields added to the map's
 * candidate-overview endpoint (resources/js/map/ui/politician-drawer.js
 * Overview tab) — sourced from VoteSmartService::fetchPoliticianRatings(),
 * which already fetched this bio data on every call but historically only
 * the ratings/positions/votes made it into any response.
 */
class MapCandidateOverviewBioTest extends TestCase
{
    use RefreshDatabase;

    public function test_includes_bio_fields_for_a_platform_politician(): void
    {
        config(['services.votesmart.api_key' => 'DEMO_KEY']);

        Politician::factory()->create([
            'full_name' => 'Jamie Rivera',
            'state' => 'CA',
            'political_office' => 'Governor',
            'slug' => 'a1b2c-jamie-rivera',
            'show_votesmart_data' => true,
            'votesmart_id' => '12345',
        ]);

        Http::fake([
            'api.votesmart.org/CandidateBio.getBio*' => Http::response([
                'bio' => [
                    'candidate' => [
                        'birthDate' => '1975-04-02',
                        'education' => 'B.A. Political Science, UCLA',
                        'profession' => 'Attorney',
                    ],
                ],
            ], 200),
            'api.votesmart.org/Rating.getCandidateRating*' => Http::response(['candidateRating' => ['rating' => []]], 200),
            'api.votesmart.org/Npat.getNpat*' => Http::response([], 200),
            'api.votesmart.org/Votes.getByOfficial*' => Http::response([], 200),
        ]);

        $response = $this->getJson('/api/v1/map/candidate-overview?slug=a1b2c-jamie-rivera');

        $response->assertOk();
        $response->assertJsonPath('candidate.birth_date', '1975-04-02');
        $response->assertJsonPath('candidate.education', 'B.A. Political Science, UCLA');
        $response->assertJsonPath('candidate.profession', 'Attorney');
    }

    public function test_omits_bio_fields_when_the_politician_has_not_enabled_vote_smart_data(): void
    {
        config(['services.votesmart.api_key' => 'DEMO_KEY']);

        Politician::factory()->create([
            'full_name' => 'Casey Nolan',
            'slug' => 'b2c3d-casey-nolan',
            'show_votesmart_data' => false,
        ]);

        Http::fake(); // a votesmart.org call here would be a consent-gate bug

        $response = $this->getJson('/api/v1/map/candidate-overview?slug=b2c3d-casey-nolan');

        $response->assertOk();
        $this->assertNull($response->json('candidate.birth_date'));
        $this->assertNull($response->json('candidate.education'));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'votesmart.org'));
    }

    public function test_omits_bio_fields_for_a_name_only_lookup_with_no_platform_politician(): void
    {
        $response = $this->getJson('/api/v1/map/candidate-overview?full_name=Unknown+Candidate&state=TX');

        $response->assertOk();
        $this->assertNull($response->json('candidate.birth_date'));
        $this->assertNull($response->json('candidate.education'));
        $this->assertNull($response->json('candidate.profession'));
    }
}
