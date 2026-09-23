<?php

use App\Models\Politician;
use App\Models\PoliticianChatterItem;
use App\Models\User;
use App\Services\StaffAccessService;
use App\Support\ChatterContributorAccess;

beforeEach(function () {
    \Spatie\Permission\Models\Role::findOrCreate('voter', 'web');
});

function communityContributor(): User
{
    $user = User::factory()->create(['user_type' => 'voter', 'platform' => 'standalone']);
    $user->assignRole('voter', ChatterContributorAccess::ROLE);
    return $user;
}

function communitySource(array $extra = []): array
{
    return array_merge([
        'politician_id' => Politician::factory()->create(['is_active' => true])->id,
        'platform' => 'news', 'source_url' => 'https://example.com/story',
        'headline' => 'A community source', 'contributor_notes' => 'Private relevance explanation',
        'source_excerpt' => 'Private selected excerpt', 'public_source' => '1',
    ], $extra);
}

it('accepts contributor evidence but never contributor publication or ownership fields', function () {
    $user = communityContributor();
    $this->actingAs($user)->get(route('contributor.chatter.index'))->assertOk()->assertSee('Search candidates');
    $this->post(route('contributor.chatter.store'), communitySource([
        'moderation_status' => 'published', 'claim_status' => 'supported', 'published_at' => now(),
        'submitted_by_user_id' => 99999, 'reviewed_by_user_id' => $user->id,
        'admin_notes' => 'Forged internal note', 'summary' => 'Forged public summary',
    ]))->assertRedirect(route('contributor.chatter.index'))->assertSessionHasNoErrors();
    $item = PoliticianChatterItem::sole();
    expect($item->moderation_status)->toBe('pending')->and($item->claim_status)->toBe('unverified')
        ->and($item->submitted_by_user_id)->toBe($user->id)->and($item->published_at)->toBeNull()
        ->and($item->reviewed_by_user_id)->toBeNull()->and($item->admin_notes)->toBeNull()
        ->and($item->summary)->toBeNull()->and($item->moderationLogs()->sole()->action)->toBe('contributor_submitted')
        ->and(PoliticianChatterItem::publiclyVisible()->count())->toBe(0);
    $this->get(route('admin.politician-chatter.index'))->assertForbidden();
    $this->post(route('admin.politician-chatter.moderate', $item), ['action' => 'publish'])->assertForbidden();
});

it('shows only the signed in contributors history and no private editorial notes', function () {
    $first = communityContributor();
    $second = communityContributor();
    $this->actingAs($first)->post(route('contributor.chatter.store'), communitySource(['headline' => 'First private submission']))->assertSessionHasNoErrors();
    $this->actingAs($second)->post(route('contributor.chatter.store'), communitySource(['headline' => 'Second private submission']))->assertSessionHasNoErrors();
    PoliticianChatterItem::where('submitted_by_user_id', $first->id)->update(['admin_notes' => 'Secret editor assessment']);
    $this->actingAs($first)->get(route('contributor.chatter.index'))->assertOk()->assertSee('First private submission')
        ->assertDontSee('Second private submission')->assertDontSee('Secret editor assessment')
        ->assertDontSee('Private selected excerpt');
});

it('rejects unauthenticated unapproved suspended unverified and revoked contributors', function () {
    $this->get(route('contributor.chatter.index'))->assertRedirect();
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('contributor.chatter.index'))->assertForbidden();
    $user = communityContributor();
    $user->update(['suspended_at' => now()]);
    $this->actingAs($user)->post(route('contributor.chatter.store'), [])->assertForbidden();
    $user->update(['suspended_at' => null, 'email_verified_at' => null]);
    $this->actingAs($user)->get(route('contributor.chatter.index'))->assertForbidden();
    $user->update(['email_verified_at' => now()]);
    $user->removeRole(ChatterContributorAccess::ROLE);
    $this->actingAs($user)->post(route('contributor.chatter.store'), [])->assertForbidden();
});

