@php
    $elections = $data['upcoming_elections'] ?? [];
    $measures = $data['recent_ballot_measures'] ?? collect();
@endphp

<div class="space-y-4">
    <div>
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Upcoming Elections</p>
        @if(empty($elections))
            <p class="text-sm text-slate-500">Nothing scheduled for your state yet.</p>
        @else
            <ul class="space-y-1.5">
                @foreach($elections as $election)
                    <li class="flex items-center justify-between text-sm">
                        <span class="text-slate-300">{{ $election['stage_name'] }}</span>
                        <span class="text-slate-500">{{ $election['election_date_formatted'] ?? 'TBD' }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div>
        <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide mb-2">Recently Added Ballot Measures</p>
        @if($measures->isEmpty())
            <p class="text-sm text-slate-500">None added recently.</p>
        @else
            <ul class="space-y-1.5">
                @foreach($measures as $measure)
                    <li class="text-sm text-slate-300 truncate">
                        {{ $measure->placeLabel() }} · {{ $measure->title }}
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
