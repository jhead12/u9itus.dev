@php
use App\Enums\EventRsvpStatus;
@endphp

<div class="bg-slate-900/60 border border-slate-800 rounded-xl p-5">
    @if($event->status->value === 'cancelled')
        <div class="text-center">
            <p class="text-lg font-semibold text-red-300">This event has been cancelled.</p>
        </div>
    @elseif($event->starts_at->isPast())
        <div class="text-center">
            <p class="text-lg font-semibold text-slate-300">This event has ended.</p>
        </div>
    @else
        <div class="mb-4">
            <p class="text-sm text-slate-400">
                @if($event->capacity)
                    <span class="text-white font-semibold">{{ $event->attendingCount() }} / {{ $event->capacity }}</span> attending
                    @if($event->isFull())
                        <span class="text-red-400 text-xs block mt-1">Capacity reached. New RSVPs will be added to the waitlist.</span>
                    @endif
                @else
                    <span class="text-white font-semibold">{{ $event->attendingCount() }}</span> attending
                @endif
            </p>
        </div>

        @auth
            @if($rsvp)
                <div class="mb-4 p-3 rounded-lg border
                    @if($rsvp->isAttending()) border-emerald-500/30 bg-emerald-500/10
                    @elseif($rsvp->isWaitlist()) border-amber-500/30 bg-amber-500/10
                    @else border-slate-700 bg-slate-800/50
                    @endif">
                    <p class="text-sm font-semibold
                        @if($rsvp->isAttending()) text-emerald-300
                        @elseif($rsvp->isWaitlist()) text-amber-300
                        @else text-slate-300
                        @endif">
                        Your RSVP: {{ $rsvp->status->label() }}
                    </p>
                    @if($rsvp->notes)
                        <p class="text-xs text-slate-400 mt-1">{{ $rsvp->notes }}</p>
                    @endif
                </div>
            @endif

            <form action="{{ route('events.rsvp', $event->slug) }}" method="POST" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Response</label>
                    <select name="status" required class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white">
                        <option value="{{ EventRsvpStatus::Yes->value }}" @selected($rsvp?->status->value === 'yes')>Yes, I'm going</option>
                        <option value="{{ EventRsvpStatus::Maybe->value }}" @selected($rsvp?->status->value === 'maybe')>Maybe</option>
                        <option value="{{ EventRsvpStatus::No->value }}" @selected($rsvp?->status->value === 'no')>No</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Guests</label>
                    <input type="number" name="guest_count" value="{{ old('guest_count', $rsvp?->guest_count ?? 1) }}" min="1" max="10" required
                        class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">Note to host (optional)</label>
                    <textarea name="notes" rows="2" maxlength="500" class="w-full bg-slate-800 border border-slate-700 rounded-lg px-3 py-2 text-sm text-white">{{ old('notes', $rsvp?->notes) }}</textarea>
                </div>

                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold rounded-lg px-4 py-2.5">{{ $rsvp ? 'Update RSVP' : 'RSVP' }}</button>
            </form>
        @else
            <a href="{{ route('login') }}" class="block w-full text-center bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold rounded-lg px-4 py-2.5">Sign in to RSVP</a>
        @endauth

        <div class="mt-4 pt-4 border-t border-slate-800">
            <a href="{{ route('events.ics', $event->slug) }}" class="text-sm text-emerald-300 hover:text-emerald-200 flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Add to calendar (.ics)
            </a>
        </div>
    @endif
</div>