it('validates public links consent active candidates and duplicates', function () {
    $this->actingAs(communityContributor());
    $payload = communitySource();
    foreach (['javascript:alert(1)', 'http://127.0.0.1/private', 'http://localhost/private', 'https://user:pass@example.com'] as $url) {
        $this->post(route('contributor.chatter.store'), array_merge($payload, ['source_url' => $url]))->assertSessionHasErrors('source_url');
    }
    $this->post(route('contributor.chatter.store'), array_merge($payload, ['public_source' => '0']))->assertSessionHasErrors('public_source');
    $this->post(route('contributor.chatter.store'), $payload)->assertSessionHasNoErrors();
    $this->post(route('contributor.chatter.store'), $payload)->assertSessionHasErrors('source_url');
    Politician::find($payload['politician_id'])->update(['is_active' => false]);
    $this->post(route('contributor.chatter.store'), array_merge($payload, ['source_url' => 'https://example.com/new']))->assertSessionHasErrors('politician_id');
    expect(PoliticianChatterItem::count())->toBe(1);
});

it('allows only owners to grant contributor access without promoting accounts to admin', function () {
    $owner = User::factory()->create(['user_type' => 'admin', 'platform' => 'standalone']);
    $owner->assignRole('admin', 'super_admin');
    skipOnboarding($owner, 'admin');
    $target = User::factory()->create(['user_type' => 'voter']);
    $target->assignRole('voter');
    $this->actingAs($owner)->put(route('admin.staff.contributor', $target), ['enabled' => '1'])->assertRedirect()->assertSessionHasNoErrors();
    $target = $target->fresh();
    expect($target->hasRole(ChatterContributorAccess::ROLE))->toBeTrue()->and($target->hasRole('admin'))->toBeFalse()
        ->and($target->hasRole('voter'))->toBeTrue()->and($target->user_type)->toBe('voter');
    $this->assertDatabaseHas('staff_access_audits', ['action' => 'contributor.assigned', 'target' => 'user:'.$target->id]);
    $this->get(route('admin.staff.index'))->assertOk()->assertSee('Save contributor access');
    $this->actingAs($target)->put(route('admin.staff.contributor', $target), ['enabled' => '1'])->assertForbidden();
    app(StaffAccessService::class)->assignContributor($owner, $target, false);
    $this->actingAs($target)->get(route('contributor.chatter.index'))->assertForbidden();
});

it('requires editor authored public context before a contributor submission can be published', function () {
    $this->actingAs(communityContributor())->post(route('contributor.chatter.store'), communitySource())->assertSessionHasNoErrors();
    $item = PoliticianChatterItem::sole();
    $editor = User::factory()->create(['user_type' => 'admin', 'platform' => 'standalone']);
    $editor->assignRole('admin', 'staff:Social Publisher');
    skipOnboarding($editor, 'admin');
    $this->actingAs($editor)->get(route('admin.politician-chatter.index'))->assertOk()->assertSee('Private relevance explanation')->assertSee('Private selected excerpt');
    $this->post(route('admin.politician-chatter.moderate', $item), ['action' => 'publish'])->assertSessionHasErrors('summary');
    $item->update(['summary' => 'Editor reviewed neutral context']);
    $this->post(route('admin.politician-chatter.moderate', $item), ['action' => 'publish'])->assertSessionHasNoErrors();
    expect($item->fresh()->moderation_status)->toBe('published');
    $item->politician->update(['page_published' => true]);
    auth()->logout();
    $this->get(route('politician.public.show', $item->politician->slug))->assertOk()
        ->assertSee('Editor reviewed neutral context')->assertDontSee('Private relevance explanation')
        ->assertDontSee('Private selected excerpt');
});

it('rate limits repeated contributor submissions', function () {
    $this->actingAs(communityContributor());
    for ($i = 0; $i < 10; $i++) {
        $this->post(route('contributor.chatter.store'), [])->assertSessionHasErrors();
    }
    $this->post(route('contributor.chatter.store'), [])->assertStatus(429);
});

