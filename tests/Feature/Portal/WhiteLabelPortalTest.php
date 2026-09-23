<?php

use App\Models\BallotMeasure;
use App\Models\Organization;
use App\Models\Politician;
use App\Models\User;
use App\Services\PortalDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeOrgOwner(): User
{
    return User::factory()->create(['platform' => 'standalone']);
}

function makeOrganization(array $attrs = []): Organization
{
    return Organization::create(array_merge([
        'name' => 'Test Union Local 42',
        'slug' => 'test-union-local-42-'.uniqid(),
        'org_type' => 'union',
        'is_active' => true,
    ], $attrs));
}

// ── Authorization ─────────────────────────────────────────────────────────

test('guest is redirected from the portal builder', function () {
    $org = makeOrganization();

    $this->get(route('portal.builder.edit', $org))->assertRedirect();
});

test('a user who does not own the organization cannot save its portal', function () {
    $owner = makeOrgOwner();
    $stranger = makeOrgOwner();
    $org = makeOrganization(['user_id' => $owner->id]);

    $this->actingAs($stranger)
        ->putJson(route('portal.builder.update', $org), ['portal_layout' => ['content' => [], 'root' => []]])
        ->assertForbidden();
});

test('the owning user can save its portal layout', function () {
    $owner = makeOrgOwner();
    $org = makeOrganization(['user_id' => $owner->id]);

    $this->actingAs($owner)
        ->putJson(route('portal.builder.update', $org), [
            'portal_layout' => ['content' => [['type' => 'Hero', 'props' => ['orgName' => 'Test Union']]], 'root' => []],
        ])
        ->assertOk()
        ->assertJson(['saved' => true]);

    expect($org->fresh()->portal_layout['content'][0]['type'])->toBe('Hero');
});

test('clearing the state field via the settings form does not 422 and actually clears it', function () {
    $owner = makeOrgOwner();
    $org = makeOrganization(['user_id' => $owner->id, 'target_state' => 'CA']);

    // Mimics the plain Blade settings form, which always submits
    // target_state (possibly "") rather than omitting it like the Puck
    // editor's JSON autosave does.
    $this->actingAs($owner)
        ->from(route('portal.builder.edit', $org))
        ->post(route('portal.builder.update', $org), [
            '_method' => 'PUT',
            'target_state' => '',
            'portal_published' => '0',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('portal.builder.edit', $org));

    expect($org->fresh()->target_state)->toBeNull();
});

// ── Public render ────────────────────────────────────────────────────────

test('an unpublished portal 404s for the public', function () {
    $org = makeOrganization(['portal_published' => false]);

    $this->get(route('portal.show', $org))->assertNotFound();
});

test('a published portal renders for the public', function () {
    $org = makeOrganization(['portal_published' => true]);

    $this->get(route('portal.show', $org))->assertOk();
});

// ── Candidate/measure scoping (PortalDataService) ───────────────────────────

test('portal data is scoped to the organization\'s target state and district', function () {
    $org = makeOrganization(['target_state' => 'CA', 'target_district' => '12']);

    $inDistrict = Politician::factory()->create([
        'state' => 'CA', 'district' => '12', 'page_published' => true, 'is_active' => true,
    ]);
    $wrongDistrict = Politician::factory()->create([
        'state' => 'CA', 'district' => '30', 'page_published' => true, 'is_active' => true,
    ]);
    $wrongState = Politician::factory()->create([
        'state' => 'NY', 'district' => '12', 'page_published' => true, 'is_active' => true,
    ]);

    $data = app(PortalDataService::class)->forOrganization($org);
    $ids = $data['candidates']->pluck('id');

    expect($ids)->toContain($inDistrict->id)
        ->not->toContain($wrongDistrict->id)
        ->not->toContain($wrongState->id);
});

// ── 501(c)(3) endorsement gate ───────────────────────────────────────────

test('a 501(c)(3) nonprofit cannot endorse a candidate', function () {
    $owner = makeOrgOwner();
    $org = makeOrganization(['user_id' => $owner->id, 'org_type' => 'c3_nonprofit', 'target_state' => 'CA']);
    $politician = Politician::factory()->create(['state' => 'CA', 'page_published' => true, 'is_active' => true]);

    $this->actingAs($owner)
        ->postJson(route('portal.builder.endorsements.store', $org), [
            'politician_id' => $politician->id,
            'position' => 'endorse',
            'label' => 'Endorsed',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('politician_id');
});

test('a 501(c)(3) nonprofit can take a ballot-measure position', function () {
    $owner = makeOrgOwner();
    $org = makeOrganization(['user_id' => $owner->id, 'org_type' => 'c3_nonprofit', 'target_state' => 'CA']);
    $measure = BallotMeasure::create([
        'state' => 'CA', 'level' => 'state', 'title' => 'Prop 1',
        'summary' => 'A test measure.', 'yes_meaning' => 'Yes means yes.', 'no_meaning' => 'No means no.',
        'status' => 'upcoming',
    ]);

    $this->actingAs($owner)
        ->postJson(route('portal.builder.endorsements.store', $org), [
            'ballot_measure_id' => $measure->id,
            'position' => 'endorse',
            'label' => 'Vote YES on Prop 1',
        ])
        ->assertCreated();
});

test('a union can endorse a candidate', function () {
    $owner = makeOrgOwner();
    $org = makeOrganization(['user_id' => $owner->id, 'org_type' => 'union', 'target_state' => 'CA']);
    $politician = Politician::factory()->create(['state' => 'CA', 'page_published' => true, 'is_active' => true]);

    $this->actingAs($owner)
        ->postJson(route('portal.builder.endorsements.store', $org), [
            'politician_id' => $politician->id,
            'position' => 'endorse',
            'label' => 'Endorsed by Our Union',
        ])
        ->assertCreated();
});
