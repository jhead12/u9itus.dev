<?php

use App\Enums\PostStatus;
use App\Models\Citizen;
use App\Models\Politician;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────────────

beforeEach(function () {
    if (class_exists(\Spatie\Permission\Models\Role::class)) {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'voter', 'guard_name' => 'web']);
    }
});

function makeAdminForPostModeration(): User
{
    $admin = User::factory()->create(['platform' => 'standalone', 'user_type' => 'admin']);
    if (method_exists($admin, 'assignRole')) {
        $admin->assignRole('admin');
    }
    skipOnboarding($admin, 'admin');
    return $admin;
}

function makeAuthoredPost(array $overrides = []): Post
{
    $citizenUser = User::factory()->create(['platform' => 'standalone', 'user_type' => 'citizen']);
    $citizen = Citizen::factory()->create(['user_id' => $citizenUser->id]);

    return Post::factory()->create(array_merge([
        'author_type' => Citizen::class,
        'author_id'   => $citizen->id,
    ], $overrides));
}

// ── Index & filters ───────────────────────────────────────────────────────

test('admin post index renders and lists posts', function () {
    $admin = makeAdminForPostModeration();
    $post = makeAuthoredPost(['title' => 'Visible Headline Title']);

    $this->actingAs($admin)
        ->get(route('admin.posts.index'))
        ->assertOk()
        ->assertSee('Visible Headline Title');
});

test('index filters by status', function () {
    $admin = makeAdminForPostModeration();

    $published = makeAuthoredPost(['title' => 'Published One']);
    $published->update(['status' => PostStatus::Published, 'published_at' => now()->subHour()]);

    $draft = makeAuthoredPost(['title' => 'Draft Two', 'status' => PostStatus::Draft]);

    $this->actingAs($admin)
        ->get(route('admin.posts.index', ['status' => PostStatus::Published->value]))
        ->assertOk()
        ->assertSee('Published One')
        ->assertDontSee('Draft Two');
});

test('index filters by search term', function () {
    $admin = makeAdminForPostModeration();
    $match = makeAuthoredPost(['title' => 'Needle In A Haystack']);
    $other = makeAuthoredPost(['title' => 'Completely Unrelated Title']);

    $this->actingAs($admin)
        ->get(route('admin.posts.index', ['search' => 'Needle']))
        ->assertOk()
        ->assertSee('Needle In A Haystack')
        ->assertDontSee('Completely Unrelated Title');
});

