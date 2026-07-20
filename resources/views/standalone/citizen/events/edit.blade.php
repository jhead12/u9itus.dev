@extends('standalone.layouts.dashboard')

@section('title', 'Edit Event')
@section('page-title', 'Edit Civic Event')

@section('content')
<div class="max-w-4xl">
    <div class="mb-6">
        <a href="{{ route('citizen.events.index') }}" class="text-sm text-slate-400 hover:text-white transition">← Back to events</a>
    </div>

    @include('standalone.events.form', ['prefix' => 'citizen', 'event' => $event, 'topics' => $topics])
</div>
@endsection
