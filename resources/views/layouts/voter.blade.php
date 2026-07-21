<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="auth-user-id" content="{{ auth()->id() }}">
    <meta name="auth-user-role" content="{{ auth()->user()?->getRoleNames()->first() }}">
    <title>@yield('title', 'Voter Portal') · {{ config('app.name', 'U9itus') }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        /* Sidebar active link highlight */
        .voter-nav-link.active { @apply bg-emerald-600/20 text-emerald-400 border-l-2 border-emerald-500; }
        /* Hide scrollbar on sidebar */
        .sidebar-scroll { scrollbar-width: none; }
        .sidebar-scroll::-webkit-scrollbar { display: none; }
    </style>

    @stack('styles')
</head>
<body class="bg-slate-900 text-white antialiased">

@php
    $voterOnboardingProgress = null;
    $showVoterStartHere = false;
    $voterStartHereChecklist = [];

    if (auth()->check() && auth()->user()?->user_type === 'voter') {
        $voterOnboardingProgress = \App\Models\OnboardingProgress::query()
            ->where('user_id', auth()->id())
            ->where('user_type', 'voter')
            ->first();

        $completedRecently = $voterOnboardingProgress?->completed_at
            ? $voterOnboardingProgress->completed_at->gte(now()->subDays(14))
            : false;

        $showVoterStartHere = $completedRecently
            && ($voterOnboardingProgress?->is_completed || $voterOnboardingProgress?->skipped);

        $voterProfile = auth()->user()->voter;

        $voterStartHereChecklist = [
            [
                'label' => 'Watched first campaign',
                'done' => $voterProfile
                    ? $voterProfile->viewSessions()->completed()->exists()
                    : false,
            ],
            [
                'label' => 'Earned first reward',
                'done' => $voterProfile
                    ? (((float) $voterProfile->total_earned) > 0 || ((float) $voterProfile->wallet_balance) > 0)
                    : false,
            ],
            [
                'label' => 'Made first referral',
                'done' => $voterProfile
                    ? ($voterProfile->referrals()->exists() || $voterProfile->referralEarnings()->exists())
                    : false,
            ],
        ];
    }

    if (! request()->routeIs('voter.dashboard')) {
        $showVoterStartHere = false;
    }
@endphp

{{-- Mobile backdrop --}}
<button id="sidebar-overlay"
    type="button"
    class="fixed inset-0 bg-black/60 z-20 hidden lg:hidden"
    onclick="toggleSidebar()"
    onkeydown="if(event.key==='Enter'||event.key===' '){toggleSidebar();}"
    aria-label="Close sidebar overlay"></button>

<div class="min-h-screen flex flex-col">

    {{-- ── Top Navigation Bar ──────────────────────────────── --}}
    <header class="sticky top-0 z-30 bg-slate-900/95 backdrop-blur border-b border-slate-700/60 h-16 flex items-center px-4 gap-4">

        {{-- Mobile menu button --}}
        <button id="voter-menu-btn" onclick="toggleSidebar()"
            class="lg:hidden p-2 rounded-lg text-slate-400 hover:text-white hover:bg-slate-700 transition"
            aria-label="Toggle menu" aria-expanded="false" aria-controls="voter-sidebar">
            <svg id="menu-icon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>

        {{-- Logo --}}
        <a href="{{ route('voter.dashboard') }}" class="flex items-center gap-1.5 text-lg font-bold hover:opacity-80 transition shrink-0">
            <span class="text-white">U9</span><span class="text-emerald-400">itus</span>
            <span class="hidden sm:inline-block text-slate-500 text-sm font-normal ml-1">Voter Portal</span>
        </a>

        <div class="flex-1"></div>

        {{-- Favorites drawer toggle --}}
        @auth
        @if(auth()->user()->voter)
        <button id="favorites-toggle-btn" onclick="toggleFavoritesPanel()"
            class="relative text-slate-400 hover:text-amber-300 transition p-2 rounded-lg hover:bg-slate-800"
            aria-label="Favorites"
            aria-expanded="false"
            aria-controls="favorites-panel">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
            </svg>
        </button>
        @endif
        @endauth

        {{-- Notifications bell (Alpine.js) --}}
        <div x-data="notificationBell()" x-cloak class="relative"
             @keydown.escape.window="open = false">
            <button @click="open = !open"
                class="relative text-slate-400 hover:text-white transition p-2 rounded-lg hover:bg-slate-800"
                aria-label="Notifications"
                :aria-expanded="open.toString()"
                aria-controls="voter-notif-panel">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
                <span x-show="unread > 0"
                      x-text="unread > 9 ? '9+' : unread"
                      class="absolute top-0 right-0 min-w-[16px] h-4 rounded-full bg-emerald-500 text-white text-[10px] font-bold flex items-center justify-center px-0.5"
                      style="display:none;"></span>
            </button>

            {{-- Dropdown panel --}}
            <div id="voter-notif-panel"
                 x-show="open"
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
                    <template x-for="(n, i) in notifications" :key="i">
                        <li class="px-4 py-3 flex gap-3 hover:bg-slate-700/40 transition"
                            :class="n.read ? 'opacity-60' : ''">
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

        {{-- Wallet balance pill --}}
        @auth
        @php $voter = auth()->user()->voter; @endphp
        @if($voter)
        <div class="hidden sm:flex items-center gap-2 text-sm">
            <div class="inline-flex items-center gap-2 bg-emerald-900/30 border border-emerald-500/30 rounded-full px-3 py-1.5">
                <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                </svg>
                <span class="text-emerald-300 font-semibold" data-wallet-balance>${{ number_format($voter->wallet_balance ?? 0, 2) }}</span>
                <span class="text-slate-500 text-xs">wallet</span>
            </div>
            <div class="inline-flex items-center gap-2 bg-amber-900/30 border border-amber-500/30 rounded-full px-3 py-1.5">
                <svg class="w-4 h-4 text-amber-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8V7m0 1v8m0 0v1"/>
                </svg>
                <span class="text-amber-300 font-semibold" data-pending-earnings>${{ number_format($voter->pending_earnings ?? 0, 2) }}</span>
                <span class="text-slate-500 text-xs">pending</span>
            </div>
        </div>
        @endif
        @endauth

        {{-- User dropdown --}}
        @auth
        <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false">
            <button @click="open = !open; if (open) { $nextTick(() => document.querySelector('#voter-user-menu a, #voter-user-menu button')?.focus()); }"
                class="flex items-center gap-2 bg-slate-800 hover:bg-slate-700 border border-slate-700 rounded-full pl-3 pr-2 py-1.5 text-sm transition"
                :aria-expanded="open.toString()"
                aria-haspopup="menu"
                aria-controls="voter-user-menu">
                <img src="{{ auth()->user()->avatar_url }}"
                     alt="{{ auth()->user()->name }}"
                     class="w-7 h-7 rounded-full object-cover shrink-0">
                <span class="hidden sm:block text-slate-200 max-w-[100px] truncate">{{ auth()->user()->name }}</span>
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div id="voter-user-menu" role="menu"
                 x-show="open" @click.outside="open = false" x-transition
                 @keydown.escape.prevent="open = false; $event.target.closest('.relative').querySelector('button').focus()"
                 class="absolute right-0 mt-2 w-48 bg-slate-800 border border-slate-700 rounded-xl shadow-xl py-1 z-50">
                <a href="{{ route('voter.profile') }}" role="menuitem"
                    class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-300 hover:text-white hover:bg-slate-700/60 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    My Profile
                </a>
                <a href="{{ route('voter.preferences') }}" role="menuitem"
                    class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-300 hover:text-white hover:bg-slate-700/60 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Preferences
                </a>
                <a href="https://docs.google.com/forms/d/1eUabk9YnV2nNPSaTzpdWxXgJxNJmrxxhnpqVat7Q_jY/viewform"
                    target="_blank" rel="noopener noreferrer" role="menuitem"
                    class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-300 hover:text-white hover:bg-slate-700/60 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
                    </svg>
                    Bug Report / Feedback ↗
                </a>
                <div class="border-t border-slate-700 my-1"></div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" role="menuitem"
                        class="w-full flex items-center gap-2 px-4 py-2.5 text-sm text-red-400 hover:text-red-300 hover:bg-slate-700/60 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                        Sign Out
                    </button>
                </form>
            </div>
        </div>
        @endauth
    </header>

    {{-- ── Body: Sidebar + Content ──────────────────────────── --}}
    <div class="flex flex-1 overflow-hidden">

        {{-- ── Sidebar ──────────────────────────────────────── --}}
        <aside id="voter-sidebar"
            class="fixed lg:static top-16 left-0 bottom-0 z-20
                   w-64 shrink-0
                   bg-slate-900 border-r border-slate-800
                   flex flex-col
                   -translate-x-full lg:translate-x-0
                   transition-transform duration-200 ease-in-out
                   sidebar-scroll overflow-y-auto">

            {{-- Voter identity card --}}
            @auth
            @php $voter = $voter ?? auth()->user()->voter; @endphp
            <div class="p-4 border-b border-slate-800">
                <div class="flex items-center gap-3">
                    <img src="{{ auth()->user()->avatar_url }}"
                         alt="{{ auth()->user()->name }}"
                         class="w-11 h-11 rounded-full object-cover shrink-0">
                    <div class="min-w-0">
                        <p class="text-white text-sm font-semibold truncate">{{ auth()->user()->name }}</p>
                        @if($voter)
                        <div class="flex items-center gap-1.5 mt-0.5">
                            <span class="inline-block w-1.5 h-1.5 rounded-full {{ $voter->is_verified ? 'bg-emerald-400' : 'bg-amber-400' }}"></span>
                            <span class="text-xs text-slate-400">{{ $voter->is_verified ? 'Verified Voter' : 'Unverified' }}</span>
                        </div>
                        @endif
                    </div>
                </div>

                @if($voter)
                <div class="mt-3 grid grid-cols-2 gap-2 text-xs text-center">
                    <div class="bg-slate-800 rounded-lg py-2 px-1">
                        <div class="text-emerald-400 font-bold" data-wallet-balance>${{ number_format($voter->wallet_balance ?? 0, 2) }}</div>
                        <div class="text-slate-500 mt-0.5">Balance</div>
                    </div>
                    <div class="bg-slate-800 rounded-lg py-2 px-1">
                        <div class="text-amber-400 font-bold" data-pending-earnings>${{ number_format($voter->pending_earnings ?? 0, 2) }}</div>
                        <div class="text-slate-500 mt-0.5">Pending</div>
                    </div>
                </div>
                @endif
            </div>
            @endauth

            {{-- Navigation links --}}
            <nav class="p-3 space-y-0.5 flex-1" aria-label="Voter portal">

                @php
                    // Nav grouped into sections (matches the politician sidebar's
                    // sectioned IA — see standalone/layouts/dashboard.blade.php).
                    $navSections = [
                        'Overview' => [
                            ['route' => 'voter.dashboard', 'label' => 'Dashboard', 'pattern' => 'voter.dashboard',
                             'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>'],
                        ],
                        'Explore' => [
                            ['route' => 'voter.ad-room',         'label' => 'Running Campaigns',  'pattern' => 'voter.ad-room',
                             'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.882v6.236a1 1 0 01-1.447.894L15 14M3 8a2 2 0 00-2 2v4a2 2 0 002 2h9a2 2 0 002-2v-4a2 2 0 00-2-2H3z"/>'],
                            ['route' => 'politicians.directory', 'label' => 'Browse Politicians', 'pattern' => 'politicians.directory',
                             'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>'],
                            ['route' => 'voter.map',               'label' => 'Interactive Map',   'pattern' => 'voter.map',
                             'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 01.553-.894L9 2m0 18l6-3m-6 3V2m6 15l5.447 2.724A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 2"/>'],
                        ],
                        'Earnings' => [
                            ['route' => 'voter.earnings',         'label' => 'Earnings',   'pattern' => 'voter.earnings',
                             'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>'],
                            ['route' => 'voter.earnings.history', 'label' => 'View History', 'pattern' => 'voter.earnings.history',
                             'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>'],
                            ['route' => 'voter.referrals',        'label' => 'Referrals',  'pattern' => 'voter.referrals*',
                             'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>'],
                        ],
                        'Account' => [
                            ['route' => 'voter.preferences', 'label' => 'Preferences', 'pattern' => 'voter.preferences*',
                             'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>'],
                            ['route' => 'voter.profile',     'label' => 'My Profile',   'pattern' => 'voter.profile*',
                             'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>'],
                        ],
                    ];
                @endphp

                @foreach($navSections as $sectionLabel => $items)
                <p class="px-3 {{ $loop->first ? 'pt-1' : 'pt-3' }} pb-1 text-[10px] font-semibold uppercase tracking-wider text-slate-500">{{ $sectionLabel }}</p>
                @foreach($items as $item)
                @php $isActive = request()->routeIs($item['pattern']); @endphp
                <a href="{{ route($item['route']) }}"
                    @if($isActive) aria-current="page" @endif
                    class="voter-nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition group
                           {{ $isActive
                               ? 'bg-emerald-600/15 text-emerald-400 border-l-2 border-emerald-500 pl-[10px]'
                               : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
                    <svg class="w-4.5 h-4.5 shrink-0 {{ $isActive ? 'text-emerald-400' : 'text-slate-500 group-hover:text-slate-300' }}"
                         style="width:18px;height:18px"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        {!! $item['icon'] !!}
                    </svg>
                    {{ $item['label'] }}
                    {{-- "New" pill on Running Campaigns when there are available campaigns --}}
                    @if($item['route'] === 'voter.ad-room' && ! $isActive)
                    <span class="ml-auto text-[10px] bg-emerald-600/30 border border-emerald-500/30 text-emerald-400 rounded-full px-1.5 py-0.5 leading-none font-semibold">LIVE</span>
                    @elseif($isActive)
                    <span class="ml-auto w-1.5 h-1.5 rounded-full bg-emerald-400 shrink-0"></span>
                    @endif
                </a>
                @endforeach
                @endforeach

            </nav>

            {{-- Payout shortcut --}}
            @auth
            @php $voter = $voter ?? auth()->user()->voter; @endphp
            @php $minPayout = (float) \App\Services\PlatformSettingsService::get('min_payout_amount', null, 5.00); @endphp
            @if($voter && ($voter->pending_earnings ?? 0) >= $minPayout)
            <div class="p-3 border-t border-slate-800">
                <form action="{{ route('voter.earnings.payout') }}" method="POST">
                    @csrf
                    <button type="submit"
                        class="w-full bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold py-2.5 rounded-lg transition flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                        </svg>
                        Request Payout
                    </button>
                </form>
                <p class="text-center text-slate-500 text-xs mt-1.5">
                    ${{ number_format($voter->pending_earnings, 2) }} available (${{ number_format($minPayout, 2) }} minimum)
                </p>
            </div>
            @endif
            @endauth

            {{-- Trust score footer --}}
            @auth
            @php $voter = $voter ?? auth()->user()->voter; @endphp
            @if($voter)
            <div class="p-4 border-t border-slate-800">
                <div class="flex items-center justify-between text-xs text-slate-500 mb-1.5">
                    <span>Trust Score</span>
                    <span class="{{ ($voter->trust_score ?? 0) >= 80 ? 'text-emerald-400' : (($voter->trust_score ?? 0) >= 50 ? 'text-amber-400' : 'text-red-400') }} font-semibold">
                        {{ $voter->trust_score ?? 100 }}/100
                    </span>
                </div>
                <div class="w-full bg-slate-800 rounded-full h-1.5 overflow-hidden" aria-hidden="true">
                    <div class="h-full rounded-full
                        {{ ($voter->trust_score ?? 0) >= 80 ? 'bg-emerald-500' : (($voter->trust_score ?? 0) >= 50 ? 'bg-amber-500' : 'bg-red-500') }}"
                        style="width: {{ $voter->trust_score ?? 100 }}%">
                    </div>
                </div>
            </div>
            @endif
            @endauth

        </aside>

        {{-- ── Main Content ─────────────────────────────────── --}}
        <main class="flex-1 min-w-0 overflow-y-auto">
            {{-- Flash messages --}}
            @if(session('success'))
            <div class="mx-4 mt-4 flex items-center gap-3 bg-emerald-900/40 border border-emerald-500/30 text-emerald-300 px-4 py-3 rounded-xl text-sm">
                <svg class="w-5 h-5 shrink-0 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                {{ session('success') }}
            </div>
            @endif

            @if($errors->any())
            <div class="mx-4 mt-4 bg-red-900/30 border border-red-500/30 text-red-300 px-4 py-3 rounded-xl text-sm">
                <ul class="space-y-0.5">
                    @foreach($errors->all() as $error)
                    <li class="flex items-center gap-2">
                        <svg class="w-4 h-4 shrink-0 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        {{ $error }}
                    </li>
                    @endforeach
                </ul>
            </div>
            @endif

            @yield('content')
        </main>

    </div>{{-- end flex body --}}
</div>{{-- end min-h-screen --}}

{{-- ── Favorites side panel (slide-in drawer) ───────────────────────── --}}
@auth
@if(auth()->user()->voter)
<button id="favorites-panel-overlay"
    type="button"
    class="fixed inset-0 bg-black/60 z-40 hidden"
    onclick="toggleFavoritesPanel()"
    aria-label="Close favorites panel"></button>

<aside id="favorites-panel"
    class="fixed top-0 right-0 bottom-0 z-50 w-[min(92vw,22rem)]
           bg-slate-900 border-l border-slate-700/60 shadow-2xl
           flex flex-col
           translate-x-full transition-transform duration-200 ease-in-out"
    aria-hidden="true"
    inert>

    <div class="h-16 px-4 flex items-center justify-between border-b border-slate-800 shrink-0">
        <div class="flex items-center gap-2">
            <svg class="w-5 h-5 text-amber-300" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
            </svg>
            <h2 class="text-sm font-semibold text-white">My Favorites</h2>
        </div>
        <button id="favorites-panel-close" onclick="toggleFavoritesPanel()"
            class="p-2 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition"
            aria-label="Close favorites panel">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <div id="favorites-panel-body" class="flex-1 overflow-y-auto p-3" data-loaded="0">
        <div class="py-10 text-center text-sm text-slate-500">Loading…</div>
    </div>

    <div class="p-3 border-t border-slate-800 shrink-0">
        <a href="{{ route('voter.favorites.index') }}"
           class="block w-full text-center bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 text-sm font-medium py-2.5 rounded-lg transition">
            View all followed politicians →
        </a>
    </div>
</aside>

<script>
    // ── Favorites drawer (voters with a voter record only) ────────────────
    // The sidebar drawer helpers (toggleSidebar / setSidebarOpen / etc.) are
    // defined in a separate always-rendered script below so the mobile
    // hamburger still works for users without a voter profile. The two
    // close each other (B12) via `typeof` guards so neither block depends on
    // the other being present.

    let _lastFavoritesTrigger = null;

    function favoritesIsOpen() {
        const panel = document.getElementById('favorites-panel');
        return !!panel && !panel.classList.contains('translate-x-full');
    }

    function toggleFavoritesPanel() {
        setFavoritesOpen(!favoritesIsOpen());
    }

    function setFavoritesOpen(open, opts) {
        const panel   = document.getElementById('favorites-panel');
        const overlay = document.getElementById('favorites-panel-overlay');
        const trigger = document.getElementById('favorites-toggle-btn');
        if (!panel || !overlay) return;

        const returnFocus = opts && opts.returnFocus === false ? false : true;

        panel.classList.toggle('translate-x-full', !open);
        overlay.classList.toggle('hidden', !open);
        panel.setAttribute('aria-hidden', open ? 'false' : 'true');
        if (open) panel.removeAttribute('inert'); else panel.setAttribute('inert', '');
        if (trigger) trigger.setAttribute('aria-expanded', open ? 'true' : 'false');

        if (open) {
            // B12: close the main sidebar if it's open (avoid stacking).
            if (typeof sidebarIsOpen === 'function' && sidebarIsOpen()) {
                setSidebarOpen(false, { returnFocus: false });
            }
            document.body.classList.add('overflow-y-hidden');
            _lastFavoritesTrigger = trigger || null;
            loadFavoritesPanel();
            // Focus the panel's close button once the slide-in has settled.
            setTimeout(() => {
                const close = document.getElementById('favorites-panel-close');
                if (close) { try { close.focus({ preventScroll: true }); } catch (_) {} }
            }, 220);
        } else {
            document.body.classList.remove('overflow-y-hidden');
            if (returnFocus) {
                const target = _lastFavoritesTrigger || trigger;
                if (target) { try { target.focus({ preventScroll: true }); } catch (_) {} }
            }
            _lastFavoritesTrigger = null;
        }
    }

    function loadFavoritesPanel(force = false) {
        const body = document.getElementById('favorites-panel-body');
        if (!force && body.dataset.loaded === '1') return;

        fetch('{{ route('voter.favorites.panel') }}', {
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
            credentials: 'same-origin',
        })
        .then(r => r.ok ? r.text() : Promise.reject())
        .then(html => {
            body.innerHTML = html;
            body.dataset.loaded = '1';
        })
        .catch(() => {
            body.innerHTML = '<div class="py-10 text-center text-sm text-red-400">Could not load favorites.</div>';
        });
    }

    // Focus trap inside the favorites panel (ported from components/modal.blade.php).
    function favoritesFocusables() {
        const panel = document.getElementById('favorites-panel');
        if (!panel || panel.classList.contains('translate-x-full')) return [];
        const sel = 'a, button, input:not([type="hidden"]), textarea, select, details, [tabindex]:not([tabindex="-1"])';
        return [...panel.querySelectorAll(sel)].filter(el => !el.hasAttribute('disabled'));
    }

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Tab') return;
        const panel = document.getElementById('favorites-panel');
        if (!panel || panel.classList.contains('translate-x-full')) return;
        const f = favoritesFocusables();
        if (!f.length) return;
        const first = f[0], last = f[f.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    });

    // Unfollow from inside the panel without a page reload.
    document.addEventListener('submit', function (e) {
        const form = e.target.closest('form[data-favorite-unfollow]');
        if (!form) return;
        e.preventDefault();

        fetch(form.action, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'Accept': 'application/json',
            },
            credentials: 'same-origin',
            body: new FormData(form),
        }).then(() => loadFavoritesPanel(true));
    });

    // Escape closes the favorites drawer when it's the open drawer.
    // (The sidebar has its own Escape handler in the always-rendered block.)
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (favoritesIsOpen()) setFavoritesOpen(false);
    });
