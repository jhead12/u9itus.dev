<?php

namespace App\Http\Controllers\Standalone;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Models\Citizen;
use App\Models\Post;
use App\Models\Politician;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin moderation for native blog Posts.
 *
 * Authors (politicians and citizens) create and publish posts themselves via
 * PostController. This controller is the admin backstop: review the pending
 * queue (including marketing auto-drafted posts), unpublish/archive content
 * that violates policy, and restore or delete it.
 *
 * The public blog surfaces (PublicPostController) gate strictly on
 * status=Published with a non-future published_at, so any status flip here is
 * reflected on /blog, RSS, and author/topic pages immediately.
 *
 * Authorization is handled by the admin route middleware group
 * (role:admin + onboarding + 2fa); no policies are used, consistent with the
 * rest of the admin surface.
 */
class AdminPostController extends Controller
{
    /** Statuses an admin may move a post to Published from. */
    private const PUBLISHABLE_FROM = [
        PostStatus::Draft,
        PostStatus::PendingApproval,
        PostStatus::Archived,
    ];

    /**
     * Paginated, filterable list of all posts across every author.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');
        $authorType = (string) $request->query('author_type', '');

        $allowedStatuses = array_map(
            static fn (PostStatus $s) => $s->value,
            PostStatus::cases()
        );
        $allowedAuthorTypes = [
            Politician::class => 'Politician',
            Citizen::class => 'Citizen',
        ];

        $posts = Post::query()
            ->with(['author', 'topics'])
            ->when($search !== '', function (Builder $q) use ($search) {
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
                $q->where(fn (Builder $q2) => $q2
                    ->where('title', 'like', $like)
                    ->orWhere('slug', 'like', $like)
                    ->orWhere('excerpt', 'like', $like));
            })
            ->when(in_array($status, $allowedStatuses, true), fn (Builder $q) => $q->where('status', $status))
            ->when(array_key_exists($authorType, $allowedAuthorTypes), fn (Builder $q) => $q->where('author_type', $authorType))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('standalone.admin.posts.index', [
            'posts' => $posts,
            'statuses' => PostStatus::cases(),
            'authorTypes' => $allowedAuthorTypes,
        ]);
    }

    /**
     * Publish a post (Draft / Pending / Archived -> Published). Mirrors
     * PostController::publish().
     */
    public function approve(Request $request, Post $post): RedirectResponse
    {
        if (! in_array($post->status, self::PUBLISHABLE_FROM, true)) {
            return back()->withErrors(['error' => 'This post is already published.']);
        }

        $post->update([
            'status' => PostStatus::Published->value,
            'published_at' => now(),
            'archived_at' => null,
        ]);

        return back()->with('success', 'Post "' . $post->title . '" has been published.');
    }

    /**
     * Unpublish a live post back to the pending queue. Net-new — authors have
     * no equivalent action.
     */
    public function unpublish(Request $request, Post $post): RedirectResponse
    {
        if ($post->status !== PostStatus::Published) {
            return back()->withErrors(['error' => 'Only published posts can be unpublished.']);
        }

        $post->update([
            'status' => PostStatus::PendingApproval->value,
            'published_at' => null,
        ]);

        return back()->with('success', 'Post "' . $post->title . '" has been unpublished and returned to the review queue.');
    }

    /**
     * Archive a published post. Mirrors PostController::archive().
     */
    public function archive(Request $request, Post $post): RedirectResponse
    {
        if ($post->status !== PostStatus::Published) {
            return back()->withErrors(['error' => 'Only published posts can be archived.']);
        }

        $post->update([
            'status' => PostStatus::Archived->value,
            'archived_at' => now(),
        ]);

        return back()->with('success', 'Post "' . $post->title . '" has been archived.');
    }

    /**
     * Restore an archived post to the review queue.
     */
    public function restore(Request $request, Post $post): RedirectResponse
    {
        if ($post->status !== PostStatus::Archived) {
            return back()->withErrors(['error' => 'Only archived posts can be restored.']);
        }

        $post->update([
            'status' => PostStatus::PendingApproval->value,
            'archived_at' => null,
        ]);

        return back()->with('success', 'Post "' . $post->title . '" has been restored to the review queue.');
    }

    /**
     * Permanently delete a post.
     */
    public function destroy(Request $request, Post $post): RedirectResponse
    {
        $title = $post->title;
        $post->delete();

        return redirect()->route('admin.posts.index')
            ->with('success', 'Post "' . $title . '" has been deleted.');
    }

    /**
     * Apply a bulk action to selected posts from the index page.
     */
    public function bulkAction(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:approve,unpublish,archive,restore,delete'],
            'post_ids' => ['required', 'array', 'min:1'],
            'post_ids.*' => ['integer', 'exists:posts,id'],
        ]);

        $action = (string) $validated['action'];
        $postIds = collect($validated['post_ids'])
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $posts = Post::query()->whereIn('id', $postIds)->get();

        if ($posts->isEmpty()) {
            return back()->withErrors(['error' => 'No posts were selected.']);
        }

        $count = 0;
        foreach ($posts as $post) {
            $applied = match ($action) {
                'approve'   => $this->applyApprove($post),
                'unpublish' => $this->applyUnpublish($post),
                'archive'   => $this->applyArchive($post),
                'restore'   => $this->applyRestore($post),
                'delete'     => (function () use ($post): bool {
                    $post->delete();
                    return true;
                })(),
                default => false,
            };

            if ($applied) {
                $count++;
            }
        }

        return back()->with('success', $count . ' post(s) affected by "' . $action . '" action.');
    }

    private function applyApprove(Post $post): bool
    {
        if (! in_array($post->status, self::PUBLISHABLE_FROM, true)) {
            return false;
        }

        $post->update([
            'status' => PostStatus::Published->value,
            'published_at' => now(),
            'archived_at' => null,
        ]);

        return true;
    }

    private function applyUnpublish(Post $post): bool
    {
        if ($post->status !== PostStatus::Published) {
            return false;
        }

        $post->update([
            'status' => PostStatus::PendingApproval->value,
            'published_at' => null,
        ]);

        return true;
    }

    private function applyArchive(Post $post): bool
    {
        if ($post->status !== PostStatus::Published) {
            return false;
        }

        $post->update([
            'status' => PostStatus::Archived->value,
            'archived_at' => now(),
        ]);

        return true;
    }

    private function applyRestore(Post $post): bool
    {
        if ($post->status !== PostStatus::Archived) {
            return false;
        }

        $post->update([
            'status' => PostStatus::PendingApproval->value,
            'archived_at' => null,
        ]);

        return true;
    }
}