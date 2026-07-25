@extends('standalone.layouts.dashboard')

@section('title', 'Office Profiles — Civic Education')
@section('page-title', 'Office Profiles')

@section('content')
<div class="px-6 py-8 max-w-7xl mx-auto">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
        <div>
            <h1 class="text-3xl font-bold text-white">Office Profiles</h1>
            <p class="text-slate-400 mt-1 max-w-2xl">
                Manage civic education data shown to voters in the "About This Office" popup while watching campaign videos.
                Filling these out helps communities across the US understand what each candidate does and how it affects their lives.
            </p>
        </div>
    </div>

    {{-- Flash messages --}}
    @if(session('success'))
        <div class="bg-emerald-500/10 border border-emerald-500/30 rounded-lg px-4 py-3 mb-6">
            <p class="text-emerald-400 text-sm">{{ session('success') }}</p>
        </div>
    @endif
    @if(session('error'))
        <div class="bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3 mb-6">
            <p class="text-red-400 text-sm">{{ session('error') }}</p>
        </div>
    @endif

    {{-- Stats bar --}}
    <div class="grid grid-cols-3 gap-4 mb-8">
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-5 text-center">
            <p class="text-3xl font-bold text-white">{{ $stats['total'] }}</p>
            <p class="text-slate-400 text-sm mt-1">Total Politicians</p>
        </div>
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-5 text-center">
            <p class="text-3xl font-bold text-emerald-400">{{ $stats['complete'] }}</p>
            <p class="text-slate-400 text-sm mt-1">Profiles Added</p>
        </div>
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-5 text-center">
            <p class="text-3xl font-bold text-blue-400">{{ $stats['verified'] }}</p>
            <p class="text-slate-400 text-sm mt-1">Verified</p>
        </div>
    </div>

    {{-- Filter / Search --}}
    <form method="GET" action="{{ route('admin.office-profiles.index') }}" class="flex flex-col sm:flex-row gap-3 mb-6">
        <input
            type="text"
            name="q"
            value="{{ request('q') }}"
            placeholder="Search by name, office, or state…"
            class="flex-1 bg-slate-800 border border-slate-700 rounded-lg px-4 py-2 text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50"
        >
        <select name="filter" class="bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50">
            <option value="" @selected(!request('filter'))>All politicians</option>
            <option value="missing" @selected(request('filter') === 'missing')>Missing profile</option>
            <option value="complete" @selected(request('filter') === 'complete')>Profile added</option>
            <option value="verified" @selected(request('filter') === 'verified')>Verified only</option>
        </select>
        <button type="submit" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-sm font-medium transition">
            Filter
        </button>
        @if(request('q') || request('filter'))
            <a href="{{ route('admin.office-profiles.index') }}" class="px-5 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-sm font-medium transition">
                Clear
            </a>
        @endif
    </form>

    {{-- Table --}}
    <div class="bg-slate-800/50 border border-slate-700 rounded-xl overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-700 text-left">
                    <th class="px-5 py-3 text-slate-400 font-medium">Politician</th>
                    <th class="px-5 py-3 text-slate-400 font-medium hidden sm:table-cell">Office / State</th>
                    <th class="px-5 py-3 text-slate-400 font-medium text-center">Profile</th>
                    <th class="px-5 py-3 text-slate-400 font-medium text-center">Verified</th>
                    <th class="px-5 py-3 text-slate-400 font-medium text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/50">
                @forelse($politicians as $politician)
                <tr class="hover:bg-slate-700/20 transition">
                    <td class="px-5 py-3.5">
                        <p class="text-white font-medium">{{ $politician->full_name }}</p>
                        <p class="text-slate-500 text-xs mt-0.5">{{ $politician->party_affiliation ?? '—' }}</p>
                    </td>
                    <td class="px-5 py-3.5 hidden sm:table-cell">
                        <p class="text-slate-300">{{ $politician->political_office ?? '—' }}</p>
                        <p class="text-slate-500 text-xs mt-0.5">{{ strtoupper($politician->state ?? '') ?: '—' }}</p>
                    </td>
                    <td class="px-5 py-3.5 text-center">
                        @if($politician->officeProfile)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-500/15 text-emerald-300 border border-emerald-500/25">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                                Added
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                Missing
                            </span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 text-center">
                        @if($politician->officeProfile?->is_verified)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-blue-500/15 text-blue-300 border border-blue-500/25">
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                Verified
                            </span>
                        @else
                            <span class="text-slate-500 text-xs">—</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ route('admin.office-profiles.edit', $politician) }}"
                               class="px-3 py-1.5 rounded-lg bg-slate-700 hover:bg-slate-600 text-white text-xs font-medium transition">
                                {{ $politician->officeProfile ? 'Edit' : 'Add Profile' }}
                            </a>
                            @if($politician->officeProfile)
                                <form method="POST" action="{{ route('admin.office-profiles.toggle-verified', $politician) }}">
                                    @csrf
                                    <button type="submit"
                                        class="px-3 py-1.5 rounded-lg text-xs font-medium transition
                                            {{ $politician->officeProfile->is_verified
                                                ? 'bg-blue-900/40 hover:bg-blue-900/70 text-blue-300'
                                                : 'bg-slate-700 hover:bg-emerald-700/50 text-slate-300 hover:text-white' }}">
                                        {{ $politician->officeProfile->is_verified ? 'Un-verify' : 'Verify' }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-5 py-12 text-center text-slate-500">
                        No politicians found matching your filters.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
      </div>
    </div>

    {{-- Pagination --}}
    @if($politicians->hasPages())
        <div class="mt-6">
            {{ $politicians->links() }}
        </div>
    @endif

</div>
@endsection
