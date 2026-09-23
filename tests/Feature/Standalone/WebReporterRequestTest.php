<?php

use App\Models\User;
use App\Models\Voter;
use App\Support\AdminAccess;
use App\Support\ChatterContributorAccess;
use Spatie\Permission\Models\Role;

function webReporterVoter(): User
{
    Role::findOrCreate('voter', 'web');
    $user = User::factory()->create(['user_type' => 'voter', 'platform' => 'standalone', 'email_verified_at' => now()]);
    $user->assignRole('voter');
    skipOnboarding($user, 'voter');
    Voter::factory()->create(['user_id' => $user->id, 'is_verified' => true, 'is_active' => true, 'flagged_for_fraud' => false]);
    return $user;
}

function webReporterOwner(): User
{
    Role::findOrCreate(AdminAccess::OWNER, 'web');
    $user = User::factory()->create(['user_type' => 'admin', 'platform' => 'standalone']);
    $user->assignRole('admin', AdminAccess::OWNER);
    skipOnboarding($user, 'admin');
    return $user;
}

it('lets a voter request Web Reporter access from the onboarding step, without granting it', function () {
    $user = webReporterVoter();

    $this->actingAs($user)->get(route('voter.onboarding.web-reporter'))
        ->assertOk()->assertSee('Request Web Reporter access')->assertSee('Not now');

    $this->actingAs($user)->post(route('voter.onboarding.complete-web-reporter'), ['action' => 'request'])
        ->assertRedirect(route('voter.dashboard'));

    $user->refresh();
    expect($user->chatter_contributor_requested_at)->not->toBeNull();
    expect(ChatterContributorAccess::allowed($user))->toBeFalse();
});

it('lets a voter skip the Web Reporter step without requesting anything', function () {
    $user = webReporterVoter();

    $this->actingAs($user)->post(route('voter.onboarding.complete-web-reporter'), ['action' => 'skip'])
        ->assertRedirect(route('voter.dashboard'));

    expect($user->fresh()->chatter_contributor_requested_at)->toBeNull();
});

it('does not let a voter double-request once already pending', function () {
    $user = webReporterVoter();
    $this->actingAs($user)->post(route('voter.onboarding.complete-web-reporter'), ['action' => 'request']);
    $firstRequestedAt = $user->fresh()->chatter_contributor_requested_at;

    $this->travel(1)->hours();
    $this->actingAs($user)->post(route('voter.onboarding.complete-web-reporter'), ['action' => 'request']);

    expect($user->fresh()->chatter_contributor_requested_at->eq($firstRequestedAt))->toBeTrue();
});

it('shows Web Reporter status on the voter profile page and returns there after requesting', function () {
    $user = webReporterVoter();

    $this->actingAs($user)->get(route('voter.profile'))
        ->assertOk()->assertSee('Request Web Reporter access')->assertDontSee('pending review');

    $this->actingAs($user)->post(route('voter.onboarding.complete-web-reporter'), ['action' => 'request', 'redirect_to' => 'profile'])
        ->assertRedirect(route('voter.profile'));

    $this->actingAs($user)->get(route('voter.profile'))
        ->assertOk()->assertSee('pending review')->assertDontSee('Request Web Reporter access');
});

it('shows approved Web Reporter access on the profile page instead of a request button', function () {
    $user = webReporterVoter();
    $user->assignRole(ChatterContributorAccess::ROLE);

    $this->actingAs($user)->get(route('voter.profile'))
        ->assertOk()->assertSee('You have Web Reporter access')->assertDontSee('Request Web Reporter access');
});

it('lists pending Web Reporter requests for a Super Admin and lets them approve one', function () {
    $owner = webReporterOwner();
    $requester = webReporterVoter();
    $requester->forceFill(['chatter_contributor_requested_at' => now()])->save();

    $this->actingAs($owner)->get(route('admin.staff.index'))
        ->assertOk()->assertSee('Pending Web Reporter requests')->assertSee($requester->email);

    $this->actingAs($owner)->put(route('admin.staff.contributor', $requester), ['enabled' => 1])
        ->assertRedirect();

    $requester->refresh();
    expect(ChatterContributorAccess::allowed($requester))->toBeTrue();
    expect($requester->chatter_contributor_requested_at)->toBeNull();
    $this->actingAs($owner)->get(route('admin.staff.index'))->assertDontSee('Pending Web Reporter requests');
});

