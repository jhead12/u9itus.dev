@extends('layouts.voter')

@section('title', 'Ad Viewing Room')

@section('content')
<div class="px-4 sm:px-6 lg:px-8 py-8 max-w-6xl mx-auto space-y-7">

    {{-- ── Page Header ──────────────────────────────────────── --}}
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-white flex items-center gap-2">
                <span class="w-8 h-8 rounded-lg bg-emerald-500/20 flex items-center justify-center">
                    <svg class="w-4.5 h-4.5 text-emerald-400" style="width:18px;height:18px" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.882v6.236a1 1 0 01-1.447.894L15 14M3 8a2 2 0 00-2 2v4a2 2 0 002 2h9a2 2 0 002-2v-4a2 2 0 00-2-2H3z"/>
                    </svg>
                </span>
                Ad Viewing Room
            </h1>
            <p class="text-slate-400 text-sm mt-1">
                Watch political messages from verified officials and earn
                <span class="text-emerald-400 font-semibold">$0.25</span> per completed view.
            </p>
        </div>

        {{-- Daily limit badge --}}
        <div class="shrink-0 flex items-center gap-3">
            <div class="text-right">
                <p class="text-xs text-slate-500 uppercase tracking-wide font-medium">Today's Views</p>
                <p class="text-lg font-bold {{ $viewsToday >= $dailyLimit ? 'text-red-400' : 'text-white' }}">
                    {{ $viewsToday }} <span class="text-slate-500 text-sm font-normal">/ {{ $dailyLimit }}</span>
                </p>
            </div>
            <div class="w-12 h-12 rounded-full border-4
                {{ $viewsToday >= $dailyLimit ? 'border-red-500/40 bg-red-900/20' : 'border-emerald-500/40 bg-emerald-900/20' }}
                flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 {{ $viewsToday >= $dailyLimit ? 'text-red-400' : 'text-emerald-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
    </div>

    {{-- ── Alerts ────────────────────────────────────────────── --}}

    {{-- Unverified banner --}}
    @if(! $voter->is_verified)
    <div class="bg-amber-900/30 border border-amber-500/40 rounded-2xl p-4 flex items-start gap-4">
        <div class="w-9 h-9 rounded-xl bg-amber-500/20 flex items-center justify-center shrink-0 mt-0.5">
            <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            </svg>
        </div>
        <div>
            <p class="text-amber-300 font-semibold text-sm">Account verification required</p>
            <p class="text-slate-400 text-xs mt-1">
                Your account isn't verified yet. You can browse available campaigns, but earnings will be held
                until verification is complete.
                <a href="{{ route('voter.profile') }}" class="text-amber-400 hover:text-amber-300 underline ml-1">Complete your profile →</a>
            </p>
        </div>
    </div>
    @endif

    {{-- Daily limit reached --}}
    @if($viewsToday >= $dailyLimit)
    <div class="bg-red-900/30 border border-red-500/40 rounded-2xl p-4 flex items-start gap-4">
        <div class="w-9 h-9 rounded-xl bg-red-500/20 flex items-center justify-center shrink-0 mt-0.5">
            <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
            </svg>
        </div>
        <div>
            <p class="text-red-300 font-semibold text-sm">Daily limit reached</p>
            <p class="text-slate-400 text-xs mt-1">
                You've reached your {{ $dailyLimit }}-view daily limit. Come back tomorrow to keep earning.
            </p>
        </div>
    </div>
    @endif

    {{-- Flagged account --}}
    @if($voter->flagged_for_fraud)
    <div class="bg-red-950/50 border border-red-600/40 rounded-2xl p-4 flex items-start gap-4">
        <svg class="w-5 h-5 text-red-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"/>
        </svg>
        <div>
            <p class="text-red-300 font-semibold text-sm">Account restricted</p>
            <p class="text-slate-400 text-xs mt-1">Your account has been flagged for review. Viewing is temporarily paused. Contact support for help.</p>
        </div>
    </div>
    @endif

    {{-- Inline validation errors (from claim redirect) --}}
    @if($errors->has('claim'))
    <div class="bg-red-900/30 border border-red-500/40 rounded-xl px-4 py-3 text-sm text-red-300 flex items-center gap-2">
        <svg class="w-4 h-4 text-red-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        {{ $errors->first('claim') }}
    </div>
    @endif

    {{-- ── Filter bar ──────────────────────────────────────── --}}
    <div class="flex flex-wrap items-center gap-3">
        <form method="GET" action="{{ route('voter.ad-room') }}" class="flex flex-wrap items-center gap-3 flex-1">
            <div class="relative min-w-[200px] flex-1 max-w-xs">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input type="text" name="q" value="{{ request('q') }}"
                    placeholder="Search campaigns…"
                    class="w-full bg-slate-800 border border-slate-700 text-white placeholder-slate-500 rounded-xl pl-9 pr-4 py-2.5 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500 transition"/>
            </div>

            <select name="level"
                class="bg-slate-800 border border-slate-700 text-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 appearance-none cursor-pointer">
                <option value="">All levels</option>
                @foreach(['Federal', 'State', 'County', 'City', 'School Board', 'Judicial'] as $level)
                <option value="{{ $level }}" {{ request('level') === $level ? 'selected' : '' }}>{{ $level }}</option>
                @endforeach
            </select>

            @if(request('q') || request('level'))
            <a href="{{ route('voter.ad-room') }}" class="text-slate-400 hover:text-white text-sm flex items-center gap-1 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
                Clear
            </a>
            @endif

            <button type="submit"
                class="bg-slate-700 hover:bg-slate-600 text-white px-4 py-2.5 rounded-xl text-sm font-medium transition">
                Filter
            </button>
        </form>

        <div class="text-slate-500 text-sm shrink-0">
            {{ $campaigns->total() }} {{ Str::plural('campaign', $campaigns->total()) }} available
        </div>
    </div>

    {{-- ── Campaign Grid ──────────────────────────────────── --}}
    @if($campaigns->isEmpty())
    <div class="text-center py-20 bg-slate-800/40 border border-slate-700/60 rounded-2xl">
        <div class="w-14 h-14 rounded-2xl bg-slate-700/60 flex items-center justify-center mx-auto mb-4">
            <svg class="w-7 h-7 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.882v6.236a1 1 0 01-1.447.894L15 14M3 8a2 2 0 00-2 2v4a2 2 0 002 2h9a2 2 0 002-2v-4a2 2 0 00-2-2H3z"/>
            </svg>
        </div>
        <h3 class="text-white font-semibold mb-1">No campaigns available right now</h3>
        <p class="text-slate-500 text-sm max-w-xs mx-auto">
            @if(request('q') || request('level'))
                No campaigns match your current filter. Try clearing the filters.
            @else
                New campaigns from verified officials are added regularly. Check back soon!
            @endif
        </p>
        @if(request('q') || request('level'))
        <a href="{{ route('voter.ad-room') }}" class="inline-block mt-4 text-emerald-400 hover:text-emerald-300 text-sm transition">
            Clear filters →
        </a>
        @endif
    </div>
    @else
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">
        @foreach($campaigns as $campaign)
        @php
            $isInProgress  = in_array($campaign->id, $inProgressTokenCampaignIds);
            $isCompleted   = in_array($campaign->id, $completedCampaignIds);
            $politician    = $campaign->politician;
            $remaining     = max(0, $campaign->total_views_requested - $campaign->views_completed);
            $fillPct       = $campaign->total_views_requested > 0
                ? min(100, round(($campaign->views_completed / $campaign->total_views_requested) * 100))
                : 0;
            $levelColors   = [
                'Federal'      => ['bg' => 'bg-blue-900/40 border-blue-700/40',    'text' => 'text-blue-400'],
                'State'        => ['bg' => 'bg-purple-900/40 border-purple-700/40','text' => 'text-purple-400'],
                'County'       => ['bg' => 'bg-amber-900/40 border-amber-700/40',  'text' => 'text-amber-400'],
                'City'         => ['bg' => 'bg-teal-900/40 border-teal-700/40',    'text' => 'text-teal-400'],
                'School Board' => ['bg' => 'bg-rose-900/40 border-rose-700/40',    'text' => 'text-rose-400'],
                'Judicial'     => ['bg' => 'bg-slate-700/60 border-slate-600/60',  'text' => 'text-slate-300'],
            ];
            $lvl    = $campaign->governance_level ?? '';
            $lvlCss = $levelColors[$lvl] ?? ['bg' => 'bg-slate-800/60 border-slate-700/60', 'text' => 'text-slate-400'];
            $payout = (float) ($campaign->voter_payout_per_view ?? 0.25);
            $dur    = (int) ($campaign->media_duration ?? 0);
        @endphp
        <div class="flex flex-col bg-slate-800/50 border border-slate-700/60 rounded-2xl overflow-hidden hover:border-slate-600 transition group">

            {{-- Thumbnail --}}
            <div class="relative bg-slate-900 aspect-video overflow-hidden">
                @if($campaign->thumbnail_url)
                    <img src="{{ $campaign->thumbnail_url }}" alt="{{ $campaign->title }}"
                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                @else
                    <div class="w-full h-full flex flex-col items-center justify-center gap-2 bg-gradient-to-br from-slate-800 to-slate-900">
                        <svg class="w-10 h-10 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10l4.553-2.069A1 1 0 0121 8.882v6.236a1 1 0 01-1.447.894L15 14M3 8a2 2 0 00-2 2v4a2 2 0 002 2h9a2 2 0 002-2v-4a2 2 0 00-2-2H3z"/>
                        </svg>
                    </div>
                @endif

                {{-- Governance level badge --}}
                @if($lvl)
                <span class="absolute top-3 left-3 text-xs font-semibold px-2.5 py-1 rounded-full border {{ $lvlCss['bg'] }} {{ $lvlCss['text'] }}">
                    {{ $lvl }}
                </span>
                @endif

                {{-- Duration badge --}}
                @if($dur)
                <span class="absolute top-3 right-3 text-xs bg-black/60 text-slate-300 px-2 py-1 rounded-full">
                    {{ $dur >= 60 ? floor($dur/60).'m '.($dur%60).'s' : $dur.'s' }}
                </span>
                @endif

                {{-- In-progress overlay --}}
                @if($isInProgress)
                <div class="absolute inset-0 bg-blue-900/50 backdrop-blur-sm flex items-center justify-center">
                    <div class="text-center">
                        <div class="w-10 h-10 rounded-full border-2 border-blue-400 flex items-center justify-center mx-auto mb-1">
                            <svg class="w-5 h-5 text-blue-300 ml-0.5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M8 5v14l11-7z"/>
                            </svg>
                        </div>
                        <span class="text-blue-300 text-xs font-semibold">Resume watching</span>
                    </div>
                </div>
                @endif
            </div>

            {{-- Card body --}}
            <div class="flex flex-col flex-1 p-4 gap-3">

                {{-- Politician + title --}}
                <div>
                    <div class="flex items-center gap-2 mb-1.5">
                        <div class="w-6 h-6 rounded-full bg-slate-700 flex items-center justify-center text-white text-xs font-bold shrink-0">
                            {{ strtoupper(substr($politician->full_name ?? 'P', 0, 1)) }}
                        </div>
                        <span class="text-slate-400 text-xs truncate">
                            {{ $politician->full_name ?? 'Official' }}
                            @if($politician->political_office ?? false)
                                <span class="text-slate-600">&middot;</span>
                                {{ $politician->political_office }}
                            @endif
                        </span>
                        @if($politician->verified_official ?? false)
                        <svg class="w-3.5 h-3.5 text-emerald-400 shrink-0" fill="currentColor" viewBox="0 0 24 24" title="Verified Official">
                            <path d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                        </svg>
                        @endif
                    </div>
                    <h3 class="text-white font-semibold text-sm leading-snug line-clamp-2">{{ $campaign->title }}</h3>
                    @if($campaign->message_summary)
                    <p class="text-slate-500 text-xs mt-1 line-clamp-2">{{ $campaign->message_summary }}</p>
                    @endif
                </div>

                {{-- Payout highlight --}}
                <div class="flex items-center justify-between bg-emerald-900/25 border border-emerald-500/20 rounded-xl px-3 py-2">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="text-emerald-300 text-xs">Your payout</span>
                    </div>
                    <span class="text-emerald-400 font-bold text-base">${{ number_format($payout, 2) }}</span>
                </div>

                {{-- Progress bar: views remaining --}}
                <div>
                    <div class="flex items-center justify-between text-xs text-slate-500 mb-1">
                        <span>{{ number_format($remaining) }} spots left</span>
                        <span>{{ $fillPct }}% filled</span>
                    </div>
                    <div class="w-full bg-slate-700 rounded-full h-1.5 overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-500
                            {{ $fillPct >= 90 ? 'bg-red-500' : ($fillPct >= 70 ? 'bg-amber-500' : 'bg-emerald-500') }}"
                            style="width: {{ $fillPct }}%">
                        </div>
                    </div>
                </div>

                {{-- CTA button --}}
                <div class="mt-auto pt-1">
                    @if($isCompleted)
                        <div class="w-full text-center py-2.5 rounded-xl bg-slate-700/50 text-slate-500 text-sm font-medium cursor-default">
                            <svg class="w-4 h-4 inline-block mr-1 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Already watched
                        </div>
                    @elseif(! $canViewMore)
                        <div class="w-full text-center py-2.5 rounded-xl bg-slate-700/50 text-slate-500 text-sm font-medium cursor-default" title="Daily limit reached or account restricted">
                            Unavailable today
                        </div>
                    @else
                        <form method="POST" action="{{ route('voter.campaigns.claim', $campaign) }}">
                            @csrf
                            <button type="submit"
                                class="w-full py-2.5 rounded-xl text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-slate-800
                                    {{ $isInProgress
                                        ? 'bg-blue-600 hover:bg-blue-500 text-white focus:ring-blue-500'
                                        : 'bg-emerald-600 hover:bg-emerald-500 text-white focus:ring-emerald-500' }}">
                                @if($isInProgress)
                                    <svg class="w-4 h-4 inline-block mr-1 -mt-0.5 ml-0.5" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M8 5v14l11-7z"/>
                                    </svg>
                                    Resume Watching
                                @else
                                    <svg class="w-4 h-4 inline-block mr-1 -mt-0.5 ml-0.5" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M8 5v14l11-7z"/>
                                    </svg>
                                    Watch &amp; Earn ${{ number_format($payout, 2) }}
                                @endif
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- ── Pagination ──────────────────────────────────────── --}}
    @if($campaigns->hasPages())
    <div class="flex justify-center pt-4">
        <nav class="flex items-center gap-1 text-sm">
            {{-- Previous --}}
            @if($campaigns->onFirstPage())
                <span class="px-3 py-2 rounded-lg text-slate-600 cursor-default">← Prev</span>
            @else
                <a href="{{ $campaigns->previousPageUrl() }}"
                   class="px-3 py-2 rounded-lg text-slate-400 hover:text-white hover:bg-slate-700 transition">← Prev</a>
            @endif

            {{-- Page numbers --}}
            @foreach($campaigns->getUrlRange(max(1, $campaigns->currentPage()-2), min($campaigns->lastPage(), $campaigns->currentPage()+2)) as $page => $url)
                @if($page == $campaigns->currentPage())
                    <span class="px-3 py-2 rounded-lg bg-emerald-600 text-white font-semibold">{{ $page }}</span>
                @else
                    <a href="{{ $url }}" class="px-3 py-2 rounded-lg text-slate-400 hover:text-white hover:bg-slate-700 transition">{{ $page }}</a>
                @endif
            @endforeach

            {{-- Next --}}
            @if($campaigns->hasMorePages())
                <a href="{{ $campaigns->nextPageUrl() }}"
                   class="px-3 py-2 rounded-lg text-slate-400 hover:text-white hover:bg-slate-700 transition">Next →</a>
            @else
                <span class="px-3 py-2 rounded-lg text-slate-600 cursor-default">Next →</span>
            @endif
        </nav>
    </div>
    @endif
    @endif

    {{-- ── How it works ─────────────────────────────────────── --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-2">
        @foreach([
            ['step' => '1', 'title' => 'Choose a Campaign', 'desc' => 'Browse official political messages targeted to your area.', 'color' => 'emerald'],
            ['step' => '2', 'title' => 'Watch the Full Ad',  'desc' => 'Watch continuously — skipping disqualifies your payout.',  'color' => 'blue'],
            ['step' => '3', 'title' => 'Earn Instantly',     'desc' => 'Credit lands in your wallet immediately after completion.','color' => 'purple'],
        ] as $s)
        <div class="flex items-start gap-3 bg-slate-800/30 border border-slate-700/40 rounded-xl p-4">
            <div class="w-8 h-8 rounded-lg bg-{{ $s['color'] }}-500/15 border border-{{ $s['color'] }}-500/20 flex items-center justify-center text-{{ $s['color'] }}-400 font-bold text-sm shrink-0">
                {{ $s['step'] }}
            </div>
            <div>
                <p class="text-white text-sm font-semibold">{{ $s['title'] }}</p>
                <p class="text-slate-400 text-xs mt-0.5 leading-relaxed">{{ $s['desc'] }}</p>
            </div>
        </div>
        @endforeach
    </div>

</div>
@endsection
