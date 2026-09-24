@extends('standalone.layouts.public')
@section('title', 'Compare candidates')
@section('meta_description', 'Compare public candidate records and sourced policy statements. Share a live comparison or print a research guide.')
@section('content')
<div class="comparison-page">
    <header class="comparison-heading">
        <p class="comparison-kicker">VOTER RESEARCH · LIVE COMPARISON</p>
        <h1>Understand your choices.</h1>
        <p>Compare up to three people for the same seat. Read the evidence, follow the sources, and make your own decision.</p>
        <p class="comparison-live">This is a live comparison of current public records, not a frozen election guide.</p>
    </header>
    {{-- Mirrors the Web Reporter candidate picker (partials/chatter-candidate-picker). --}}
    <form id="comparison-search" class="comparison-finder" role="search">
        <label class="comparison-search-field">Search candidates<input id="comparison-query" type="search" maxlength="120" placeholder="Type a name, state, office, or party…" aria-describedby="comparison-search-help" aria-controls="comparison-results" autocomplete="off"></label>
        <label class="comparison-state-field">State<select id="comparison-state"><option value="">All states</option>@foreach($states as $state)<option value="{{ $state }}">{{ $state }}</option>@endforeach</select></label>
        <div class="comparison-address">
            <label>Find by address — locates their district<input id="comparison-address" type="text" maxlength="160" autocomplete="off" placeholder="Street address, e.g. 101 W Abram St, Arlington"></label>
            <button id="comparison-address-find" type="button">Find</button>
            <button id="comparison-address-clear" type="button" hidden>Show all</button>
        </div>
        <p id="comparison-search-help" class="comparison-help">A city name alone often can't be resolved — many cities span several districts. A street address gives the most reliable match. You can also type a district, such as CA-03.</p>
    </form>
    <p id="comparison-search-status" class="comparison-help" role="status" aria-live="polite"></p>
    <div id="comparison-results" class="comparison-results" role="group" aria-label="Candidate results"></div>
    <p id="comparison-status" role="status" aria-live="polite"></p>
    <div id="comparison-actions" class="comparison-controls" hidden>
        <button id="comparison-share" type="button">Copy comparison link</button>
        <button id="comparison-print" type="button">Print / Save as PDF</button>
        <label id="comparison-link-fallback" hidden>Copy this link<input id="comparison-link" readonly></label>
    </div>
    <div id="comparison-content"><p class="comparison-empty">Choose a state to see races with running candidates, search for a candidate, or find your district by address. No account needed.</p></div>
    <div id="comparison-print-guide" class="comparison-print-only"></div>
    <footer id="comparison-print-footer" hidden class="comparison-print-only">
        <p class="guide-brand">U9itus · Independent voter research</p>
        <h2 id="comparison-source-heading">Where can I check the sources?</h2>
        <p>Use these numbered references to read the original material.</p><ol id="comparison-sources"></ol>
        <h2>How can I reopen this comparison?</h2>
        <div class="comparison-print-link"><img id="comparison-qr" alt="QR code for this live comparison" width="110" height="110"><div><p id="comparison-generated"></p><p>Scan the QR code or open this address. The online records can change after this guide is printed.</p><p id="comparison-url"></p></div></div>
    </footer>
    <noscript>JavaScript is required to select candidates and load the comparison.</noscript>
</div>
@endsection
@push('scripts')
@vite('resources/js/compare/app.js')
@endpush
