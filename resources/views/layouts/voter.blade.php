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
        <button onclick="toggleSidebar()"
            class="lg:hidden p-2 rounded-lg text-slate-400 hover:text-white hover:bg-slate-700 transition"
            aria-label="Toggle menu">
            <svg id="menu-icon" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>

        {{-- Logo --}}
        <a href="{{ route('voter.dashboard') }}" class="flex items-center gap-1.5 text-lg font-bold hover:opacity-80 transition shrink-0">
            <span class="text-white">U9</span><span class="text-emerald-400">itus</span>
            <span class="hidden sm:inline-block text-slate-500 text-sm font-normal ml-1">Voter Portal</span>
        </a>

        <div class="flex-1"></div>

        {{-- Notifications bell (Alpine.js) --}}
        <div x-data="notificationBell()" x-cloak class="relative">
            <button @click="open = !open"
                class="relative text-slate-400 hover:text-white transition p-2 rounded-lg hover:bg-slate-800"
                aria-label="Notifications">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
                <span x-show="unread > 0"
                      x-text="unread > 9 ? '9+' : unread"
                      class="absolute top-0 right-0 min-w-[16px] h-4 rounded-full bg-emerald-500 text-white text-[10px] font-bold flex items-center justify-center px-0.5"
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
        <div class="relative" x-data="{ open: false }">
            <button @click="open = !open"
                class="flex items-center gap-2 bg-slate-800 hover:bg-slate-700 border border-slate-700 rounded-full pl-3 pr-2 py-1.5 text-sm transition">
                <div class="w-7 h-7 rounded-full bg-emerald-700 flex items-center justify-center text-white text-xs font-bold shrink-0">
                    {{ strtoupper(substr(auth()->user()->name ?? 'V', 0, 1)) }}
                </div>
                <span class="hidden sm:block text-slate-200 max-w-[100px] truncate">{{ auth()->user()->name }}</span>
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div x-show="open" @click.outside="open = false" x-transition
                class="absolute right-0 mt-2 w-48 bg-slate-800 border border-slate-700 rounded-xl shadow-xl py-1 z-50">
                <a href="{{ route('voter.profile') }}"
                    class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-300 hover:text-white hover:bg-slate-700/60 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    My Profile
                </a>
                <a href="{{ route('voter.preferences') }}"
                    class="flex items-center gap-2 px-4 py-2.5 text-sm text-slate-300 hover:text-white hover:bg-slate-700/60 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Preferences
                </a>
                <div class="border-t border-slate-700 my-1"></div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                        class="w-full flex items-center gap-2 px-4 py-2.5 text-sm text-red-400 hover:text-red-300 hover:bg-slate-700/60 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                    <div class="w-11 h-11 rounded-full bg-gradient-to-br from-emerald-600 to-teal-700 flex items-center justify-center text-white font-bold text-lg shrink-0">
                        {{ strtoupper(substr(auth()->user()->name ?? 'V', 0, 1)) }}
                    </div>
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
            <nav class="p-3 space-y-0.5 flex-1">

                @php
                    $navItems = [
                        ['route' => 'voter.dashboard',        'label' => 'Dashboard',           'pattern' => 'voter.dashboard',
                         'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>'],
                        ['route' => 'voter.ad-room',          'label' => 'Running Campaigns',    'pattern' => 'voter.ad-room',
                         'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.882v6.236a1 1 0 01-1.447.894L15 14M3 8a2 2 0 00-2 2v4a2 2 0 002 2h9a2 2 0 002-2v-4a2 2 0 00-2-2H3z"/>'],
                        ['route' => 'politicians.directory',  'label' => 'Browse Politicians',   'pattern' => 'politicians.directory',
                         'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>'],
                        ['route' => 'us.map',                 'label' => 'Interactive Map',      'pattern' => 'us.map',
                         'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 01.553-.894L9 2m0 18l6-3m-6 3V2m6 15l5.447 2.724A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 2"/>'],
                        ['route' => 'voter.earnings',         'label' => 'Earnings',             'pattern' => 'voter.earnings',
                         'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>'],
                        ['route' => 'voter.earnings.history', 'label' => 'View History',         'pattern' => 'voter.earnings.history',
                         'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>'],
                        ['route' => 'voter.referrals',        'label' => 'Referrals',            'pattern' => 'voter.referrals*',
                         'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>'],
                        ['route' => 'voter.preferences',      'label' => 'Preferences',          'pattern' => 'voter.preferences*',
                         'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>'],
                        ['route' => 'voter.profile',          'label' => 'My Profile',           'pattern' => 'voter.profile*',
                         'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>'],
                    ];
                @endphp

                @foreach($navItems as $item)
                @php $isActive = request()->routeIs($item['pattern']); @endphp
                <a href="{{ route($item['route']) }}"
                    class="voter-nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition group
                           {{ $isActive
                               ? 'bg-emerald-600/15 text-emerald-400 border-l-2 border-emerald-500 pl-[10px]'
                               : 'text-slate-400 hover:text-white hover:bg-slate-800' }}">
                    <svg class="w-4.5 h-4.5 shrink-0 {{ $isActive ? 'text-emerald-400' : 'text-slate-500 group-hover:text-slate-300' }}"
                         style="width:18px;height:18px"
                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                <div class="w-full bg-slate-800 rounded-full h-1.5 overflow-hidden">
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
    function toggleSidebar() {
        const sidebar  = document.getElementById('voter-sidebar');
        const overlay  = document.getElementById('sidebar-overlay');
        const isHidden = sidebar.classList.contains('-translate-x-full');

        sidebar.classList.toggle('-translate-x-full', !isHidden);
        overlay.classList.toggle('hidden', !isHidden);
    }
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
