@extends('standalone.layouts.dashboard')

@section('title', 'Committees · '.$measure->title)
@section('page-title', 'Ballot Measures')

@php
    $input = 'w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50';
@endphp

@section('content')
<div class="px-6 py-8 max-w-5xl mx-auto space-y-8">
    <div>
        <a href="{{ route('admin.ballot-measures.index') }}" class="text-sm text-slate-400 hover:text-white">← Ballot measures</a>
        <h1 class="text-2xl font-bold text-white mt-2">Who's funding this</h1>
        <p class="text-slate-300 mt-1">{{ $measure->placeLabel() }}{{ $measure->measure_number ? ' · #'.$measure->measure_number : '' }} — {{ $measure->title }}</p>
        <p class="text-slate-400 text-sm mt-2 max-w-3xl">
            Link each campaign committee that supports or opposes this measure. Committee names often don't say which side they're on,
            so check each one against its filing. Only verified links are shown to voters. Links with warnings wait in the
            <a href="{{ route('admin.ballot-measure-committees.index') }}" class="text-emerald-400 hover:text-emerald-300">review queue</a>.
        </p>
    </div>

    @if(session('warning'))
        <div class="bg-amber-500/10 border border-amber-500/30 rounded-lg px-4 py-3 text-amber-300 text-sm">{{ session('warning') }}</div>
    @endif

    {{-- State's official filing site --}}
    <form method="POST" action="{{ route('admin.ballot-measures.finance-url', $measure) }}" class="bg-slate-800/50 border border-slate-700 rounded-xl p-5 space-y-3">
        @csrf
        @method('PUT')
        <label for="campaign_finance_url" class="block text-sm font-medium text-slate-300">{{ strtoupper($measure->state) }} official campaign finance site</label>
        <p class="text-xs text-slate-500">Shown to voters as "View official filings", even before any committee is linked. Evidence links on other sites are flagged for review.</p>
        <div class="flex flex-col sm:flex-row gap-3">
            <input type="url" id="campaign_finance_url" name="campaign_finance_url" value="{{ old('campaign_finance_url', $financeUrl) }}"
                placeholder="https://cal-access.sos.ca.gov/" class="{{ $input }}">
            <button type="submit" class="px-5 py-2.5 rounded-lg bg-slate-700 hover:bg-slate-600 text-white text-sm font-medium transition whitespace-nowrap">Save site</button>
        </div>
    </form>

    {{-- Add a committee --}}
    <form method="POST" action="{{ route('admin.ballot-measures.committees.store', $measure) }}" class="bg-slate-800/50 border border-slate-700 rounded-xl p-5 space-y-4">
        @csrf
        <h2 class="text-lg font-semibold text-white">Link a committee</h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label for="committee_id" class="block text-sm font-medium text-slate-300 mb-1.5">Filer ID <span class="text-red-400">*</span></label>
                <input type="text" id="committee_id" name="committee_id" value="{{ old('committee_id') }}" placeholder="1486767" class="{{ $input }}" required>
            </div>
            <div class="sm:col-span-2">
                <label for="committee_name" class="block text-sm font-medium text-slate-300 mb-1.5">Committee name <span class="text-red-400">*</span></label>
                <input type="text" id="committee_name" name="committee_name" value="{{ old('committee_name') }}" placeholder="As it appears on the filing" class="{{ $input }}" required>
            </div>
            <div>
                <label for="position" class="block text-sm font-medium text-slate-300 mb-1.5">Side <span class="text-red-400">*</span></label>
                <select id="position" name="position" class="{{ $input }}" required>
                    <option value="support" @selected(old('position') === 'support')>Supports (Yes)</option>
                    <option value="oppose" @selected(old('position') === 'oppose')>Opposes (No)</option>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label for="source_url" class="block text-sm font-medium text-slate-300 mb-1.5">Evidence link <span class="text-red-400">*</span></label>
                <input type="url" id="source_url" name="source_url" value="{{ old('source_url') }}" placeholder="The filing or committee page that shows its position" class="{{ $input }}" required>
            </div>
        </div>
        <label class="flex items-start gap-2 text-sm text-slate-300">
            <input type="checkbox" name="confirmed" value="1" class="mt-0.5 rounded border-slate-600 bg-slate-900 text-emerald-500" @checked(old('confirmed'))>
            I checked this against the filing: this committee takes this side of this measure. <span class="text-slate-500">(Verifies on entry unless a warning is found.)</span>
        </label>
        <div class="flex justify-end">
            <button type="submit" class="px-6 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold transition">Link committee</button>
        </div>
    </form>

    {{-- Existing links --}}
    <div class="space-y-3">
        <h2 class="text-lg font-semibold text-white">Linked committees ({{ $committees->count() }})</h2>
        @forelse($committees as $link)
            @include('standalone.admin.ballot-measures._committee-row', ['link' => $link])
        @empty
            <p class="text-slate-500 text-sm">No committees linked yet.</p>
        @endforelse
    </div>
</div>
@endsection
