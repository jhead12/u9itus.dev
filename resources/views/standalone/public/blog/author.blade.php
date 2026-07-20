@extends('standalone.layouts.public')

@section('title', 'Blog — ' . ($author->full_name ?? $author->name ?? 'Author'))
@section('meta_description', 'Posts by ' . ($author->full_name ?? $author->name ?? 'U9itus') . ' on U9itus.')

@section('content')
<div class="min-h-screen bg-slate-950 py-12">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-8">
            <a href="{{ route('blog.index') }}" class="text-sm text-slate-400 hover:text-white transition">← All posts</a>
            <h1 class="mt-3 text-3xl sm:text-4xl font-bold text-white">
                {{ $author->full_name ?? $author->name ?? 'U9itus Author' }}
            </h1>
            <p class="mt-2 text-slate-400 capitalize">{{ $authorType }} · {{ $posts->total() }} post{{ $posts->total() !== 1 ? 's' : '' }}</p>
        </div>

        @if($posts->isEmpty())
        <div class="bg-slate-900/50 border border-slate-800 rounded-xl p-8 text-center">
            <p class="text-slate-400">No published posts yet.</p>
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
                    <div class="mt-4 text-xs text-slate-500">
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
