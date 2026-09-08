<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $ogTitle }} — {{ config('app.name', 'U9itus') }}</title>
    @include('standalone.partials.seo-head')
    <meta name="description" content="{{ $ogDescription }}">
    <link rel="canonical" href="{{ $ogUrl }}">
    <meta property="og:type"  content="website">
    <meta property="og:url"   content="{{ $ogUrl }}">
    <meta property="og:title" content="{{ $ogTitle }} — {{ config('app.name', 'U9itus') }}">
    <meta property="og:description" content="{{ $ogDescription }}">
    <meta name="twitter:card" content="summary">

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
    // Values may be bare numerics (FEC totals) or pre-formatted "$1,234" strings
    // (donor rows carried over from the OpenSecrets-style scrape).
    $fmtMoney = function ($v) {
        if ($v === null || $v === '') return '—';
        $s = trim((string) $v);
        if (str_starts_with($s, '$')) return $s;
        return is_numeric($s) ? '$' . number_format((float) $s) : '—';
    };
    $name = $committee->name ?: $committee->fec_committee_id;
    $fecUrl = 'https://www.fec.gov/data/committee/' . $committee->fec_committee_id . '/';
    $races = collect($profile->spending_by_race ?? []);
    $org = $committee->organization && $committee->organization->is_active ? $committee->organization : null;
@endphp

<nav class="sticky top-0 z-40 bg-slate-900/80 backdrop-blur-md border-b border-slate-700/50">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 flex items-center justify-between h-14">
        <a href="{{ route('pacs.directory') }}" class="text-sm text-slate-400 hover:text-white transition">← All committees</a>
        <a href="{{ url('/') }}" class="text-lg font-bold hover:opacity-80 transition">
            <span class="text-white">U9</span><span class="text-emerald-400">itus</span>
        </a>
    </div>
</nav>

