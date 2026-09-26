<?php

use App\Models\Politician;

test('comparison page and qr are public and contain no account requirement', function () {
    $this->get('/compare')->assertOk()->assertSee('Understand your choices.')->assertSee('comparison-search');
    $response = $this->get('/compare/qr?'.http_build_query(['query' => 'state=CA&district=CA-03&selected=profile:1']));
    $response->assertOk()->assertHeader('Content-Type', 'image/svg+xml')->assertSee('<svg', false);
    $this->getJson('/compare/qr?'.http_build_query(['query' => str_repeat('x', 2001)]))->assertUnprocessable();
});

test('candidate search filters state before applying its limit', function () {
    Politician::factory()->create(['full_name' => 'Jamie Carter', 'state' => 'CA', 'page_published' => true, 'is_active' => true]);
    Politician::factory()->create(['full_name' => 'Jamie Morgan', 'state' => 'TX', 'page_published' => true, 'is_active' => true]);
    $this->getJson('/api/v1/map/politician-search?q=Jamie&state=CA')->assertOk()
        ->assertJsonCount(1, 'results')->assertJsonPath('results.0.state', 'CA');
    $this->getJson('/api/v1/map/politician-search?q=Jamie&state=XX')->assertUnprocessable();
});

test('name search matches every word across name, office, party, and state like the reporter picker', function () {
    Politician::factory()->create(['full_name' => 'Jamie Carter', 'state' => 'CA', 'political_office' => 'Governor', 'party_affiliation' => 'Democratic', 'page_published' => true, 'is_active' => true]);
    Politician::factory()->create(['full_name' => 'Jamie Morgan', 'state' => 'CA', 'political_office' => 'Mayor', 'party_affiliation' => 'Republican', 'page_published' => true, 'is_active' => true]);
    $this->getJson('/api/v1/map/politician-search?q=jamie+governor')->assertOk()
        ->assertJsonCount(1, 'results')->assertJsonPath('results.0.full_name', 'Jamie Carter');
    $this->getJson('/api/v1/map/politician-search?q=republican+ca')->assertOk()
        ->assertJsonCount(1, 'results')->assertJsonPath('results.0.full_name', 'Jamie Morgan');
    $this->getJson('/api/v1/map/politician-search?q=jamie&limit=26')->assertUnprocessable();
});

test('district search matches exact district variants without leaking other seats or private profiles', function () {
    foreach ([['Alex Rivera', 'CA', 'CA-03', true], ['Jamie Carter', 'CA', '3', true], ['Robin Nelson', 'CA', 'CA-30', true], ['Morgan Parker', 'TX', 'TX-03', true], ['Sam Taylor', 'CA', 'CA-03', false]] as [$name, $state, $district, $published]) {
        Politician::factory()->create(['full_name' => $name, 'state' => $state, 'district' => $district, 'political_office' => 'U.S. Representative', 'page_published' => $published, 'is_active' => true]);
    }
    foreach (['3', '03', 'CA-03', 'District 3', 'CD-03', 'CD 3'] as $q) {
        $this->getJson('/api/v1/map/politician-search?'.http_build_query(['q' => $q, 'state' => 'CA', 'mode' => 'district']))
            ->assertOk()->assertJsonCount(2, 'results')->assertJsonPath('district_label', 'CA-03');
    }
    $this->getJson('/api/v1/map/politician-search?q=TX-03&state=CA&mode=district')
        ->assertOk()->assertJsonCount(0, 'results')->assertJsonPath('message', 'The district and selected state do not match.');
});

test('address search uses the reporters district lookup and only exposes published candidates', function () {
    $this->mock(\App\Services\DistrictLookupService::class)->shouldReceive('lookup')->once()
        ->with('101 W Abram St, Arlington, TX')->andReturn(['state' => 'TX', 'district_number' => '6']);
    Politician::factory()->create(['full_name' => 'Alex Rivera', 'state' => 'TX', 'district' => 'TX-06', 'political_office' => 'U.S. Representative', 'page_published' => true, 'is_active' => true]);
    $this->getJson('/api/v1/map/politician-search?'.http_build_query(['q' => '101 W Abram St, Arlington', 'state' => 'TX', 'mode' => 'address']))
        ->assertOk()->assertJsonPath('district_label', 'TX-06')->assertJsonCount(1, 'results');
});

test('address search gives actionable feedback when lookup fails', function () {
    $this->mock(\App\Services\DistrictLookupService::class)->shouldReceive('lookup')->once()->andReturn(null);
    $this->getJson('/api/v1/map/politician-search?q=Unresolved&state=CA&mode=address')->assertOk()
        ->assertJsonCount(0, 'results')->assertJsonPath('message', 'Could not resolve that address. Try a full street address; a city can span several districts.');
});

test('district search supports at-large seats and excludes state legislative seats', function () {
    foreach ([['Alex Rivera', 'U.S. Representative'], ['Jamie Carter', 'State Representative']] as [$name, $office]) {
        Politician::factory()->create(['full_name' => $name, 'state' => 'AK', 'district' => 'AK-AL', 'political_office' => $office, 'page_published' => true, 'is_active' => true]);
    }
    $this->getJson('/api/v1/map/politician-search?q=AL&state=AK&mode=district')->assertOk()
        ->assertJsonCount(1, 'results')->assertJsonPath('district_label', 'AK-AL');
});

