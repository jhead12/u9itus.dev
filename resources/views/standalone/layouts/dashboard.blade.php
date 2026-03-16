<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="auth-user-id" content="{{ auth()->id() }}">
    <meta name="auth-user-role" content="{{ auth()->user()?->getRoleNames()->first() }}">
    <title>@yield('title', 'Dashboard') — {{ config('app.name', 'U9itus') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif

    <style>
        * { font-family: 'Inter', sans-serif; }
        .sidebar-link { @apply flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-medium text-slate-400 hover:bg-slate-700/60 hover:text-white transition-all; }
        .sidebar-link.active { @apply bg-emerald-500/10 text-emerald-400 border border-emerald-500/20; }
        .stat-card { @apply bg-slate-800/50 border border-slate-700/50 rounded-xl p-5; }
    </style>

    @stack('styles')
</head>
<body class="h-full bg-slate-900 text-white antialiased">

<div class="flex h-full min-h-screen">

    {{-- ===== SIDEBAR ===== --}}
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-50 w-64 bg-slate-900 border-r border-slate-800 flex flex-col transform -translate-x-full lg:translate-x-0 transition-transform duration-300">

        {{-- Logo --}}
        <div class="flex items-center gap-2 px-6 py-5 border-b border-slate-800">
            <div class="text-2xl font-light tracking-tight">
                <span class="font-bold">U9</span><span class="text-emerald-400">itus</span>
            </div>
            <span class="ml-auto text-xs text-slate-500 uppercase tracking-wide">
                {{ auth()->user()?->getRoleNames()->first() ?? 'Portal' }}
            </span>
        </div>

        {{-- Navigation --}}
        <nav class="flex-1 px-3 py-4 space-y-1 overflow-y-auto">

            @if(auth()->user()?->hasRole('politician'))
                <p class="px-4 py-2 text-xs font-semibold text-slate-500 uppercase tracking-wider">Overview</p>

                <a href="{{ route('politician.dashboard') }}"
                   class="sidebar-link {{ request()->routeIs('politician.dashboard') ? 'active' : '' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    Dashboard
                </a>

                <p class="px-4 py-2 text-xs font-semibold text-slate-500 uppercase tracking-wider mt-2">Campaigns</p>

                <a href="{{ route('politician.campaigns.index') }}"
                   class="sidebar-link {{ request()->routeIs('politician.campaigns.*') ? 'active' : '' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.87v6.26a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    My Campaigns
                </a>

                <a href="{{ route('politician.campaigns.create') }}"
                   class="sidebar-link {{ request()->routeIs('politician.campaigns.create') ? 'active' : '' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    New Campaign
                </a>

                <p class="px-4 py-2 text-xs font-semibold text-slate-500 uppercase tracking-wider mt-2">Insights</p>

                <a href="{{ route('politician.analytics') }}"
                   class="sidebar-link {{ request()->routeIs('politician.analytics*') ? 'active' : '' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    Analytics
                </a>

                <p class="px-4 py-2 text-xs font-semibold text-slate-500 uppercase tracking-wider mt-2">Account</p>

                <a href="{{ route('politician.billing') }}"
                   class="sidebar-link {{ request()->routeIs('politician.billing*') ? 'active' : '' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
                    Billing
                </a>

                <a href="{{ route('politician.referrals') }}"
                   class="sidebar-link {{ request()->routeIs('politician.referrals*') ? 'active' : '' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Referrals
                </a>

                <a href="{{ route('politician.profile') }}"
                   class="sidebar-link {{ request()->routeIs('politician.profile*') ? 'active' : '' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    Profile
                </a>

                {{-- Phase 13: Public Profile Page --}}
                <a href="{{ route('politician.public-page') }}"
                   class="sidebar-link {{ request()->routeIs('politician.public-page*') ? 'active' : '' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Public Page
                </a>

            @elseif(auth()->user()?->hasRole('voter'))
                <a href="{{ route('voter.dashboard') }}" class="sidebar-link {{ request()->routeIs('voter.dashboard') ? 'active' : '' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    Dashboard
                </a>
                <a href="{{ route('voter.earnings') }}" class="sidebar-link">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Earnings
                </a>
            @elseif(auth()->user()?->hasRole('admin'))
                <p class="px-4 py-2 text-xs font-semibold text-slate-500 uppercase tracking-wider">Overview</p>

                <a href="{{ route('admin.dashboard') }}" class="sidebar-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7"/></svg>
                    Dashboard
                </a>

                <p class="px-4 py-2 text-xs font-semibold text-slate-500 uppercase tracking-wider mt-2">Campaigns</p>

                <a href="{{ route('admin.campaigns.pending') }}" class="sidebar-link {{ request()->routeIs('admin.campaigns.pending') ? 'active' : '' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.87v6.26a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    Pending Approval
                </a>

                <a href="{{ route('admin.campaigns.running') }}" class="sidebar-link {{ request()->routeIs('admin.campaigns.running') ? 'active' : '' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    Running Campaigns
                </a>

                <p class="px-4 py-2 text-xs font-semibold text-slate-500 uppercase tracking-wider mt-2">Users</p>

                <a href="{{ route('admin.users.index') }}" class="sidebar-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                    All Users
                </a>

                <a href="{{ route('admin.candidate-matches.index') }}" class="sidebar-link {{ request()->routeIs('admin.candidate-matches.*') ? 'active' : '' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9h8M8 13h6m5 8H5a2 2 0 01-2-2V5a2 2 0 012-2h6.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    Candidate Matches
                </a>

                <a href="{{ route('admin.kyc.index') }}" class="sidebar-link {{ request()->routeIs('admin.kyc.*') ? 'active' : '' }}" title="Know Your Customer — Identity Verification">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"/></svg>
                    KYC Review
                    <span class="ml-auto text-xs text-slate-600">ID</span>
                </a>

                <a href="{{ route('admin.fraud.index') }}" class="sidebar-link {{ request()->routeIs('admin.fraud.*') ? 'active' : '' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    Fraud Detection
                </a>

                <p class="px-4 py-2 text-xs font-semibold text-slate-500 uppercase tracking-wider mt-2">Finance</p>

                <a href="{{ route('admin.payouts.index') }}" class="sidebar-link {{ request()->routeIs('admin.payouts.*') ? 'active' : '' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    Payouts
                </a>

                <a href="{{ route('admin.analytics') }}" class="sidebar-link {{ request()->routeIs('admin.analytics*') ? 'active' : '' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    Analytics
                </a>

                <p class="px-4 py-2 text-xs font-semibold text-slate-500 uppercase tracking-wider mt-2">System</p>

                <a href="{{ route('admin.settings') }}" class="sidebar-link {{ request()->routeIs('admin.settings*') && !request()->routeIs('admin.platform-settings*') ? 'active' : '' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Settings
                </a>

                <a href="{{ route('admin.platform-settings') }}" class="sidebar-link {{ request()->routeIs('admin.platform-settings*') ? 'active' : '' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    Platform Pricing
                </a>

                <a href="{{ route('admin.email-templates.index') }}" class="sidebar-link {{ request()->routeIs('admin.email-templates*') ? 'active' : '' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    Email Templates
                </a>

                <p class="px-4 py-2 text-xs font-semibold text-slate-500 uppercase tracking-wider mt-2">Account</p>

                <a href="{{ route('admin.profile') }}" class="sidebar-link {{ request()->routeIs('admin.profile*') ? 'active' : '' }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    My Profile
                </a>
            @endif

        </nav>

        {{-- Footer --}}
        <div class="px-3 py-4 border-t border-slate-800">
            <div class="flex items-center gap-3 px-3 py-2 mb-2">
                <div class="w-8 h-8 rounded-full bg-emerald-500/20 flex items-center justify-center text-emerald-400 text-sm font-bold">
                    {{ strtoupper(substr(auth()->user()?->name ?? 'U', 0, 1)) }}
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-slate-200 truncate">{{ auth()->user()?->name }}</p>
                    <p class="text-xs text-slate-500 truncate">{{ auth()->user()?->email }}</p>
                </div>
            </div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full sidebar-link text-left hover:text-red-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    Sign Out
                </button>
            </form>
        </div>
    </aside>

    {{-- Mobile overlay --}}
        <button id="sidebar-overlay"
            type="button"
            class="fixed inset-0 bg-slate-900/80 z-40 lg:hidden hidden"
            onclick="toggleSidebar()"
            onkeydown="if(event.key==='Enter'||event.key===' '){toggleSidebar();}"
            aria-label="Close sidebar overlay"></button>

    {{-- ===== MAIN CONTENT ===== --}}
    <div class="flex-1 flex flex-col lg:ml-64 min-h-screen">

        {{-- Top bar --}}
        <header class="sticky top-0 z-30 bg-slate-900/90 backdrop-blur border-b border-slate-800 px-4 sm:px-6 h-16 flex items-center gap-4">
            {{-- Mobile menu button --}}
            <button onclick="toggleSidebar()" class="lg:hidden text-slate-400 hover:text-white">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>

            <div class="flex-1">
                <h1 class="text-base font-semibold text-slate-200">@yield('page-title', 'Dashboard')</h1>
            </div>

            {{-- Notifications bell (Alpine.js) --}}
            <div x-data="notificationBell()" x-cloak class="relative">
                <button @click="toggle()"
                    class="relative text-slate-400 hover:text-white transition"
                    aria-label="Notifications">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    <span x-show="unread > 0"
                          x-text="unread > 9 ? '9+' : unread"
                          class="absolute -top-1.5 -right-1.5 min-w-[16px] h-4 rounded-full bg-emerald-500 text-white text-[10px] font-bold flex items-center justify-center px-0.5"
                          style="display:none;"></span>
                </button>

                {{-- Dropdown panel --}}
                <div x-show="open"
                     @click.outside="open = false"
                     x-transition
                     class="absolute right-0 mt-2 w-80 bg-slate-800 border border-slate-700 rounded-xl shadow-2xl overflow-hidden z-50"
                     style="display:none;">
                    <div class="px-4 py-3 border-b border-slate-700 flex items-center justify-between">
                        <p class="text-sm font-semibold text-white">Notifications</p>
                        <button @click="markAllRead()" class="text-xs text-emerald-400 hover:text-emerald-300 transition">Mark all read</button>
                    </div>

                    <ul class="max-h-72 overflow-y-auto divide-y divide-slate-700/50">
                        <template x-if="notifications.length === 0">
                            <li class="px-4 py-6 text-sm text-slate-500 text-center">No notifications yet.</li>
                        </template>
                        <template x-for="(n, i) in notifications" :key="n.id ?? i">
                            <li class="px-4 py-3 flex gap-3 hover:bg-slate-700/40 transition"
                                :class="n.read ? 'opacity-60' : ''"
                                @click="markRead(n)">
                                <div class="mt-0.5 w-2 h-2 rounded-full shrink-0 mt-1.5"
                                     :class="n.type === 'success' ? 'bg-emerald-400' : (n.type === 'warning' ? 'bg-amber-400' : 'bg-red-400')"></div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm text-slate-200 leading-snug" x-text="n.message"></p>
                                    <p class="text-xs text-slate-500 mt-0.5" x-text="n.time"></p>
                                </div>
                            </li>
                        </template>
                    </ul>
                </div>
            </div>
        </header>

        {{-- Flash messages --}}
        <div class="px-4 sm:px-6 pt-4">
            @if(session('success'))
                <div class="mb-4 p-4 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="mb-4 p-4 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">
                    {{ session('error') }}
                </div>
            @endif
            @if ($errors->any())
                <div class="mb-4 p-4 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        {{-- Page content --}}
        <main class="flex-1 px-4 sm:px-6 py-4 pb-8">
            @yield('content')
        </main>

    </div>
</div>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        sidebar.classList.toggle('-translate-x-full');
        overlay.classList.toggle('hidden');
    }
</script>

@stack('scripts')

{{-- ── Toast notifications (Alpine.js) ──────────────────────────────── --}}
<div id="toast-container"
     x-data="toastManager()"
     x-cloak
     class="fixed bottom-5 right-5 z-[100] flex flex-col gap-2 pointer-events-none">
    <template x-for="(t, i) in toasts" :key="t.id">
        <div x-show="t.visible"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="pointer-events-auto flex items-start gap-3 px-4 py-3 rounded-xl shadow-lg border min-w-[260px] max-w-sm"
             :class="{
                 'bg-emerald-900/80 border-emerald-500/40 text-emerald-200': t.type === 'success',
                 'bg-amber-900/80 border-amber-500/40 text-amber-200': t.type === 'warning',
                 'bg-red-900/80 border-red-500/40 text-red-200': t.type === 'error',
                 'bg-slate-800 border-slate-600/40 text-slate-200': t.type === 'info'
             }">
            {{-- icon --}}
            <div class="mt-0.5 shrink-0">
                <svg x-show="t.type === 'success'" class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <svg x-show="t.type === 'error'" class="w-4 h-4 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                <svg x-show="t.type === 'warning'" class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                <svg x-show="t.type === 'info'" class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <p class="text-sm leading-snug flex-1" x-text="t.message"></p>
            <button @click="remove(i)" class="ml-1 shrink-0 opacity-50 hover:opacity-100 transition">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </template>
