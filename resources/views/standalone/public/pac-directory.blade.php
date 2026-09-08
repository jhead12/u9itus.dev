<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $ogTitle }} — {{ config('app.name', 'U9itus') }}</title>
    @include('standalone.partials.seo-head', ['seoDescription' => $ogDescription, 'seoCanonical' => $ogUrl])

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800&display=swap" rel="stylesheet" />

    @if(file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif

    <style>* { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-950 min-h-screen antialiased text-slate-100">

@php
    $fmtMoney = fn ($v) => is_numeric($v) ? '$' . number_format((float) $v) : null;
    $lastPage = max(1, (int) ceil($total / $perPage));
    $qs = fn (array $overrides = []) => http_build_query(array_filter(array_merge([
        'type'  => $filters['type'],
        'party' => $filters['party'],
        'state' => $filters['state'],
        'q'     => $filters['q'],
    ], $overrides), fn ($v) => $v !== null && $v !== ''));
    $typeTabs = ['' => 'All', 'super_pac' => 'Super PACs', 'pac' => 'PACs', 'party' => 'Party committees'];
@endphp

<nav class="sticky top-0 z-40 bg-slate-900/80 backdrop-blur-md border-b border-slate-700/50">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 flex items-center justify-between h-14">
        <a href="{{ url('/') }}" class="text-lg font-bold hover:opacity-80 transition">
            <span class="text-white">U9</span><span class="text-emerald-400">itus</span>
        </a>
        <div class="flex items-center gap-4">
            <a href="{{ route('politicians.directory') }}" class="hidden sm:inline-block text-sm text-slate-300 hover:text-white transition">Politicians</a>
            <a href="{{ route('us.map') }}" class="text-sm text-slate-300 hover:text-white transition">Map</a>
        </div>
    </div>
</nav>

<header class="border-b border-slate-800 bg-gradient-to-b from-slate-900 to-slate-950">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 py-10">
        <p class="text-xs font-semibold uppercase tracking-widest text-emerald-400 mb-2">Follow the money</p>
        <h1 class="text-2xl sm:text-3xl font-extrabold text-white">PAC &amp; Committee Directory</h1>
        <p class="mt-2 max-w-2xl text-sm text-slate-400">
            Independent-expenditure committees, Super PACs and party committees that spend to support or
            oppose federal candidates. Financials and spending are sourced from the Federal Election
            Commission; each committee links to the races it spends on.
        </p>
    </div>
</header>

<main class="max-w-6xl mx-auto px-4 sm:px-6 py-8">

    {{-- Filters --}}
    <form method="GET" action="{{ route('pacs.directory') }}" class="mb-6 space-y-4">
        <div class="flex flex-wrap gap-2">
            @foreach($typeTabs as $val => $label)
                <a href="?{{ $qs(['type' => $val, 'page' => null]) }}"
                   class="px-3 py-1.5 rounded-full text-xs font-semibold border transition
                   {{ (string) $filters['type'] === (string) $val
                        ? 'border-emerald-500 bg-emerald-500/15 text-emerald-300'
                        : 'border-slate-700 text-slate-300 hover:border-slate-500' }}">{{ $label }}</a>
            @endforeach
        </div>
        <div class="flex flex-wrap gap-3">
            <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Search committee name…"
                   class="flex-1 min-w-[200px] bg-slate-900 border border-slate-700 text-sm rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500" />
            <select name="party" class="bg-slate-900 border border-slate-700 text-sm rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                <option value="">Any party</option>
                <option value="DEM"  @selected($filters['party'] === 'DEM')>Democratic</option>
                <option value="REP"  @selected($filters['party'] === 'REP')>Republican</option>
                <option value="IND"  @selected($filters['party'] === 'IND')>Independent / other</option>
            </select>
            <select name="state" class="bg-slate-900 border border-slate-700 text-sm rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-emerald-500">
                <option value="">Any HQ state</option>
                @foreach($facets['states'] as $st)
                    <option value="{{ $st }}" @selected($filters['state'] === $st)>{{ $st }}</option>
                @endforeach
            </select>
            <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-sm font-semibold text-white transition">Filter</button>
            @if($filters['type'] || $filters['party'] || $filters['state'] || $filters['q'])
                <a href="{{ route('pacs.directory') }}" class="px-3 py-2 text-sm text-slate-400 hover:text-white transition self-center">Reset</a>
            @endif
        </div>
    </form>

    <p class="text-xs text-slate-500 mb-4">{{ number_format($total) }} committee{{ $total === 1 ? '' : 's' }}</p>

    @if($committees->isEmpty())
        <div class="rounded-xl border border-slate-800 bg-slate-900/50 p-10 text-center text-slate-400">
            No committees match these filters yet.
        </div>
    @else
        <ul class="grid gap-3 sm:grid-cols-2">
            @foreach($committees as $c)
                @php $p = $c->profile; @endphp
                <li>
                    <a href="{{ route('pacs.show', $c->publicSlug()) }}"
                       class="block h-full rounded-xl border border-slate-800 bg-slate-900/60 p-4 hover:border-emerald-500/50 hover:bg-slate-900 transition">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="font-semibold text-white truncate">{{ $c->name ?: $c->fec_committee_id }}</p>
                                <p class="mt-0.5 text-xs text-slate-400">
                                    {{ $p->kindLabel() }}
                                    @if($p->party) · {{ $p->party }} @endif
                                    @if($p->state) · {{ $p->state }} @endif
                                </p>
                            </div>
                            @if($p->is_super_pac)
                                <span class="shrink-0 text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full bg-amber-500/15 text-amber-300 border border-amber-500/30">Super PAC</span>
                            @endif
                        </div>
                        <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-xs">
                            @if($fmtMoney($p->independent_expenditures))
                                <span class="text-slate-300">IE spending
                                    <span class="font-semibold text-white">{{ $fmtMoney($p->independent_expenditures) }}</span></span>
                            @elseif($fmtMoney($p->total_disbursements))
                                <span class="text-slate-300">Disbursed
                                    <span class="font-semibold text-white">{{ $fmtMoney($p->total_disbursements) }}</span></span>
                            @endif
                            @if(!empty($p->spending_by_race))
                                <span class="text-slate-400">{{ count($p->spending_by_race) }} race{{ count($p->spending_by_race) === 1 ? '' : 's' }}</span>
                            @endif
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>

        @if($lastPage > 1)
            <nav class="mt-8 flex items-center justify-center gap-3 text-sm">
                @if($filters['page'] > 1)
                    <a href="?{{ $qs(['page' => $filters['page'] - 1]) }}" class="px-3 py-1.5 rounded-lg border border-slate-700 text-slate-300 hover:border-slate-500 transition">← Prev</a>
                @endif
                <span class="text-slate-500">Page {{ $filters['page'] }} of {{ $lastPage }}</span>
                @if($filters['page'] < $lastPage)
                    <a href="?{{ $qs(['page' => $filters['page'] + 1]) }}" class="px-3 py-1.5 rounded-lg border border-slate-700 text-slate-300 hover:border-slate-500 transition">Next →</a>
                @endif
            </nav>
        @endif
    @endif

    <p class="mt-10 text-xs text-slate-600">
        Source: Federal Election Commission (fec.gov). Figures are sums of itemized filings for the
        listed cycle and may lag the committee's most recent activity. This directory is informational
        and is not affiliated with, or endorsed by, any committee listed.
    </p>
</main>

</body>
</html>