</script>
@endif
@endauth

{{-- ── Toast notifications (Alpine.js) ──────────────────────────────── --}}
<div
    id="toast-container"
    x-data="toaster()"
    x-cloak
    class="fixed bottom-6 right-6 z-[9999] flex flex-col gap-3 pointer-events-none"
    style="max-width:24rem;">
    <template x-for="(t, i) in toasts" :key="t.id">
        <div
            x-show="t.visible"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            :class="{
                'bg-emerald-500/10 border-emerald-500/30 text-emerald-300': t.type === 'success',
                'bg-red-500/10 border-red-500/30 text-red-300':             t.type === 'error',
                'bg-amber-500/10 border-amber-500/30 text-amber-300':       t.type === 'warning',
                'bg-blue-500/10 border-blue-500/30 text-blue-300':          t.type === 'info'
            }"
            class="pointer-events-auto border rounded-xl px-4 py-3 shadow-xl text-sm flex items-center gap-3">
            <span x-text="t.message" class="flex-1 leading-snug"></span>
        </div>
    </template>
</div>

@if($showVoterStartHere)
<div id="floating-start-here-voter"
     data-key="voter-{{ auth()->id() }}-{{ $voterOnboardingProgress?->completed_at?->timestamp ?? 'na' }}"
     class="fixed bottom-5 left-5 z-[110] w-[min(92vw,320px)]">
    <button id="start-here-voter-toggle"
            type="button"
            class="w-full inline-flex items-center justify-between gap-3 rounded-xl border border-emerald-500/35 bg-slate-900/95 px-4 py-3 text-left shadow-2xl shadow-emerald-900/30">
        <span>
            <span class="block text-xs uppercase tracking-wide text-emerald-300">New Here?</span>
            <span class="block text-sm font-semibold text-white">Start Here</span>
        </span>
        <svg class="w-4 h-4 text-emerald-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <div id="start-here-voter-panel" class="mt-2 hidden rounded-xl border border-slate-700 bg-slate-900/95 p-3 shadow-2xl">
        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400 mb-2">Suggested First Steps</p>
        <div class="space-y-2">
            <a href="{{ route('voter.ad-room') }}" class="block rounded-lg border border-slate-700/80 bg-slate-800/60 px-3 py-2 text-sm text-slate-200 hover:border-emerald-500/40 hover:text-white transition">Browse Running Campaigns</a>
            <a href="{{ route('voter.earnings') }}" class="block rounded-lg border border-slate-700/80 bg-slate-800/60 px-3 py-2 text-sm text-slate-200 hover:border-emerald-500/40 hover:text-white transition">Check Earnings Dashboard</a>
            <a href="{{ route('voter.referrals') }}" class="block rounded-lg border border-slate-700/80 bg-slate-800/60 px-3 py-2 text-sm text-slate-200 hover:border-emerald-500/40 hover:text-white transition">Set Up Referral Link</a>
        </div>
        <div class="mt-3 rounded-lg border border-slate-700/70 bg-slate-800/40 p-2.5">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-500 mb-2">Progress</p>
            <ul class="space-y-1.5">
                @foreach($voterStartHereChecklist as $item)
                    <li class="flex items-center gap-2 text-xs {{ $item['done'] ? 'text-emerald-300' : 'text-slate-400' }}">
                        <span class="inline-flex h-4 w-4 items-center justify-center rounded-full border {{ $item['done'] ? 'border-emerald-500/60 bg-emerald-500/15' : 'border-slate-600 bg-slate-700/40' }}">
                            @if($item['done'])
                                <svg class="h-2.5 w-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                                </svg>
                            @endif
                        </span>
                        <span>{{ $item['label'] }}{{ $item['done'] ? ' - Done' : '' }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
        <button id="start-here-voter-dismiss"
                type="button"
                class="mt-3 w-full rounded-lg border border-slate-700 px-3 py-2 text-xs font-medium text-slate-400 hover:text-white hover:border-slate-500 transition">
            Dismiss this helper
        </button>
    </div>
</div>

<script>
(function () {
    const widget = document.getElementById('floating-start-here-voter');
    if (!widget) return;

    const keySuffix = widget.getAttribute('data-key') || 'voter';
    const storeKey = 'u9itus:start-here:' + keySuffix;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const trackEvent = function (eventType, context = {}) {
        fetch('/api/v1/onboarding-handoff-events', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken,
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                role: 'voter',
                event_type: eventType,
                widget_key: keySuffix,
                context: context,
            }),
        }).catch(() => {});
    };

    try {
        if (window.localStorage.getItem(storeKey) === 'dismissed') {
            widget.remove();
            return;
        }
    } catch (_) {
        // Ignore storage failures.
    }

    const toggle = document.getElementById('start-here-voter-toggle');
    const panel = document.getElementById('start-here-voter-panel');
    const dismiss = document.getElementById('start-here-voter-dismiss');
    if (!toggle || !panel || !dismiss) return;

    toggle.addEventListener('click', function () {
        const wasHidden = panel.classList.contains('hidden');
        panel.classList.toggle('hidden');
        if (wasHidden) {
            trackEvent('opened', { route: window.location.pathname });
        }
    });

    dismiss.addEventListener('click', function () {
        trackEvent('dismissed', { route: window.location.pathname });
        try {
            window.localStorage.setItem(storeKey, 'dismissed');
        } catch (_) {
            // Ignore storage failures.
        }

        widget.remove();
    });
}());
</script>
@endif

