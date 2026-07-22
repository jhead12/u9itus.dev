@extends('standalone.layouts.dashboard')

@section('title', $post->title)
@section('page-title', 'Blog Post')

@php($routePrefix = auth()->user()->hasRole('politician') ? 'politician.posts' : 'citizen.posts')

@section('content')
<div class="max-w-2xl space-y-6">
    <div class="mb-6">
        <a href="{{ route($routePrefix . '.index') }}" class="text-sm text-slate-400 hover:text-white transition">← Back to posts</a>
    </div>

    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6 space-y-4">
        <div class="flex items-center gap-2 text-sm">
            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                @match($post->status->value)
                    @case('published') bg-emerald-500/10 text-emerald-300 @break
                    @case('pending_approval') bg-amber-500/10 text-amber-300 @break
                    @case('archived') bg-slate-600/20 text-slate-400 @break
                    @default bg-slate-600/20 text-slate-400
                @endmatch">
                {{ ucfirst(str_replace('_', ' ', $post->status->value)) }}
            </span>
            @if($post->published_at)
            <span class="text-slate-400">{{ $post->published_at->format('F j, Y') }}</span>
            @endif
        </div>

        <h1 class="text-2xl font-bold text-white">{{ $post->title }}</h1>
        @if($post->subtitle)
        <p class="text-lg text-slate-300">{{ $post->subtitle }}</p>
        @endif

        @if($post->featured_image_url)
        <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}" class="w-full rounded-lg" />
        @endif

        <div class="prose prose-invert max-w-none">
            {!! $post->body !!}
        </div>
    </div>

    <div class="flex items-center gap-4">
        <a href="{{ route($routePrefix . '.edit', $post) }}"
           class="bg-amber-500 hover:bg-amber-400 text-slate-900 font-semibold rounded-lg px-4 py-2 text-sm transition-colors">
            Edit Post
        </a>
        @if($post->status->value === 'published')
        <a href="{{ route('blog.show', $post) }}" target="_blank" class="text-slate-400 hover:text-white text-sm">View public page →</a>
        @endif
    </div>
</div>
@endsection
