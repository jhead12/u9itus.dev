@extends('standalone.layouts.dashboard')

@section('title', 'Import from Voter Guide')
@section('page-title', 'Ballot Measures')

@php
    $input = 'w-full bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50';
    $ctx = fn ($key, $default = null) => old($key, $context[$key] ?? $default);
@endphp

@section('content')
<div class="px-6 py-8 max-w-5xl mx-auto">
    <h1 class="text-2xl font-bold text-white mb-2">Import from Voter Guide</h1>
    <p class="text-slate-400 text-sm mb-8 max-w-2xl">
        Upload a voter guide (PDF, scan or text) or paste a link to one. Text is read from the file, or OCR'd if it is a scan,
        and ballot measures are pulled out for you to review before anything is saved.
    </p>

    @if(session('error'))
        <div class="bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3 mb-6"><p class="text-red-400 text-sm">{{ session('error') }}</p></div>
    @endif
    @if($errors->any())
        <div class="bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3 mb-6">
            @foreach($errors->all() as $error)<p class="text-red-300 text-sm">• {{ $error }}</p>@endforeach
        </div>
    @endif

    @if($measures === null)
    <form method="POST" action="{{ route('admin.ballot-measures.import.preview') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-6 space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div>
                    <label for="guide_file" class="block text-sm font-medium text-slate-300 mb-1.5">Guide file <span class="text-slate-500 font-normal">(PDF, image or .txt, up to 20 MB)</span></label>
                    <input type="file" id="guide_file" name="guide_file" accept=".pdf,.png,.jpg,.jpeg,.tif,.tiff,.webp,.txt"
                        class="block w-full text-sm text-slate-300 file:mr-3 file:rounded-lg file:border-0 file:bg-slate-700 file:px-4 file:py-2 file:text-white hover:file:bg-slate-600">
                </div>
                <div>
                    <label for="guide_url" class="block text-sm font-medium text-slate-300 mb-1.5">…or a link to the guide</label>
                    <input type="url" id="guide_url" name="guide_url" value="{{ old('guide_url') }}" placeholder="https://county.gov/voter-guide.pdf" class="{{ $input }}">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
                <div>
                    <label for="state" class="block text-sm font-medium text-slate-300 mb-1.5">State <span class="text-red-400">*</span></label>
                    <input type="text" id="state" name="state" maxlength="2" value="{{ $ctx('state') }}" class="{{ $input }} uppercase" required>
                </div>
                <div>
                    <label for="level" class="block text-sm font-medium text-slate-300 mb-1.5">Level <span class="text-red-400">*</span></label>
                    <select id="level" name="level" class="{{ $input }}" required>
                        @foreach(\App\Models\BallotMeasure::LEVELS as $key => $label)
                            <option value="{{ $key }}" @selected($ctx('level', 'county') === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="election_date" class="block text-sm font-medium text-slate-300 mb-1.5">Election date</label>
                    <input type="date" id="election_date" name="election_date" value="{{ $ctx('election_date') }}" class="{{ $input }}">
                </div>
                <div>
                    <label for="county" class="block text-sm font-medium text-slate-300 mb-1.5">County</label>
                    <input type="text" id="county" name="county" value="{{ $ctx('county') }}" placeholder="San Diego County" class="{{ $input }}">
                </div>
                <div class="sm:col-span-2">
                    <label for="locality" class="block text-sm font-medium text-slate-300 mb-1.5">City / district <span class="text-slate-500 font-normal">(for city or district guides)</span></label>
                    <input type="text" id="locality" name="locality" value="{{ $ctx('locality') }}" placeholder="Lakeside Union School District" class="{{ $input }}">
                </div>
            </div>
        </div>

        <div class="flex items-center justify-between gap-4">
            <a href="{{ route('admin.ballot-measures.index') }}" class="px-5 py-2.5 rounded-lg bg-slate-700 hover:bg-slate-600 text-white text-sm font-medium transition">← Back to list</a>
            <button type="submit" class="px-6 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold transition shadow-md shadow-emerald-900/30">Extract measures</button>
        </div>
    </form>
    @else
    <form method="POST" action="{{ route('admin.ballot-measures.import.store') }}" class="space-y-6">
        @csrf
        @foreach(['state', 'level', 'county', 'locality', 'election_date', 'source_url'] as $field)
            <input type="hidden" name="{{ $field }}" value="{{ $context[$field] ?? '' }}">
        @endforeach

        <div class="bg-slate-800/50 border border-slate-700 rounded-xl px-5 py-4 text-sm text-slate-300">
            Found <strong class="text-white">{{ count($measures) }}</strong> {{ Str::plural('measure', count($measures)) }} for
            <strong class="text-white">{{ $context['state'] }}{{ ($context['locality'] ?? $context['county']) ? ' · '.($context['locality'] ?? $context['county']) : '' }}</strong>
            ({{ \App\Models\BallotMeasure::LEVELS[$context['level']] }}).
            Fix anything the parser got wrong and untick what should not be imported.
        </div>

        @foreach($measures as $i => $measure)
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-5 space-y-3">
            <label class="flex items-center gap-2 text-sm text-slate-300">
                <input type="checkbox" name="measures[{{ $i }}][include]" value="1" checked class="rounded border-slate-600 bg-slate-900 text-emerald-500">
                Import this measure
            </label>
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                <input type="text" name="measures[{{ $i }}][title]" value="{{ $measure['title'] }}" placeholder="Title" class="{{ $input }} sm:col-span-3">
                <input type="text" name="measures[{{ $i }}][measure_number]" value="{{ $measure['measure_number'] }}" placeholder="Number" class="{{ $input }}">
            </div>
            <textarea name="measures[{{ $i }}][summary]" rows="3" placeholder="Summary" class="{{ $input }} resize-y">{{ $measure['summary'] }}</textarea>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <textarea name="measures[{{ $i }}][yes_meaning]" rows="2" placeholder="What a Yes vote means" class="{{ $input }} resize-y">{{ $measure['yes_meaning'] }}</textarea>
                <textarea name="measures[{{ $i }}][no_meaning]" rows="2" placeholder="What a No vote means" class="{{ $input }} resize-y">{{ $measure['no_meaning'] }}</textarea>
            </div>
        </div>
        @endforeach

        <div class="flex items-center justify-between gap-4">
            <a href="{{ route('admin.ballot-measures.import') }}" class="px-5 py-2.5 rounded-lg bg-slate-700 hover:bg-slate-600 text-white text-sm font-medium transition">← Start over</a>
            <button type="submit" class="px-6 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold transition shadow-md shadow-emerald-900/30">Import selected</button>
        </div>
    </form>
    @endif
</div>
@endsection
