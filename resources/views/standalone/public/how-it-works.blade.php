@extends('standalone.layouts.public')
@section('title', 'How it works')
@section('meta_description', 'How U9itus connects politicians, citizens, and voters: research your ballot, run video messages, and earn for watching.')
@php($money = fn (float $v) => '$' . number_format($v, 2))
@section('content')
<div class="mx-auto max-w-3xl px-4 py-12 text-slate-200">
    <p class="text-xs font-bold tracking-widest text-emerald-300">HOW IT WORKS</p>
    <h1 class="mt-3 text-4xl font-bold text-white">A direct line between people and the officials who represent them</h1>
    <p class="mt-4 leading-relaxed text-slate-300">U9itus is a place to research who is on your ballot. It also lets politicians, candidates, and citizens reach voters with short video messages, and it pays viewers for their time.</p>

    <h2 class="mt-12 text-2xl font-semibold text-white">Research your ballot, free</h2>
    <p class="mt-3 leading-relaxed text-slate-300">You don't need an account for any of this.</p>
    <ul class="mt-4 space-y-2 leading-relaxed text-slate-300">
        <li><a href="{{ route('district.lookup') }}" class="text-emerald-300 underline hover:text-emerald-200">Find your districts</a> and the officials who represent you.</li>
        <li>Browse the <a href="{{ route('us.map') }}" class="text-emerald-300 underline hover:text-emerald-200">map</a> and the <a href="{{ route('politicians.directory') }}" class="text-emerald-300 underline hover:text-emerald-200">politician directory</a>.</li>
        <li><a href="{{ route('candidates.compare') }}" class="text-emerald-300 underline hover:text-emerald-200">Compare candidates</a> side by side, with a <a href="{{ route('candidates.compare.glossary') }}" class="text-emerald-300 underline hover:text-emerald-200">guide to what each office does</a>.</li>
        <li>See who funds campaigns in the <a href="{{ route('pacs.directory') }}" class="text-emerald-300 underline hover:text-emerald-200">PAC directory</a>, and read community <a href="{{ route('blog.index') }}" class="text-emerald-300 underline hover:text-emerald-200">posts</a> and <a href="{{ route('events.index') }}" class="text-emerald-300 underline hover:text-emerald-200">events</a>.</li>
    </ul>

    <h2 class="mt-12 text-2xl font-semibold text-white">For politicians and candidates</h2>
    <ol class="mt-4 list-decimal space-y-2 pl-5 leading-relaxed text-slate-300">
        <li>Claim or create your profile and verify that it's you.</li>
        <li>Upload a short video or connect a live stream, then choose the state, city, or district to reach.</li>
        <li>Set a budget and submit the message. An admin reviews every campaign before it runs.</li>
        <li>You pay {{ $money($pricing['politician_per_view']) }} only for each viewer who watches the whole message.</li>
    </ol>

    <h2 class="mt-12 text-2xl font-semibold text-white">For citizens</h2>
    <p class="mt-4 leading-relaxed text-slate-300">Citizens can run messages about local causes and ballot issues, write posts, and host events. An admin reviews every message before it runs, and ballot-issue messages also need a PAC registration ID. Once you verify your identity, your posts go live right away instead of waiting for review.</p>

    <h2 class="mt-12 text-2xl font-semibold text-white">For voters</h2>
    <ol class="mt-4 list-decimal space-y-2 pl-5 leading-relaxed text-slate-300">
        <li>Sign up free and verify your account.</li>
        <li>We notify you when a message from your area is ready. Each message can be watched once, through a one-time link.</li>
        <li>Watch it to the end and earn {{ $money($pricing['viewer_payout']) }}.</li>
        <li>Get paid once your balance reaches {{ $money($pricing['min_payout']) }}. Invite friends and earn a bonus on what they earn.</li>
    </ol>

    <div class="mt-12 flex flex-wrap gap-3">
        <a href="{{ route('register') }}" class="rounded-lg bg-emerald-600 px-5 py-3 font-semibold text-white transition hover:bg-emerald-500">Create an account</a>
        <a href="{{ route('pricing') }}" class="rounded-lg border border-slate-600 px-5 py-3 font-semibold text-slate-200 transition hover:border-slate-400">See pricing</a>
    </div>
    <p class="mt-8 text-sm text-slate-400">Still have questions? <a href="{{ route('contact') }}" class="text-emerald-300 underline hover:text-emerald-200">Contact us</a>.</p>
</div>
@endsection
