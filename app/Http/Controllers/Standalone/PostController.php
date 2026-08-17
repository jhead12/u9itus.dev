<?php

namespace App\Http\Controllers\Standalone;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\PromotePostRequest;
use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Models\Citizen;
use App\Models\Politician;
use App\Models\PoliticianTopic;
use App\Models\Post;
use App\Services\MediaStorageService;
use App\Services\PostEmbedService;
use App\Services\PostPromotionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
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

    private function postsQuery(): Builder
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
        $topics = PoliticianTopic::orderBy('sort_order')->orderBy('name')->get();

        return view('standalone.posts.create', [
            'topics' => $topics,
        ]);
    }

    public function store(StorePostRequest $request, MediaStorageService $media)
    {
        [$authorType, $authorId] = $this->requireAuthor();

        $data = $request->validated();
        unset($data['featured_image_file']);
        $imageUploadFailed = false;

        if ($request->hasFile('featured_image_file')) {
            $uploadedUrl = $media->storeImage(
                $request->file('featured_image_file'),
                $this->imageDirectory($authorType, $authorId),
                $authorId
            );
            if ($uploadedUrl) {
                $data['featured_image_url'] = $uploadedUrl;
            } else {
                $imageUploadFailed = true;
            }
        }

        $data['author_type'] = $authorType;
        $data['author_id'] = $authorId;
        $data['status'] = PostStatus::Draft->value;

        $topicIds = $request->input('topic_ids', []);
        unset($data['topic_ids']);

        $post = Post::create($data);
        if (! empty($topicIds)) {
            $post->topics()->sync($topicIds);
        }

        $redirect = redirect()->route($this->rolePrefix().'.posts.edit', $post);

        return $imageUploadFailed
            ? $redirect->with('error', 'Post saved, but the featured image could not be uploaded. Please try again.')
            : $redirect->with('success', 'Post saved as draft.');
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

        $topics = PoliticianTopic::orderBy('sort_order')->orderBy('name')->get();

        return view('standalone.posts.edit', [
            'post' => $post,
            'topics' => $topics,
            'selectedTopicIds' => $post->topics->pluck('id')->all(),
        ]);
    }

    public function update(UpdatePostRequest $request, Post $post, MediaStorageService $media)
    {
        $this->authorizeOwnership($post);

        $data = $request->validated();
        unset($data['featured_image_file']);
        $topicIds = $request->input('topic_ids', []);
        unset($data['topic_ids']);
        $imageUploadFailed = false;

        if ($request->hasFile('featured_image_file')) {
            $uploadedUrl = $media->storeImage(
                $request->file('featured_image_file'),
                $this->imageDirectory($post->author_type, (int) $post->author_id),
                (int) $post->author_id
            );
            if ($uploadedUrl) {
                $media->deleteByUrl($post->featured_image_url);
                $data['featured_image_url'] = $uploadedUrl;
            } else {
                $imageUploadFailed = true;
            }
        }

        $post->update($data);
        $post->topics()->sync($topicIds);

        // Autosave hits this same action in the background (either a fetch() with
        // Accept: application/json, or a beforeunload sendBeacon() call, which
        // can't set headers — hence the explicit marker field). Either way, give
        // it a lightweight response instead of a redirect, and skip session flash
        // messages entirely so a background save can't leave a stale banner for
        // the user to see on their next real page load.
        if ($request->wantsJson() || $request->boolean('_background_save')) {
            // Post::boot() regenerates the slug when the title changes, which
            // this same request may just have done — every slug-keyed URL on
            // the page (this update action included) is now stale. Report the
            // current ones back so the client can patch itself instead of the
            // next background save 404ing against the old slug.
            return response()->json([
                'saved' => ! $imageUploadFailed,
                'editUrl' => route($this->rolePrefix().'.posts.edit', $post),
                'updateUrl' => route($this->rolePrefix().'.posts.update', $post),
                'publishUrl' => route($this->rolePrefix().'.posts.publish', $post),
                'archiveUrl' => route($this->rolePrefix().'.posts.archive', $post),
                'publicUrl' => route('blog.show', $post),
            ]);
        }

        $redirect = redirect()->route($this->rolePrefix().'.posts.edit', $post);

        return $imageUploadFailed
            ? $redirect->with('error', 'Post updated, but the featured image could not be uploaded. Please try again.')
            : $redirect->with('success', 'Post updated.');
    }

    public function destroy(Post $post)
    {
        $this->authorizeOwnership($post);

        $post->delete();

        return redirect()
            ->route($this->rolePrefix().'.posts.index')
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
        } catch (\LogicException|\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Post promoted for {$days} day(s).");
    }

    /**
     * Upload an image for use inline in the post body editor.
     */
    public function uploadImage(Request $request, MediaStorageService $media): JsonResponse
    {
        [$authorType, $authorId] = $this->requireAuthor();

        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ]);

        $url = $media->storeImage($request->file('image'), $this->imageDirectory($authorType, $authorId), $authorId);

        abort_unless($url, 422, 'That image could not be processed.');

        return response()->json(['url' => $url]);
    }

    /**
     * Resolve a pasted YouTube / Instagram / SoundCloud / X / TikTok URL into
     * embeddable HTML for use inline in the post body editor.
     */
    public function createEmbed(Request $request, PostEmbedService $embeds): JsonResponse
    {
        $this->requireAuthor();

        $request->validate([
            'url' => ['required', 'url', 'max:2048'],
        ]);

        $html = $embeds->resolve($request->string('url')->toString());

        abort_unless($html, 422, 'That URL isn\'t a supported YouTube, Instagram, SoundCloud, X, or TikTok link.');

        return response()->json(['html' => $html]);
    }

    private function imageDirectory(string $authorType, int $authorId): string
    {
        $type = $authorType === Politician::class ? 'politician' : 'citizen';

        return "posts/{$type}/{$authorId}";
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
