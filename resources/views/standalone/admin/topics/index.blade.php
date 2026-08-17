@extends('standalone.layouts.dashboard')

@section('title', 'Topics — Issue Taxonomy')
@section('page-title', 'Topics')

@section('content')
<div class="px-6 py-8 max-w-7xl mx-auto">

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
        <div>
            <h1 class="text-3xl font-bold text-white">Topics</h1>
            <p class="text-slate-400 mt-1 max-w-2xl">
                The issue-area taxonomy (Healthcare, Climate Action, etc.) used to tag campaigns, causes, and voter badges.
            </p>
        </div>
        <a href="{{ route('admin.topics.create') }}"
           class="px-5 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold transition shadow-md shadow-emerald-900/30 whitespace-nowrap">
            + New Topic
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

    <form method="GET" action="{{ route('admin.topics.index') }}" class="flex flex-col sm:flex-row gap-3 mb-6">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search by name or slug…"
            class="flex-1 bg-slate-800 border border-slate-700 rounded-lg px-4 py-2 text-white placeholder-slate-500 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50">
        <select name="filter" class="bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50">
            <option value="" @selected(!request('filter'))>All topics</option>
            <option value="active" @selected(request('filter') === 'active')>Active only</option>
            <option value="inactive" @selected(request('filter') === 'inactive')>Inactive only</option>
        </select>
        <button type="submit" class="px-5 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded-lg text-sm font-medium transition">Filter</button>
        @if(request('q') || request('filter'))
            <a href="{{ route('admin.topics.index') }}" class="px-5 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-lg text-sm font-medium transition">Clear</a>
        @endif
    </form>

    <div class="bg-slate-800/50 border border-slate-700 rounded-xl overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-700 text-left">
                    <th class="px-5 py-3 text-slate-400 font-medium">Name</th>
                    <th class="px-5 py-3 text-slate-400 font-medium hidden sm:table-cell">Slug</th>
                    <th class="px-5 py-3 text-slate-400 font-medium text-center">Sort</th>
                    <th class="px-5 py-3 text-slate-400 font-medium text-center">Status</th>
                    <th class="px-5 py-3 text-slate-400 font-medium text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-700/50">
                @forelse($topics as $topic)
                <tr class="hover:bg-slate-700/20 transition">
                    <td class="px-5 py-3.5">
                        <p class="text-white font-medium">{{ $topic->name }}</p>
                        @if($topic->description)
                            <p class="text-slate-500 text-xs mt-0.5 max-w-md truncate">{{ $topic->description }}</p>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 hidden sm:table-cell text-slate-300 font-mono text-xs">{{ $topic->slug }}</td>
                    <td class="px-5 py-3.5 text-center text-slate-300">{{ $topic->sort_order }}</td>
                    <td class="px-5 py-3.5 text-center">
                        @if($topic->is_active)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-500/15 text-emerald-300 border border-emerald-500/25">Active</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-slate-700 text-slate-400 border border-slate-600">Inactive</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ route('admin.topics.edit', $topic) }}"
                               class="px-3 py-1.5 rounded-lg bg-slate-700 hover:bg-slate-600 text-white text-xs font-medium transition">Edit</a>
                            <form method="POST" action="{{ route('admin.topics.destroy', $topic) }}"
                                  onsubmit="return confirm('Delete this topic? This cannot be undone.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="px-3 py-1.5 rounded-lg bg-red-900/40 hover:bg-red-900/70 text-red-300 hover:text-white text-xs font-medium transition">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-5 py-12 text-center text-slate-500">No topics found matching your filters.</td></tr>
                @endforelse
            </tbody>
        </table>
      </div>
    </div>

    @if($topics->hasPages())
        <div class="mt-6">{{ $topics->links() }}</div>
    @endif

</div>
@endsection