</div>

{{-- ── Alpine.js components + Echo boot ───────────────────────────────── --}}
<script>
(function () {
    // ── Toast manager ──────────────────────────────────────────────────
    window.toastManager = function () {
        return {
            toasts: [],
            _counter: 0,
            add(message, type = 'info', duration = 5000) {
                const id = ++this._counter;
                this.toasts.push({ id, message, type, visible: true });
                if (duration > 0) setTimeout(() => this.removeById(id), duration);
            },
            removeById(id) {
                const idx = this.toasts.findIndex(t => t.id === id);
                if (idx !== -1) this.toasts.splice(idx, 1);
            },
            remove(idx) { this.toasts.splice(idx, 1); }
        };
    };

    // Global toast() helper called by echo.js listeners
    window._toastProxy = null;
    window.toast = function (message, type = 'info') {
        if (window._toastProxy) {
            window._toastProxy.add(message, type);
        } else {
            // queue until Alpine boots the component
            document.addEventListener('alpine:initialized', () => {
                const el = document.getElementById('toast-container');
                if (el && el._x_dataStack) {
                    el._x_dataStack[0].add(message, type);
                }
            }, { once: true });
        }
    };

    // ── Notification bell ──────────────────────────────────────────────
    window.notificationBell = function () {
        return {
            open: false,
            unread: 0,
            notifications: [],
            async init() {
                // Expose push() so Echo listeners can reach it
                window._notificationBell = this;
                await this.loadFromServer();
            },
            toggle() {
                this.open = !this.open;
                if (this.open) this.loadFromServer();
            },
            async loadFromServer() {
                try {
                    const res = await fetch('/api/notifications', {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                    });

                    if (!res.ok) return;

                    const payload = await res.json();
                    const rows = Array.isArray(payload?.data) ? payload.data : [];

                    this.notifications = rows.map((row) => {
                        const data = row?.data ?? {};
                        return {
                            id: row?.id ?? null,
                            message: data?.message ?? 'Notification',
                            type: this.mapType(data?.type),
                            read: !!row?.read_at,
                            time: this.formatTime(row?.created_at),
                        };
                    });

                    this.unread = this.notifications.reduce((sum, n) => sum + (n.read ? 0 : 1), 0);
                } catch (_) {
                    // Keep bell usable in degraded mode when notifications API is unavailable.
                }
            },
            push(message, type = 'info') {
                const now = new Date();
                this.notifications.unshift({
                    id: null,
                    message,
                    type,
                    read: false,
                    time: now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
                });
                this.unread++;
                // Also show a toast
                window.toast(message, type);
            },
            async markRead(notification) {
                if (!notification || notification.read) return;

                notification.read = true;
                this.unread = Math.max(0, this.unread - 1);

                if (!notification.id) return;

                try {
                    await fetch('/api/notifications/' + notification.id + '/mark-as-read', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        },
                        credentials: 'same-origin',
                    });
                } catch (_) {
                    // Ignore network errors; UI state is still updated locally.
                }
            },
            async markAllRead() {
                this.notifications.forEach(n => n.read = true);
                this.unread = 0;

                try {
                    await fetch('/api/notifications/mark-all-as-read', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        },
                        credentials: 'same-origin',
                    });
                } catch (_) {
                    // Ignore network errors; UI state is still updated locally.
                }
            },
            mapType(serverType) {
                if (!serverType) return 'info';
                if (serverType === 'low_balance') return 'warning';
                if (serverType === 'campaign_rejected' || serverType === 'fraud_alert') return 'error';
                if (serverType === 'campaign_approved' || serverType === 'payout_processed') return 'success';
                return 'info';
            },
            formatTime(timestamp) {
                if (!timestamp) {
                    return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                }

                const date = new Date(timestamp);
                if (Number.isNaN(date.getTime())) {
                    return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                }

                const diffMs = Date.now() - date.getTime();
                const diffMin = Math.floor(diffMs / 60000);

                if (diffMin < 1) return 'Just now';
                if (diffMin < 60) return diffMin + 'm ago';

                const diffHour = Math.floor(diffMin / 60);
                if (diffHour < 24) return diffHour + 'h ago';

                const diffDay = Math.floor(diffHour / 24);
                if (diffDay < 7) return diffDay + 'd ago';

                return date.toLocaleDateString();
            }
        };
    };

    // ── Wire up Echo listeners after DOM is ready ──────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        const userId = document.querySelector('meta[name="auth-user-id"]')?.content;
        const role   = document.querySelector('meta[name="auth-user-role"]')?.content;

        if (!window.Echo || !userId || !role) return;

        const push = (msg, type) => {
            if (window._notificationBell) window._notificationBell.push(msg, type);
            else window.toast(msg, type);
        };

        if (role === 'admin') {
            window.Echo.private('admin.monitor')
                .listen('.fraud.flag.raised', e => {
                    push('⚑ Fraud flag raised — Voter #' + (e.voter_id ?? '?'), 'warning');
                })
                .listen('.session.completed', e => {
                    push('✓ View session completed — campaign #' + (e.campaign_id ?? '?'), 'info');
                });
        }

        if (role === 'politician') {
            window.Echo.private('politician.' + userId)
                .listen('.campaign.approved', e => {
                    const title = e.title ?? 'Campaign';
                    const msg = e.status === 'scheduled'
                        ? `✅ Campaign "${title}" approved and scheduled!`
                        : `✅ Campaign "${title}" approved and is now live!`;
                    push(msg, 'success');
                    // Update UI if on campaigns page
                    if (window.location.pathname.includes('/politician/campaigns')) {
                        setTimeout(() => window.location.reload(), 2000);
                    }
                })
                .listen('.campaign.rejected', e => {
                    const title = e.title ?? 'Campaign';
                    const reason = e.reason ? ` Reason: ${e.reason}` : '';
                    push(`❌ Campaign "${title}" was rejected.${reason}`, 'error');
                    // Update UI if on campaigns page
                    if (window.location.pathname.includes('/politician/campaigns')) {
                        setTimeout(() => window.location.reload(), 2000);
                    }
                })
                .listen('.campaign.stopped', e => {
                    const title = e.title ?? 'Campaign';
                    const reason = e.reason ? ` Reason: ${e.reason}` : '';
                    push(`⏸️ Campaign "${title}" has been paused by admin.${reason}`, 'warning');
                    // Update UI if on campaigns page
                    if (window.location.pathname.includes('/politician/campaigns')) {
                        setTimeout(() => window.location.reload(), 2000);
                    }
                })
                .listen('.campaign.reactivated', e => {
                    const title = e.title ?? 'Campaign';
                    push(`▶️ Campaign "${title}" has been reactivated!`, 'success');
                    // Update UI if on campaigns page
                    if (window.location.pathname.includes('/politician/campaigns')) {
                        setTimeout(() => window.location.reload(), 2000);
                    }
                });
        }

        if (role === 'voter') {
            window.Echo.private('voter.' + userId)
                .listen('.ad.token.delivered', e => {
                    push('📨 New ad available — earn $0.25!', 'success');
                })
                .listen('.session.completed', e => {
                    push('💰 Payout of $0.25 credited to your wallet.', 'success');
                })
                .listen('.payout.processed', e => {
                    push('🏦 Batch payout of $' + (e.amount ?? '?') + ' processed.', 'success');
                });
        }
    });
}());
</script>
</body>
</html>
