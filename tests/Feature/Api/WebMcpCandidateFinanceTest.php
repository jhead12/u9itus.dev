<?php

use App\Models\Committee;
use App\Models\Politician;
use App\Models\PoliticianDonorSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Covers the two finance-facing additions to GET /api/v1/mcp/candidates:
 *  - the `funded_by` filter + evidence (`funding_match`)
 *  - committee-ID → name hydration in the dossier's `outside_spending`
 */
function mcpFinancePolitician(array $attrs = []): Politician
{
    return Politician::factory()->create(array_merge([
        'page_published' => true,
        'is_active' => true,
        'slug' => 'slug-'.fake()->unique()->numerify('######'),
        'state' => 'TX',
    ], $attrs));
}

it('filters candidates by funder and cites the matching contributions', function () {
    $funded = mcpFinancePolitician(['full_name' => 'Funded Fran']);
    $unfunded = mcpFinancePolitician(['full_name' => 'Plain Pat']);

    PoliticianDonorSnapshot::create([
        'politician_id' => $funded->id,
        'top_contributors' => [['name' => 'NORPAC', 'total' => 25000]],
        'pac_affiliations' => [[
            'group' => 'aipac_pro_israel',
            'label' => 'AIPAC / Pro-Israel',
            'matched_name' => 'NORPAC',
            'total' => 25000,
        ]],
        'outside_spending' => [[
            'committee_id' => 'C00401224',
            'committee_name' => 'AIPAC PAC',
            'total' => 80000,
            'support_oppose' => 'S',
        ]],
        'enriched_at' => now(),
    ]);

    PoliticianDonorSnapshot::create([
        'politician_id' => $unfunded->id,
        'top_contributors' => [['name' => 'Acme Trial Lawyers', 'total' => 9000]],
        'enriched_at' => now(),
    ]);

    $res = $this->getJson('/api/v1/mcp/candidates?state=TX&funded_by=AIPAC');

    $res->assertOk()
        ->assertJsonPath('total', 1)
        ->assertJsonPath('results.0.full_name', 'Funded Fran');

    $kinds = collect($res->json('results.0.funding_match'))->pluck('kind')->all();
    expect($kinds)->toContain('pac_affiliation')
        ->and($kinds)->toContain('outside_spending')
        ->and($kinds)->toContain('contributor');
});

it('expands a known advocacy group to its aligned PAC name patterns', function () {
    $p = mcpFinancePolitician();

    PoliticianDonorSnapshot::create([
        'politician_id' => $p->id,
        // "norpac" only appears via the aipac_pro_israel pattern list.
        'top_contributors' => [['name' => 'NORPAC', 'total' => 12000]],
        'enriched_at' => now(),
    ]);

    $this->getJson('/api/v1/mcp/candidates?funded_by=AIPAC')
        ->assertOk()
        ->assertJsonPath('total', 1);
});

it('omits funding_match and matches nothing for an unknown funder', function () {
    $p = mcpFinancePolitician();
    PoliticianDonorSnapshot::create([
        'politician_id' => $p->id,
        'top_contributors' => [['name' => 'NORPAC', 'total' => 12000]],
        'enriched_at' => now(),
    ]);

    $this->getJson('/api/v1/mcp/candidates?funded_by=nonesuch-xyz')
        ->assertOk()
        ->assertJsonPath('total', 0);

    $this->getJson('/api/v1/mcp/candidates?state=TX')
        ->assertOk()
        ->assertJsonMissingPath('results.0.funding_match');
});

it('resolves a raw FEC committee id to its registry name in the dossier', function () {
    $p = mcpFinancePolitician();

    PoliticianDonorSnapshot::create([
        'politician_id' => $p->id,
        'outside_spending' => [
            ['committee_id' => 'C00799031', 'committee_name' => 'C00799031', 'total' => 120000, 'support_oppose' => 'S'],
            ['committee_id' => 'C09999999', 'committee_name' => 'C09999999', 'total' => 5000, 'support_oppose' => 'O'],
        ],
        'enriched_at' => now(),
    ]);

    Committee::create([
        'fec_committee_id' => 'C00799031',
        'name' => 'United Democracy Project',
        'name_resolved_at' => now(),
    ]);

    $res = $this->getJson("/api/v1/mcp/candidates/{$p->uuid}")->assertOk();

    $spending = collect($res->json('donor_snapshot.outside_spending'))->keyBy('committee_id');

    expect($spending['C00799031']['committee_name'])->toBe('United Democracy Project')
        ->and($spending['C00799031']['committee']['name_resolved'])->toBeTrue()
        // No registry row — the raw id stays put and is flagged unresolved.
        ->and($spending['C09999999']['committee_name'])->toBe('C09999999')
        ->and($spending['C09999999']['committee']['name_resolved'])->toBeFalse();
});
