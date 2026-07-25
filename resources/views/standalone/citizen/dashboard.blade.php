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
        @if($user->hasRole('voter'))
        <a href="{{ route('voter.dashboard') }}"
           class="shrink-0 inline-flex items-center gap-1.5 text-xs font-medium text-slate-400 hover:text-white border border-slate-600 hover:border-slate-500 rounded-lg px-3 py-2 transition">
            🗳️ Switch to Voter Portal
        </a>
        @endif
    </div>

    {{-- Stats grid --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Campaigns</p>
            <p class="text-3xl font-bold text-white">{{ number_format($campaignCount) }}</p>
            <p class="text-xs text-slate-500 mt-1">
                @if($campaignCount === 1) 1 campaign @else {{ $campaignCount }} campaigns @endif
            </p>
        </div>

        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Blog Posts</p>
            <p class="text-3xl font-bold text-white">{{ number_format($postCount) }}</p>
            <p class="text-xs text-slate-500 mt-1">
                @if($postCount === 1) 1 post @else {{ $postCount }} posts @endif
            </p>
        </div>

        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Civic Events</p>
            <p class="text-3xl font-bold text-white">{{ number_format($eventCount) }}</p>
            <p class="text-xs text-slate-500 mt-1">
                @if($eventCount === 1) 1 event @else {{ $eventCount }} events @endif
            </p>
        </div>

        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Credit Balance</p>
            <p class="text-3xl font-bold text-emerald-400">${{ number_format($creditBalance, 2) }}</p>
            <p class="text-xs text-slate-500 mt-1">Available for campaigns</p>
        </div>
    </div>

    {{-- Quick actions --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <a href="{{ route('citizen.campaigns.create') }}" class="bg-slate-800/50 border border-slate-700/50 hover:border-amber-500/40 rounded-xl p-4 text-center transition group">
            <div class="text-amber-400 mb-2 flex justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            </div>
            <p class="text-xs font-medium text-slate-300 group-hover:text-white transition">New Campaign</p>
        </a>

        <a href="{{ route('citizen.campaigns.index') }}" class="bg-slate-800/50 border border-slate-700/50 hover:border-emerald-500/40 rounded-xl p-4 text-center transition group">
            <div class="text-emerald-400 mb-2 flex justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.87v6.26a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
            </div>
            <p class="text-xs font-medium text-slate-300 group-hover:text-white transition">View Campaigns</p>
        </a>

        <a href="{{ route('citizen.posts.create') }}" class="bg-slate-800/50 border border-slate-700/50 hover:border-indigo-500/40 rounded-xl p-4 text-center transition group">
            <div class="text-indigo-400 mb-2 flex justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
            </div>
            <p class="text-xs font-medium text-slate-300 group-hover:text-white transition">New Post</p>
        </a>

        <a href="{{ route('citizen.posts.index') }}" class="bg-slate-800/50 border border-slate-700/50 hover:border-indigo-500/40 rounded-xl p-4 text-center transition group">
            <div class="text-indigo-400 mb-2 flex justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 12h4"/></svg>
            </div>
            <p class="text-xs font-medium text-slate-300 group-hover:text-white transition">View Posts</p>
        </a>

        <a href="{{ route('citizen.events.create') }}" class="bg-slate-800/50 border border-slate-700/50 hover:border-cyan-500/40 rounded-xl p-4 text-center transition group">
            <div class="text-cyan-400 mb-2 flex justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <p class="text-xs font-medium text-slate-300 group-hover:text-white transition">New Event</p>
        </a>

        <a href="{{ route('citizen.events.index') }}" class="bg-slate-800/50 border border-slate-700/50 hover:border-cyan-500/40 rounded-xl p-4 text-center transition group">
            <div class="text-cyan-400 mb-2 flex justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            </div>
            <p class="text-xs font-medium text-slate-300 group-hover:text-white transition">View Events</p>
        </a>

        <a href="{{ route('citizen.billing') }}" class="bg-slate-800/50 border border-slate-700/50 hover:border-emerald-500/40 rounded-xl p-4 text-center transition group relative">
            <div class="text-emerald-400 mb-2 flex justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
            </div>
            <p class="text-xs font-medium text-slate-300 group-hover:text-white transition">Billing &amp; Credits</p>
            <p class="text-xs text-slate-500 group-hover:text-slate-400 transition mt-0.5">${{ number_format($creditBalance, 2) }} available</p>
        </a>

        <a href="{{ route('2fa.setup') }}" class="bg-slate-800/50 border border-slate-700/50 hover:border-blue-500/40 rounded-xl p-4 text-center transition group">
            <div class="text-blue-400 mb-2 flex justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            </div>
            <p class="text-xs font-medium text-slate-300 group-hover:text-white transition">Two-Factor Auth</p>
            <p class="text-xs text-slate-500 group-hover:text-slate-400 transition mt-0.5">{{ auth()->user()->hasTwoFactorEnabled() ? 'Enabled' : 'Not enabled' }}</p>
        </a>
    </div>

</div>
@endsection
