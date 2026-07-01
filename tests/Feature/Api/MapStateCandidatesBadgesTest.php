<?php

namespace Tests\Feature\Api;

use App\Models\Politician;
use App\Models\PoliticianTopic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MapStateCandidatesBadgesTest extends TestCase
{
    use RefreshDatabase;

    public function test_statewide_candidate_includes_public_badge_icon_and_color(): void
    {
        $topic = PoliticianTopic::create([
            'name'             => 'Healthcare Access',
            'slug'             => 'healthcare-access',
            'icon'             => '🏥',
            'badge_icon_url'   => 'https://cdn.example.com/badges/healthcare.svg',
            'badge_color'      => '#22c55e',
            'sort_order'       => 0,
            'is_active'        => true,
            'voter_selectable' => true,
            'auto_earned_only' => false,
        ]);

        $politician = Politician::factory()->create([
            'state'             => 'CA',
            'governance_level'  => 'State',
            'political_office'  => 'Governor',
            'term_status'       => 'seated',
            'is_active'         => true,
            'is_running_candidate' => false,
        ]);

        $politician->addBadge($topic->id, 'self_declared');

        $response = $this->getJson('/api/v1/map/state-candidates?state=CA');

        $response->assertOk();

        $candidates = $response->json('offices.0.candidates');
        $candidate = collect($candidates)->firstWhere('full_name', $politician->full_name);

        $this->assertNotNull($candidate, 'Expected the seeded politician to appear in the statewide office group.');
        $this->assertArrayHasKey('badges', $candidate);
        $this->assertCount(1, $candidate['badges']);
        $this->assertSame('Healthcare Access', $candidate['badges'][0]['name']);
        $this->assertSame('https://cdn.example.com/badges/healthcare.svg', $candidate['badges'][0]['icon']);
        $this->assertSame('#22c55e', $candidate['badges'][0]['color']);
    }

    public function test_badges_are_capped_at_four_and_private_badges_are_excluded(): void
    {
        $politician = Politician::factory()->create([
            'state'             => 'TX',
            'governance_level'  => 'State',
            'political_office'  => 'Attorney General',
            'term_status'       => 'seated',
            'is_active'         => true,
            'is_running_candidate' => false,
        ]);

        foreach (range(1, 5) as $i) {
            $topic = PoliticianTopic::create([
                'name'             => "Topic {$i}",
                'slug'             => "topic-{$i}",
                'icon'             => '🏅',
                'sort_order'       => $i,
                'is_active'        => true,
                'voter_selectable' => true,
                'auto_earned_only' => false,
            ]);
            $politician->addBadge($topic->id, 'self_declared');
        }

        $privateTopic = PoliticianTopic::create([
            'name'             => 'Hidden Topic',
            'slug'             => 'hidden-topic',
            'icon'             => '🙈',
            'sort_order'       => 99,
            'is_active'        => true,
            'voter_selectable' => true,
            'auto_earned_only' => false,
        ]);
        $badge = $politician->addBadge($privateTopic->id, 'self_declared');
        $badge->update(['is_public' => false]);

        $response = $this->getJson('/api/v1/map/state-candidates?state=TX');

        $response->assertOk();

        $candidates = $response->json('offices.0.candidates');
        $candidate = collect($candidates)->firstWhere('full_name', $politician->full_name);

        $this->assertNotNull($candidate);
        $this->assertCount(4, $candidate['badges']);
        $this->assertNotContains('Hidden Topic', array_column($candidate['badges'], 'name'));
    }

    public function test_scraped_candidates_without_a_platform_profile_have_empty_badges(): void
    {
        \App\Models\ElectionCandidateRecord::create([
            'source'                 => 'ballotpedia',
            'external_candidate_id'  => 'jane-scraped-candidate-ny',
            'full_name'              => 'Jane Scraped Candidate',
            'political_office'       => 'Secretary of State',
            'party_affiliation'      => 'Independent',
            'state'                  => 'NY',
            'governance_level'       => 'state',
            'election_date'          => now()->addMonths(6)->toDateString(),
            'payload'                => ['status' => 'running'],
        ]);

        $response = $this->getJson('/api/v1/map/state-candidates?state=NY');

        $response->assertOk();

        $candidates = $response->json('offices.0.candidates');
        $candidate = collect($candidates)->firstWhere('full_name', 'Jane Scraped Candidate');

        $this->assertNotNull($candidate);
        $this->assertSame([], $candidate['badges']);
    }
}
