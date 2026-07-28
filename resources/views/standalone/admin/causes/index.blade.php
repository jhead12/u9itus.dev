@extends('standalone.layouts.dashboard')

@section('title', 'Causes')
@section('page-title', 'Causes')

@section('content')
<div class="px-6 py-8 max-w-7xl mx-auto">

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
        <div>
            <h1 class="text-3xl font-bold text-white">Causes</h1>
            <p class="text-slate-400 mt-1 max-w-2xl">
                Specific, nameable issues under a Topic (e.g. "Expand Medicaid in Texas") that voters can favorite.
            </p>
        </div>
        <a href="{{ route('admin.causes.create') }}"
           class="px-5 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold transition shadow-md shadow-emerald-900/30 whitespace-nowrap">
            + New Cause
        </a>
    </div>

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

    <form method="GET" action="{{ route('admin.causes.index') }}" class="flex flex-col sm:flex-row gap-3 mb-6">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search by title or state…"
            class="flex-1 bg-slate-800 border border-slate-700 rounded-lg px-4 py-2 text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50">
        <select name="topic_id" class="bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50">
            <option value="">All topics</option>
            @foreach($topics as $topic)
                <option value="{{ $topic->id }}" @selected((string) request('topic_id') === (string) $topic->id)>{{ $topic->name }}</option>
            @endforeach
        </select>
        <button type="submit" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-sm font-medium transition">Filter</button>
        @if(request('q') || request('topic_id'))
            <a href="{{ route('admin.causes.index') }}" class="px-5 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-sm font-medium transition">Clear</a>
        @endif
    </form>

    <div class="bg-slate-800/50 border border-slate-700 rounded-xl overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-700 text-left">
                    <th class="px-5 py-3 text-slate-400 font-medium">Title</th>
                    <th class="px-5 py-3 text-slate-400 font-medium hidden sm:table-cell">Topic</th>
                    <th class="px-5 py-3 text-slate-400 font-medium hidden sm:table-cell">Scope</th>
                    <th class="px-5 py-3 text-slate-400 font-medium text-center">Status</th>
                    <th class="px-5 py-3 text-slate-400 font-medium text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/50">
                @forelse($causes as $cause)
                <tr class="hover:bg-slate-700/20 transition">
                    <td class="px-5 py-3.5">
                        <p class="text-white font-medium">{{ $cause->title }}</p>
                        @if($cause->description)
                            <p class="text-slate-500 text-xs mt-0.5 max-w-md truncate">{{ $cause->description }}</p>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 hidden sm:table-cell text-slate-300">{{ $cause->topic?->name ?? '—' }}</td>
                    <td class="px-5 py-3.5 hidden sm:table-cell text-slate-300">
                        {{ $cause->state ? strtoupper($cause->state) . ($cause->county ? " · {$cause->county}" : '') : 'National' }}
                    </td>
                    <td class="px-5 py-3.5 text-center">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-500/15 text-emerald-300 border border-emerald-500/25">{{ ucfirst($cause->status) }}</span>
                    </td>
                    <td class="px-5 py-3.5 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ route('admin.causes.edit', $cause) }}"
                               class="px-3 py-1.5 rounded-lg bg-slate-700 hover:bg-slate-600 text-white text-xs font-medium transition">Edit</a>
                            <form method="POST" action="{{ route('admin.causes.destroy', $cause) }}"
                                  onsubmit="return confirm('Delete this cause? This cannot be undone.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="px-3 py-1.5 rounded-lg bg-red-900/40 hover:bg-red-900/70 text-red-300 hover:text-white text-xs font-medium transition">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-5 py-12 text-center text-slate-500">No causes found matching your filters.</td></tr>
                @endforelse
            </tbody>
        </table>
      </div>
    </div>

    @if($causes->hasPages())
        <div class="mt-6">{{ $causes->links() }}</div>
    @endif

</div>
@endsection
