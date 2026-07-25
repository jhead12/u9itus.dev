@php
use App\Enums\EventRsvpStatus;
@endphp

@extends('standalone.layouts.dashboard')

@section('title', 'Manage RSVPs — ' . $event->title)
@section('page-title', 'Manage RSVPs')

@section('content')
<div class="max-w-5xl">
    <div class="mb-6">
        <a href="{{ route($role . '.events.index') }}" class="text-sm text-slate-400 hover:text-white transition">← Back to events</a>
    </div>

    <div class="mb-8">
        <h2 class="text-2xl font-bold text-white mb-2">{{ $event->title }}</h2>
        <p class="text-slate-400 text-sm">
            {{ $event->starts_at->format('l, F j, Y g:i A') }} {{ $event->timezone }}
            <span class="mx-2">·</span>
            <a href="{{ route('events.show', $event->slug) }}" target="_blank" class="text-indigo-300 hover:text-indigo-200">View public page →</a>
        </p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-4">
            <p class="text-2xl font-bold text-white">{{ $attendingCount }}</p>
            <p class="text-sm text-slate-400">Confirmed / Going</p>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-4">
            <p class="text-2xl font-bold text-white">{{ $pendingCount }}</p>
            <p class="text-sm text-slate-400">Pending approval</p>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-4">
            <p class="text-2xl font-bold text-white">{{ $waitlistCount }}</p>
            <p class="text-sm text-slate-400">Waitlist</p>
        </div>
    </div>

    @if($event->rsvps->isEmpty())
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-8 text-center">
            <p class="text-slate-300">No RSVPs yet.</p>
        </div>
    @else
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-900/60 text-slate-400">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium">Guest</th>
                        <th class="px-4 py-3 text-left font-medium">Status</th>
                        <th class="px-4 py-3 text-left font-medium">Guests</th>
                        <th class="px-4 py-3 text-left font-medium">Note</th>
                        <th class="px-4 py-3 text-left font-medium">Date</th>
                        <th class="px-4 py-3 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/50">
                    @foreach($event->rsvps as $rsvp)
                        <tr>
                            <td class="px-4 py-3 text-white">{{ $rsvp->user?->name ?? 'Guest' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex px-2 py-1 rounded text-xs font-medium
                                    @if($rsvp->status->value === 'yes' || $rsvp->status->value === 'approved') bg-emerald-500/10 text-emerald-400 border border-emerald-500/20
                                    @elseif($rsvp->status->value === 'pending') bg-amber-500/10 text-amber-400 border border-amber-500/20
                                    @elseif($rsvp->status->value === 'waitlist') bg-orange-500/10 text-orange-400 border border-orange-500/20
                                    @elseif($rsvp->status->value === 'declined' || $rsvp->status->value === 'no') bg-slate-600/10 text-slate-400 border border-slate-600/20
                                    @else bg-slate-600/10 text-slate-300 border border-slate-600/20
                                    @endif">
                                    {{ $rsvp->status->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-300">{{ $rsvp->guest_count }}</td>
                            <td class="px-4 py-3 text-slate-300 max-w-xs truncate" title="{{ $rsvp->notes }}">{{ $rsvp->notes }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ $rsvp->created_at->format('M j, Y g:i A') }}</td>
                            <td class="px-4 py-3 text-right">
                                @if($rsvp->status === EventRsvpStatus::Pending)
                                    <form action="{{ route($role . '.events.rsvps.approve', [$event, $rsvp]) }}" method="POST" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="text-emerald-400 hover:text-emerald-300 text-sm mr-3">Approve</button>
                                    </form>
                                    <form action="{{ route($role . '.events.rsvps.decline', [$event, $rsvp]) }}" method="POST" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="text-red-400 hover:text-red-300 text-sm">Decline</button>
                                    </form>
                                @else
                                    <span class="text-slate-500 text-sm">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
