@extends('standalone.layouts.dashboard')

@section('title', 'Edit Blog Post')
@section('page-title', 'Edit Blog Post')

@php($routePrefix = auth()->user()->hasRole('politician') ? 'politician.posts' : 'citizen.posts')

@section('content')
<div class="max-w-6xl space-y-6">
    <div class="mb-6">
        <a href="{{ route($routePrefix . '.index') }}" class="text-sm text-slate-400 hover:text-white transition">← Back to posts</a>
    </div>

    @if(session('success'))
    <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-sm text-emerald-200">
        {{ session('success') }}
    </div>
    @endif

    @include('standalone.posts._form', ['post' => $post, 'topics' => $topics, 'selectedTopicIds' => $selectedTopicIds])
</div>
@endsection
