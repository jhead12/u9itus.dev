@extends('standalone.layouts.public')

@section('title', 'Blog — ' . $topic->name)
@section('meta_description', 'Latest posts about ' . $topic->name . ' on U9itus.')

@section('content')
<div class="min-h-screen bg-slate-950 py-12">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-8">
            <a href="{{ route('blog.index') }}" class="text-sm text-slate-400 hover:text-white transition">← All posts</a>
            <h1 class="mt-3 text-3xl sm:text-4xl font-bold text-white">{{ $topic->name }}</h1>
            <p class="mt-2 text-slate-400">Posts tagged with {{ $topic->name }}.</p>
        </div>

        @if($featured)
        <div class="mb-8 bg-gradient-to-r from-amber-900/30 to-purple-900/30 border border-amber-500/30 rounded-2xl overflow-hidden">
            <div class="p-6 sm:p-8">
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-xs font-bold tracking-wider text-amber-400 uppercase bg-amber-500/10 border border-amber-500/20 rounded-full px-3 py-1">Featured</span>
                </div>
                <h2 class="text-2xl sm:text-3xl font-bold text-white">
                    <a href="{{ route('blog.show', $featured) }}" class="hover:text-amber-400 transition">{{ $featured->title }}</a>
                </h2>
                @if($featured->excerpt)
                <p class="mt-3 text-slate-300 line-clamp-3 max-w-2xl">{{ $featured->excerpt }}</p>
                @endif
                <div class="mt-4 flex items-center gap-2 text-sm text-slate-400">
                    <span>{{ $featured->author?->full_name ?? $featured->author?->name ?? 'U9itus' }}</span>
                    <span>·</span>
                    <time datetime="{{ $featured->published_at->toIso8601String() }}">{{ $featured->published_at->format('M j, Y') }}</time>
                </div>
            </div>
        </div>
        @endif

        @if($posts->isEmpty())
        <div class="bg-slate-900/50 border border-slate-800 rounded-xl p-8 text-center">
            <p class="text-slate-400">No published posts in this topic yet.</p>
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
                        <time datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->format('M j, Y') }}</time>
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
