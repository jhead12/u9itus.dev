@extends('standalone.layouts.public')
@section('title', 'Contact us')
@section('meta_description', 'Questions, feedback, or problems with U9itus? Send us a message and we will get back to you.')
@section('content')
<div class="mx-auto max-w-3xl px-4 py-12 text-slate-200">
    <p class="text-xs font-bold tracking-widest text-emerald-300">SUPPORT</p>
    <h1 class="mt-3 text-4xl font-bold text-white">Contact us</h1>
    <p class="mt-4 leading-relaxed text-slate-300">Have a question about your account, found something that looks wrong, or want to share feedback? Send us a message through our contact form and we will get back to you by email.</p>
    <p class="mt-8">
        <a href="{{ $formUrl }}" target="_blank" rel="noopener noreferrer"
           class="inline-block rounded-lg bg-emerald-600 px-5 py-3 font-semibold text-white transition hover:bg-emerald-500">
            Open the contact form
        </a>
    </p>
    <p class="mt-4 text-sm text-slate-400">The form opens in Google Forms in a new tab.</p>
</div>
@endsection
