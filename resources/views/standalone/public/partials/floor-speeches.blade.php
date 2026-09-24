@if($floorSpeeches->isNotEmpty())
<section id="profile-speeches" class="scroll-mt-64">
    <div class="flex items-end justify-between gap-4 mb-4">
        <div>
            <h2 class="text-xl font-bold text-white flex items-center gap-2">
                <span class="w-1 h-6 rounded-full inline-block" style="background:var(--p13-accent,#f59e0b)"></span>
                On the Floor
            </h2>
            <p class="text-xs text-slate-400 mt-1">What {{ $politician->full_name }} has said in Congress, from the Congressional Record</p>
        </div>
        <a href="{{ route('politician.public.speeches', $politician->slug) }}" class="text-sm font-semibold whitespace-nowrap" style="color:var(--p13-accent,#f59e0b)">Search all {{ number_format($floorSpeechTotal) }} →</a>
    </div>

    <div class="grid gap-3 md:grid-cols-2">
        @foreach($floorSpeeches as $speech)
            @include('standalone.public.partials.floor-speech-card', ['speech' => $speech])
        @endforeach
    </div>

    <p class="text-xs text-slate-500 mt-3">
        Source: <a href="https://www.govinfo.gov/app/collection/crec" target="_blank" rel="noopener" class="underline">Congressional Record</a> (GovInfo). Issue, position and quote are drawn from the speech automatically; the quote is always the speaker's exact words. C-SPAN links open that day's floor session.
    </p>
</section>
@endif
