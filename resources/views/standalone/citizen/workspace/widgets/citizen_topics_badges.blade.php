@php
    $badges = $data['badges'] ?? collect();
    $availableTopics = $data['available_topics'] ?? collect();
@endphp

<div class="space-y-3">
    @if($badges->isEmpty())
        <p class="text-sm text-slate-500">No interests picked yet — add a few below so your local news feed can match them.</p>
    @else
        <div class="flex flex-wrap gap-1.5">
            @foreach($badges as $badge)
                <form method="POST" action="{{ route('citizen.badges.destroy', $badge->topic_id) }}" class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" title="Remove interest"
                        class="inline-flex items-center gap-1 text-xs font-medium px-2.5 py-1 rounded-full bg-amber-500/10 text-amber-400 border border-amber-500/30 hover:bg-amber-500/20 transition">
                        {{ $badge->topic->icon ?? '🏷️' }} {{ $badge->topic->name }}
                        <span class="text-amber-500/60">✕</span>
                    </button>
                </form>
            @endforeach
        </div>
    @endif

    @if($availableTopics->isNotEmpty())
        <form method="POST" action="" data-base-url="{{ url('/citizen/badges') }}" class="flex items-center gap-2 pt-2 border-t border-slate-700/40"
              onsubmit="this.action = this.dataset.baseUrl + '/' + encodeURIComponent(this.topic_id.value); return this.topic_id.value !== '';">
            @csrf
            <select name="topic_id" id="citizen-add-interest-topic" aria-label="Add an interest" class="flex-1 rounded-lg bg-slate-800 border-slate-600 text-xs text-slate-200">
                <option value="">Add an interest…</option>
                @foreach($availableTopics as $topic)
                    <option value="{{ $topic->id }}">{{ $topic->icon }} {{ $topic->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="text-xs font-medium px-2.5 py-1.5 rounded-lg bg-slate-700/60 text-slate-300 hover:bg-slate-700 transition">
                Add
            </button>
        </form>
    @endif
</div>
