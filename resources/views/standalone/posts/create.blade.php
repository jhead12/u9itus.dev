@extends('standalone.layouts.dashboard')

@section('title', 'New Blog Post')
@section('page-title', 'New Blog Post')

@section('content')
<div class="max-w-6xl">
    <div class="mb-6">
        <a href="{{ route(auth()->user()->hasRole('politician') ? 'politician.posts.index' : 'citizen.posts.index') }}"
           class="text-sm text-slate-400 hover:text-white transition">
            ← Back to posts
        </a>
    </div>

    @include('standalone.posts._form', ['post' => null, 'topics' => $topics, 'selectedTopicIds' => []])
</div>
@endsection
