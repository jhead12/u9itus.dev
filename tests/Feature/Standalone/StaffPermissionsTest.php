<?php

use App\Models\Politician;
use App\Models\PoliticianChatterItem;
use App\Models\Post;
use App\Models\User;
use App\Services\AdminPermissionInstaller;
use App\Services\StaffAccessService;
use App\Support\AdminAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

function permissionStaff(array $roles = []): User
{
    $user = User::factory()->create(['user_type' => 'admin', 'platform' => 'standalone']);
    $user->assignRole(array_merge(['admin'], $roles));
    skipOnboarding($user, 'admin');
    return $user;
}

it('denies base admins and isolates social staff across web and API', function () {
    $base = permissionStaff();
    $this->actingAs($base)->get('/admin/dashboard')->assertOk()->assertSee('No operational permissions');
    $this->get('/admin/politician-chatter')->assertForbidden();
    $social = permissionStaff(['staff:Social Reviewer']);
    $this->actingAs($social)->get('/admin/politician-chatter')->assertOk();
    foreach (['/admin/analytics', '/admin/posts', '/admin/campaigns/pending', '/admin/users', '/admin/settings', '/admin/staff-access'] as $url) {
        $this->get($url)->assertForbidden();
    }
    $this->actingAs($social, 'sanctum')->getJson('/api/v1/admin/analytics')->assertForbidden();
    $this->actingAs($social, 'web')->get('/admin/dashboard')->assertOk()->assertDontSee('Total Revenue')->assertDontSee('href="'.route('admin.analytics').'"', false);
});

it('blocks forged publishing and live edits by a social reviewer', function () {
    $social = permissionStaff(['staff:Social Reviewer']);
    $item = PoliticianChatterItem::create([
        'politician_id' => Politician::factory()->create()->id, 'platform' => 'x',
        'source_url' => 'https://x.com/source/status/1', 'headline' => 'Original',
        'summary' => 'Context', 'moderation_status' => 'pending',
    ]);
    $this->actingAs($social)->post(route('admin.politician-chatter.moderate', $item), ['action' => 'publish'])->assertForbidden();
    expect($item->fresh()->moderation_status)->toBe('pending');
    $item->update(['moderation_status' => 'published', 'published_at' => now()]);
    $this->put(route('admin.politician-chatter.update', $item), ['headline' => 'Changed'])->assertForbidden();
    expect($item->fresh()->headline)->toBe('Original');
});

it('lets blog editors create sanitized drafts but not publish them', function () {
    $editor = permissionStaff(['staff:Blog Editor']);
    $this->actingAs($editor)->get('/admin/posts/create')->assertOk();
    $this->post('/admin/posts', ['title' => 'Staff draft', 'body' => '<p>Article</p><script>alert(1)</script>', 'status' => 'published'])->assertRedirect();
    $post = Post::sole();
    expect($post->status->value)->toBe('draft')->and($post->body)->not->toContain('<script>');
    $this->post(route('admin.posts.approve', $post))->assertForbidden();
    $this->post(route('admin.posts.bulk-action'), ['action' => 'approve', 'post_ids' => [$post->id]])->assertForbidden();
    $publisher = permissionStaff(['staff:Blog Publisher']);
    $this->actingAs($publisher)->post(route('admin.posts.approve', $post))->assertRedirect();
    $this->actingAs($editor)->put(route('admin.posts.update', $post), ['title' => 'Alter live', 'body' => 'text'])->assertForbidden();
    auth()->logout();
    $this->get(route('blog.show', $post->fresh()->slug))->assertOk()->assertSee('Staff draft');
});

