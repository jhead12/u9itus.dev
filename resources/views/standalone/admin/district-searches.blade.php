@extends('standalone.layouts.dashboard')

@section('title', 'District Searches')
@section('page-title', 'District Search Insights')

@section('content')
@php
    $sourceLabel = static function (?string $source): string {
        return match ($source) {
            'census_geocoder' => 'Census Geocoder',
            'google_civic' => 'Google Civic',
            null, '' => 'unknown',
            default => ucwords(str_replace('_', ' ', $source)),
        };
    };
@endphp
<div class="px-6 py-8 max-w-7xl mx-auto space-y-6">
    <div>
        <h1 class="text-3xl font-bold text-white">District Search Insights</h1>
        <p class="text-slate-400 mt-1">Review lookup quality, fallback usage, and newly discovered officials from public district searches.</p>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Total Searches</p>
            <p class="text-3xl font-bold text-white">{{ number_format($stats['total']) }}</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Resolved</p>
            <p class="text-3xl font-bold text-emerald-400">{{ number_format($stats['resolved']) }}</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Unresolved</p>
            <p class="text-3xl font-bold text-rose-400">{{ number_format($stats['unresolved']) }}</p>
        </div>
        <div class="stat-card">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Officials Discovered</p>
            <p class="text-3xl font-bold text-cyan-400">{{ number_format($stats['officials_discovered']) }}</p>
        </div>
    </div>

    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-4">
        <form method="GET" action="{{ route('admin.district-searches.index') }}" class="grid grid-cols-1 md:grid-cols-5 gap-3">
            <label for="district-search-q" class="sr-only">Search address or district</label>
            <input
                id="district-search-q"
                type="text"
                name="q"
                value="{{ request('q') }}"
                placeholder="Search address or district"
                class="md:col-span-2 bg-slate-900/60 border border-slate-700 text-white placeholder-slate-500 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500"
            />

            <label for="district-search-state" class="sr-only">State</label>
            <input
                id="district-search-state"
                type="text"
                name="state"
                value="{{ request('state') }}"
                maxlength="2"
                placeholder="State (CA)"
                class="bg-slate-900/60 border border-slate-700 text-white placeholder-slate-500 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 uppercase"
            />

            <label for="district-search-resolved" class="sr-only">Filter by resolved status</label>
            <select
                id="district-search-resolved"
                name="resolved"
                class="bg-slate-900/60 border border-slate-700 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500"
            >
                <option value="">All statuses</option>
                <option value="1" @selected(request('resolved') === '1')>Resolved</option>
                <option value="0" @selected(request('resolved') === '0')>Unresolved</option>
            </select>

            <label for="district-search-source" class="sr-only">Filter by source</label>
            <select
                id="district-search-source"
                name="source"
                class="bg-slate-900/60 border border-slate-700 text-white rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500"
            >
                <option value="">All sources</option>
                @foreach($sourceCounts as $source => $count)
                    <option value="{{ $source }}" @selected(request('source') === $source)>
                        {{ $sourceLabel($source) }} ({{ $count }})
                    </option>
                @endforeach
            </select>

            <div class="md:col-span-5 flex gap-2">
                <button type="submit" class="bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2 rounded-lg text-sm font-semibold transition">
                    Apply Filters
                </button>
                <a href="{{ route('admin.district-searches.index') }}" class="bg-slate-700 hover:bg-slate-600 text-white px-4 py-2 rounded-lg text-sm transition">
                    Reset
                </a>
                <a href="{{ route('admin.district-searches.export', request()->query()) }}" class="bg-cyan-700 hover:bg-cyan-600 text-white px-4 py-2 rounded-lg text-sm transition">
                    Export CSV
                </a>
            </div>
        </form>
    </div>

    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-900/60 text-slate-400 text-xs uppercase tracking-wide">
                    <tr>
                        <th class="px-4 py-3 text-left">When</th>
                        <th class="px-4 py-3 text-left">Query</th>
                        <th class="px-4 py-3 text-left">Resolved</th>
                        <th class="px-4 py-3 text-left">Source</th>
                        <th class="px-4 py-3 text-left">Officials</th>
                        <th class="px-4 py-3 text-left">IP</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/40">
                    @forelse($searches as $search)
                        <tr class="hover:bg-slate-900/30">
                            <td class="px-4 py-3 text-slate-400 whitespace-nowrap">
                                {{ optional($search->created_at)->format('Y-m-d H:i') }}
                            </td>
                            <td class="px-4 py-3">
                                <p class="text-slate-200">{{ $search->query_address }}</p>
                                @if($search->matched_address)
                                    <p class="text-xs text-slate-500 mt-1">Matched: {{ $search->matched_address }}</p>
                                @endif
                                @if($search->district_code)
                                    <p class="text-xs text-cyan-400 mt-1">{{ $search->district_code }}</p>
                                @endif
                                @if($search->error_message)
                                    <p class="text-xs text-rose-400 mt-1">{{ $search->error_message }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if($search->resolved)
                                    <span class="px-2 py-1 rounded-full text-xs bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">Resolved</span>
                                @else
                                    <span class="px-2 py-1 rounded-full text-xs bg-rose-500/20 text-rose-400 border border-rose-500/30">Unresolved</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-300 whitespace-nowrap">{{ $sourceLabel($search->source) }}</td>
                            <td class="px-4 py-3 text-slate-300 whitespace-nowrap">{{ number_format($search->discovered_officials_count) }}</td>
                            <td class="px-4 py-3 text-slate-500 whitespace-nowrap">{{ $search->ip_address ?: 'n/a' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-slate-500">No district searches found for the selected filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-4 py-3 border-t border-slate-700/40">
            {{ $searches->links() }}
        </div>
    </div>
</div>
@endsection
