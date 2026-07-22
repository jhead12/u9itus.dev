<?php

namespace App\Http\Controllers\Standalone;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\PromotePostRequest;
use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Models\Citizen;
use App\Models\Politician;
use App\Models\Post;
use App\Services\PostPromotionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Blog post CRUD for Citizen and Politician authors.
 *
 * Routes are mounted under both /citizen and /politician middleware groups;
 * the controller resolves the author from the authenticated user.
 */
class PostController extends Controller
{
    /**
     * Resolve the polymorphic author tuple for the current user.
     *
     * @return array{0: class-string, 1: int}|null
     */
    private function currentAuthor(): ?array
    {
        $user = Auth::user();
        if (! $user) {
            return null;
        }

        if ($user->hasRole('politician')) {
            $politician = $user->politician;
            if ($politician) {
                return [Politician::class, $politician->id];
            }
        }

        if ($user->hasRole('citizen')) {
            $citizen = $user->citizen;
            if ($citizen) {
                return [Citizen::class, $citizen->id];
            }
        }

        return null;
    }

    private function requireAuthor(): array
    {
        $author = $this->currentAuthor();
        abort_unless($author, 403, 'Only citizens and politicians can manage blog posts.');
        return $author;
    }

    private function postsQuery(): \Illuminate\Database\Eloquent\Builder
    {
        [$authorType, $authorId] = $this->requireAuthor();
        return Post::where('author_type', $authorType)
            ->where('author_id', $authorId)
            ->latest();
    }

    public function index(Request $request)
    {
        $posts = $this->postsQuery()->paginate(15);

        return view('standalone.posts.index', [
            'posts' => $posts,
        ]);
    }

    public function create()
    {
        $this->requireAuthor();
        $topics = \App\Models\PoliticianTopic::orderBy('sort_order')->orderBy('name')->get();

        return view('standalone.posts.create', [
            'topics' => $topics,
        ]);
    }

    public function store(StorePostRequest $request)
    {
        [$authorType, $authorId] = $this->requireAuthor();

        $data = $request->validated();
        $data['author_type'] = $authorType;
        $data['author_id'] = $authorId;
        $data['status'] = PostStatus::Draft->value;

        $topicIds = $request->input('topic_ids', []);
        unset($data['topic_ids']);

        $post = Post::create($data);
        if (! empty($topicIds)) {
            $post->topics()->sync($topicIds);
        }

        return redirect()
            ->route($this->rolePrefix() . '.posts.edit', $post)
            ->with('success', 'Post saved as draft.');
    }

    public function show(Post $post)
    {
        $this->authorizeOwnership($post);

        return view('standalone.posts.show', [
            'post' => $post,
        ]);
    }

    public function edit(Post $post)
    {
        $this->authorizeOwnership($post);

        $topics = \App\Models\PoliticianTopic::orderBy('sort_order')->orderBy('name')->get();

        return view('standalone.posts.edit', [
            'post' => $post,
            'topics' => $topics,
            'selectedTopicIds' => $post->topics->pluck('id')->all(),
        ]);
    }

    public function update(UpdatePostRequest $request, Post $post)
    {
        $this->authorizeOwnership($post);

        $data = $request->validated();
        $topicIds = $request->input('topic_ids', []);
        unset($data['topic_ids']);

        $post->update($data);
        $post->topics()->sync($topicIds);

        return redirect()
            ->route($this->rolePrefix() . '.posts.edit', $post)
            ->with('success', 'Post updated.');
    }

    public function destroy(Post $post)
    {
        $this->authorizeOwnership($post);

        $post->delete();

        return redirect()
            ->route($this->rolePrefix() . '.posts.index')
            ->with('success', 'Post deleted.');
    }

    public function publish(Post $post)
    {
        $this->authorizeOwnership($post);

        abort_unless(
            in_array($post->status, [PostStatus::Draft, PostStatus::PendingApproval, PostStatus::Archived], true),
            422,
            'Only draft, pending, or archived posts can be published.'
        );

        // TODO: approval gate for unverified citizens (mirror CitizenCampaign rules).
        $post->update([
            'status' => PostStatus::Published->value,
            'published_at' => now(),
            'archived_at' => null,
        ]);

        return back()->with('success', 'Post published.');
    }

    public function archive(Post $post)
    {
        $this->authorizeOwnership($post);

        abort_unless($post->status === PostStatus::Published, 422, 'Only published posts can be archived.');

        $post->update([
            'status' => PostStatus::Archived->value,
            'archived_at' => now(),
        ]);

        return back()->with('success', 'Post archived.');
    }

    public function promote(PromotePostRequest $request, Post $post, PostPromotionService $service)
    {
        $this->authorizeOwnership($post);

        $days = (int) $request->validated('days');

        try {
            $service->promote($post, $days);
        } catch (\LogicException | \InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Post promoted for {$days} day(s).");
    }

    private function authorizeOwnership(Post $post): void
    {
        $user = Auth::user();
        abort_unless($user, 403);

        [$authorType, $authorId] = $this->currentAuthor();
        abort_unless(
            $post->author_type === $authorType && (int) $post->author_id === (int) $authorId,
            403,
            'You do not own this post.'
        );
    }

    private function rolePrefix(): string
    {
        $user = Auth::user();
        if ($user && $user->hasRole('politician')) {
            return 'politician';
        }
        return 'citizen';
    }
}
