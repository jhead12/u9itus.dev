@if($committees->isNotEmpty() || ($legislation && $legislation->policy_areas))
@php
    $ord = fn (int $n) => $n.(in_array($n % 100, [11, 12, 13], true) ? 'th' : ([1 => 'st', 2 => 'nd', 3 => 'rd'][$n % 10] ?? 'th'));
    $subcommitteeCount = $committees->sum(fn ($c) => $c['subcommittees']->count());
    // Weighted like the issue-badge score: filing a bill says more than signing on to one.
    $cosponsorWeight = (float) config('u9itus.issues.legislation_cosponsor_weight', 0.2);
    $areaWeight = fn (array $c) => $c['sponsored'] + $cosponsorWeight * $c['cosponsored'];
    $policyAreas = collect($legislation?->policy_areas ?? [])->sortByDesc($areaWeight)->take(6);
    $maxWeight = max(0.1, (float) ($policyAreas->map($areaWeight)->max() ?? 0));
    $seatLabel = fn ($seat) => $seat->title ?: ($seat->side ? ucfirst($seat->side) : null);
@endphp
<section id="profile-committees" class="scroll-mt-64">
    <h2 class="text-xl font-bold text-white flex items-center gap-2">
        <span class="w-1 h-6 rounded-full inline-block" style="background:var(--p13-accent,#f59e0b)"></span>
        Committees &amp; Legislation
    </h2>

    @if($committees->isNotEmpty())
        <p class="text-xs text-slate-400 mt-1 mb-4">
            {{ $committees->count() }} {{ Str::plural('committee', $committees->count()) }}@if($subcommitteeCount) · {{ $subcommitteeCount }} {{ Str::plural('subcommittee', $subcommitteeCount) }}@endif
        </p>

        <ul class="grid gap-3 md:grid-cols-2">
            @foreach($committees as $committee)
                @php($seat = $committee['seat'])
                <li class="bg-slate-800/40 border border-slate-700/40 rounded-xl p-4">
                    <div class="flex items-start justify-between gap-3">
                        @if($seat->url)
                            <a href="{{ $seat->url }}" target="_blank" rel="noopener" class="font-semibold text-white hover:underline">{{ $seat->name }}</a>
                        @else
                            <p class="font-semibold text-white">{{ $seat->name }}</p>
                        @endif
                        @if($label = $seatLabel($seat))
                            <span class="flex-shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $seat->title ? 'bg-amber-500/15 text-amber-300' : 'bg-slate-700/60 text-slate-300' }}">{{ $label }}</span>
                        @endif
                    </div>
                    @if($committee['subcommittees']->isNotEmpty())
                        <ul class="mt-3 flex flex-wrap gap-1.5" aria-label="Subcommittees">
                            @foreach($committee['subcommittees'] as $sub)
                                <li class="rounded-md bg-slate-900/60 border border-slate-700/40 px-2 py-1 text-xs text-slate-300">
                                    {{ $sub->name }}@if($sub->title)<span class="text-amber-300"> · {{ $sub->title }}</span>@endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    @if($policyAreas->isNotEmpty())
        <div class="bg-slate-800/40 border border-slate-700/40 rounded-xl p-5 mt-4">
            <h3 class="text-sm font-semibold text-white">Legislative focus</h3>
            <p class="text-xs text-slate-400 mt-1 mb-4">
                {{ number_format($legislation->sponsored_total) }} bills sponsored and {{ number_format($legislation->cosponsored_total) }} cosponsored since the {{ $ord($legislation->since_congress) }} Congress, by policy area
            </p>
            <ul class="space-y-2.5">
                @foreach($policyAreas as $area => $counts)
                    <li>
                        <div class="flex items-baseline justify-between gap-3 text-sm">
                            <span class="text-slate-200">{{ $area }}</span>
                            <span class="text-xs text-slate-400 tabular-nums whitespace-nowrap">{{ $counts['sponsored'] }} sponsored · {{ $counts['cosponsored'] }} cosponsored</span>
                        </div>
                        <div class="mt-1 h-1.5 rounded-full bg-slate-700/50 overflow-hidden" aria-hidden="true">
                            <div class="h-full rounded-full" style="width:{{ max(2, round(100 * $areaWeight($counts) / $maxWeight)) }}%;background:var(--p13-accent,#f59e0b)"></div>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <p class="text-xs text-slate-500 mt-3">
        Source:
        @if($committees->isNotEmpty())<a href="https://github.com/unitedstates/congress-legislators" target="_blank" rel="noopener" class="underline">congress-legislators</a> (current committee rosters)@endif
        @if($committees->isNotEmpty() && $policyAreas->isNotEmpty()) · @endif
        @if($policyAreas->isNotEmpty())<a href="https://api.congress.gov/" target="_blank" rel="noopener" class="underline">Congress.gov API</a> (bills and joint resolutions; policy areas assigned by the Library of Congress)@endif.
    </p>
</section>
@endif
