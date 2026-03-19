<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>District Lookup — {{ config('app.name', 'U9itus') }}</title>
    <meta name="description" content="Enter your address to find your district and view candidates running in your area.">

    @if(file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
</head>
<body class="bg-slate-950 min-h-screen antialiased text-slate-100">
    <nav class="sticky top-0 z-40 bg-slate-900/80 backdrop-blur-md border-b border-slate-700/50">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 flex items-center justify-between h-14">
            <a href="{{ url('/') }}" class="flex items-center space-x-1 text-lg font-bold hover:opacity-80 transition">
                <span class="text-white">U9</span><span class="text-emerald-400">itus</span>
            </a>
            <div class="flex items-center gap-3 text-sm">
                <a href="{{ route('politicians.directory') }}" class="text-slate-300 hover:text-white transition">Browse Candidates</a>
                @auth
                    <a href="{{ route('dashboard') }}" class="text-slate-300 hover:text-white transition">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="text-slate-300 hover:text-white transition">Sign In</a>
                @endauth
            </div>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4 sm:px-6 py-10">
        <section class="mb-8">
            <h1 class="text-3xl sm:text-4xl font-bold text-white mb-3">Find Your District</h1>
            <p class="text-slate-400 max-w-2xl">
                Enter your home address to identify your congressional district and see candidates currently published on U9itus in your area.
            </p>
            <p class="text-slate-500 text-sm mt-2">For best results, enter a full address with street, city, and state. ZIP-only lookups may not return full district detail.</p>
        </section>

        <section class="bg-slate-900/70 border border-slate-700/50 rounded-2xl p-4 sm:p-6 mb-6">
            <form method="GET" action="{{ route('district.lookup') }}" class="flex flex-col sm:flex-row gap-3">
                <input
                    type="text"
                    name="address"
                    value="{{ $address }}"
                    placeholder="123 Main St, Los Angeles, CA 90012 or 92555"
                    class="flex-1 bg-slate-800 border border-slate-700 text-white placeholder-slate-500 rounded-lg px-4 py-3 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
                    required
                    maxlength="255"
                />
                <button
                    type="submit"
                    class="bg-emerald-600 hover:bg-emerald-500 text-white px-5 py-3 rounded-lg text-sm font-semibold transition"
                >
                    Lookup District
                </button>
            </form>

            @if($error)
                <p class="mt-3 text-sm text-rose-300">{{ $error }}</p>
            @endif
        </section>

        @if($lookupResult)
            <section class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
                <div class="bg-slate-900/60 border border-slate-700/50 rounded-xl p-5">
                    <p class="text-xs uppercase tracking-wider text-slate-400 mb-2">Matched Address</p>
                    <p class="text-white text-sm leading-relaxed">{{ $lookupResult['matched_address'] ?? 'N/A' }}</p>
                </div>
                <div class="bg-slate-900/60 border border-slate-700/50 rounded-xl p-5">
                    <p class="text-xs uppercase tracking-wider text-slate-400 mb-2">Resolved District</p>
                    <p class="text-white text-lg font-semibold">
                        {{ $lookupResult['district_code'] ?? 'Not available' }}
                    </p>
                    @if(!empty($lookupResult['source']))
                        @php
                            $sourceLabel = match ($lookupResult['source']) {
                                'census_geocoder' => 'Census Geocoder',
                                'google_civic' => 'Google Civic',
                                default => ucwords(str_replace('_', ' ', (string) $lookupResult['source'])),
                            };
                        @endphp
                        <p class="text-slate-400 text-xs mt-1 uppercase tracking-wide">Source: {{ $sourceLabel }}</p>
                    @endif
                    @if(!empty($lookupResult['district_label']))
                        <p class="text-slate-400 text-sm mt-1">{{ $lookupResult['district_label'] }}</p>
                    @endif
                </div>
            </section>

            <section>
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-bold text-white">Candidates Running In Your Area</h2>
                    <span class="text-sm text-slate-400">{{ $candidates->count() }} found</span>
                </div>

                @if($candidates->isEmpty())
                    <div class="bg-slate-900/60 border border-slate-700/50 rounded-xl p-6">
                        <p class="text-slate-300">No published candidates were found for this district yet.</p>
                        <p class="text-slate-500 text-sm mt-1">Try another address or browse all candidates.</p>
                        <a href="{{ route('politicians.directory') }}" class="inline-block mt-4 text-emerald-400 hover:text-emerald-300 text-sm">
                            Browse all candidates ->
                        </a>
                    </div>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($candidates as $candidate)
                            <a href="{{ route('politician.public.show', $candidate->slug) }}" class="group block bg-slate-900/60 border border-slate-700/50 hover:border-emerald-500/40 rounded-xl p-5 transition">
                                <p class="text-white font-semibold group-hover:text-emerald-300 transition">{{ $candidate->full_name }}</p>
                                <p class="text-slate-400 text-sm mt-1">{{ $candidate->political_office ?: 'Political Candidate' }}</p>

                                <div class="mt-3 space-y-1 text-xs text-slate-400">
                                    @if($candidate->district)
                                        <p>District: {{ $candidate->district }}</p>
                                    @endif
                                    @if($candidate->state || $candidate->city)
                                        <p>{{ implode(', ', array_filter([$candidate->city, $candidate->state])) }}</p>
                                    @endif
                                    <p>Active campaigns: {{ $candidate->active_campaigns_count }}</p>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="mt-10">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-bold text-white">Current Running Candidates (Public Records)</h2>
                    <span class="text-sm text-slate-400">{{ $runningCandidates->count() }} found</span>
                </div>

                @if($runningCandidates->isEmpty())
                    <div class="bg-slate-900/60 border border-slate-700/50 rounded-xl p-6">
                        <p class="text-slate-300">No current election-record candidates were found for this district yet.</p>
                    </div>
                @else
                    @if($topContenders->isNotEmpty())
                        <div class="mb-4">
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-amber-300 mb-2">Top Contenders</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                @foreach($topContenders as $candidate)
                                    <div class="bg-amber-900/20 border border-amber-600/30 rounded-lg p-4">
                                        <p class="text-white font-semibold">{{ $candidate['full_name'] }}</p>
                                        <p class="text-slate-300 text-sm mt-1">{{ $candidate['political_office'] ?: 'Candidate' }}</p>
                                        <p class="text-xs text-slate-400 mt-2">
                                            {{ $candidate['party_affiliation'] ?: 'Party not listed' }}
                                            @if(!empty($candidate['election_date']))
                                                • Election: {{ $candidate['election_date'] }}
                                            @endif
                                        </p>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($runningCandidates as $candidate)
                            <div class="bg-slate-900/60 border border-slate-700/50 rounded-xl p-5">
                                <p class="text-white font-semibold">{{ $candidate['full_name'] }}</p>
                                <p class="text-slate-400 text-sm mt-1">{{ $candidate['political_office'] ?: 'Candidate' }}</p>

                                <div class="mt-3 space-y-1 text-xs text-slate-400">
                                    @if(!empty($candidate['district']))
                                        <p>District: {{ $candidate['district'] }}</p>
                                    @endif
                                    <p>Party: {{ $candidate['party_affiliation'] ?: 'Not listed' }}</p>
                                    @if(!empty($candidate['election_date']))
                                        <p>Election date: {{ $candidate['election_date'] }}</p>
                                    @endif
                                    <p>Source: {{ $candidate['source_label'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif
    </main>
</body>
</html>
