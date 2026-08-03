<?php

namespace App\Http\Controllers\Standalone;

use App\Http\Controllers\Controller;
use App\Models\Citizen;
use App\Models\Politician;
use App\Models\PoliticianTopic;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Public blog discovery surfaces.
 */
class PublicPostController extends Controller
{
    private const PER_PAGE = 12;

    public function index(Request $request)
    {
        $topicSlug = $request->query('topic');
        $topic = null;

        $posts = Post::query()
            ->with(['author', 'topics'])
            ->published();

        if ($topicSlug) {
            $topic = PoliticianTopic::where('slug', $topicSlug)->firstOrFail();
            $posts->whereHas('topics', fn ($q) => $q->where('politician_topics.id', $topic->id));
        }

        $posts = $posts->latest('published_at')->paginate(self::PER_PAGE)
            ->withQueryString();

        $topics = Cache::remember('blog_topics', 300, fn () => PoliticianTopic::orderBy('sort_order')->orderBy('name')->get());

        return view('standalone.public.blog.index', [
            'posts' => $posts,
            'topics' => $topics,
            'activeTopic' => $topic,
        ]);
    }

    public function topic(string $slug)
    {
        $topic = PoliticianTopic::where('slug', $slug)->firstOrFail();

        $posts = Post::query()
            ->with(['author', 'topics'])
            ->published()
            ->whereHas('topics', fn ($q) => $q->where('politician_topics.id', $topic->id))
            ->latest('published_at')
            ->paginate(self::PER_PAGE);

        $featured = Cache::remember("blog_featured_topic_{$topic->id}", 300, fn () => Post::query()
            ->with('author')
            ->published()
            ->promoted()
            ->whereHas('topics', fn ($q) => $q->where('politician_topics.id', $topic->id))
            ->inRandomOrder()
            ->first());

        $topics = Cache::remember('blog_topics', 300, fn () => PoliticianTopic::orderBy('sort_order')->orderBy('name')->get());

        return view('standalone.public.blog.topic', [
            'topic' => $topic,
            'posts' => $posts,
            'topics' => $topics,
            'featured' => $featured,
        ]);
    }

    public function author(string $type, string $slug)
    {
        $model = match ($type) {
            'politician' => Politician::class,
            'citizen' => Citizen::class,
            default => abort(404),
        };

        $author = $model::where('slug', $slug)->firstOrFail();

        $posts = Post::query()
            ->with(['topics'])
            ->published()
            ->where('author_type', $model)
            ->where('author_id', $author->id)
            ->latest('published_at')
            ->paginate(self::PER_PAGE);

        return view('standalone.public.blog.author', [
            'author' => $author,
            'authorType' => $type,
            'posts' => $posts,
        ]);
    }

    public function show(string $slug)
    {
        $post = Post::query()
            ->with(['author', 'topics'])
            ->where('slug', $slug)
            ->published()
            ->firstOrFail();

        $related = Post::query()
            ->with('author')
            ->published()
            ->where('id', '!=', $post->id)
            ->whereHas('topics', function ($q) use ($post): void {
                $topicIds = $post->topics->pluck('id')->all();
                $q->whereIn('politician_topics.id', $topicIds);
            })
            ->latest('published_at')
            ->take(3)
            ->get();

        return view('standalone.public.blog.show', [
            'post' => $post,
            'relatedPosts' => $related,
        ]);
    }

    public function feed()
    {
        $posts = Post::query()
            ->with('author')
            ->published()
            ->latest('published_at')
            ->take(20)
            ->get();

        return response()
            ->view('standalone.public.blog.feed', ['posts' => $posts])
            ->header('Content-Type', 'application/rss+xml; charset=utf-8');
    }
}