it('lets a Super Admin dismiss a Web Reporter request without granting access', function () {
    $owner = webReporterOwner();
    $requester = webReporterVoter();
    $requester->forceFill(['chatter_contributor_requested_at' => now()])->save();

    $this->actingAs($owner)->delete(route('admin.staff.contributor-request.dismiss', $requester))
        ->assertRedirect();

    $requester->refresh();
    expect($requester->chatter_contributor_requested_at)->toBeNull();
    expect(ChatterContributorAccess::allowed($requester))->toBeFalse();
});

it('lets an owner approve a Web Reporter request for a phone-verified account with no email verification', function () {
    // Most voters here verify by phone during onboarding and never touch
    // email verification, so approval must not hard-require email_verified_at.
    $owner = webReporterOwner();
    $requester = webReporterVoter();
    $requester->forceFill([
        'email_verified_at' => null,
        'phone_verified_at' => now(),
        'chatter_contributor_requested_at' => now(),
    ])->save();

    $this->actingAs($owner)->put(route('admin.staff.contributor', $requester), ['enabled' => 1])
        ->assertRedirect()->assertSessionHasNoErrors();

    $requester->refresh();
    expect(ChatterContributorAccess::allowed($requester))->toBeTrue();
});

it('still refuses to approve a Web Reporter request with no accepted verification', function () {
    $owner = webReporterOwner();
    $requester = webReporterVoter();
    $requester->voter()->update(['is_verified' => false, 'stripe_account_status' => 'pending']);
    $requester->forceFill([
        'email_verified_at' => null,
        'phone_verified_at' => null,
        'idme_verified_at' => null,
        'chatter_contributor_requested_at' => now(),
    ])->save();

    $this->actingAs($owner)->put(route('admin.staff.contributor', $requester), ['enabled' => 1])
        ->assertRedirect()->assertSessionHasErrors('contributor');

    expect(ChatterContributorAccess::allowed($requester->fresh()))->toBeFalse();
});

it('approves identity-verified reporters without contact timestamps and permits the submission page', function (string $method) {
    $owner = webReporterOwner();
    $requester = webReporterVoter();
    $requester->forceFill([
        'email_verified_at' => null, 'phone_verified_at' => null,
        'idme_verified_at' => $method === 'idme' ? now() : null,
        'chatter_contributor_requested_at' => now(),
    ])->save();
    $requester->voter()->update([
        'is_verified' => $method === 'voter',
        'stripe_account_status' => $method === 'stripe' ? 'active' : 'pending',
    ]);
    expect(ChatterContributorAccess::allowed($requester->fresh()))->toBeFalse();
    $this->actingAs($owner)->put(route('admin.staff.contributor', $requester), ['enabled' => 1])
        ->assertRedirect()->assertSessionHasNoErrors();
    $requester->refresh();
    expect($requester->chatter_contributor_requested_at)->toBeNull();
    expect($requester->hasRole('admin'))->toBeFalse();
    expect(ChatterContributorAccess::allowed($requester))->toBeTrue();
    $this->actingAs($requester)->get(route('contributor.chatter.index'))->assertOk();
})->with(['voter', 'stripe', 'idme']);

it('does not use an inactive or fraud-flagged voter record as verification', function (string $field, bool $value) {
    $requester = webReporterVoter();
    $requester->forceFill(['email_verified_at' => null, 'phone_verified_at' => null, 'idme_verified_at' => null])->save();
    $requester->voter()->update([$field => $value]);
    $this->actingAs(webReporterOwner())->put(route('admin.staff.contributor', $requester), ['enabled' => 1])
        ->assertSessionHasErrors('contributor');
    expect($requester->fresh()->hasRole(ChatterContributorAccess::ROLE))->toBeFalse();
})->with([['is_active', false], ['flagged_for_fraud', true]]);

it('does not let non-owner staff approve or dismiss Web Reporter requests', function () {
    Role::findOrCreate('staff:Social Reviewer', 'web');
    $staff = User::factory()->create(['user_type' => 'admin', 'platform' => 'standalone']);
    $staff->assignRole('admin', 'staff:Social Reviewer');
    skipOnboarding($staff, 'admin');

    $requester = webReporterVoter();
    $requester->forceFill(['chatter_contributor_requested_at' => now()])->save();

    $this->actingAs($staff)->get(route('admin.staff.index'))->assertForbidden();
    $this->actingAs($staff)->put(route('admin.staff.contributor', $requester), ['enabled' => 1])->assertForbidden();
    $this->actingAs($staff)->delete(route('admin.staff.contributor-request.dismiss', $requester))->assertForbidden();

    expect($requester->fresh()->chatter_contributor_requested_at)->not->toBeNull();
});
