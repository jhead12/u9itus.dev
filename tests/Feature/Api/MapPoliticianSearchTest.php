<?php

namespace Tests\Feature\Api;

use App\Models\Politician;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /api/v1/map/politician-search — live typeahead powering the "Politicians"
 * group in the map search palette. Added in a99866f8 alongside the viral-moment
 * work; covered here because it merged without a dedicated test.
 */
class MapPoliticianSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_matching_published_active_politicians(): void
    {
        $match = Politician::factory()->create([
            'full_name' => 'Elizabeth Warren',
            'state' => 'MA',
            'political_office' => 'US Senator',
            'party_affiliation' => 'Democrat',
            'page_published' => true,
            'is_active' => true,
            'slug' => 'a3f9b-elizabeth-warren',
        ]);

        $other = Politician::factory()->create([
            'full_name' => 'Ed Markey',
            'state' => 'MA',
            'page_published' => true,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/map/politician-search?q=warren');

        $response->assertOk();

        $names = array_column($response->json('results'), 'full_name');

        $this->assertContains('Elizabeth Warren', $names);
        $this->assertNotContains('Ed Markey', $names);
    }

    public function test_excludes_unpublished_and_inactive_profiles(): void
    {
        Politician::factory()->create([
            'full_name' => 'Warren Unpublished',
            'page_published' => false,
            'is_active' => true,
        ]);
        Politician::factory()->create([
            'full_name' => 'Warren Inactive',
            'page_published' => true,
            'is_active' => false,
        ]);

        $response = $this->getJson('/api/v1/map/politician-search?q=Warren');

        $response->assertOk();
        $this->assertSame([], $response->json('results'));
    }

    public function test_returns_empty_for_queries_shorter_than_two_chars(): void
    {
        Politician::factory()->create([
            'full_name' => 'Woody Allen',
            'page_published' => true,
            'is_active' => true,
        ]);

        $this->getJson('/api/v1/map/politician-search?q=W')
            ->assertOk()
            ->assertJson(['results' => []]);
    }

    public function test_payload_shape_includes_profile_and_display_fields(): void
    {
        Politician::factory()->create([
            'full_name' => 'Elizabeth Warren',
            'state' => 'MA',
            'political_office' => 'US Senator',
            'party_affiliation' => 'Democrat',
            'page_published' => true,
            'is_active' => true,
            'slug' => 'a3f9b-elizabeth-warren',
            'profile_photo_url' => 'https://cdn.example.com/ew.jpg',
            'ballotpedia_id' => 'Elizabeth_Warren',
            'verified_official' => true,
        ]);

        $row = $this->getJson('/api/v1/map/politician-search?q=warren')->json('results.0');

        $this->assertSame('Elizabeth Warren', $row['full_name']);
        $this->assertSame('US Senator', $row['office']);
        $this->assertSame('MA', $row['state']);
        $this->assertTrue($row['verified']);
        $this->assertSame('https://cdn.example.com/ew.jpg', $row['photo']);
        $this->assertSame('https://ballotpedia.org/Elizabeth_Warren', $row['ballotpedia_url']);
        $this->assertStringEndsWith('/p/a3f9b-elizabeth-warren', $row['profile_url']);
        $this->assertNotEmpty($row['bio_excerpt']);
    }

    public function test_prefix_matches_rank_above_substring_matches(): void
    {
        // "Warren Davidson" starts with the query → rank 0.
        Politician::factory()->create([
            'full_name' => 'Warren Davidson',
            'page_published' => true,
            'is_active' => true,
        ]);
        // "Dee Warren" only contains the query → rank 1.
        Politician::factory()->create([
            'full_name' => 'Dee Warren',
            'page_published' => true,
            'is_active' => true,
        ]);

        $names = array_column(
            $this->getJson('/api/v1/map/politician-search?q=Warren')->json('results'),
            'full_name'
        );

        $this->assertSame(['Warren Davidson', 'Dee Warren'], $names);
    }

    public function test_profile_photo_relative_path_is_absolutized(): void
    {
        Politician::factory()->create([
            'full_name' => 'Warren Rel',
            'page_published' => true,
            'is_active' => true,
            'profile_photo_url' => 'storage/photos/warren.png',
        ]);

        $photo = $this->getJson('/api/v1/map/politician-search?q=Warren Rel')->json('results.0.photo');

        $this->assertNotNull($photo);
        $this->assertTrue(str_starts_with($photo, 'http://') || str_starts_with($photo, 'https://'),
            "Relative photo path should be absolutized via url(). Got: {$photo}");
    }
}