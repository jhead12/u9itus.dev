<?php

use App\Models\User;
use App\Models\WorkspaceWidget;
use App\Services\AdminPermissionInstaller;
use App\Support\AdminAccess;

function workspaceStaff(array $roles = []): User
{
    $user = User::factory()->create(['user_type' => 'admin', 'platform' => 'standalone']);
    $user->assignRole(array_merge(['admin'], $roles));
    skipOnboarding($user, 'admin');
    return $user;
}

it('hides suspended accounts from the dashboard recent-registrations widget', function () {
    $owner = workspaceStaff([AdminAccess::OWNER]);
    User::factory()->create(['name' => 'Active Newcomer', 'suspended_at' => null]);
    User::factory()->create(['name' => 'Suspended Newcomer', 'suspended_at' => now()]);

    $this->actingAs($owner)->get('/admin/dashboard')
        ->assertOk()
        ->assertSee('Active Newcomer')
        ->assertDontSee('Suspended Newcomer');
});

it('hides suspended accounts from the staff access roster', function () {
    $owner = workspaceStaff([AdminAccess::OWNER]);
    $activeAdmin = User::factory()->create(['name' => 'Active Staffer', 'user_type' => 'admin']);
    $suspendedAdmin = User::factory()->create(['name' => 'Suspended Staffer', 'user_type' => 'admin', 'suspended_at' => now()]);

    $this->actingAs($owner)->get('/admin/staff-access')
        ->assertOk()
        ->assertSee('Active Staffer')
        ->assertDontSee('Suspended Staffer');
});

it('denies workspace access without the workspace.view permission but allows the owner', function () {
    app(AdminPermissionInstaller::class)->install();
    $owner = workspaceStaff([AdminAccess::OWNER]);
    $plainStaff = workspaceStaff();

    $this->actingAs($owner)->get('/admin/workspace')->assertOk();
    $this->actingAs($plainStaff)->get('/admin/workspace')->assertForbidden();
});

it('seeds default widgets on first visit and lets a user add, reorder, and remove widgets', function () {
    app(AdminPermissionInstaller::class)->install();
    $owner = workspaceStaff([AdminAccess::OWNER]);

    $this->actingAs($owner)->get('/admin/workspace')->assertOk();
    expect(WorkspaceWidget::where('user_id', $owner->id)->count())->toBeGreaterThan(0);

    $this->post(route('admin.workspace.widgets.store'), ['widget_key' => 'voting_updates'])->assertRedirect();
    $widget = WorkspaceWidget::where('user_id', $owner->id)->where('widget_key', 'voting_updates')->sole();

    $this->postJson(route('admin.workspace.widgets.layout'), [
        'widgets' => [['id' => $widget->id, 'position' => 0, 'width' => 'full']],
    ])->assertOk();
    expect($widget->fresh()->width)->toBe('full');

    $this->delete(route('admin.workspace.widgets.destroy', $widget))->assertRedirect();
    expect(WorkspaceWidget::find($widget->id))->toBeNull();
});

it('does not let a user delete another user\'s widget', function () {
    app(AdminPermissionInstaller::class)->install();
    $owner = workspaceStaff([AdminAccess::OWNER]);
    $otherOwner = workspaceStaff([AdminAccess::OWNER]);

    $widget = WorkspaceWidget::create([
        'user_id' => $otherOwner->id,
        'widget_key' => 'stats_snapshot',
        'position' => 0,
        'width' => 'full',
    ]);

    $this->actingAs($owner)->delete(route('admin.workspace.widgets.destroy', $widget))->assertForbidden();
    expect(WorkspaceWidget::find($widget->id))->not->toBeNull();
});
