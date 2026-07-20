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

    {{-- Campaign stats + CTA --}}
    <div class="bg-slate-800/60 border border-slate-700/50 rounded-xl p-6 flex flex-col sm:flex-row sm:items-center gap-4">
        <div class="flex-1">
            <h3 class="text-white font-semibold text-base mb-1">Your Campaigns</h3>
            <p class="text-slate-400 text-sm">
                @if($campaignCount > 0)
                    You have <span class="text-white font-medium">{{ $campaignCount }}</span> campaign{{ $campaignCount !== 1 ? 's' : '' }}.
                @else
                    No campaigns yet. Create your first local or community ad.
                @endif
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('citizen.billing') }}"
               class="inline-flex items-center gap-1.5 text-sm font-medium text-emerald-300 hover:text-emerald-200 bg-emerald-500/10 hover:bg-emerald-500/20 border border-emerald-500/20 rounded-lg px-4 py-2 transition">
                <span>${{ number_format($citizen->credit_balance ?? 0, 2) }}</span>
                Billing
            </a>
            @if($campaignCount > 0)
            <a href="{{ route('citizen.campaigns.index') }}"
               class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-300 hover:text-white bg-slate-700/50 hover:bg-slate-700 rounded-lg px-4 py-2 transition">
                View All
            </a>
            @endif
            <a href="{{ route('citizen.campaigns.create') }}"
               class="inline-flex items-center gap-2 bg-amber-500 hover:bg-amber-400 text-slate-900 font-semibold rounded-lg px-4 py-2.5 text-sm transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                New Campaign
            </a>
        </div>
    </div>

    {{-- ── Two-Factor Authentication ────────────────────────────────────────── --}}
    <div class="mt-6 bg-slate-800/50 border border-slate-700/60 rounded-2xl px-6 py-4 flex items-center justify-between">
        <div>
            <p class="text-sm font-semibold text-white">Two-Factor Authentication</p>
            <p class="text-xs text-slate-400 mt-0.5">{{ auth()->user()->hasTwoFactorEnabled() ? 'Enabled — your account is protected.' : 'Not enabled — add extra security to your account.' }}</p>
        </div>
        <a href="{{ route('2fa.setup') }}" class="ml-4 shrink-0 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold px-4 py-2 rounded-lg transition">
            {{ auth()->user()->hasTwoFactorEnabled() ? 'Manage' : 'Enable' }}
        </a>
    </div>

