@extends('standalone.layouts.public')

@section('title', $post->meta_title ?? $post->title)
@section('meta_description', $post->meta_description ?? $post->excerpt)
@section('canonical', $post->canonical_url ?? route('blog.show', $post))

@push('meta')
    <meta property="og:type" content="article">
    <meta property="og:url" content="{{ $post->canonical_url ?? route('blog.show', $post) }}">
    <meta property="og:title" content="{{ $post->meta_title ?? $post->title }}">
    <meta property="og:description" content="{{ Str::limit($post->meta_description ?? $post->excerpt ?? strip_tags($post->body ?? ''), 160) }}">
    @if($post->featured_image_url)
    <meta property="og:image" content="{{ $post->featured_image_url }}">
    @endif
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $post->meta_title ?? $post->title }}">
    <meta name="twitter:description" content="{{ Str::limit($post->meta_description ?? $post->excerpt ?? strip_tags($post->body ?? ''), 160) }}">
    @if($post->featured_image_url)
    <meta name="twitter:image" content="{{ $post->featured_image_url }}">
    @endif
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@@type": "BlogPosting",
        "headline": "{{ $post->title }}",
        "description": "{{ Str::limit($post->meta_description ?? $post->excerpt ?? strip_tags($post->body ?? ''), 200) }}",
        "url": "{{ $post->canonical_url ?? route('blog.show', $post) }}",
        "datePublished": "{{ $post->published_at->toIso8601String() }}",
        "author": {
            "@@type": "Organization",
            "name": "{{ $post->author?->full_name ?? $post->author?->name ?? 'U9itus' }}"
        },
        "publisher": {
            "@@type": "Organization",
            "name": "{{ config('app.name', 'U9itus') }}"
        }
    }
    </script>
@endpush

@section('content')
<div class="min-h-screen bg-slate-950 py-12">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <article class="bg-slate-900/60 border border-slate-800 rounded-2xl overflow-hidden">
            @if($post->featured_image_url)
            <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}" class="w-full h-64 sm:h-80 object-cover" />
            @endif

            <div class="p-6 sm:p-10">
                @if($post->topics->isNotEmpty())
                <div class="flex flex-wrap gap-2 mb-4">
                    @foreach($post->topics as $topic)
                    <a href="{{ route('blog.topic', $topic) }}"
                       class="text-xs font-medium text-amber-400 hover:text-amber-300 bg-amber-500/10 border border-amber-500/20 rounded-full px-3 py-1 transition">
                        {{ $topic->name }}
                    </a>
                    @endforeach
                </div>
                @endif

                <h1 class="text-3xl sm:text-4xl font-bold text-white">{{ $post->title }}</h1>

                @if($post->subtitle)
                <p class="mt-3 text-xl text-slate-300">{{ $post->subtitle }}</p>
                @endif

                <div class="mt-6 flex items-center gap-3 text-sm text-slate-400">
                    <span class="font-medium text-white">{{ $post->author?->full_name ?? $post->author?->name ?? 'U9itus' }}</span>
                    <span>·</span>
                    <time datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->format('F j, Y') }}</time>
                    @if($post->location_name)
                    <span>·</span>
                    <span>{{ $post->location_name }}</span>
                    @endif
                </div>

                @include('standalone.shared.referral-share-actions', [
                    'shareLink' => $post->canonical_url ?? route('blog.show', $post),
                    'shareSubject' => $post->title,
                    'shareMessage' => $post->excerpt ?? $post->subtitle ?? $post->title,
                ])

                <div class="mt-8 prose prose-invert prose-lg max-w-none">
                    {!! $post->body !!}
                </div>
            </div>
        </article>

        @if($relatedPosts->isNotEmpty())
        <div class="mt-10">
            <h2 class="text-xl font-semibold text-white mb-4">Related posts</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach($relatedPosts as $related)
                <a href="{{ route('blog.show', $related) }}" class="block bg-slate-900/60 border border-slate-800 rounded-xl p-4 hover:border-slate-700 transition">
                    <h3 class="font-semibold text-white">{{ $related->title }}</h3>
                    <p class="mt-1 text-sm text-slate-400 line-clamp-2">{{ $related->excerpt }}</p>
                </a>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