test('index filters by author type', function () {
    $admin = makeAdminForPostModeration();

    $citizenUser = User::factory()->create(['platform' => 'standalone', 'user_type' => 'citizen']);
    $citizen = Citizen::factory()->create(['user_id' => $citizenUser->id]);
    $citizenPost = Post::factory()->create([
        'title' => 'Citizen Penned',
        'author_type' => Citizen::class,
        'author_id' => $citizen->id,
    ]);

    $politicianUser = User::factory()->create(['platform' => 'standalone', 'user_type' => 'politician']);
    $politician = Politician::factory()->create(['user_id' => $politicianUser->id]);
    $politicianPost = Post::factory()->create([
        'title' => 'Politician Penned',
        'author_type' => Politician::class,
        'author_id' => $politician->id,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.posts.index', ['author_type' => Politician::class]))
        ->assertOk()
        ->assertSee('Politician Penned')
        ->assertDontSee('Citizen Penned');
});

// ── approve ───────────────────────────────────────────────────────────────

test('approve publishes a pending post', function () {
    $admin = makeAdminForPostModeration();
    $post = makeAuthoredPost(['status' => PostStatus::PendingApproval]);

    $this->actingAs($admin)
        ->post(route('admin.posts.approve', $post))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($post->fresh()->status)->toBe(PostStatus::Published)
        ->and($post->fresh()->published_at)->not->toBeNull()
        ->and($post->fresh()->archived_at)->toBeNull();
});

test('approve rejects an already-published post', function () {
    $admin = makeAdminForPostModeration();
    $post = makeAuthoredPost();
    $post->update(['status' => PostStatus::Published, 'published_at' => now()->subHour()]);

    $this->actingAs($admin)
        ->post(route('admin.posts.approve', $post))
        ->assertSessionHasErrors(['error']);

    expect($post->fresh()->status)->toBe(PostStatus::Published);
});

// ── unpublish ─────────────────────────────────────────────────────────────

test('unpublish returns a published post to pending', function () {
    $admin = makeAdminForPostModeration();
    $post = makeAuthoredPost();
    $post->update(['status' => PostStatus::Published, 'published_at' => now()->subHour()]);

    $this->actingAs($admin)
        ->post(route('admin.posts.unpublish', $post))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($post->fresh()->status)->toBe(PostStatus::PendingApproval)
        ->and($post->fresh()->published_at)->toBeNull();
});

test('unpublish rejects a non-published post', function () {
    $admin = makeAdminForPostModeration();
    $post = makeAuthoredPost(['status' => PostStatus::Draft]);

    $this->actingAs($admin)
        ->post(route('admin.posts.unpublish', $post))
        ->assertSessionHasErrors(['error']);

    expect($post->fresh()->status)->toBe(PostStatus::Draft);
});

// ── archive ───────────────────────────────────────────────────────────────

test('archive archives a published post', function () {
    $admin = makeAdminForPostModeration();
    $post = makeAuthoredPost();
    $post->update(['status' => PostStatus::Published, 'published_at' => now()->subHour()]);

    $this->actingAs($admin)
        ->post(route('admin.posts.archive', $post))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($post->fresh()->status)->toBe(PostStatus::Archived)
        ->and($post->fresh()->archived_at)->not->toBeNull();
});

test('archive rejects a non-published post', function () {
    $admin = makeAdminForPostModeration();
    $post = makeAuthoredPost(['status' => PostStatus::Draft]);

    $this->actingAs($admin)
        ->post(route('admin.posts.archive', $post))
        ->assertSessionHasErrors(['error']);

    expect($post->fresh()->status)->toBe(PostStatus::Draft);
});

// ── restore ───────────────────────────────────────────────────────────────

test('restore returns an archived post to pending', function () {
    $admin = makeAdminForPostModeration();
    $post = makeAuthoredPost();
    $post->update(['status' => PostStatus::Archived, 'archived_at' => now()->subHour()]);

    $this->actingAs($admin)
        ->post(route('admin.posts.restore', $post))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($post->fresh()->status)->toBe(PostStatus::PendingApproval)
        ->and($post->fresh()->archived_at)->toBeNull();
});

test('restore rejects a non-archived post', function () {
    $admin = makeAdminForPostModeration();
    $post = makeAuthoredPost();
    $post->update(['status' => PostStatus::Published, 'published_at' => now()->subHour()]);

    $this->actingAs($admin)
        ->post(route('admin.posts.restore', $post))
        ->assertSessionHasErrors(['error']);

    expect($post->fresh()->status)->toBe(PostStatus::Published);
});

// ── destroy ───────────────────────────────────────────────────────────────

test('destroy deletes a post and redirects to the index', function () {
    $admin = makeAdminForPostModeration();
    $post = makeAuthoredPost();

    $this->actingAs($admin)
        ->delete(route('admin.posts.destroy', $post))
        ->assertRedirect(route('admin.posts.index'))
        ->assertSessionHas('success');

    $this->assertDatabaseMissing('posts', ['id' => $post->id]);
});

// ── bulkAction ────────────────────────────────────────────────────────────

test('bulk action approves selected publishable posts', function () {
    $admin = makeAdminForPostModeration();
    $pending = makeAuthoredPost(['status' => PostStatus::PendingApproval]);
    $published = makeAuthoredPost();
    $published->update(['status' => PostStatus::Published, 'published_at' => now()->subHour()]);

    $this->actingAs($admin)
        ->post(route('admin.posts.bulk-action'), [
            'action' => 'approve',
            'post_ids' => [$pending->id, $published->id],
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    // Only the pending post was publishable; the already-published one is skipped.
    expect($pending->fresh()->status)->toBe(PostStatus::Published)
        ->and($published->fresh()->status)->toBe(PostStatus::Published);
});

test('bulk action rejects an invalid action', function () {
    $admin = makeAdminForPostModeration();
    $post = makeAuthoredPost(['status' => PostStatus::PendingApproval]);

    $this->actingAs($admin)
        ->post(route('admin.posts.bulk-action'), [
            'action' => 'bogus',
            'post_ids' => [$post->id],
        ])
        ->assertSessionHasErrors(['action']);
});

test('bulk action requires at least one selected post', function () {
    $admin = makeAdminForPostModeration();

    $this->actingAs($admin)
        ->post(route('admin.posts.bulk-action'), [
            'action' => 'approve',
            'post_ids' => [],
        ])
        ->assertSessionHasErrors(['post_ids']);
});

// ── Authorization ─────────────────────────────────────────────────────────

test('post moderation routes are forbidden for non-admin users', function () {
    $user = User::factory()->create(['platform' => 'standalone', 'user_type' => 'voter']);
    if (method_exists($user, 'assignRole')) {
        $user->assignRole('voter');
    }
    skipOnboarding($user, 'voter');

    $this->actingAs($user)
        ->get(route('admin.posts.index'))
        ->assertForbidden();
});