@extends('standalone.layouts.dashboard')

@section('title', 'My Events')
@section('page-title', 'My Events')

@section('content')
<div class="max-w-5xl">
    <div class="flex items-center justify-between mb-6">
        <a href="{{ route('citizen.dashboard') }}" class="text-sm text-slate-400 hover:text-white transition">← Back to dashboard</a>
        <a href="{{ route('citizen.events.create') }}" class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm hover:bg-indigo-700">Create Event</a>
    </div>

    @if($events->isEmpty())
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-8 text-center">
            <p class="text-slate-300">You haven't created any civic events yet.</p>
            <a href="{{ route('citizen.events.create') }}" class="mt-4 inline-block px-5 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Create your first event</a>
        </div>
    @else
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-900/60 text-slate-400">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium">Title</th>
                        <th class="px-4 py-3 text-left font-medium">Type</th>
                        <th class="px-4 py-3 text-left font-medium">Starts</th>
                        <th class="px-4 py-3 text-left font-medium">Status</th>
                        <th class="px-4 py-3 text-left font-medium">RSVPs</th>
                        <th class="px-4 py-3 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/50">
                    @foreach($events as $event)
                        <tr>
                            <td class="px-4 py-3">
                                <a href="{{ route('events.show', $event->slug) }}" target="_blank" class="text-indigo-300 hover:text-indigo-200">{{ $event->title }}</a>
                            </td>
                            <td class="px-4 py-3 text-slate-300">{{ $event->event_type->label() }}</td>
                            <td class="px-4 py-3 text-slate-300">{{ $event->starts_at->format('M j, Y g:i A') }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex px-2 py-1 rounded text-xs font-medium
                                    @if($event->status->value === 'published') bg-emerald-500/10 text-emerald-400 border border-emerald-500/20
                                    @elseif($event->status->value === 'pending_approval') bg-amber-500/10 text-amber-400 border border-amber-500/20
                                    @elseif($event->status->value === 'cancelled') bg-red-500/10 text-red-400 border border-red-500/20
                                    @else bg-slate-600/10 text-slate-300 border border-slate-600/20
                                    @endif">
                                    {{ $event->status->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-300">{{ $event->attendingCount() }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('citizen.events.edit', $event) }}" class="text-indigo-300 hover:text-indigo-200 text-sm mr-3">Edit</a>
                                @if($event->status->value !== 'cancelled' && $event->status->value !== 'completed')
                                    <form action="{{ route('citizen.events.cancel', $event) }}" method="POST" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="text-red-400 hover:text-red-300 text-sm" onclick="return confirm('Cancel this event?')">Cancel</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($events->hasPages())
            <div class="mt-6">{{ $events->links() }}</div>
        @endif
    @endif
</div>
@endsection
