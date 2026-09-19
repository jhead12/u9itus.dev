@php
    $chip = match ($vote->member_vote) {
        'yea' => ['Yea', 'bg-emerald-900/40 border-emerald-700/50 text-emerald-300'],
        'nay' => ['Nay', 'bg-red-900/40 border-red-700/50 text-red-300'],
        'present' => ['Present', 'bg-amber-900/30 border-amber-700/50 text-amber-300'],
        'not_voting' => ['Not voting', 'bg-slate-800 border-slate-600 text-slate-300'],
        default => ['Other', 'bg-slate-800 border-slate-600 text-slate-300'],
    };
    $billUrl = $vote->billUrl();
    $heading = $vote->title ?: $vote->question ?: 'Roll call vote';
@endphp
<li class="flex items-start gap-3 py-3">
    <span class="mt-0.5 inline-flex w-20 justify-center flex-shrink-0 text-[11px] font-semibold border rounded-full px-2 py-1 {{ $chip[1] }}">{{ $chip[0] }}</span>
    <div class="min-w-0 flex-1">
        <p class="text-sm text-white leading-snug">
            @if($billUrl)
                <a href="{{ $billUrl }}" target="_blank" rel="noopener" class="hover:underline">{{ $heading }}</a>
            @else
                {{ $heading }}
            @endif
        </p>
        <p class="text-xs text-slate-400 mt-1">
            {{ $vote->voted_at?->format('M j, Y') ?? 'Date unavailable' }}
            · {{ $vote->chamber === 'senate' ? 'Senate' : 'House' }} roll call {{ $vote->roll_number }}
            @if($vote->bill_number) · {{ $vote->bill_number }} @endif
            @if($vote->question && $vote->title) · {{ $vote->question }} @endif
            @if($vote->result) · {{ $vote->result }} ({{ $vote->yeas }}–{{ $vote->nays }}) @endif
        </p>
    </div>
</li>
