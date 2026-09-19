<?php

namespace Tests\Feature\Web;

use App\Models\Politician;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HomepageFeaturedCandidatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['u9itus.fraud.ipinfo_api_key' => 'test-key']);
    }

    private function candidate(string $name, string $state, string $termStatus = 'running'): Politician
    {
        return Politician::factory()->create([
            'full_name' => $name,
            'state' => $state,
            'term_status' => $termStatus,
            'page_published' => true,
            'slug' => str($name)->slug()->toString(),
        ]);
    }

    private function fakeRegion(string $region): void
    {
        Http::fake(['ipinfo.io/*' => Http::response(['region' => $region])]);
    }

    public function test_full_region_name_matches_state_codes_and_is_labelled_local(): void
    {
        $this->fakeRegion('California');
        $this->candidate('Casey California', 'CA');
        $this->candidate('Owen Ohio', 'OH');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Casey California');
        $response->assertSee('Owen Ohio');
        $response->assertSee('1 from CA', false);
        $response->assertSee('Near you');
        $response->assertSee('Featured candidates');
        $response->assertDontSee('Featured nationwide');
    }

    public function test_no_local_candidates_falls_back_to_nationwide_label(): void
    {
        $this->fakeRegion('California');
        $this->candidate('Owen Ohio', 'OH');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Owen Ohio');
        $response->assertSee('Featured nationwide');
        $response->assertDontSee('Near you');
    }

    public function test_failed_geo_lookup_uses_nationwide_label(): void
    {
        Http::fake(['ipinfo.io/*' => Http::response([], 500)]);
        $this->candidate('Owen Ohio', 'OH');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Featured nationwide');
        $response->assertDontSee('Near you');
    }

    public function test_status_chip_reflects_term_status(): void
    {
        Http::fake(['ipinfo.io/*' => Http::response([], 500)]);
        $this->candidate('Sam Seated', 'OH', 'seated');
        $this->candidate('Rae Running', 'MA', 'running');

        $response = $this->get('/');

        $response->assertSee('Incumbent');
        $response->assertSee('Candidate');
    }

    public function test_rewards_and_civic_identity_sections_are_hidden_by_default(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('id="revenue"', false);
        $response->assertDontSee('id="civic-identity"', false);
        $response->assertSee('Find My District');
    }

    public function test_rewards_and_civic_identity_sections_can_be_enabled(): void
    {
        config([
            'platform.home.show_rewards_section' => true,
            'platform.home.show_civic_identity_section' => true,
        ]);

        $response = $this->get('/');

        $response->assertSee('id="revenue"', false);
        $response->assertSee('id="civic-identity"', false);
    }
}
