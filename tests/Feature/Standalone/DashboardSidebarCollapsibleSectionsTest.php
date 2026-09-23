<?php

use App\Models\Politician;
use App\Models\User;
use App\Support\AdminAccess;
use Spatie\Permission\Models\Role;

function collapsibleSidebarPolitician(): User
{
    Role::findOrCreate('politician', 'web');
    $user = User::factory()->create(['user_type' => 'politician', 'platform' => 'standalone']);
    $user->assignRole('politician');
    skipOnboarding($user, 'politician');
    Politician::factory()->create(['user_id' => $user->id]);
    return $user;
}

function collapsibleSidebarCitizen(): User
{
    Role::findOrCreate('citizen', 'web');
    $user = User::factory()->create(['user_type' => 'citizen', 'platform' => 'standalone']);
    $user->assignRole('citizen');
    skipOnboarding($user, 'citizen');
    \App\Models\Citizen::factory()->create(['user_id' => $user->id]);
    return $user;
}

function collapsibleSidebarAdmin(): User
{
    $user = User::factory()->create(['user_type' => 'admin', 'platform' => 'standalone']);
    $user->assignRole(['admin', AdminAccess::OWNER]);
    skipOnboarding($user, 'admin');
    return $user;
}

it('renders each politician sidebar section as a collapsible toggle with a matching body', function () {
    $user = collapsibleSidebarPolitician();

    $html = $this->actingAs($user)->get(route('politician.dashboard'))->assertOk()->getContent();

    foreach (['Overview', 'Campaigns', 'Events', 'Insights', 'Account'] as $section) {
        $key = "politician:{$section}";
        expect($html)->toContain('data-sidebar-key="'.$key.'"');
    }
    expect(substr_count($html, 'data-sidebar-toggle'))->toBe(substr_count($html, 'data-sidebar-body'));
});

it('renders each citizen sidebar section as a collapsible toggle with a matching body', function () {
    $user = collapsibleSidebarCitizen();

    $html = $this->actingAs($user)->get(route('citizen.dashboard'))->assertOk()->getContent();

    foreach (['Overview', 'Campaigns', 'Posts', 'Events', 'Account'] as $section) {
        $key = "citizen:{$section}";
        expect($html)->toContain('data-sidebar-key="'.$key.'"');
    }
    expect(substr_count($html, 'data-sidebar-toggle'))->toBe(substr_count($html, 'data-sidebar-body'));
});

it('renders each visible admin sidebar section as a collapsible toggle with a matching body', function () {
    $owner = collapsibleSidebarAdmin();

    $html = $this->actingAs($owner)->get(route('admin.dashboard'))->assertOk()->getContent();

    foreach (['admin:Overview', 'admin:Accounts', 'admin:Candidates &amp; Data'] as $key) {
        expect($html)->toContain('data-sidebar-key="'.$key.'"');
    }
    expect(substr_count($html, 'data-sidebar-toggle'))->toBe(substr_count($html, 'data-sidebar-body'));
});
