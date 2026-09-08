<?php

use App\Models\Committee;
use App\Models\CommitteeProfile;
use App\Models\Politician;

function makeCommitteeWithProfile(array $committee = [], array $profile = []): Committee
{
    $c = Committee::create(array_merge([
        'fec_committee_id' => 'C' . str_pad((string) random_int(1, 99999999), 8, '0', STR_PAD_LEFT),
        'name' => 'Test Victory Fund',
        'first_seen_at' => now(),
        'last_seen_at' => now(),
    ], $committee));

    CommitteeProfile::create(array_merge([
        'committee_id' => $c->id,
        'fec_committee_id' => $c->fec_committee_id,
        'committee_type' => 'O',
        'committee_type_full' => 'Super PAC (Independent Expenditure-Only)',
        'is_super_pac' => true,
        'party' => 'DEM',
        'state' => 'DC',
        'cycle' => 2026,
        'total_receipts' => 5_000_000,
        'total_disbursements' => 4_200_000,
        'independent_expenditures' => 3_900_000,
        'top_donors' => [['name' => 'Big Donor LLC', 'total' => '$1,000,000']],
        'spending_by_race' => [[
            'politician_id' => null, 'politician_slug' => null,
            'candidate_name' => 'JANE DOE', 'office' => 'Senate', 'state' => 'OH', 'district' => '00',
            'support' => 900_000, 'oppose' => 0,
        ]],
        'recent_expenditures' => [[
            'candidate_name' => 'JANE DOE', 'support_oppose' => 'S',
            'amount' => 12_000, 'date' => '2026-09-01', 'purpose' => 'MEDIA BUY',
        ]],
        'enriched_at' => now(),
    ], $profile));

    return $c->fresh('profile');
}

test('pac directory lists enriched committees', function () {
    makeCommitteeWithProfile(['name' => 'Sunrise Forward PAC']);

    $this->get('/pacs')
        ->assertOk()
        ->assertSee('Sunrise Forward PAC')
        ->assertSee('PAC &amp; Committee Directory', false);
});

test('a committee without an enriched profile is not listed', function () {
    Committee::create([
        'fec_committee_id' => 'C99999999',
        'name' => 'Unenriched Committee',
        'first_seen_at' => now(),
        'last_seen_at' => now(),
    ]);

    $this->get('/pacs')->assertOk()->assertDontSee('Unenriched Committee');
});

test('pac detail page renders financials, races and donors', function () {
    $c = makeCommitteeWithProfile(['name' => 'Detail Test Fund']);

    $this->get('/pacs/' . $c->publicSlug())
        ->assertOk()
        ->assertSee('Detail Test Fund')
        ->assertSee('Super PAC')
        ->assertSee('Spending in these races')
        ->assertSee('JANE DOE')
        ->assertSee('Big Donor LLC')
        ->assertSee('MEDIA BUY');
});

test('bare FEC id redirects to the slugged url', function () {
    $c = makeCommitteeWithProfile();

    $this->get('/pacs/' . $c->fec_committee_id)
        ->assertRedirect('/pacs/' . $c->publicSlug());
});

test('unknown committee 404s', function () {
    $this->get('/pacs/C00000000')->assertNotFound();
});

test('spending_by_race links to a politician profile when resolved', function () {
    $pol = Politician::factory()->create([
        'page_published' => true,
        'is_active' => true,
        'full_name' => 'Linked Candidate',
    ]);

    $c = makeCommitteeWithProfile([], [
        'spending_by_race' => [[
            'politician_id' => $pol->id,
            'politician_slug' => $pol->slug,
            'candidate_name' => 'Linked Candidate',
            'office' => 'House', 'state' => 'TX', 'district' => '07',
            'support' => 0, 'oppose' => 250_000,
        ]],
    ]);

    $this->get('/pacs/' . $c->publicSlug())
        ->assertOk()
        ->assertSee('/p/' . $pol->slug, false)
        ->assertSee('Linked Candidate');
});
