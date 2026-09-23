<?php

use App\Models\User;
use App\Models\Voter;
use App\Support\ChatterContributorAccess;
use Spatie\Permission\Models\Role;

function dashboardSidebarVoter(): User
{
    Role::findOrCreate('voter', 'web');
    $user = User::factory()->create(['user_type' => 'voter', 'platform' => 'standalone', 'email_verified_at' => now()]);
    $user->assignRole('voter');
    skipOnboarding($user, 'voter');
    Voter::factory()->create(['user_id' => $user->id, 'is_verified' => true, 'is_active' => true, 'flagged_for_fraud' => false]);
    return $user;
}

it('shows Favorites and a Replay Onboarding link in the voter dashboard sidebar', function () {
    $user = dashboardSidebarVoter();

    $this->actingAs($user)->get(route('voter.dashboard'))
        ->assertOk()
        ->assertSee('Favorites')
        ->assertSee(route('voter.favorites.index'), false)
        ->assertSee('Replay Onboarding')
        ->assertSee(route('voter.onboarding.welcome'), false);
});

it('points the sidebar Web Reporter link at the onboarding request step when access has not been requested', function () {
    $user = dashboardSidebarVoter();

    $this->actingAs($user)->get(route('voter.dashboard'))
        ->assertOk()
        ->assertSee('Web Reporter')
        ->assertSee(route('voter.onboarding.web-reporter'), false)
        ->assertDontSee('PENDING');
});

it('shows a PENDING badge on the sidebar Web Reporter link once access has been requested', function () {
    $user = dashboardSidebarVoter();
    $user->forceFill(['chatter_contributor_requested_at' => now()])->save();

    $this->actingAs($user)->get(route('voter.dashboard'))
        ->assertOk()
        ->assertSee('Web Reporter')
        ->assertSee('PENDING')
        ->assertSee(route('voter.onboarding.web-reporter'), false);
});

it('points the sidebar Web Reporter link at the submission page once access is approved', function () {
    $user = dashboardSidebarVoter();
    $user->assignRole(ChatterContributorAccess::ROLE);

    $this->actingAs($user)->get(route('voter.dashboard'))
        ->assertOk()
        ->assertSee('Web Reporter')
        ->assertSee(route('contributor.chatter.index'), false)
        ->assertDontSee('PENDING');
});

it('shows a Study Systems link in the sidebar that points to the study systems page', function () {
    $user = dashboardSidebarVoter();

    $this->actingAs($user)->get(route('voter.dashboard'))
        ->assertOk()
        ->assertSee('Study Systems')
        ->assertSee(route('voter.study-systems'), false);
});
