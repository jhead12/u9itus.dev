<?php

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

test('portal:create provisions a branded, owned portal the owner can edit', function () {
    $owner = User::factory()->create(['platform' => 'standalone', 'email' => 'director@local42.org']);

    $exitCode = Artisan::call('portal:create', [
        'name' => 'Teachers Union Local 42',
        '--type' => 'union',
        '--state' => 'ca',
        '--district' => '12',
        '--owner' => 'director@local42.org',
        '--logo' => 'https://local42.org/logo.png',
        '--color' => '#1d4ed8',
    ]);

    expect($exitCode)->toBe(0);
    expect(Artisan::output())
        ->toContain('Created portal for Teachers Union Local 42')
        ->toContain('/portal/teachers-union-local-42');

    $org = Organization::where('slug', 'teachers-union-local-42')->firstOrFail();
    expect($org->org_type)->toBe('union')
        ->and($org->target_state)->toBe('CA')
        ->and($org->target_district)->toBe('12')
        ->and($org->user_id)->toBe($owner->id)
        ->and($org->is_active)->toBeTrue()
        ->and($org->portal_published)->toBeFalse();

    $hero = $org->portal_layout['content'][0];
    expect($hero['type'])->toBe('Hero')
        ->and($hero['props']['orgName'])->toBe('Teachers Union Local 42')
        ->and($hero['props']['logoUrl'])->toBe('https://local42.org/logo.png')
        ->and($hero['props']['primaryColor'])->toBe('#1d4ed8');

    $this->actingAs($owner)->get(route('portal.builder.edit', $org))->assertOk();
});

test('portal:create --publish makes the portal publicly visible', function () {
    Artisan::call('portal:create', ['name' => 'Yes on Prop 1', '--type' => 'campaign_coalition', '--publish' => true]);

    $org = Organization::where('slug', 'yes-on-prop-1')->firstOrFail();
    $this->get(route('portal.show', $org))->assertOk();
});

test('re-running portal:create updates only passed fields and keeps an edited layout', function () {
    Artisan::call('portal:create', ['name' => 'Green Valley PAC', '--type' => 'pac', '--state' => 'OR']);

    $org = Organization::where('slug', 'green-valley-pac')->firstOrFail();
    $org->update(['portal_layout' => ['content' => [['type' => 'EmbedCta', 'props' => ['id' => 'x']]], 'root' => []]]);

    $exitCode = Artisan::call('portal:create', ['name' => 'Green Valley PAC', '--district' => '4']);

    expect($exitCode)->toBe(0);
    expect(Artisan::output())->toContain('Updated portal');
    expect(Organization::where('slug', 'green-valley-pac')->count())->toBe(1);

    $org->refresh();
    expect($org->org_type)->toBe('pac')
        ->and($org->target_state)->toBe('OR')
        ->and($org->target_district)->toBe('4')
        ->and($org->portal_layout['content'][0]['type'])->toBe('EmbedCta');

    Artisan::call('portal:create', ['name' => 'Green Valley PAC', '--reset-layout' => true]);
    expect($org->fresh()->portal_layout['content'][0]['type'])->toBe('Hero');
});

test('portal:create warns that a 501(c)(3) can only take ballot-measure positions', function () {
    $exitCode = Artisan::call('portal:create', ['name' => 'Civic Literacy Fund', '--type' => 'c3_nonprofit']);

    expect($exitCode)->toBe(0);
    expect(Artisan::output())->toContain('only take ballot-measure positions');
    expect(Organization::where('slug', 'civic-literacy-fund')->firstOrFail()->canEndorseCandidates())->toBeFalse();
});

test('portal:create rejects a new portal with a missing or unknown type', function () {
    expect(Artisan::call('portal:create', ['name' => 'No Type Org']))->toBe(1);
    expect(Artisan::output())->toContain('needs --type');

    expect(Artisan::call('portal:create', ['name' => 'Bad Type Org', '--type' => 'church']))->toBe(1);
    expect(Artisan::output())->toContain('must be one of');

    expect(Organization::count())->toBe(0);
});

test('portal:create rejects an owner email with no account and a malformed color', function () {
    expect(Artisan::call('portal:create', ['name' => 'Org A', '--type' => 'pac', '--owner' => 'nobody@example.com']))->toBe(1);
    expect(Artisan::output())->toContain('No user found with email nobody@example.com');

    expect(Artisan::call('portal:create', ['name' => 'Org B', '--type' => 'pac', '--color' => 'blue']))->toBe(1);
    expect(Artisan::output())->toContain('6-digit hex');

    expect(Organization::count())->toBe(0);
});
