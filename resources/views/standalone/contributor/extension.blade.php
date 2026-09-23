@extends('standalone.layouts.public')
@section('title', 'Install the U9itus Source Clipper')
@section('content')
<div class="max-w-3xl mx-auto px-4 py-8 space-y-6 text-slate-200">
    <header>
        <p class="text-sm text-amber-300">Community contributors · Desktop browser pilot</p>
        <h1 class="mt-2 text-3xl font-bold text-white">Clip a source while you browse</h1>
        <p class="mt-3 text-slate-400">Send a public news article or social post to your U9itus submission form. You choose the politician and explain its relevance before submitting for editorial review.</p>
    </header>
    <section class="rounded-xl border border-slate-700 p-5 space-y-4">
        <h2 class="text-xl font-semibold">Install in Chrome or Edge</h2>
        <p>This pilot is installed manually. It is not yet listed in a browser extension store.</p>
        <a class="inline-block rounded-lg bg-emerald-600 px-5 py-3 font-semibold text-white" href="{{ asset('downloads/u9itus-source-clipper-0.1.0.zip') }}" download>Download Source Clipper 0.1.0</a>
        <ol class="list-decimal pl-6 space-y-2 text-sm text-slate-300">
            <li>Extract the downloaded ZIP into a folder you will keep on your computer.</li>
            <li>Open <code>chrome://extensions</code> in Chrome, or <code>edge://extensions</code> in Edge.</li>
            <li>Turn on Developer mode, click <strong>Load unpacked</strong>, and select the extracted folder containing <code>manifest.json</code>.</li>
            <li>Pin <strong>U9itus Source Clipper</strong> from your browser’s extensions menu.</li>
        </ol>
        <p class="text-sm text-slate-400">Managed work or school browsers may restrict manual extension installation. You can always <a href="{{ route('contributor.chatter.index') }}" class="text-emerald-300 underline">paste a link into the submission form</a>. Mobile browsers can use that form too.</p>
    </section>
    <section class="space-y-3">
        <h2 class="text-xl font-semibold">Submit your first clipping</h2>
        <ol class="list-decimal pl-6 space-y-2">
            <li>Open the specific public article or post. Select a short passage if you want to include an excerpt.</li>
            <li>Click the extension and review the link, suggested headline, and excerpt. Confirm the content is public.</li>
            <li>Click <strong>Continue on U9itus</strong>. Sign in if prompted; your clipping will be waiting in the form.</li>
            <li>Select the politician, add relevance, confirm the source, and click <strong>Submit for review</strong>.</li>
        </ol>
    </section>
    <section class="rounded-xl border border-slate-700 p-5 space-y-3">
        <h2 class="text-xl font-semibold">Your privacy and access</h2>
        <p class="text-sm text-slate-300">The extension reads the current page’s link, title, and selected text only when you click it. It does not read passwords, cookies, browsing history, or whole articles, and it does not run a background crawler. Review the clipping carefully: the extension cannot identify every private page.</p>
        <p class="text-sm text-slate-300">Continuing opens a temporary draft in a new U9itus tab. It expires after 30 minutes and is cleared when imported into the form. Excerpts and relevance notes remain private to editors after submission. Nothing is submitted or published automatically.</p>
        <p class="text-sm text-slate-300">Your verified account must already have contributor access from a Super Admin. Installing the extension does not grant access. Remove it any time from your browser’s extensions page.</p>
    </section>
</div>
@endsection
