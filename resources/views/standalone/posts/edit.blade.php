@extends('standalone.layouts.dashboard')

@section('title', 'Edit Blog Post')
@section('page-title', 'Edit Blog Post')

@php($routePrefix = auth()->user()->hasRole('politician') ? 'politician.posts' : 'citizen.posts')

@section('content')
<div class="max-w-2xl space-y-6">
    <div class="mb-6">
        <a href="{{ route($routePrefix . '.index') }}" class="text-sm text-slate-400 hover:text-white transition">← Back to posts</a>
    </div>

    @if(session('success'))
    <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm text-emerald-200">
        {{ session('success') }}
    </div>
    @endif

    @include('standalone.posts._form', ['post' => $post, 'topics' => $topics, 'selectedTopicIds' => $selectedTopicIds])

    <!-- Publishing actions -->
    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6 space-y-3">
        <h2 class="text-sm font-semibold text-slate-200">Publishing</h2>
        <div class="flex flex-wrap items-center gap-3">
            @if($post->status->value !== 'published')
            <form method="POST" action="{{ route($routePrefix . '.publish', $post) }}">
                @csrf
                <button type="submit"
                        class="bg-emerald-600 hover:bg-emerald-500 text-white font-semibold rounded-lg px-4 py-2 text-sm transition">
                    Publish Now
                </button>
            </form>
            @endif

            @if($post->status->value === 'published')
            <form method="POST" action="{{ route($routePrefix . '.archive', $post) }}">
                @csrf
                <button type="submit"
                        class="bg-slate-700 hover:bg-slate-600 text-white font-semibold rounded-lg px-4 py-2 text-sm transition">
                    Archive
                </button>
            </form>
            @endif

            <a href="{{ route('blog.show', $post) }}" target="_blank"
               class="text-amber-400 hover:text-amber-300 text-sm font-medium">
                View public page →
            </a>
        </div>
    </div>

    <!-- Promotion placeholder (Phase 3) -->
    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6">
        <h2 class="text-sm font-semibold text-slate-200">Promote This Post</h2>
        <p class="text-sm text-slate-400 mt-1">
            Spend wallet credits to feature this post on topic pages, the voter ad room, and the map. (Coming in Phase 3.)
        </p>
    </div>
</div>
@endsection
