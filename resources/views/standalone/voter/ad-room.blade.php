@extends('layouts.voter')

@section('title', 'Ad Viewing Room')

@section('content')
<div class="px-4 sm:px-6 lg:px-8 py-8 max-w-6xl mx-auto space-y-7">

    @php
        $defaultPayout = (float) \App\Services\PlatformSettingsService::get('viewer_payout_per_view', null, 0.25);
    @endphp

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
                <span class="text-emerald-400 font-semibold">${{ number_format($defaultPayout, 2) }}</span> per completed view.
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

    {{-- Browse Politicians Banner --}}
    <div class="bg-blue-500/10 border border-blue-500/30 rounded-2xl p-4 flex items-center gap-4 justify-between hover:border-blue-400/50 transition group">
        <div class="flex items-center gap-3">
            <div class="w-9 h-9 rounded-xl bg-blue-500/20 flex items-center justify-center shrink-0 group-hover:bg-blue-500/30 transition">
                <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
            <div>
                <p class="text-blue-200 font-semibold text-sm">Want to learn more about these politicians?</p>
                <p class="text-slate-400 text-xs mt-0.5">
                    Browse our directory to research verified officials, view their profiles &amp; transparency data before watching.
                </p>
            </div>
        </div>
        <a href="{{ route('politicians.directory') }}"
           class="shrink-0 bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 rounded-xl text-sm font-medium transition whitespace-nowrap">
            Browse Politicians →
        </a>
    </div>

    {{-- ── Promoted community blog posts ──────────────────────── --}}
    @if($promotedPosts->isNotEmpty())
    <div class="bg-purple-900/20 border border-purple-500/30 rounded-2xl p-5">
        <div class="flex items-center gap-3 mb-4">
            <div class="w-8 h-8 rounded-lg bg-purple-500/20 flex items-center justify-center">
                <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/>
                </svg>
            </div>
            <div>
                <h2 class="text-lg font-bold text-white">Latest from the community</h2>
                <p class="text-slate-400 text-xs">Promoted stories from citizens and politicians.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($promotedPosts as $post)
            <a href="{{ route('blog.show', $post) }}" target="_blank" rel="noopener"
               class="block bg-slate-800/60 border border-slate-700/60 hover:border-purple-500/40 rounded-xl p-4 transition group">
                <h3 class="text-white font-semibold text-sm line-clamp-2 group-hover:text-purple-300 transition">{{ $post->title }}</h3>
                @if($post->excerpt)
                <p class="mt-2 text-slate-400 text-xs line-clamp-2">{{ $post->excerpt }}</p>
                @endif
                <div class="mt-3 flex items-center gap-2 text-xs text-slate-500">
                    <span>{{ $post->author?->full_name ?? $post->author?->name ?? 'U9itus' }}</span>
                    <span>·</span>
                    <time datetime="{{ $post->published_at->toIso8601String() }}">{{ $post->published_at->format('M j, Y') }}</time>
                </div>
            </a>
            @endforeach
        </div>
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
                @foreach(config('u9itus.governance_levels', []) as $levelValue => $levelLabel)
                <option value="{{ $levelValue }}" {{ request('level') === $levelValue ? 'selected' : '' }}>{{ $levelLabel }}</option>
                @endforeach
            </select>

            {{-- Sprint 3: Topic filter --}}
            <select name="topic_id"
                class="bg-slate-800 border border-slate-700 text-slate-300 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 appearance-none cursor-pointer">
                <option value="">All topics</option>
                @foreach($topics as $topic)
                <option value="{{ $topic->id }}" {{ request('topic_id') == $topic->id ? 'selected' : '' }}>
                    {{ $topic->name }} {{ $topic->icon ? $topic->icon : '' }}
                </option>
                @endforeach
            </select>

            @if(request('q') || request('level') || request('topic_id'))
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

        @if($citizenCampaigns->isNotEmpty())
        <p class="text-slate-400 text-sm mt-5">
            Good news — there are local &amp; community campaigns below you can watch right now.
        </p>
        <a href="#local-ads" class="inline-block mt-2 text-amber-400 hover:text-amber-300 text-sm font-medium transition">
            Jump to local campaigns ↓
        </a>
        @endif

        <p class="text-slate-500 text-xs mt-4">
            In the meantime, <a href="{{ route('voter.map') }}" class="text-emerald-400 hover:text-emerald-300 underline">explore the interactive map</a> to see who represents you.
        </p>
    </div>
    @else
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">
        @foreach($campaigns as $campaign)
        @php
            $isInProgress  = in_array($campaign->id, $inProgressTokenCampaignIds);
            $isWatchedBefore = in_array($campaign->id, $watchedBeforeIds);
            $isExcluded      = in_array($campaign->id, $excludedCampaignIds);
            $politician    = $campaign->politician;
            $remaining     = max(0, $campaign->total_views_requested - $campaign->views_completed);
            $fillPct       = $campaign->total_views_requested > 0
                ? min(100, round(($campaign->views_completed / $campaign->total_views_requested) * 100))
                : 0;
            $hasRecentReports = ($campaign->recent_reports_count ?? 0) > 0;
            $levelColors   = [
                'federal' => ['bg' => 'bg-blue-900/40 border-blue-700/40', 'text' => 'text-blue-400'],
                'state'   => ['bg' => 'bg-purple-900/40 border-purple-700/40', 'text' => 'text-purple-400'],
                'county'  => ['bg' => 'bg-amber-900/40 border-amber-700/40', 'text' => 'text-amber-400'],
                'city'    => ['bg' => 'bg-teal-900/40 border-teal-700/40', 'text' => 'text-teal-400'],
                'school'  => ['bg' => 'bg-rose-900/40 border-rose-700/40', 'text' => 'text-rose-400'],
                'special' => ['bg' => 'bg-indigo-900/40 border-indigo-700/40', 'text' => 'text-indigo-300'],
            ];
            $lvl    = strtolower((string) ($campaign->governance_level ?? ''));
            $lvlLabelMap = config('u9itus.governance_levels', []);
            $lvlLabel = $lvlLabelMap[$lvl] ?? ucfirst($lvl);
            $lvlCss = $levelColors[$lvl] ?? ['bg' => 'bg-slate-800/60 border-slate-700/60', 'text' => 'text-slate-400'];
            $payout = (float) ($campaign->voter_payout_per_view ?? $defaultPayout);
            $dur    = (int) ($campaign->media_duration ?? 0);
        @endphp
        <div class="flex flex-col bg-slate-800/50 rounded-2xl overflow-hidden hover:border-slate-600 transition group
            {{ $hasRecentReports ? 'border-2 border-red-500/60' : 'border border-slate-700/60' }}">

            {{-- Recent Report Warning Banner --}}
            @if($hasRecentReports)
            <div class="bg-red-900/40 border-b border-red-500/40 px-3 py-2 flex items-center gap-2">
                <svg class="w-4 h-4 text-red-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span class="text-red-300 text-xs font-medium">
                    {{ $campaign->recent_reports_count }} recent issue {{ Str::plural('report', $campaign->recent_reports_count) }}
                </span>
            </div>
            @endif

            {{-- Thumbnail --}}
            <div class="relative bg-slate-900 aspect-video overflow-hidden">
                @if($campaign->thumbnail_url)
                    <img src="{{ $campaign->thumbnail_url }}" alt="{{ $campaign->title }}"
                        class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                @else
                    @php
                        $placeholderGradients = [
                            'federal' => 'from-blue-950 via-blue-900 to-slate-900',
                            'state'   => 'from-purple-950 via-purple-900 to-slate-900',
                            'county'  => 'from-amber-950 via-amber-900 to-slate-900',
                            'city'    => 'from-teal-950 via-teal-900 to-slate-900',
                            'school'  => 'from-rose-950 via-rose-900 to-slate-900',
                            'special' => 'from-indigo-950 via-indigo-900 to-slate-900',
                        ];
                        $placeholderGradient = $placeholderGradients[$lvl] ?? 'from-slate-800 via-slate-850 to-slate-900';
                        $politicianName = optional($politician)->display_name ?? optional($politician)->first_name.' '.optional($politician)->last_name;
                        $initials = collect(explode(' ', trim($politicianName)))->map(fn($w) => strtoupper(substr($w,0,1)))->take(2)->implode('');
                    @endphp
                    <div class="w-full h-full flex flex-col items-center justify-center gap-3 bg-gradient-to-br {{ $placeholderGradient }}">
                        {{-- Politician avatar placeholder --}}
                        <div class="w-14 h-14 rounded-full bg-white/10 border border-white/20 flex items-center justify-center shadow-lg">
                            @if($initials)
                                <span class="text-xl font-bold text-white/80 tracking-wide">{{ $initials }}</span>
                            @else
                                <svg class="w-7 h-7 text-white/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            @endif
                        </div>
                        {{-- Campaign title --}}
                        <p class="text-white/60 text-xs font-medium text-center px-4 leading-snug line-clamp-2 max-w-[80%]">
                            {{ $campaign->title }}
                        </p>
                        {{-- Video pending indicator --}}
                        <div class="flex items-center gap-1.5 text-white/30">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10l4.553-2.069A1 1 0 0121 8.882v6.236a1 1 0 01-1.447.894L15 14M3 8a2 2 0 00-2 2v4a2 2 0 002 2h9a2 2 0 002-2v-4a2 2 0 00-2-2H3z"/>
                            </svg>
                            <span class="text-xs">Video coming soon</span>
                        </div>
                    </div>
                @endif

                {{-- Governance level badge --}}
                @if($lvl)
                <span class="absolute top-3 left-3 text-xs font-semibold px-2.5 py-1 rounded-full border {{ $lvlCss['bg'] }} {{ $lvlCss['text'] }}">
                    {{ $lvlLabel }}
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
                        @if($politician->page_published && $politician->slug)
                        <a href="{{ route('politician.public.show', $politician->slug) }}"
                           target="_blank" rel="noopener"
                           class="text-slate-400 text-xs truncate hover:text-emerald-400 transition"
                           title="View {{ $politician->full_name }}'s public page">
                            {{ $politician->full_name ?? 'Official' }}
                            @if($politician->political_office ?? false)
                                <span class="text-slate-600">&middot;</span>
                                {{ $politician->political_office }}
                            @endif
                            <svg class="w-3 h-3 inline-block ml-0.5 -mt-0.5 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                            </svg>
                        </a>
                        @else
                        <span class="text-slate-400 text-xs truncate">
                            {{ $politician->full_name ?? 'Official' }}
                            @if($politician->political_office ?? false)
                                <span class="text-slate-600">&middot;</span>
                                {{ $politician->political_office }}
                            @endif
                        </span>
                        @endif
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

                {{-- Sprint 3: Topic tags --}}
                @if($campaign->topics && $campaign->topics->count() > 0)
                <div class="flex flex-wrap gap-1">
                    @foreach($campaign->topics->take(3) as $topic)
                    <span class="inline-flex items-center gap-1 text-xs bg-slate-700/60 text-slate-300 px-2.5 py-1 rounded-full">
                        @if($topic->icon)
                            {{ $topic->icon }}
                        @endif
                        {{ $topic->name }}
                    </span>
                    @endforeach
                    @if($campaign->topics->count() > 3)
                    <span class="inline-flex items-center gap-1 text-xs bg-slate-700/60 text-slate-400 px-2.5 py-1 rounded-full">
                        +{{ $campaign->topics->count() - 3 }}
                    </span>
                    @endif
                </div>
                @endif

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
                    @if($isExcluded)
                        <div class="w-full text-center py-2.5 rounded-xl bg-slate-700/50 text-slate-500 text-sm font-medium cursor-default">
                            <svg class="w-4 h-4 inline-block mr-1 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            {{ $campaign->allow_repeat_views ? 'View limit reached' : 'Already watched' }}
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
                                    {{ $isWatchedBefore ? 'Watch Again &amp; Earn' : 'Watch &amp; Earn' }} ${{ number_format($payout, 2) }}
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

    {{-- ── Community & Local Ads (Citizen Campaigns) ─────────── --}}
    @if($citizenCampaigns->isNotEmpty())
    <div id="local-ads" class="pt-6 border-t border-slate-700/60 scroll-mt-24">
        <div class="flex items-center gap-3 mb-5">
            <div class="w-8 h-8 rounded-lg bg-amber-500/20 flex items-center justify-center">
                <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
            <div>
                <h2 class="text-lg font-bold text-white">Community & Local Ads</h2>
                <p class="text-slate-400 text-xs">Messages from local businesses, community notices, and ballot-issue advocates.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5">
            @foreach($citizenCampaigns as $campaign)
            @php
                $isWatchedBefore = in_array($campaign->id, $citizenWatchedBeforeIds);
                $isExcluded      = in_array($campaign->id, $citizenExcludedIds);
                $citizen         = $campaign->citizen;
                $remaining       = max(0, $campaign->total_views_requested - $campaign->views_completed);
                $fillPct         = $campaign->total_views_requested > 0
                    ? min(100, round(($campaign->views_completed / $campaign->total_views_requested) * 100))
                    : 0;
                $adTypeLabels = [
                    'local_business'     => 'Local Business',
                    'community_notice'   => 'Community Notice',
                    'ballot_issue'       => 'Ballot Issue',
                    'general_announcement' => 'Announcement',
                ];
                $adTypeLabel = $adTypeLabels[$campaign->citizen_ad_type->value ?? ''] ?? 'Community Ad';
                $payout      = (float) ($campaign->voter_payout_per_view ?? 0.50);
                $dur         = (int) ($campaign->media_duration ?? 0);
            @endphp
            <div class="flex flex-col bg-slate-800/50 rounded-2xl overflow-hidden hover:border-slate-600 transition border border-slate-700/60">
                {{-- Thumbnail --}}
                <div class="relative bg-slate-900 aspect-video overflow-hidden">
                    @if($campaign->thumbnail_url)
                        <img src="{{ $campaign->thumbnail_url }}" alt="{{ $campaign->title }}"
                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                    @else
                        @php
                            $placeholderGradient = 'from-amber-950 via-amber-900 to-slate-900';
                            $sponsorName = $citizen->business_name ?: $citizen->full_name;
                            $initials = collect(explode(' ', trim($sponsorName)))->map(fn($w) => strtoupper(substr($w,0,1)))->take(2)->implode('');
                        @endphp
                        <div class="w-full h-full flex flex-col items-center justify-center gap-3 bg-gradient-to-br {{ $placeholderGradient }}">
                            <div class="w-14 h-14 rounded-full bg-white/10 border border-white/20 flex items-center justify-center shadow-lg">
                                @if($initials)
                                    <span class="text-xl font-bold text-white/80 tracking-wide">{{ $initials }}</span>
                                @else
                                    <svg class="w-7 h-7 text-white/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                    </svg>
                                @endif
                            </div>
                            <p class="text-white/60 text-xs font-medium text-center px-4 leading-snug line-clamp-2 max-w-[80%]">{{ $campaign->title }}</p>
                        </div>
                    @endif

                    <span class="absolute top-3 left-3 text-xs font-semibold px-2.5 py-1 rounded-full border bg-amber-900/40 border-amber-700/40 text-amber-400">
                        {{ $adTypeLabel }}
                    </span>

                    @if($dur)
                    <span class="absolute top-3 right-3 text-xs bg-black/60 text-slate-300 px-2 py-1 rounded-full">
                        {{ $dur >= 60 ? floor($dur/60).'m '.($dur%60).'s' : $dur.'s' }}
                    </span>
                    @endif
                </div>

                {{-- Card body --}}
                <div class="flex flex-col flex-1 p-4 gap-3">
                    <div>
                        <div class="flex items-center gap-2 mb-1.5">
                            <div class="w-6 h-6 rounded-full bg-slate-700 flex items-center justify-center text-white text-xs font-bold shrink-0">
                                {{ strtoupper(substr($sponsorName ?? 'C', 0, 1)) }}
                            </div>
                            <span class="text-slate-400 text-xs truncate">{{ $sponsorName ?? 'Community Sponsor' }}
                                @if($citizen->city ?? false)
                                    <span class="text-slate-600">·</span> {{ $citizen->city }}
                                @endif
                            </span>
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

                    {{-- Progress bar --}}
                    <div>
                        <div class="flex items-center justify-between text-xs text-slate-500 mb-1">
                            <span>{{ number_format($remaining) }} spots left</span>
                            <span>{{ $fillPct }}% filled</span>
                        </div>
                        <div class="w-full bg-slate-700 rounded-full h-1.5 overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-500
                                {{ $fillPct >= 90 ? 'bg-red-500' : ($fillPct >= 70 ? 'bg-amber-500' : 'bg-emerald-500') }}"
                                style="width: {{ $fillPct }}%"></div>
                        </div>
                    </div>

                    {{-- CTA --}}
                    <div class="mt-auto pt-1">
                        @if($isExcluded)
                            <div class="w-full text-center py-2.5 rounded-xl bg-slate-700/50 text-slate-500 text-sm font-medium cursor-default">
                                <svg class="w-4 h-4 inline-block mr-1 -mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                {{ $campaign->allow_repeat_views ? 'View limit reached' : 'Already watched' }}
                            </div>
                        @elseif(! $canViewMore)
                            <div class="w-full text-center py-2.5 rounded-xl bg-slate-700/50 text-slate-500 text-sm font-medium cursor-default" title="Daily limit reached or account restricted">
                                Unavailable today
                            </div>
                        @else
                            <a href="{{ route('voter.citizen-campaigns.watch', $campaign) }}"
                               class="block w-full text-center py-2.5 rounded-xl text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-slate-800 bg-amber-600 hover:bg-amber-500 text-white focus:ring-amber-500">
                                <svg class="w-4 h-4 inline-block mr-1 -mt-0.5 ml-0.5" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M8 5v14l11-7z"/>
                                </svg>
                                {{ $isWatchedBefore ? 'Watch Again & Earn' : 'Watch & Earn' }} ${{ number_format($payout, 2) }}
                            </a>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
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
