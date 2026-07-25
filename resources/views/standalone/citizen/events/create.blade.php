@extends('standalone.layouts.dashboard')

@section('title', 'Create Event')
@section('page-title', 'Create Civic Event')

@section('content')
<div class="max-w-4xl">
    <div class="mb-6">
        <a href="{{ route('citizen.events.index') }}" class="text-sm text-slate-400 hover:text-white transition">← Back to events</a>
    </div>

    @include('standalone.events.form', ['prefix' => 'citizen', 'event' => null, 'topics' => $topics])
</div>
@endsection
