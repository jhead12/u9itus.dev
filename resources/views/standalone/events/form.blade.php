@php
use App\Enums\CivicEventStatus;
use App\Enums\CivicEventType;
@endphp

{{-- expects $prefix (citizen, politician, or groups), $event (optional),
     $topics iterable, and — only for prefixes whose routes need it, e.g.
     groups.events.* which are scoped under /groups/{group}/events —
     $routeParams (an array merged into every route() call here, e.g.
     ['group' => $group]). Citizen/politician routes need no route
     parameters of their own, so they simply don't pass $routeParams. --}}
@php
$isEdit = isset($event);
$heading = $isEdit ? 'Edit Event' : 'Create Event';
$routeParams = $routeParams ?? [];
$action = $isEdit
    ? route($prefix . '.events.update', [...$routeParams, 'event' => $event])
    : route($prefix . '.events.store', $routeParams);
@endphp

<div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6">
    <h2 class="text-xl font-bold text-white mb-6">{{ $heading }}</h2>

    <form action="{{ $action }}" method="POST" class="space-y-6">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Title <span class="text-red-400">*</span></label>
                <input type="text" name="title" value="{{ old('title', $event?->title) }}" required maxlength="255"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500/50 focus:border-indigo-500">
                @error('title')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Event Type <span class="text-red-400">*</span></label>
                <select name="event_type" required class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm">
                    @foreach(CivicEventType::cases() as $type)
                        <option value="{{ $type->value }}" @selected(old('event_type', $event?->event_type?->value) === $type->value)>{{ $type->label() }}</option>
                    @endforeach
                </select>
                @error('event_type')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Status <span class="text-red-400">*</span></label>
                <select name="status" required class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm">
                    @foreach(CivicEventStatus::cases() as $status)
                        @continue(in_array($status->value, ['cancelled','completed'], true) && !$isEdit)
                        <option value="{{ $status->value }}" @selected(old('status', $event?->status?->value) === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
                @error('status')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-slate-300 mb-1.5">Description <span class="text-red-400">*</span></label>
            <textarea name="description" rows="6" required class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm">{{ old('description', $event?->description) }}</textarea>
            @error('description')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Location / City <span class="text-red-400">*</span></label>
                <input type="text" name="location_name" value="{{ old('location_name', $event?->location_name) }}" required maxlength="255"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm">
                @error('location_name')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Venue Name</label>
                <input type="text" name="venue_name" value="{{ old('venue_name', $event?->venue_name) }}" maxlength="255"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm">
            </div>

            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Street Address</label>
                <input type="text" name="address" value="{{ old('address', $event?->address) }}" maxlength="255"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm">
            </div>

            <div class="grid grid-cols-3 gap-4 md:col-span-2">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">City</label>
                    <input type="text" name="city" value="{{ old('city', $event?->city) }}" maxlength="255" class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">State</label>
                    <input type="text" name="state" value="{{ old('state', $event?->state) }}" maxlength="10" class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-1.5">ZIP</label>
                    <input type="text" name="zip" value="{{ old('zip', $event?->zip) }}" maxlength="20" class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm">
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Latitude <span class="text-red-400">*</span></label>
                <input type="number" step="any" name="latitude" value="{{ old('latitude', $event?->latitude) }}" required
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm">
                @error('latitude')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Longitude <span class="text-red-400">*</span></label>
                <input type="number" step="any" name="longitude" value="{{ old('longitude', $event?->longitude) }}" required
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm">
                @error('longitude')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Time Zone <span class="text-red-400">*</span></label>
                <input type="text" name="timezone" value="{{ old('timezone', $event?->timezone ?? 'America/New_York') }}" required maxlength="50"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm">
                @error('timezone')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Starts At <span class="text-red-400">*</span></label>
                <input type="datetime-local" name="starts_at" required
                    value="{{ old('starts_at', $event?->starts_at?->format('Y-m-d\\TH:i')) }}"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm">
                @error('starts_at')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Ends At <span class="text-red-400">*</span></label>
                <input type="datetime-local" name="ends_at" required
                    value="{{ old('ends_at', $event?->ends_at?->format('Y-m-d\\TH:i')) }}"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm">
                @error('ends_at')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Capacity</label>
                <input type="number" name="capacity" value="{{ old('capacity', $event?->capacity) }}" min="1"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm">
                @error('capacity')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="flex items-center h-full pt-6">
                <label class="inline-flex items-center">
                    <input type="checkbox" name="rsvp_requires_approval" value="1" @checked(old('rsvp_requires_approval', $event?->rsvp_requires_approval))
                        class="rounded border-slate-600 bg-slate-900/60 text-indigo-600 focus:ring-indigo-500">
                    <span class="ml-2 text-sm text-slate-300">RSVPs require host approval</span>
                </label>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="flex items-center">
                <label class="inline-flex items-center">
                    <input type="checkbox" name="is_virtual" value="1" @checked(old('is_virtual', $event?->is_virtual))
                        class="rounded border-slate-600 bg-slate-900/60 text-indigo-600 focus:ring-indigo-500">
                    <span class="ml-2 text-sm text-slate-300">Virtual event</span>
                </label>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-300 mb-1.5">Virtual URL</label>
                <input type="url" name="virtual_url" value="{{ old('virtual_url', $event?->virtual_url) }}" maxlength="500"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm">
                @error('virtual_url')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="md:col-span-2">
            <label class="block text-sm font-medium text-slate-300 mb-1.5">Topics</label>
            <div class="mt-2 grid grid-cols-2 md:grid-cols-3 gap-3">
                @foreach($topics as $topic)
                    <label class="inline-flex items-center">
                        <input type="checkbox" name="topics[]" value="{{ $topic->id }}"
                            @checked(in_array($topic->id, old('topics', $event ? $event->topics->pluck('id')->toArray() : [])))
                            class="rounded border-slate-600 bg-slate-900/60 text-indigo-600 focus:ring-indigo-500">
                        <span class="ml-2 text-sm text-slate-300">{{ $topic->name }}</span>
                    </label>
                @endforeach
            </div>
            @error('topics')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="flex items-center justify-between pt-4">
            <a href="{{ route($prefix . '.events.index', $routeParams) }}" class="text-sm text-slate-400 hover:text-white">Cancel</a>
            <button type="submit" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm font-semibold">{{ $isEdit ? 'Update Event' : 'Create Event' }}</button>
        </div>
    </form>
</div>
