@if(!empty($votingRecord))
@php
    $chamberLabel = $votingRecord['chamber'] === 'senate' ? 'Senate' : 'House';
    $ord = fn (int $n) => $n.(in_array($n % 100, [11, 12, 13], true) ? 'th' : ([1 => 'st', 2 => 'nd', 3 => 'rd'][$n % 10] ?? 'th'));
    $cast = $votingRecord['yea'] + $votingRecord['nay'] + $votingRecord['present'];
@endphp
<section id="voting-record">
    <div class="flex items-end justify-between gap-4 mb-4">
        <div>
            <h2 class="text-xl font-bold text-white flex items-center gap-2">
                <span class="w-1 h-6 rounded-full inline-block" style="background:var(--p13-accent,#f59e0b)"></span>
                Voting Record
            </h2>
            <p class="text-xs text-slate-400 mt-1">
                {{ $chamberLabel }} roll-call votes in the {{ $ord($votingRecord['congress']) }} Congress
                @if($votingRecord['as_of']) · through {{ $votingRecord['as_of']->format('M j, Y') }} @endif
            </p>
        </div>
        <a href="{{ route('politician.public.votes', $politician->slug) }}" class="text-sm font-semibold p13-link whitespace-nowrap">Full record →</a>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
        <div class="bg-slate-800/40 border border-slate-700/40 rounded-xl p-4">
            <p class="text-2xl font-bold text-white">{{ number_format($cast) }}</p>
            <p class="text-xs text-slate-400 mt-1">Votes cast</p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/40 rounded-xl p-4">
            <p class="text-2xl font-bold text-white">{{ number_format($votingRecord['yea']) }} <span class="text-slate-500 text-base">Yea</span> / {{ number_format($votingRecord['nay']) }} <span class="text-slate-500 text-base">Nay</span></p>
            <p class="text-xs text-slate-400 mt-1">How they voted</p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/40 rounded-xl p-4">
            <p class="text-2xl font-bold text-white">{{ rtrim(rtrim(number_format($votingRecord['missed_pct'], 1), '0'), '.') }}%</p>
            <p class="text-xs text-slate-400 mt-1">Votes missed ({{ number_format($votingRecord['not_voting']) }})</p>
        </div>
        <div class="bg-slate-800/40 border border-slate-700/40 rounded-xl p-4">
            <p class="text-2xl font-bold text-white">{{ $votingRecord['party_line_pct'] !== null ? rtrim(rtrim(number_format($votingRecord['party_line_pct'], 1), '0'), '.').'%' : '—' }}</p>
            <p class="text-xs text-slate-400 mt-1">With their party's majority</p>
        </div>
    </div>

    <div class="bg-slate-800/40 border border-slate-700/40 rounded-xl px-5">
        <ul class="divide-y divide-slate-700/40">
            @foreach($votingRecord['recent'] as $vote)
                @include('standalone.public.partials.vote-row', ['vote' => $vote])
            @endforeach
        </ul>
    </div>

    <p class="text-xs text-slate-500 mt-3">
        Source: <a href="{{ $votingRecord['chamber'] === 'senate' ? 'https://www.senate.gov/legislative/votes_new.htm' : 'https://clerk.house.gov/Votes' }}" target="_blank" rel="noopener" class="underline">{{ $chamberLabel === 'Senate' ? 'U.S. Senate' : 'House Clerk' }}</a>
        · <a href="https://bioguide.congress.gov/search/bio/{{ $politician->bioguide_id }}" target="_blank" rel="noopener" class="underline">Congress bioguide</a>.
        “Missed” counts every roll call the member did not vote on, including absences for illness or leave.
    </p>
</section>
@endif
