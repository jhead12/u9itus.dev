<?php

namespace Tests\Feature\Api;

use App\Models\Citizen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /api/v1/map/business-search — live typeahead powering the "Local
 * Businesses" group in the map search palette. Mirrors
 * MapPoliticianSearchTest's coverage shape.
 */
class MapBusinessSearchTest extends TestCase
{
    use RefreshDatabase;

    private function mappableCitizen(array $overrides = []): Citizen
    {
        return Citizen::factory()->create(array_merge([
            'show_on_map' => true,
            'is_active' => true,
            'latitude' => 37.7749,
            'longitude' => -122.4194,
        ], $overrides));
    }

    public function test_returns_matching_opted_in_businesses(): void
    {
        $match = $this->mappableCitizen(['business_name' => 'Golden Gate Bakery']);
        $other = $this->mappableCitizen(['business_name' => 'Chicago Hardware Co']);

        $response = $this->getJson('/api/v1/map/business-search?q=golden');

        $response->assertOk();
        $names = array_column($response->json('results'), 'name');

        $this->assertContains('Golden Gate Bakery', $names);
        $this->assertNotContains('Chicago Hardware Co', $names);
    }

    public function test_excludes_businesses_not_opted_in_to_the_map(): void
    {
        $this->mappableCitizen(['business_name' => 'Private Bakery', 'show_on_map' => false]);

        $response = $this->getJson('/api/v1/map/business-search?q=bakery');

        $response->assertOk();
        $this->assertSame([], $response->json('results'));
    }

    public function test_excludes_businesses_without_coordinates(): void
    {
        $this->mappableCitizen(['business_name' => 'Ungeocoded Bakery', 'latitude' => null, 'longitude' => null]);

        $response = $this->getJson('/api/v1/map/business-search?q=bakery');

        $response->assertOk();
        $this->assertSame([], $response->json('results'));
    }

    public function test_falls_back_to_full_name_when_business_name_is_blank(): void
    {
        $this->mappableCitizen(['business_name' => null, 'full_name' => 'Jordan Baker']);

        $row = $this->getJson('/api/v1/map/business-search?q=jordan')->json('results.0');

        $this->assertSame('Jordan Baker', $row['name']);
    }

    public function test_returns_empty_for_queries_shorter_than_two_chars(): void
    {
        $this->mappableCitizen(['business_name' => 'Woody Deli']);

        $this->getJson('/api/v1/map/business-search?q=W')
            ->assertOk()
            ->assertJson(['results' => []]);
    }

    public function test_payload_shape_includes_map_and_display_fields(): void
    {
        $this->mappableCitizen([
            'business_name' => 'Golden Gate Bakery',
            'business_category' => 'food',
            'address_line_1' => '1 Market St',
            'city' => 'San Francisco',
            'state' => 'CA',
            'zip' => '94102',
            'verified_at' => now(),
        ]);

        $row = $this->getJson('/api/v1/map/business-search?q=golden')->json('results.0');

        $this->assertSame('Golden Gate Bakery', $row['name']);
        $this->assertSame('food', $row['category']);
        $this->assertSame('1 Market St, San Francisco, CA, 94102', $row['address']);
        $this->assertSame('CA', $row['state']);
        $this->assertSame(37.7749, $row['lat']);
        $this->assertSame(-122.4194, $row['lng']);
        $this->assertTrue($row['verified']);
    }

    public function test_prefix_matches_rank_above_substring_matches(): void
    {
        $this->mappableCitizen(['business_name' => 'Golden Gate Bakery']);
        $this->mappableCitizen(['business_name' => 'Old Golden Diner']);

        $names = array_column(
            $this->getJson('/api/v1/map/business-search?q=Golden')->json('results'),
            'name'
        );

        $this->assertSame(['Golden Gate Bakery', 'Old Golden Diner'], $names);
    }
}
