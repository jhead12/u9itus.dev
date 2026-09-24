@extends('standalone.layouts.public')
@section('title', 'Pricing')
@section('meta_description', 'What it costs to run a video message on U9itus, and what viewers earn for watching one.')
@php($money = fn (float $v) => '$' . number_format($v, 2))
@section('content')
<div class="mx-auto max-w-3xl px-4 py-12 text-slate-200">
    <p class="text-xs font-bold tracking-widest text-emerald-300">PRICING</p>
    <h1 class="mt-3 text-4xl font-bold text-white">Pay only for completed views</h1>
    <p class="mt-4 leading-relaxed text-slate-300">You buy credits and set a budget for each video message. A view is charged only when a registered viewer watches your message to the end. There are no subscriptions or setup fees.</p>

    <h2 class="mt-12 text-2xl font-semibold text-white">Running a message</h2>
    <dl class="mt-4 divide-y divide-slate-700 border-y border-slate-700">
        <div class="flex items-baseline justify-between gap-4 py-4">
            <dt><span class="font-medium text-white">Politicians and candidates</span><br><span class="text-sm text-slate-400">Campaign messages to voters in your district</span></dt>
            <dd class="whitespace-nowrap text-xl font-semibold text-white">{{ $money($pricing['politician_per_view']) }} <span class="text-sm font-normal text-slate-400">/ view</span></dd>
        </div>
        <div class="flex items-baseline justify-between gap-4 py-4">
            <dt><span class="font-medium text-white">Citizen community messages</span><br><span class="text-sm text-slate-400">Local causes, events, and announcements</span></dt>
            <dd class="whitespace-nowrap text-xl font-semibold text-white">{{ $money($pricing['citizen_per_view']) }} <span class="text-sm font-normal text-slate-400">/ view</span></dd>
        </div>
        <div class="flex items-baseline justify-between gap-4 py-4">
            <dt><span class="font-medium text-white">Ballot-issue messages</span><br><span class="text-sm text-slate-400">Require a PAC registration ID and admin review</span></dt>
            <dd class="whitespace-nowrap text-xl font-semibold text-white">{{ $money($pricing['ballot_issue_per_view']) }} <span class="text-sm font-normal text-slate-400">/ view</span></dd>
        </div>
    </dl>
    <ul class="mt-4 space-y-2 text-sm leading-relaxed text-slate-400">
        <li>The minimum campaign is 10 views ({{ $money($pricing['min_budget']) }} at the politician rate).</li>
        <li>Card payments add a {{ rtrim(rtrim(number_format($pricing['card_fee_percent'], 2), '0'), '.') }}% processing fee so your full credit amount lands in your balance.</li>
    </ul>

    <h2 class="mt-12 text-2xl font-semibold text-white">Watching messages</h2>
    <dl class="mt-4 divide-y divide-slate-700 border-y border-slate-700">
        <div class="flex items-baseline justify-between gap-4 py-4">
            <dt><span class="font-medium text-white">Viewers earn</span><br><span class="text-sm text-slate-400">For each message watched to the end</span></dt>
            <dd class="whitespace-nowrap text-xl font-semibold text-emerald-300">{{ $money($pricing['viewer_payout']) }} <span class="text-sm font-normal text-slate-400">/ view</span></dd>
        </div>
        <div class="flex items-baseline justify-between gap-4 py-4">
            <dt><span class="font-medium text-white">Referral bonus</span><br><span class="text-sm text-slate-400">Of what each viewer you refer earns, for as long as they watch</span></dt>
            <dd class="whitespace-nowrap text-xl font-semibold text-emerald-300">{{ rtrim(rtrim(number_format($pricing['referral_percent'], 2), '0'), '.') }}%</dd>
        </div>
    </dl>
    <p class="mt-4 text-sm leading-relaxed text-slate-400">Earnings are paid out once your balance reaches {{ $money($pricing['min_payout']) }}. Joining is free.</p>

    <div class="mt-12 flex flex-wrap gap-3">
        <a href="{{ route('register') }}" class="rounded-lg bg-emerald-600 px-5 py-3 font-semibold text-white transition hover:bg-emerald-500">Create an account</a>
        <a href="{{ route('how-it-works') }}" class="rounded-lg border border-slate-600 px-5 py-3 font-semibold text-slate-200 transition hover:border-slate-400">How it works</a>
    </div>
    <p class="mt-8 text-sm text-slate-400">Questions about pricing? <a href="{{ route('contact') }}" class="text-emerald-300 underline hover:text-emerald-200">Contact us</a>.</p>
</div>
@endsection
