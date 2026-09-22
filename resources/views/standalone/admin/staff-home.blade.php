@extends('standalone.layouts.dashboard')
@section('title', 'Staff workspace')
@section('page-title', 'Staff workspace')
@section('content')
<div class="max-w-4xl space-y-6">
    <h2 class="text-2xl font-bold">Welcome, {{ auth()->user()->name }}</h2>
    <p class="text-slate-400">Your assigned tools are available in the navigation. Contact a Super Admin if you need additional access.</p>
    <div class="rounded-xl border border-slate-700 bg-slate-800 p-5">
        <h3 class="font-semibold mb-3">Your permissions</h3>
        <ul class="grid sm:grid-cols-2 gap-2 text-sm text-slate-300">
            @forelse(collect(\App\Support\AdminAccess::catalog())->filter(fn ($label, $key) => \App\Support\AdminAccess::allowed(auth()->user(), $key)) as $label)
                <li>{{ $label }}</li>
            @empty
                <li>No operational permissions have been assigned yet.</li>
            @endforelse
        </ul>
    </div>
</div>
@endsection
