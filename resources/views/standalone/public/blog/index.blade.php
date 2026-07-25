@extends('standalone.layouts.public')

@section('title', 'Blog' . ($activeTopic ? ' — ' . $activeTopic->label : ''))
@section('meta_description', 'Latest civic and political updates, community stories, and campaign news from U9itus.')

@section('content')
<div class="min-h-screen bg-slate-950 py-12">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-8">
            <h1 class="text-3xl sm:text-4xl font-bold text-white">U9itus Blog</h1>
            <p class="mt-2 text-slate-400">Local stories, campaign updates, ballot-measure explainers, and civic events.</p>
        </div>

        <!-- Topic filters -->
        @if($topics->isNotEmpty())
        <div class="mb-8 flex flex-wrap gap-2">
            <a href="{{ route('blog.index') }}"
               class="text-sm rounded-full px-3 py-1 border transition
                  {{ $activeTopic ? 'border-slate-700 text-slate-400 hover:text-white' : 'border-amber-500 text-amber-400' }}">
                All
            </a>
            @foreach($topics as $topic)
            <a href="{{ route('blog.topic', $topic) }}"
               class="text-sm rounded-full px-3 py-1 border transition
                  {{ $activeTopic && $activeTopic->id === $topic->id ? 'border-amber-500 text-amber-400' : 'border-slate-700 text-slate-400 hover:text-white' }}">
                {{ $topic->name }}
            </a>
            @endforeach
        </div>
        @endif

        @if($posts->isEmpty())
        <div class="bg-slate-900/50 border border-slate-800 rounded-xl p-8 text-center">
            <p class="text-slate-400">No published posts yet. Check back soon.</p>
        </div>
        @else
        <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($posts as $post)
            <article class="bg-slate-900/60 border border-slate-800 rounded-xl overflow-hidden hover:border-slate-700 transition">
                @if($post->featured_image_url)
                <a href="{{ route('blog.show', $post) }}">
                    <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}" class="w-full h-40 object-cover" />
                </a>
                @endif
                <div class="p-5">
                    @if($post->topics->isNotEmpty())
                    <div class="flex flex-wrap gap-2 mb-3">
                        @foreach($post->topics as $topic)
                        <a href="{{ route('blog.topic', $topic) }}" class="text-xs text-amber-400 hover:text-amber-300">
                            {{ $topic->name }}
                        </a>
                        @endforeach
                    </div>
                    @endif

                    <h2 class="text-lg font-semibold text-white">
                        <a href="{{ route('blog.show', $post) }}" class="hover:text-amber-400 transition">
                            {{ $post->title }}
                        </a>
                    </h2>

                    @if($post->excerpt)
                    <p class="mt-2 text-sm text-slate-400 line-clamp-3">{{ $post->excerpt }}</p>
                    @endif

                    <div class="mt-4 flex items-center gap-2 text-xs text-slate-500">
                        <span>{{ $post->author?->full_name ?? $post->author?->name ?? 'U9itus' }}</span>
                        <span>·</span>
                        <time datetime="{{ $post->published_at->toIso8601String() }}">
                            {{ $post->published_at->format('M j, Y') }}
                        </time>
                    </div>
                </div>
            </article>
            @endforeach
        </div>

        <div class="mt-8">
            {{ $posts->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
