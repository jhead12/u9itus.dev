<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        $groupCanonicalUrl = $group->scope
            ? route('groups.public.show', ['group' => $group, 'scope' => $group->scopeUrlSegment()])
            : route('groups.public.show', $group);
    @endphp

    <title>{{ $group->name }} — {{ config('app.name', 'U9itus') }}</title>
    @include('standalone.partials.seo-head')
    <meta name="description" content="{{ Str::limit($group->description ?? ($group->name.' is a neighborhood group on U9itus.'), 160) }}">
    <link rel="canonical" href="{{ $groupCanonicalUrl }}">
    <meta property="og:type"        content="website">
    <meta property="og:url"         content="{{ $groupCanonicalUrl }}">
    <meta property="og:title"       content="{{ $group->name }} — {{ config('app.name', 'U9itus') }}">
    <meta property="og:description" content="{{ Str::limit($group->description ?? '', 160) }}">
    <meta name="twitter:card"       content="summary">
    <meta name="twitter:title"      content="{{ $group->name }} — {{ config('app.name', 'U9itus') }}">

    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800&display=swap" rel="stylesheet" />

    {{-- Vite assets --}}
    @if(file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif

    <style>
        * { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-slate-950 min-h-screen antialiased flex flex-col">

    {{-- ── Top Nav Bar ── --}}
    <nav class="sticky top-0 z-40 bg-slate-900/80 backdrop-blur-md border-b border-slate-700/50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 flex items-center justify-between h-14">
            <a href="{{ url('/') }}" class="flex items-center space-x-1 text-lg font-bold hover:opacity-80 transition">
                <span class="text-white">U9</span><span class="text-emerald-400">itus</span>
            </a>
            <div class="flex items-center gap-3">
                <a href="{{ route('groups.directory') }}" class="text-sm text-slate-300 hover:text-white transition">
                    <span aria-hidden="true">←</span> <span class="sm:hidden">Groups</span><span class="hidden sm:inline">Neighborhood Groups</span>
                </a>
                @auth
                    <a href="{{ route('dashboard') }}" class="text-sm text-slate-300 hover:text-white transition">Dashboard</a>
                @else
                    <a href="{{ route('register.voter') }}" class="bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-semibold px-4 py-2 rounded-lg transition">
                        Create Free Account
                    </a>
                    <a href="{{ route('login') }}" class="text-sm text-slate-300 hover:text-white transition">Sign In</a>
                @endauth
            </div>
        </div>
    </nav>

    <div class="flex-1 max-w-4xl mx-auto px-4 sm:px-6 py-8 space-y-6 w-full">

        {{-- ── Hero ── --}}
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl p-6 sm:p-8">
            <div class="flex flex-wrap items-start justify-between gap-4 mb-3">
                <div class="flex flex-wrap items-center gap-2.5">
                    <h1 class="text-2xl sm:text-3xl font-bold text-white">{{ $group->name }}</h1>
                    @if($group->scope)
                    <span class="inline-flex items-center rounded-full border border-indigo-500/30 bg-indigo-500/10 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-indigo-300">
                        {{ $group->scope }}
                    </span>
                    @endif
                </div>
                @if($isAdmin)
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1 rounded-full border border-amber-500/30 bg-amber-500/10 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-amber-300">
                        You created this group
                    </span>
                    <a href="{{ route('groups.edit', $group) }}"
                       class="inline-flex items-center gap-1.5 rounded-lg border border-slate-700 bg-slate-800 hover:bg-slate-700 text-slate-300 px-2.5 py-1 text-xs font-medium transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        Settings
                    </a>
                </div>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-4 text-sm text-slate-400 mb-5">
                @if($group->city || $group->state)
                <span class="flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    {{ implode(', ', array_filter([$group->city, $group->state])) }}
                </span>
                @endif
                <span class="flex items-center gap-1.5">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    {{ $group->members_count }} {{ Str::plural('member', $group->members_count) }}
                </span>
            </div>

            {{-- Join / Leave --}}
            @auth
                @if($isAdmin)
                    <span class="text-xs text-slate-500">As the creator, you're always a member of this group.</span>
                @elseif($isMember)
                    <form method="POST" action="{{ route('groups.leave', $group) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="bg-slate-700 hover:bg-slate-600 text-white text-sm font-medium px-4 py-2 rounded-lg transition">
                            Leave Group
                        </button>
                    </form>
                @elseif(auth()->user()->hasAnyRole(['voter', 'citizen']))
                    <form method="POST" action="{{ route('groups.join', $group) }}">
                        @csrf
                        <button type="submit" class="bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold px-4 py-2 rounded-lg transition">
                            Join Group
                        </button>
                    </form>
                @endif
            @else
                <a href="{{ route('login') }}?return={{ urlencode($groupCanonicalUrl) }}"
                   class="inline-block bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold px-4 py-2 rounded-lg transition">
                    Sign In to Join
                </a>
            @endauth

            @if($errors->has('group'))
            <p class="mt-3 text-sm text-rose-300">{{ $errors->first('group') }}</p>
            @endif
            @if(session('status'))
            <p class="mt-3 text-sm text-emerald-300">{{ session('status') }}</p>
            @endif
        </div>

        {{-- ── Description ── --}}
        @if($group->description)
        <div>
            <h2 class="text-lg font-bold text-white mb-3">About This Group</h2>
            <div class="bg-slate-800/40 border border-slate-700/40 rounded-xl p-5 text-sm text-slate-300 leading-relaxed whitespace-pre-line">
                {{ $group->description }}
            </div>
        </div>
        @endif

        {{-- "Active Causes" intentionally omitted: no schema link between
             Cause and NeighborhoodGroup exists yet, and there's no UI path
             to create one, so the section could only ever render an empty
             stub — worse for UX than not showing it at all. Re-add once
             causes can actually be linked to a group. --}}

    </div>

    {{-- ── Footer ── --}}
    <footer class="border-t border-slate-800 mt-10">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 py-8">
            <div class="flex flex-col sm:flex-row items-center justify-between gap-4 text-sm text-slate-500">
                <p>&copy; {{ date('Y') }} {{ config('app.name', 'U9itus') }}. All rights reserved.</p>
                <div class="flex items-center gap-4">
                    <a href="{{ url('/') }}" class="hover:text-white transition">Home</a>
                    <a href="{{ route('groups.directory') }}" class="hover:text-white transition">Neighborhood Groups</a>
                </div>
            </div>
        </div>
    </footer>

</body>
</html>