<script>
    // ── Voter mobile sidebar drawer (always rendered) ────────────────────
    // The sidebar is hidden by a CSS transform (off-screen) rather than
    // display:none, so its focusable descendants stay in the tab order when
    // "closed". We use `inert` to pull them out of the tab order + a11y tree
    // until the drawer opens. On desktop (≥1024px) the sidebar is always
    // visible (lg:static), so it must never be inert — syncSidebarA11y()
    // enforces that on load and on breakpoint crossings.

    const VOTER_MQ = window.matchMedia('(max-width: 1023px)');
    let _lastSidebarTrigger = null;

    function sidebarIsOpen() {
        const sidebar = document.getElementById('voter-sidebar');
        return !!sidebar && !sidebar.classList.contains('-translate-x-full');
    }

    function toggleSidebar() {
        setSidebarOpen(!sidebarIsOpen());
    }

    function setSidebarOpen(open, opts) {
        const sidebar = document.getElementById('voter-sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        const btn     = document.getElementById('voter-menu-btn');
        if (!sidebar || !overlay) return;

        // No-op on desktop: the sidebar is always visible there (lg:static)
        // and the hamburger is hidden, so closing it would wrongly hide nav.
        if (!VOTER_MQ.matches && !open) return;

        const returnFocus = opts && opts.returnFocus === false ? false : true;

        sidebar.classList.toggle('-translate-x-full', !open);
        overlay.classList.toggle('hidden', !open);
        if (btn) btn.setAttribute('aria-expanded', open ? 'true' : 'false');

        if (open) {
            // B12: close the favorites drawer if it's open (avoid stacking).
            if (typeof favoritesIsOpen === 'function' && favoritesIsOpen()) {
                setFavoritesOpen(false, { returnFocus: false });
            }
            document.body.classList.add('overflow-y-hidden');
            _lastSidebarTrigger = btn;
            // Move focus into the drawer once the slide-in has visually settled.
            setTimeout(() => {
                const first = sidebar.querySelector('a[href], button:not([disabled])');
                if (first) { try { first.focus({ preventScroll: true }); } catch (_) {} }
            }, 220);
        } else {
            document.body.classList.remove('overflow-y-hidden');
            if (returnFocus) {
                const target = _lastSidebarTrigger || btn;
                if (target) { try { target.focus({ preventScroll: true }); } catch (_) {} }
            }
            _lastSidebarTrigger = null;
        }

        syncSidebarA11y();
    }

    function syncSidebarA11y() {
        const sidebar = document.getElementById('voter-sidebar');
        const btn     = document.getElementById('voter-menu-btn');
        if (!sidebar) return;
        const mobile = VOTER_MQ.matches;
        const open   = sidebarIsOpen();
        if (!mobile) {
            // Desktop: always visible — never inert/hidden.
            sidebar.removeAttribute('inert');
            sidebar.setAttribute('aria-hidden', 'false');
            if (btn) btn.setAttribute('aria-expanded', 'false');
        } else if (open) {
            sidebar.removeAttribute('inert');
            sidebar.setAttribute('aria-hidden', 'false');
            if (btn) btn.setAttribute('aria-expanded', 'true');
        } else {
            sidebar.setAttribute('inert', '');
            sidebar.setAttribute('aria-hidden', 'true');
            if (btn) btn.setAttribute('aria-expanded', 'false');
        }
    }

    // Escape closes the sidebar when it's the open drawer.
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (sidebarIsOpen()) setSidebarOpen(false);
    });

    // Boot + breakpoint-cross sync: ensure the aside has inert/aria-hidden
    // matching its (mobile) closed state on initial load and on resize.
    function initVoterSidebar() { syncSidebarA11y(); }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initVoterSidebar);
    } else {
        initVoterSidebar();
    }
    VOTER_MQ.addEventListener('change', syncSidebarA11y);