<main class="max-w-4xl mx-auto px-4 sm:px-6 py-8 space-y-6">

    {{-- ── Header ── --}}
    <section class="rounded-2xl border border-slate-800 bg-slate-900/60 p-6">
        <div class="flex items-start gap-4">
            @if($org && $org->logo_url)
                <img src="{{ $org->logo_url }}" alt="" class="w-14 h-14 rounded-lg object-contain bg-white/5 p-1 shrink-0">
            @endif
            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-xl sm:text-2xl font-extrabold text-white">{{ $name }}</h1>
                    @if($profile->is_super_pac)
                        <span class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full bg-amber-500/15 text-amber-300 border border-amber-500/30">Super PAC</span>
                    @endif
                </div>
                <p class="mt-1 text-sm text-slate-400">
                    {{ $profile->kindLabel() }}
                    @if($profile->designation_full) · {{ $profile->designation_full }} @endif
                    @if($profile->party) · {{ $profile->party }} @endif
                </p>
                <dl class="mt-3 grid grid-cols-2 sm:grid-cols-3 gap-x-4 gap-y-2 text-xs">
                    <div>
                        <dt class="text-slate-500">FEC ID</dt>
                        <dd class="text-slate-200 font-mono">{{ $committee->fec_committee_id }}</dd>
                    </div>
                    @if($profile->treasurer_name)
                        <div>
                            <dt class="text-slate-500">Treasurer</dt>
                            <dd class="text-slate-200">{{ $profile->treasurer_name }}</dd>
                        </div>
                    @endif
                    @if($profile->city || $profile->state)
                        <div>
                            <dt class="text-slate-500">Headquarters</dt>
                            <dd class="text-slate-200">{{ collect([$profile->city, $profile->state])->filter()->join(', ') }}</dd>
                        </div>
                    @endif
                </dl>
                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="{{ $fecUrl }}" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg border border-slate-700 text-slate-200 hover:border-slate-500 transition">
                        FEC filings ↗
                    </a>
                    @if($profile->fec_website_url)
                        <a href="{{ \Illuminate\Support\Str::startsWith($profile->fec_website_url, ['http://','https://']) ? $profile->fec_website_url : 'https://' . $profile->fec_website_url }}"
                           target="_blank" rel="noopener nofollow"
                           class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg border border-slate-700 text-slate-200 hover:border-slate-500 transition">
                            Committee website ↗
                        </a>
                    @endif
                    @if($org && $org->website_url)
                        <a href="{{ $org->website_url }}" target="_blank" rel="noopener nofollow"
                           class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-1.5 rounded-lg border border-emerald-600/40 bg-emerald-500/10 text-emerald-200 hover:border-emerald-500 transition">
                            {{ $org->name }} ↗
                        </a>
                    @endif
                </div>
                @if($org && $org->description)
                    <p class="mt-3 text-sm text-slate-300">{{ $org->description }}</p>
                @endif
            </div>
        </div>
    </section>

    {{-- ── Cycle financials ── --}}
    <section class="rounded-2xl border border-slate-800 bg-slate-900/60 p-6">
        <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-4">
            {{ $profile->cycle }} cycle · reported to the FEC
            @if($profile->coverage_end_date)
                <span class="font-normal normal-case tracking-normal text-slate-500">· through {{ $profile->coverage_end_date->format('M j, Y') }}</span>
            @endif
        </h2>
        <dl class="grid grid-cols-2 sm:grid-cols-3 gap-4">
            <div class="rounded-xl bg-slate-800/50 p-4">
                <dt class="text-xs text-slate-400">Total raised</dt>
                <dd class="mt-1 text-lg font-bold text-white tabular-nums">{{ $fmtMoney($profile->total_receipts) }}</dd>
            </div>
            <div class="rounded-xl bg-slate-800/50 p-4">
                <dt class="text-xs text-slate-400">Total spent</dt>
                <dd class="mt-1 text-lg font-bold text-white tabular-nums">{{ $fmtMoney($profile->total_disbursements) }}</dd>
            </div>
            <div class="rounded-xl bg-slate-800/50 p-4">
                <dt class="text-xs text-slate-400">Independent expenditures</dt>
                <dd class="mt-1 text-lg font-bold text-emerald-300 tabular-nums">{{ $fmtMoney($profile->independent_expenditures) }}</dd>
            </div>
            <div class="rounded-xl bg-slate-800/50 p-4">
                <dt class="text-xs text-slate-400">Cash on hand</dt>
                <dd class="mt-1 text-lg font-bold text-white tabular-nums">{{ $fmtMoney($profile->cash_on_hand) }}</dd>
            </div>
            <div class="rounded-xl bg-slate-800/50 p-4">
                <dt class="text-xs text-slate-400">Debts owed</dt>
                <dd class="mt-1 text-lg font-bold text-rose-300 tabular-nums">{{ $fmtMoney($profile->debts_owed) }}</dd>
            </div>
        </dl>
    </section>

    {{-- ── Spending by race ── --}}
    @if($races->isNotEmpty())
        <section class="rounded-2xl border border-slate-800 bg-slate-900/60 p-6">
            <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-4">
                Spending in these races
                <span class="font-normal normal-case tracking-normal text-slate-500">· independent expenditures, {{ $profile->cycle }}</span>
            </h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-slate-500 border-b border-slate-800">
                            <th class="py-2 pr-3 font-medium">Candidate</th>
                            <th class="py-2 px-3 font-medium">Office</th>
                            <th class="py-2 px-3 font-medium text-right">Supporting</th>
                            <th class="py-2 pl-3 font-medium text-right">Opposing</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        @foreach($races as $r)
                            <tr>
                                <td class="py-2.5 pr-3">
                                    @if(!empty($r['politician_slug']))
                                        <a href="{{ route('politician.public.show', $r['politician_slug']) }}"
                                           class="text-slate-100 font-medium hover:text-emerald-300 underline decoration-slate-700 underline-offset-2 hover:decoration-emerald-400">{{ $r['candidate_name'] }}</a>
                                    @else
                                        <span class="text-slate-200">{{ $r['candidate_name'] }}</span>
                                    @endif
                                </td>
                                <td class="py-2.5 px-3 text-slate-400 text-xs">
                                    {{ collect([$r['office'] ?? null, $r['state'] ?? null, ($r['district'] ?? null) && $r['district'] !== '00' ? 'District ' . ltrim((string) $r['district'], '0') : null])->filter()->join(' · ') ?: '—' }}
                                </td>
                                <td class="py-2.5 px-3 text-right tabular-nums {{ ($r['support'] ?? 0) > 0 ? 'text-emerald-300 font-semibold' : 'text-slate-600' }}">
                                    {{ ($r['support'] ?? 0) > 0 ? $fmtMoney($r['support']) : '—' }}
                                </td>
                                <td class="py-2.5 pl-3 text-right tabular-nums {{ ($r['oppose'] ?? 0) > 0 ? 'text-rose-300 font-semibold' : 'text-slate-600' }}">
                                    {{ ($r['oppose'] ?? 0) > 0 ? $fmtMoney($r['oppose']) : '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    {{-- ── Recent expenditures ── --}}
    @if(!empty($profile->recent_expenditures))
        <section class="rounded-2xl border border-slate-800 bg-slate-900/60 p-6">
            <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-4">Recent independent expenditures</h2>
            <ul class="space-y-2.5">
                @foreach($profile->recent_expenditures as $e)
                    <li class="flex items-start justify-between gap-3 text-sm">
                        <span class="min-w-0">
                            <span class="text-[10px] font-semibold uppercase tracking-wide px-1.5 py-0.5 rounded-full border align-middle
                                {{ ($e['support_oppose'] ?? '') === 'O'
                                    ? 'border-rose-500/40 bg-rose-500/10 text-rose-300'
                                    : 'border-emerald-500/40 bg-emerald-500/10 text-emerald-300' }}">
                                {{ ($e['support_oppose'] ?? '') === 'O' ? 'Oppose' : 'Support' }}
                            </span>
                            <span class="text-slate-200">{{ $e['candidate_name'] ?? '—' }}</span>
                            @if(!empty($e['purpose']))
                                <span class="text-slate-500">— {{ $e['purpose'] }}</span>
                            @endif
                            @if(!empty($e['date']))
                                <span class="block text-xs text-slate-600 mt-0.5">{{ \Illuminate\Support\Carbon::parse($e['date'])->format('M j, Y') }}</span>
                            @endif
                        </span>
                        <span class="shrink-0 font-semibold text-white tabular-nums">{{ $fmtMoney($e['amount'] ?? null) }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- ── Top donors ── --}}
    @if(!empty($profile->top_donors))
        <section class="rounded-2xl border border-slate-800 bg-slate-900/60 p-6">
            <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-4">
                Top donors to this committee
                <span class="font-normal normal-case tracking-normal text-slate-500">· FEC Schedule A</span>
            </h2>
            <ol class="space-y-2">
                @foreach(array_slice($profile->top_donors, 0, 10) as $i => $d)
                    <li class="flex items-center justify-between gap-2 text-sm">
                        <span class="text-xs text-slate-500 tabular-nums w-5">{{ $i + 1 }}.</span>
                        <span class="flex-1 truncate text-slate-200">{{ $d['name'] ?? '—' }}</span>
                        <span class="font-semibold text-white tabular-nums">{{ $fmtMoney($d['total'] ?? null) }}</span>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    <p class="text-xs text-slate-600 pt-2">
        Source: Federal Election Commission (<a href="{{ $fecUrl }}" target="_blank" rel="noopener" class="underline hover:text-slate-400">fec.gov</a>).
        Figures are sums of itemized filings for the {{ $profile->cycle }} cycle and may lag the committee's
        most recent activity; a committee's full totals may be higher than shown. Last refreshed
        {{ optional($profile->enriched_at)->diffForHumans() }}. U9itus is not affiliated with, or endorsed
        by, this committee.
    </p>
</main>

</body>
</html>
