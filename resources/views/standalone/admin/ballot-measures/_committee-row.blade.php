{{-- One committee link with its integrity flags and review actions. Expects $link. --}}
@php
    $positionPill = $link->position === 'support'
        ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300'
        : 'border-rose-500/30 bg-rose-500/10 text-rose-300';
    $statusPill = match ($link->status) {
        'verified' => 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300',
        'rejected' => 'border-slate-500/30 bg-slate-500/10 text-slate-400',
        default => 'border-amber-500/30 bg-amber-500/10 text-amber-300',
    };
    $open = $link->openFlags();
    $accepted = array_values(array_diff($link->integrity_flags ?? [], $open));
@endphp
<div class="bg-slate-800/50 border border-slate-700 rounded-xl p-4 space-y-3">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-white font-medium">{{ $link->committee_name }}</p>
            <p class="text-xs text-slate-400 mt-0.5">
                {{ $link->state }} filer ID {{ $link->committee_id }} ·
                <a href="{{ $link->source_url }}" target="_blank" rel="noopener noreferrer" class="text-emerald-400 hover:text-emerald-300">evidence</a>
                @if($link->status === 'verified' && $link->verifiedBy)
                    · verified by {{ $link->verifiedBy->name }} {{ $link->verified_at?->diffForHumans() }}
                @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center rounded-full border {{ $positionPill }} px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide">{{ $link->position === 'support' ? 'Supports' : 'Opposes' }}</span>
            <span class="inline-flex items-center rounded-full border {{ $statusPill }} px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide">{{ $link->status }}</span>
            @if($link->status === 'pending' && $link->priority_score)
                <span class="inline-flex items-center rounded-full border border-orange-500/30 bg-orange-500/10 text-orange-300 px-2.5 py-0.5 text-[10px] font-semibold"
                      title="Risk priority = severity {{ $link->severity }} × occurrence {{ $link->occurrence }} × detectability {{ $link->detectability }}">RPN {{ $link->priority_score }}</span>
            @endif
        </div>
    </div>

    @if($open !== [] || $accepted !== [])
    <ul class="text-xs space-y-1">
        @foreach($open as $flag)
            <li class="{{ in_array($flag, \App\Support\MeasureCommitteeRules::HARD_FLAGS, true) ? 'text-red-400' : 'text-amber-300' }}">⚠ {{ \App\Support\MeasureCommitteeRules::FLAG_LABELS[$flag] ?? $flag }}</li>
        @endforeach
        @foreach($accepted as $flag)
            <li class="text-slate-500">✓ Accepted by reviewer: {{ \App\Support\MeasureCommitteeRules::FLAG_LABELS[$flag] ?? $flag }}</li>
        @endforeach
    </ul>
    @endif

    @if($link->review_note)
        <p class="text-xs text-slate-400 whitespace-pre-line">{{ $link->review_note }}</p>
    @endif

    <div class="flex flex-wrap items-center gap-2">
        @if($link->status !== 'verified')
        <form method="POST" action="{{ route('admin.ballot-measure-committees.verify', $link) }}">
            @csrf
            <button type="submit" class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-medium transition"
                @if($open !== []) onclick="return confirm('This link has warnings. Verify only if the filing confirms this committee takes this side of this measure.')" @endif>
                {{ $open !== [] ? 'Verify anyway' : 'Verify' }}
            </button>
        </form>
        @endif
        @if($link->status !== 'rejected')
        <form method="POST" action="{{ route('admin.ballot-measure-committees.reject', $link) }}" class="flex items-center gap-2">
            @csrf
            <input type="text" name="review_note" placeholder="Reason (optional)" maxlength="1000"
                class="bg-slate-900 border border-slate-600 rounded-lg px-3 py-1.5 text-white placeholder-slate-500 text-xs w-44">
            <button type="submit" class="px-3 py-1.5 rounded-lg bg-slate-700 hover:bg-slate-600 text-white text-xs font-medium transition">Reject</button>
        </form>
        @endif
        <form method="POST" action="{{ route('admin.ballot-measure-committees.destroy', $link) }}" onsubmit="return confirm('Remove this committee link?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="px-3 py-1.5 rounded-lg bg-red-900/40 hover:bg-red-900/70 text-red-300 hover:text-white text-xs font-medium transition">Remove</button>
        </form>
    </div>
</div>
