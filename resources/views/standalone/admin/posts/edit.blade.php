@extends('standalone.layouts.dashboard')
@section('title', $post->exists ? 'Edit post' : 'New draft')
@section('page-title', 'Blog editor')
@section('content')
<div class="max-w-4xl space-y-5">
    <h2 class="text-2xl font-bold">{{ $post->exists ? 'Edit post' : 'Write a draft' }}</h2>
    <p class="text-slate-400">New posts are saved as drafts. Publishing requires a separate approval. Your staff display name appears as the author.</p>
    @if($errors->any())<p role="alert" class="text-red-300">{{ $errors->first() }}</p>@endif
    <form method="POST" action="{{ $post->exists ? route('admin.posts.update', $post) : route('admin.posts.store') }}" class="space-y-4">
        @csrf
        @if($post->exists) @method('PUT') @endif
        @foreach(['title' => 'Title', 'subtitle' => 'Subtitle'] as $field => $label)
            <label class="block"><span>{{ $label }}</span><input name="{{ $field }}" maxlength="255" @required($field === 'title') value="{{ old($field, $post->$field) }}" class="mt-1 block w-full rounded-lg bg-slate-800 border-slate-600"></label>
        @endforeach
        <label class="block"><span>Excerpt</span><textarea name="excerpt" rows="3" maxlength="2000" class="mt-1 block w-full rounded-lg bg-slate-800 border-slate-600">{{ old('excerpt', $post->excerpt) }}</textarea></label>
        <label class="block"><span>Body (text or supported HTML)</span><textarea name="body" required rows="18" maxlength="100000" class="mt-1 block w-full rounded-lg bg-slate-800 border-slate-600">{{ old('body', $post->body) }}</textarea></label>
        <button class="rounded-lg bg-emerald-600 px-5 py-2">Save {{ $post->exists ? 'changes' : 'draft' }}</button>
    </form>
</div>
@endsection
