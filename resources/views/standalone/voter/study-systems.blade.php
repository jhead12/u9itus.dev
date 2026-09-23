@extends('layouts.voter')

@section('title', 'Study Systems')

@section('content')
<div class="max-w-7xl mx-auto py-8 px-4 sm:px-6 lg:px-8">

    {{-- Header --}}
    <div class="mb-6">
        <h1 class="text-2xl sm:text-3xl font-bold text-white flex items-center gap-3">
            <span class="w-10 h-10 rounded-xl bg-emerald-500/20 border border-emerald-500/30 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
            </span>
            Study Systems
        </h1>
        <p class="mt-2 text-sm text-slate-400">A curated set of outside tools and resources — research, government lookup, focus aids, and utilities. These are independent third-party sites; U9itus doesn't run or vouch for their content.</p>
    </div>

    @php
        $studySystemCategories = [
            'Government & Civic' => [
                ['label' => 'Government Directory', 'url' => 'https://graph.civlab.org/us', 'description' => 'Look up elected officials and government structure'],
            ],
            'Research & Reading' => [
                ['label' => 'Unpaywall', 'url' => 'http://unpaywall.org', 'description' => 'Free research papers'],
                ['label' => 'Open Library', 'url' => 'http://openlibrary.org', 'description' => 'Borrow books online'],
                ['label' => 'DOAJ', 'url' => 'http://doaj.org', 'description' => 'Free academic journals'],
                ['label' => 'Internet Archive', 'url' => 'http://archive.org', 'description' => 'Internet archives'],
                ['label' => 'Project Gutenberg', 'url' => 'http://gutenberg.org', 'description' => '70K+ free books'],
                ['label' => 'OpenStax', 'url' => 'http://openstax.org', 'description' => 'Free textbooks'],
                ['label' => 'Open Culture', 'url' => 'http://openculture.com', 'description' => 'Free courses'],
                ['label' => 'Elicit', 'url' => 'http://elicit.org', 'description' => 'Research assistant'],
                ['label' => 'Consensus', 'url' => 'http://consensus.app', 'description' => 'Research-backed answers'],
                ['label' => 'Connected Papers', 'url' => 'http://connectedpapers.com', 'description' => 'Research connections'],
                ['label' => 'Semantic Scholar', 'url' => 'http://semanticscholar.org', 'description' => 'Academic search'],
                ['label' => 'SciSpace', 'url' => 'http://scispace.com', 'description' => 'Understand research papers'],
                ['label' => 'Archive.ph', 'url' => 'http://archive.ph', 'description' => 'Save webpages'],
            ],
            'Media & Design Tools' => [
                ['label' => 'Photopea', 'url' => 'http://photopea.com', 'description' => 'Photoshop alternative'],
                ['label' => 'Squoosh', 'url' => 'http://squoosh.app', 'description' => 'Compress images'],
                ['label' => 'Remove.bg', 'url' => 'http://remove.bg', 'description' => 'Remove backgrounds'],
                ['label' => 'Cleanup.pictures', 'url' => 'http://cleanup.pictures', 'description' => 'Remove objects'],
                ['label' => 'Unscreen', 'url' => 'http://unscreen.com', 'description' => 'Remove video backgrounds'],
                ['label' => 'Carbon', 'url' => 'http://carbon.now.sh', 'description' => 'Beautiful code images'],
                ['label' => 'Ray.so', 'url' => 'http://ray.so', 'description' => 'Code screenshots'],
                ['label' => 'Shots.so', 'url' => 'http://shots.so', 'description' => 'Product mockups'],
                ['label' => 'Smartmockups', 'url' => 'http://smartmockups.com', 'description' => 'Create mockups'],
            ],
            'Streaming & Discovery' => [
                ['label' => 'AlternativeTo', 'url' => 'http://alternativeto.net', 'description' => 'App alternatives'],
                ['label' => 'JustWatch', 'url' => 'http://justwatch.com', 'description' => 'Find where to stream'],
                ['label' => 'SimilarSites', 'url' => 'http://similarsites.com', 'description' => 'Find similar websites'],
                ['label' => 'Radio Garden', 'url' => 'http://radio.garden', 'description' => 'Explore global radio'],
                ['label' => 'Every Noise', 'url' => 'http://everynoise.com', 'description' => 'Discover music genres'],
                ['label' => 'TuneFind', 'url' => 'http://tunefind.com', 'description' => 'Find songs from shows'],
            ],
            'Focus & Ambience' => [
                ['label' => 'Music for Programming', 'url' => 'http://musicforprogramming.net', 'description' => 'Focus music'],
                ['label' => 'myNoise', 'url' => 'http://mynoise.net', 'description' => 'Custom background sounds'],
                ['label' => 'Coffitivity', 'url' => 'http://coffitivity.com', 'description' => 'Café ambience'],
            ],
            'Security & Privacy' => [
                ['label' => 'Have I Been Pwned', 'url' => 'http://haveibeenpwned.com', 'description' => 'Check data breaches'],
                ['label' => 'VirusTotal', 'url' => 'http://virustotal.com', 'description' => 'Scan files & URLs'],
                ['label' => 'Privnote', 'url' => 'http://privnote.com', 'description' => 'Self-destructing notes'],
                ['label' => 'Temp-Mail', 'url' => 'http://temp-mail.org', 'description' => 'Temporary email'],
                ['label' => '10 Minute Mail', 'url' => 'http://10minutemail.com', 'description' => 'Temporary email'],
                ['label' => 'File.io', 'url' => 'http://file.io', 'description' => 'Temporary file sharing'],
            ],
            'Dev & Productivity Tools' => [
                ['label' => 'Wolfram Alpha', 'url' => 'http://wolframalpha.com', 'description' => 'Solve complex problems'],
                ['label' => 'Summarize.tech', 'url' => 'http://summarize.tech', 'description' => 'YouTube summaries'],
                ['label' => 'Phind', 'url' => 'http://phind.com', 'description' => 'AI for developers'],
                ['label' => 'Regex101', 'url' => 'http://regex101.com', 'description' => 'Test regex'],
                ['label' => 'CodeBeautify', 'url' => 'http://codebeautify.org', 'description' => 'Format code'],
                ['label' => 'JSON Formatter', 'url' => 'http://jsonformatter.org', 'description' => 'Format JSON'],
                ['label' => 'Explainshell', 'url' => 'http://explainshell.com', 'description' => 'Understand terminal commands'],
                ['label' => 'Raindrop.io', 'url' => 'http://raindrop.io', 'description' => 'Bookmark manager'],
                ['label' => 'Downdetector', 'url' => 'http://downdetector.com', 'description' => 'Check outages'],
                ['label' => 'TinEye', 'url' => 'http://tineye.com', 'description' => 'Reverse image search'],
                ['label' => 'Fast.com', 'url' => 'http://fast.com', 'description' => 'Internet speed test'],
                ['label' => 'Smallpdf', 'url' => 'http://smallpdf.com', 'description' => 'PDF tools'],
                ['label' => 'iLovePDF', 'url' => 'http://ilovepdf.com', 'description' => 'Merge/split PDFs'],
            ],
        ];
    @endphp

    <div class="space-y-8">
        @foreach($studySystemCategories as $categoryName => $links)
        <section>
            <h2 class="text-sm font-semibold text-slate-400 uppercase tracking-wide mb-3">{{ $categoryName }}</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach($links as $link)
                <a href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer"
                   class="group bg-slate-800/50 border border-slate-700/50 hover:border-emerald-500/40 rounded-xl p-4 transition flex items-start justify-between gap-2">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-white group-hover:text-emerald-300 transition truncate">{{ $link['label'] }}</p>
                        <p class="text-xs text-slate-500 mt-0.5">{{ $link['description'] }}</p>
                    </div>
                    <svg class="w-4 h-4 text-slate-600 group-hover:text-emerald-400 transition shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                    </svg>
                </a>
                @endforeach
            </div>
        </section>
        @endforeach
    </div>

</div>
@endsection