it('lets an owner create custom roles and revoke access in an existing session', function () {
    $owner = permissionStaff([AdminAccess::OWNER]);
    $staff = permissionStaff();
    $this->actingAs($owner)->get('/admin/staff-access')->assertOk();
    $this->post(route('admin.staff.roles.store'), ['name' => 'Social desk', 'permissions' => ['chatter.view']])->assertRedirect();
    $role = Role::findByName('staff:Social desk', 'web');
    $this->put(route('admin.staff.assign', $staff), ['roles' => [$role->id]])->assertRedirect();
    $this->actingAs($staff)->get('/admin/politician-chatter')->assertOk();
    app(StaffAccessService::class)->assign($owner, $staff, [], false);
    $this->get('/admin/politician-chatter')->assertForbidden();
    expect(DB::table('staff_access_audits')->count())->toBe(3);
});

it('protects owner invariants and rejects arbitrary capabilities', function () {
    $owner = permissionStaff([AdminAccess::OWNER]);
    $this->actingAs($owner)->put(route('admin.staff.assign', $owner), ['roles' => []])->assertSessionHasErrors('super_admin');
    expect($owner->fresh()->hasRole(AdminAccess::OWNER))->toBeTrue();
    $this->post(route('admin.staff.roles.store'), ['name' => 'Fake', 'permissions' => ['made.up']])->assertSessionHasErrors('permissions');
    $this->put(route('admin.staff.roles.update', Role::findByName('admin', 'web')), ['name' => 'Admin', 'permissions' => []])->assertForbidden();
    expect(fn () => $owner->delete())->toThrow(\Illuminate\Validation\ValidationException::class);
    expect(fn () => $owner->update(['suspended_at' => now()]))->toThrow(\Illuminate\Validation\ValidationException::class);
    $second = permissionStaff([AdminAccess::OWNER]);
    app(StaffAccessService::class)->assign($second, $owner->fresh(), [], false);
    expect($owner->fresh()->hasRole(AdminAccess::OWNER))->toBeFalse();
});

it('does not let staff manage roles and keeps finance viewers read only', function () {
    $viewer = permissionStaff(['staff:Finance Viewer']);
    $this->actingAs($viewer)->post(route('admin.staff.roles.store'), ['name' => 'Escalate'])->assertForbidden();
    $this->get('/admin/analytics/export/campaign-accounting')->assertForbidden();
    $this->post('/admin/payouts/batch-process')->assertForbidden();
    $this->actingAs($viewer, 'sanctum')->postJson('/api/v1/admin/payouts/process')->assertForbidden();
});

it('preserves edited starter roles and grants no new admin access on installation', function () {
    $role = Role::findByName('staff:Social Reviewer', 'web');
    $role->syncPermissions(['chatter.view']);
    app(AdminPermissionInstaller::class)->install();
    expect($role->fresh()->permissions->pluck('name')->all())->toBe(['chatter.view']);
    $staff = permissionStaff();
    app(\App\Services\UserRoleService::class)->repairSpatieRole($staff);
    expect(AdminAccess::allowed($staff, 'finance.reports.view'))->toBeFalse();
});

it('has an explicit entry for every existing admin route and denies new routes', function () {
    foreach (Route::getRoutes() as $route) {
        if (str_starts_with($route->uri(), 'admin/') || str_starts_with($route->uri(), 'api/v1/admin/')) {
            if (in_array($route->getName(), ['admin.login', 'admin.login.submit'])) {
                continue;
            }
            expect(array_key_exists($route->getName(), config('admin_routes')))->toBeTrue($route->getName());
        }
    }
    $owner = permissionStaff([AdminAccess::OWNER]);
    expect(AdminAccess::canRoute($owner, 'admin.future.secret'))->toBeFalse();
});

it('bootstraps an explicit verified owner without resetting credentials', function () {
    $user = User::factory()->create();
    $password = $user->password;
    $this->artisan('admin:bootstrap-owner', ['email' => $user->email])->assertSuccessful();
    expect(AdminAccess::owner($user->fresh()))->toBeTrue()->and($user->fresh()->password)->toBe($password);
    expect(DB::table('staff_access_audits')->where('action', 'owner.bootstrap.cli')->count())->toBe(1);
});
