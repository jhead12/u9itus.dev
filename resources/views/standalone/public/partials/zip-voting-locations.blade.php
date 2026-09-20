<section class="mb-8" aria-labelledby="zip-voting-title">
    <h2 id="zip-voting-title" class="text-2xl font-bold text-white mb-3">Where to Vote</h2>
    <p class="text-slate-400 mb-4">Voting locations associated with this ZIP area. These are not confirmed as your assigned polling place. Enter your full street address to look up your voting information.</p>
    @if($zipVotingLocations)
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($zipVotingLocations as $location)
                <article class="bg-slate-900/60 border border-slate-700/50 rounded-xl p-5">
                    <p class="text-xs uppercase tracking-wider text-emerald-400 mb-2">{{ $location['label'] }}</p>
                    <h3 class="text-white font-semibold">{{ $location['name'] }}</h3>
                    <p class="text-slate-300 text-sm mt-1">{{ $location['address'] }}</p>
                    <p class="text-slate-300 text-sm mt-3">{{ $location['election_name'] }} · {{ $location['election_day'] }}</p>
                    @if($location['hours'])
                        <p class="text-slate-400 text-sm mt-2">Hours: {{ $location['hours'] }}</p>
                    @endif
                    @if($location['start_date'] || $location['end_date'])
                        <p class="text-slate-400 text-sm mt-1">Dates: {{ $location['start_date'] ?? 'Not provided' }} – {{ $location['end_date'] ?? $location['election_day'] }}</p>
                    @endif
                    <a class="inline-block text-emerald-400 hover:text-emerald-300 font-semibold text-sm mt-3" href="https://www.google.com/maps/dir/?api=1&amp;destination={{ rawurlencode($location['address']) }}" target="_blank" rel="noopener noreferrer">Get directions →</a>
                    <p class="text-slate-500 text-xs mt-3">{{ $location['source'] }} · Saved {{ $location['checked'] }}</p>
                </article>
            @endforeach
        </div>
    @else
        <p class="bg-slate-900/60 border border-slate-700/50 rounded-xl p-5 text-slate-300">No recent polling-location information is available for this ZIP yet. Locations will appear here when current election details are available.</p>
    @endif
    <a href="https://www.usa.gov/find-polling-place" target="_blank" rel="noopener noreferrer" class="inline-block text-emerald-400 hover:text-emerald-300 text-sm mt-4">Confirm your polling place with your election office →</a>
</section>