</script>

{{-- ── Alpine.js components + Echo boot ───────────────────────────────── --}}
<script>
(function () {
    'use strict';

    // ── Toast notifications ────────────────────────────────────────────
    window.toaster = function () {
        return {
            toasts: [],
            nextId: 1,
            add(message, type = 'info') {
                const id = this.nextId++;
                this.toasts.push({ id, message, type, visible: true });
                setTimeout(() => {
                    const toast = this.toasts.find(t => t.id === id);
                    if (toast) toast.visible = false;
                    setTimeout(() => {
                        const idx = this.toasts.findIndex(t => t.id === id);
                        if (idx !== -1) this.toasts.splice(idx, 1);
                    }, 300);
                }, 4000);
            }
        };
    };

    window.toast = function (message, type = 'info') {
        // If not yet Alpine-ready, queue until it boots
        if (!document.querySelector('#toast-container')?._x_dataStack) {
            document.addEventListener('alpine:initialized', () => {
                if (document.querySelector('#toast-container')?._x_dataStack?.[0]) {
                    const el = document.querySelector('#toast-container');
                    el._x_dataStack[0].add(message, type);
                }
            }, { once: true });
        } else {
            const el = document.querySelector('#toast-container');
            if (el?._x_dataStack?.[0]) {
                el._x_dataStack[0].add(message, type);
            }
        }
    };

    // ── Notification bell ──────────────────────────────────────────────
    window.notificationBell = function () {
        return {
            open: false,
            unread: 0,
            notifications: [],
            init() {
                // Expose push() so Echo listeners can reach it
                window._notificationBell = this;
            },
            push(message, type = 'info') {
                const now = new Date();
                this.notifications.unshift({
                    message,
                    type,
                    read: false,
                    time: now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
                });
                this.unread++;
                // Also show a toast
                window.toast(message, type);
            },
            markAllRead() {
                this.notifications.forEach(n => n.read = true);
                this.unread = 0;
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

        if (role === 'voter') {
            window.Echo.private('voter.' + userId)
                .listen('.ad.token.delivered', e => {
                    const amount = e.earning_amount ?? '0.25';
                    push(`📨 New ad available — watch and earn $${amount}!`, 'success');
                    // Update UI if on dashboard or ad room
                    if (window.location.pathname.includes('/voter/dashboard') || window.location.pathname.includes('/voter/ad-room')) {
                        setTimeout(() => window.location.reload(), 1500);
                    }
                })
                .listen('.session.completed', e => {
                    const amount = e.payout_amount ?? '0.25';
                    push(`💰 Earned $${amount}! Credited to pending earnings and queued for payout.`, 'success');
                    // Update wallet balance display
                    if (window.location.pathname.includes('/voter')) {
                        setTimeout(() => window.location.reload(), 2000);
                    }
                })
                .listen('.payout.processed', e => {
                    const amount = e.amount ?? '?';
                    push(`🏦 Batch payout of $${amount} has been processed and sent!`, 'success');
                    // Refresh earnings page
                    if (window.location.pathname.includes('/voter/earnings')) {
                        setTimeout(() => window.location.reload(), 2000);
                    }
                });
        }
    });
}());
</script>

@stack('scripts')
</body>
</html>