it('provides a public clip handoff but keeps extension setup and submissions restricted', function () {
    $this->get(route('contributor.chatter.clip'))->assertOk()
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('chatter-clip-import.js')->assertDontSee('resources/js/app.js');
    $this->get(route('contributor.chatter.extension'))->assertRedirect();
    $this->actingAs(User::factory()->create())->get(route('contributor.chatter.extension'))->assertForbidden();
    $user = communityContributor();
    $this->actingAs($user)->get(route('contributor.chatter.extension'))->assertOk()
        ->assertSee('u9itus-source-clipper-0.2.0.zip')->assertSee('Load unpacked');
    $this->get(route('contributor.chatter.index'))->assertOk()->assertSee('data-restore-clip="true"', false);
    $this->withSession(['_old_input' => ['headline' => 'Preserve my edit']])
        ->get(route('contributor.chatter.index'))->assertOk()->assertSee('Preserve my edit')
        ->assertSee('data-restore-clip="false"', false);
    $user->removeRole(ChatterContributorAccess::ROLE);
    $this->actingAs($user)->get(route('contributor.chatter.extension'))->assertForbidden();
});

it('preserves two factor enforcement and denies guest accounts', function () {
    $user = communityContributor();
    $user->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()])->save();
    $this->actingAs($user)->get(route('contributor.chatter.index'))->assertRedirect(route('2fa.challenge'))
        ->assertSessionHas('url.intended', route('contributor.chatter.index'));
    $this->post(route('contributor.chatter.store'), [])->assertRedirect(route('2fa.challenge'));
    $user->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null, 'is_guest' => true])->save();
    $this->actingAs($user)->get(route('contributor.chatter.index'))->assertForbidden();
});

it('rolls back submissions when the audit write fails', function () {
    $user = communityContributor();
    $payload = communitySource();
    $event = 'eloquent.creating: '.\App\Models\PoliticianChatterModerationLog::class;
    \Illuminate\Support\Facades\Event::listen($event, function () { throw new \RuntimeException('Audit unavailable'); });
    try {
        $this->actingAs($user)->post(route('contributor.chatter.store'), $payload)->assertStatus(500);
        expect(PoliticianChatterItem::count())->toBe(0);
    } finally {
        \Illuminate\Support\Facades\Event::forget($event);
    }
});

it('returns a voter to the clipping form after completing two factor verification', function () {
    $user = communityContributor();
    $user->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => ['ABCD-EFGH']])->save();
    $this->actingAs($user)->get(route('contributor.chatter.index'))->assertRedirect(route('2fa.challenge'));
    $this->post(route('2fa.challenge.verify'), ['code' => 'ABCD-EFGH'])->assertRedirect(route('contributor.chatter.index'));
    $this->get(route('contributor.chatter.index'))->assertOk();
});

it('returns an approved staff contributor to the clipping form after admin two factor verification', function () {
    $user = User::factory()->create(['user_type' => 'admin', 'platform' => 'standalone']);
    $user->assignRole('admin', 'staff:Social Publisher');
    skipOnboarding($user, 'admin');
    $user->forceFill(['admin_two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'admin_two_factor_confirmed_at' => now(), 'admin_two_factor_recovery_codes' => ['ABCD-EFGH']])->save();
    \App\Models\PlatformSetting::updateOrCreate(['key' => 'admin_2fa_enforced', 'user_tier' => null], [
        'value' => '1', 'type' => 'boolean', 'category' => 'general', 'is_active' => true,
    ]);
    $this->actingAs($user)->get(route('contributor.chatter.index'))->assertRedirect(route('admin.2fa.challenge'));
    $this->post(route('admin.2fa.challenge.verify'), ['code' => 'ABCD-EFGH'])->assertRedirect(route('contributor.chatter.index'));
    $this->get(route('contributor.chatter.index'))->assertOk();
});
