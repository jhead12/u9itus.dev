<?php

namespace Tests\Unit\Models;

use App\Models\Organization;
use Database\Seeders\OrganizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_aipac_organization(): void
    {
        (new OrganizationSeeder())->run();

        $this->assertDatabaseCount('organizations', 1);
        $this->assertDatabaseHas('organizations', [
            'slug' => 'aipac',
            'pac_group_key' => 'aipac_pro_israel',
            'verification_status' => 'unverified',
        ]);
    }

    public function test_seeder_is_idempotent(): void
    {
        (new OrganizationSeeder())->run();
        (new OrganizationSeeder())->run();

        $this->assertDatabaseCount('organizations', 1);
    }

    public function test_badges_group_and_dedupe_multiple_matches_in_same_group(): void
    {
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

        $badges = Organization::badgesForPacAffiliations([
            ['group' => 'aipac_pro_israel', 'label' => 'AIPAC / Pro-Israel', 'matched_name' => 'AIPAC', 'total' => '$50,000'],
            ['group' => 'aipac_pro_israel', 'label' => 'AIPAC / Pro-Israel', 'matched_name' => 'NORPAC', 'total' => '$10,000'],
        ]);

        $this->assertCount(1, $badges);
        $this->assertSame('aipac_pro_israel', $badges[0]['group']);
        $this->assertSame('AIPAC', $badges[0]['label']); // org name wins over raw config label
        $this->assertSame(['AIPAC', 'NORPAC'], $badges[0]['matched_names']);
        $this->assertSame('aipac', $badges[0]['org_slug']);
        $this->assertSame('https://example.com/aipac.png', $badges[0]['logo_url']);
    }

    public function test_badges_fall_back_to_raw_label_when_no_organization_row_exists(): void
    {
        // Simulates a historical snapshot row written before any Organization existed.
        $badges = Organization::badgesForPacAffiliations([
            ['group' => 'aipac_pro_israel', 'label' => 'AIPAC / Pro-Israel', 'matched_name' => 'AIPAC', 'total' => '$50,000'],
        ]);

        $this->assertCount(1, $badges);
        $this->assertSame('AIPAC / Pro-Israel', $badges[0]['label']);
        $this->assertNull($badges[0]['org_slug']);
        $this->assertNull($badges[0]['logo_url']);
    }

    public function test_badges_returns_empty_array_for_empty_input(): void
    {
        $this->assertSame([], Organization::badgesForPacAffiliations([]));
    }
}
