<?php

use App\Models\User;
use App\Support\AdminAccess;

function dashboardSidebarAdminOwner(): User
{
    $user = User::factory()->create(['user_type' => 'admin', 'platform' => 'standalone']);
    $user->assignRole(['admin', AdminAccess::OWNER]);
    skipOnboarding($user, 'admin');
    return $user;
}

it('groups the admin sidebar into labeled sections instead of one flat list', function () {
    $owner = dashboardSidebarAdminOwner();

    $response = $this->actingAs($owner)->get(route('admin.dashboard'))->assertOk();

    foreach (['Overview', 'Content', 'Campaigns', 'Accounts', 'Candidates & Data', 'Trust & Finance', 'Settings', 'Account'] as $section) {
        $response->assertSee($section);
    }

    foreach (['Users', 'Staff access', 'Deleted accounts'] as $label) {
        $response->assertSee($label);
    }
});

it('keeps the Users, Staff access, and Deleted accounts links together under Accounts', function () {
    $owner = dashboardSidebarAdminOwner();

    $html = $this->actingAs($owner)->get(route('admin.dashboard'))->assertOk()->getContent();

    $accountsPos = strpos($html, '>Accounts<');
    $candidatesPos = strpos($html, '>Candidates &amp; Data<');
    $usersPos = strpos($html, route('admin.users.index'));
    $staffPos = strpos($html, route('admin.staff.index'));
    $deletedPos = strpos($html, route('admin.deleted-accounts.index'));

    expect($accountsPos)->not->toBeFalse();
    expect($candidatesPos)->not->toBeFalse();
    expect($usersPos)->toBeGreaterThan($accountsPos);
    expect($staffPos)->toBeGreaterThan($accountsPos);
    expect($deletedPos)->toBeGreaterThan($accountsPos);
    expect($usersPos)->toBeLessThan($candidatesPos);
    expect($staffPos)->toBeLessThan($candidatesPos);
    expect($deletedPos)->toBeLessThan($candidatesPos);
});

it('places Submit a public source inside the Content section, after the other content links', function () {
    $owner = dashboardSidebarAdminOwner();

    $html = $this->actingAs($owner)->get(route('admin.dashboard'))->assertOk()->getContent();

    $contentPos = strpos($html, '>Content<');
    $campaignsPos = strpos($html, '>Campaigns<');
    $writeBlogPostPos = strpos($html, '>Write a blog post<');
    $submitSourcePos = strpos($html, '>Submit a public source<');

    expect($contentPos)->not->toBeFalse();
    expect($campaignsPos)->not->toBeFalse();
    expect($submitSourcePos)->not->toBeFalse();
    expect($submitSourcePos)->toBeGreaterThan($contentPos);
    expect($submitSourcePos)->toBeGreaterThan($writeBlogPostPos);
    expect($submitSourcePos)->toBeLessThan($campaignsPos);
});

it('does not render a section header when none of its links are visible to a scoped staff member', function () {
    $user = User::factory()->create(['user_type' => 'admin', 'platform' => 'standalone']);
    $user->assignRole('admin');
    skipOnboarding($user, 'admin');

    $response = $this->actingAs($user)->get(route('admin.dashboard'))->assertOk();

    $response->assertDontSee('Settings');
});
