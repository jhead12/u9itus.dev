<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
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

{{-- Mobile backdrop --}}
<div id="sidebar-overlay" class="fixed inset-0 bg-black/60 z-20 hidden lg:hidden" onclick="toggleSidebar()"></div>

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

        {{-- Wallet balance pill --}}
        @auth
        @php $voter = auth()->user()->voter; @endphp
        @if($voter)
        <div class="hidden sm:flex items-center gap-2 bg-emerald-900/30 border border-emerald-500/30 rounded-full px-4 py-1.5 text-sm">
            <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
            </svg>
            <span class="text-emerald-300 font-semibold">${{ number_format($voter->wallet_balance ?? 0, 2) }}</span>
            <span class="text-slate-500 text-xs">balance</span>
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
                        <div class="text-emerald-400 font-bold">${{ number_format($voter->wallet_balance ?? 0, 2) }}</div>
                        <div class="text-slate-500 mt-0.5">Balance</div>
                    </div>
                    <div class="bg-slate-800 rounded-lg py-2 px-1">
                        <div class="text-amber-400 font-bold">${{ number_format($voter->pending_earnings ?? 0, 2) }}</div>
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
                        ['route' => 'voter.dashboard',        'label' => 'Dashboard',       'pattern' => 'voter.dashboard',
                         'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>'],
                        ['route' => 'voter.earnings',         'label' => 'Earnings',         'pattern' => 'voter.earnings',
                         'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>'],
                        ['route' => 'voter.earnings.history', 'label' => 'View History',     'pattern' => 'voter.earnings.history',
                         'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>'],
                        ['route' => 'voter.referrals',        'label' => 'Referrals',        'pattern' => 'voter.referrals*',
                         'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>'],
                        ['route' => 'voter.preferences',      'label' => 'Preferences',      'pattern' => 'voter.preferences*',
                         'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>'],
                        ['route' => 'voter.profile',          'label' => 'My Profile',       'pattern' => 'voter.profile*',
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
                    @if($isActive)
                    <span class="ml-auto w-1.5 h-1.5 rounded-full bg-emerald-400 shrink-0"></span>
                    @endif
                </a>
                @endforeach

            </nav>

            {{-- Payout shortcut --}}
            @auth
            @php $voter = $voter ?? auth()->user()->voter; @endphp
            @if($voter && ($voter->pending_earnings ?? 0) >= config('u9itus.batch_payout_min', 10))
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
                    ${{ number_format($voter->pending_earnings, 2) }} available
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

<script>
    function toggleSidebar() {
        const sidebar  = document.getElementById('voter-sidebar');
        const overlay  = document.getElementById('sidebar-overlay');
        const isHidden = sidebar.classList.contains('-translate-x-full');

        sidebar.classList.toggle('-translate-x-full', !isHidden);
        overlay.classList.toggle('hidden', !isHidden);
    }
</script>

@stack('scripts')
</body>
</html>
