<?php

namespace Tests\Feature\Api;

use App\Models\Organization;
use App\Models\Politician;
use App\Models\PoliticianDonorSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MapCandidateEconomyTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_full_data_for_enriched_politician_with_matching_organization(): void
    {
        $politician = Politician::factory()->create(['is_active' => true]);

        Organization::create([
            'name' => 'AIPAC',
            'slug' => 'aipac',
            'org_type' => 'pac',
            'pac_group_key' => 'aipac_pro_israel',
            'logo_url' => 'https://example.com/aipac.png',
            'website_url' => 'https://www.aipac.org',
            'verification_status' => 'unverified',
            'is_active' => true,
        ]);

        PoliticianDonorSnapshot::create([
            'politician_id' => $politician->id,
            'top_industries' => [
                ['name' => 'Finance & Banking', 'total' => '$1,000,000'],
                ['name' => 'Technology', 'total' => '$500,000'],
            ],
            'top_contributors' => [
                ['name' => 'Acme Corp Employees', 'total' => '$52,000'],
            ],
            'fec_summary' => [
                'receipts' => '$3,200,000',
                'disbursements' => '$2,100,000',
                'cash_on_hand' => '$1,100,000',
                'debt' => '$0',
            ],
            'pac_affiliations' => [
                ['group' => 'aipac_pro_israel', 'label' => 'AIPAC / Pro-Israel', 'matched_name' => 'AIPAC', 'total' => '$50,000'],
            ],
            'opensecrets_source_url' => 'https://www.opensecrets.org/profiles/x',
            'fec_source_url' => 'https://www.fec.gov/data/candidate/x',
            'election_cycle' => 2026,
            'enriched_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/map/candidate-economy?' . http_build_query(['slug' => $politician->slug]));

        $response->assertOk();
        $response->assertJson([
            'has_data' => true,
            'reason' => null,
            'election_cycle' => 2026,
        ]);

        $response->assertJsonPath('top_industries.0.name', 'Finance & Banking');
        $response->assertJsonPath('top_industries.0.pct', 100);
        $response->assertJsonPath('top_industries.1.pct', 50);
        $response->assertJsonPath('pac_affiliations.0.group', 'aipac_pro_israel');
        $response->assertJsonPath('pac_affiliations.0.logo_url', 'https://example.com/aipac.png');
        $response->assertJsonPath('pac_affiliations.0.org_slug', 'aipac');
        $response->assertJsonPath('sources.fec_url', 'https://www.fec.gov/data/candidate/x');
    }

    public function test_returns_not_yet_enriched_when_politician_has_no_snapshot(): void
    {
        $politician = Politician::factory()->create(['is_active' => true]);

        $response = $this->getJson('/api/v1/map/candidate-economy?' . http_build_query(['slug' => $politician->slug]));

        $response->assertOk();
        $response->assertJson([
            'has_data' => false,
            'reason' => 'not_yet_enriched',
        ]);
    }

    public function test_returns_not_yet_enriched_when_snapshot_only_has_a_guessed_link_and_all_null_summary(): void
    {
        // Reproduces the production James Gallagher case: a guessed/unverified
        // OpenSecrets link plus a summary table that never actually parsed.
        // Before the hasOpenSecretsSummary() fix, the all-null summary shape
        // read as "has data" and the map drawer surfaced the broken link.
        $politician = Politician::factory()->create(['is_active' => true]);

        PoliticianDonorSnapshot::create([
            'politician_id' => $politician->id,
            'opensecrets_source_url' => 'https://www.opensecrets.org/profiles/james-gallagher/us_congress/summary',
            'opensecrets_summary' => ['total_raised' => null, 'total_spent' => null, 'cash_on_hand' => null, 'debt' => null],
            'election_cycle' => 2026,
            'enriched_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/map/candidate-economy?' . http_build_query(['slug' => $politician->slug]));

        $response->assertOk();
        $response->assertJson([
            'has_data' => false,
            'reason' => 'not_yet_enriched',
        ]);
    }

    public function test_returns_not_on_platform_when_no_politician_matches(): void
    {
        $response = $this->getJson('/api/v1/map/candidate-economy?' . http_build_query(['full_name' => 'Nobody Real']));

        $response->assertOk();
        $response->assertJson([
            'has_data' => false,
            'reason' => 'not_on_platform',
        ]);
    }

    public function test_pac_affiliation_without_matching_organization_still_renders_with_null_logo(): void
    {
        $politician = Politician::factory()->create(['is_active' => true]);

        PoliticianDonorSnapshot::create([
            'politician_id' => $politician->id,
            'top_industries' => null,
            'top_contributors' => null,
            'fec_summary' => null,
            'outside_spending' => null,
            'pac_affiliations' => [
                ['group' => 'aipac_pro_israel', 'label' => 'AIPAC / Pro-Israel', 'matched_name' => 'AIPAC', 'total' => '$50,000'],
            ],
            'election_cycle' => 2026,
            'enriched_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/map/candidate-economy?' . http_build_query(['slug' => $politician->slug]));

        $response->assertOk();
        $response->assertJsonPath('has_data', true);
        $response->assertJsonPath('pac_affiliations.0.label', 'AIPAC / Pro-Israel');
        $response->assertJsonPath('pac_affiliations.0.logo_url', null);
        $response->assertJsonPath('pac_affiliations.0.org_slug', null);
    }

    public function test_returns_422_when_no_lookup_params_provided(): void
    {
        $response = $this->getJson('/api/v1/map/candidate-economy');

        $response->assertStatus(422);
    }
}