test('address lookup with a missing district does not become an at-large search', function () {
    $this->mock(\App\Services\DistrictLookupService::class)->shouldReceive('lookup')->once()
        ->andReturn(['state' => 'AK', 'district_number' => '']);
    $this->getJson('/api/v1/map/politician-search?q=Unresolved&state=AK&mode=address')
        ->assertOk()->assertJsonCount(0, 'results')->assertJsonPath('message', 'Could not resolve that address. Try a full street address; a city can span several districts.');
});

test('office glossary describes compared offices and keeps the map statewide wording', function (string $office, ?string $slug) {
    expect(\App\Support\OfficeGlossary::for($office)['slug'] ?? null)->toBe($slug);
})->with([
    ['U.S. Representative', 'us-representative'], ['United States House', 'us-representative'], ['U.S. Senate', 'us-senator'],
    ['Governor', 'governor'], ['Lt. Governor', 'lieutenant-governor'], ['Comptroller', 'state-controller'],
    ['City Council Member District 3', 'city-council-member'], ['Superior Court Judge Seat 2', 'trial-court-judge'], ['Mayor', 'mayor'],
    ['City Treasurer', null], ['State Representative', null],
]);

test('glossary page is public and anchors every office; comparisons link to it', function () {
    $response = $this->get('/compare/glossary')->assertOk()->assertSee('What does each office do?')->assertSee('very different powers from place to place');
    foreach (array_keys(\App\Support\OfficeGlossary::ENTRIES) as $slug) $response->assertSee('id="'.$slug.'"', false);
    expect(\App\Support\OfficeGlossary::statewideRoles()['Governor'])->toStartWith('The Governor is the chief executive of the state.')
        ->and(\App\Support\OfficeGlossary::statewideRoles()['Governor'])->not->toContain('all executive')
        ->and(array_keys(\App\Support\OfficeGlossary::statewideRoles()))->toBe(['U.S. Senators', 'Governor', 'Lieutenant Governor', 'Attorney General', 'State Treasurer', 'State Controller', 'Secretary of State',
            'Superintendent of Public Instruction', 'Insurance Commissioner', 'State Auditor', 'Agriculture Commissioner', 'Labor Commissioner',
            'Land Commissioner', 'Board of Equalization', 'State Legislature', 'Other Statewide']);

    $p = Politician::factory()->create(['full_name' => 'Jamie Carter', 'state' => 'CA', 'district' => 'CA-03', 'political_office' => 'U.S. Representative', 'governance_level' => 'Federal', 'term_status' => 'running', 'is_running_candidate' => true, 'page_published' => true, 'is_active' => true]);
    $this->getJson('/api/v1/map/candidate-comparison?'.http_build_query(['state' => 'CA', 'id' => $p->id, 'full_name' => $p->full_name, 'context' => 'research']))
        ->assertOk()->assertJsonPath('seat.role.slug', 'us-representative')->assertJsonPath('seat.role.scope', 'general')->assertJsonPath('seat.role.place', 'CA')
        ->assertJsonPath('seat.role.glossary_url', url('/compare/glossary').'#us-representative');
});

test('sourced city notes replace state notes and the general description only for their own place', function () {
    $note = fn (string $text) => ['description' => $text, 'source_label' => 'Official charter', 'source_url' => 'https://example.gov/charter', 'reviewed_at' => '2026-09-24'];
    config(['office_glossary.overrides' => [
        'NY' => ['cities' => ['New York' => ['mayor' => $note('Runs city agencies and proposes the budget.')]]],
        'TX' => ['lieutenant-governor' => $note('Presides over the Texas Senate and controls its committee assignments.')],
    ]]);
    $nyc = \App\Support\OfficeGlossary::forPlace('Mayor', 'NY', 'new york');
    expect($nyc['scope'])->toBe('city')->and($nyc['place'])->toBe('New York, NY')->and($nyc['description'])->toStartWith('Runs city agencies')
        ->and($nyc['source_url'])->toBe('https://example.gov/charter');
    $oakland = \App\Support\OfficeGlossary::forPlace('Mayor', 'CA', 'Oakland');
    expect($oakland['scope'])->toBe('general')->and($oakland['place'])->toBe('Oakland, CA')->and($oakland['source_url'])->toBeNull();
    expect(\App\Support\OfficeGlossary::forPlace('Lt. Governor', 'TX')['scope'])->toBe('state')
        ->and(\App\Support\OfficeGlossary::forPlace('Lt. Governor', 'CA')['scope'])->toBe('general');
    $this->get('/compare/glossary')->assertOk()->assertSee('In New York, NY')->assertSee('In TX')
        ->assertSee('Reviewed September 24, 2026')->assertSee('https://example.gov/charter', false);
});

test('every configured place note cites an official https source and a review date', function () {
    expect(config('office_glossary.overrides'))->toBeArray();
    foreach (config('office_glossary.overrides') as $state => $entries) {
        expect(\App\Support\PoliticianDataRules::ALLOWED_STATES)->toContain($state);
        $notes = collect($entries)->except('cities')->all();
        foreach ($entries['cities'] ?? [] as $cityNotes) $notes = [...$notes, ...array_values($cityNotes)];
        foreach ($notes as $slug => $note) {
            expect($note['source_url'] ?? '')->toStartWith('https://')
                ->and($note['source_label'] ?? '')->not->toBe('')
                ->and($note['description'] ?? '')->not->toBe('')
                ->and(strtotime($note['reviewed_at'] ?? ''))->not->toBeFalse();
        }
        foreach (collect($entries)->except('cities')->keys() as $slug) expect(\App\Support\OfficeGlossary::ENTRIES)->toHaveKey($slug);
    }
});
