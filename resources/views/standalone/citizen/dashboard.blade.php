@extends('standalone.layouts.dashboard')

@section('title', 'My Dashboard')
@section('page-title', 'Dashboard')

@section('content')
<div class="space-y-6">

    {{-- Welcome banner --}}
    <div class="bg-gradient-to-r from-amber-500/10 to-slate-800/50 border border-amber-500/20 rounded-xl p-6 flex flex-col sm:flex-row sm:items-center gap-4">
        <div class="flex-1">
            <h2 class="text-lg font-semibold text-white">
                Welcome, {{ $citizen->full_name ?? $user->name }} 🏘️
            </h2>
            <p class="text-slate-400 text-sm mt-0.5">
                Citizen Account
                @if($citizen?->city && $citizen?->state) · {{ $citizen->city }}, {{ $citizen->state }} @endif
            </p>
        </div>
    </div>

    {{-- Coming soon notice --}}
    <div class="bg-slate-800/60 border border-slate-700/50 rounded-xl p-6">
        <h3 class="text-white font-semibold text-base mb-1.5">Campaign tools coming soon</h3>
        <p class="text-slate-400 text-sm">
            Local and community advertising campaigns, along with ballot-issue campaigns, will be
            available here once your account setup is complete.
        </p>
    </div>

</div>
@endsection
