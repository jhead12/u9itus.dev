@extends('layouts.voter')

@section('title', 'Watch: ' . $campaign->title)

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 py-8">

    {{-- Breadcrumb --}}
    <div class="mb-5">
        <a href="{{ route('voter.dashboard') }}" class="inline-flex items-center gap-1.5 text-slate-400 hover:text-emerald-400 text-sm transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Back to Dashboard
        </a>
    </div>

    {{-- Campaign Header --}}
    <div class="mb-5 p-5 bg-slate-800/60 border border-slate-700/60 rounded-2xl">
        <h1 class="text-xl font-bold text-white leading-snug">{{ $campaign->title }}</h1>
        <p class="text-slate-400 mt-1.5 text-sm">
            <span class="text-slate-500">Sponsored by</span>
            <span class="text-emerald-400 font-medium">{{ $campaign->politician->full_name ?? 'Unknown' }}</span>
            @if($campaign->politician->political_office ?? false)
                <span class="text-slate-600">&middot;</span>
                <span class="text-slate-400">{{ $campaign->politician->political_office }}</span>
            @endif
        </p>
    </div>

    {{-- Earn Banner --}}
    <div class="bg-emerald-900/30 border border-emerald-500/25 rounded-2xl px-5 py-3.5 mb-5 flex items-center gap-3">
        <div class="w-8 h-8 rounded-full bg-emerald-500/20 flex items-center justify-center shrink-0">
            <svg class="w-4 h-4 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <div>
            <p class="text-emerald-300 text-sm font-medium">
                Earn <strong class="text-emerald-400 text-base">${{ number_format($payout, 2) }}</strong>
                for watching at least <strong class="text-white">{{ $mustWatch }}%</strong> of this message
            </p>
            <p class="text-slate-500 text-xs mt-0.5">Do not skip — rewards require continuous viewing</p>
        </div>
    </div>

    {{-- Video Player --}}
    @php
        $videoId  = null;
        $mediaUrl = $campaign->media_url ?? '';
        if (preg_match('/youtu\.be\/([a-zA-Z0-9_-]+)/', $mediaUrl, $_m))         { $videoId = $_m[1]; }
        elseif (preg_match('/[?&]v=([a-zA-Z0-9_-]+)/', $mediaUrl, $_m))         { $videoId = $_m[1]; }
        elseif (preg_match('/\/embed\/([a-zA-Z0-9_-]+)/', $mediaUrl, $_m))     { $videoId = $_m[1]; }
        $isYouTube = !empty($videoId);
    @endphp
    <div class="relative bg-black rounded-2xl overflow-hidden shadow-2xl ring-1 ring-slate-700/50" id="player-wrapper">
        @if($isYouTube)
            <div id="yt-player-container" class="w-full aspect-video"></div>
        @else
            <video
                id="ad-video"
                class="w-full aspect-video"
                controlsList="nodownload nofullscreen noplaybackrate"
                disablePictureInPicture
                disableRemotePlayback
                playsinline
                preload="metadata"
                oncontextmenu="return false;"
            >
                @if($campaign->media_url)
                    <source src="{{ $campaign->media_url }}" type="video/mp4">
                @endif
                Your browser does not support HTML5 video.
            </video>
        @endif

        {{-- Fraud Prevention: Transparent blocker prevents seeking/interaction with video controls --}}
        <div id="control-blocker" class="hidden absolute inset-0 z-10" style="pointer-events: auto; cursor: not-allowed;"></div>

        {{-- Overlay before play --}}
        <div id="play-overlay" class="absolute inset-0 flex flex-col items-center justify-center bg-black/60 cursor-pointer">
            <div class="w-20 h-20 rounded-full bg-emerald-500/20 border-2 border-emerald-400 flex items-center justify-center mb-4">
                <svg class="w-10 h-10 text-emerald-400 ml-1" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M8 5v14l11-7z"/>
                </svg>
            </div>
            <p class="text-white font-semibold">Click to Play &amp; Earn</p>
            <p class="text-slate-400 text-sm mt-1">{{ $duration }}s video &middot; must watch {{ $mustWatch }}%</p>
        </div>

        {{-- Progress bar --}}
        <div id="progress-track" class="absolute bottom-0 left-0 right-0 h-1 bg-slate-700">
            <div id="progress-bar" class="h-full bg-emerald-500 transition-all duration-500" style="width:0%"></div>
        </div>
    </div>

    {{-- Status messages --}}
    <div id="status-msg" class="mt-5 hidden text-center py-4 px-6 rounded-2xl"></div>

    {{-- ── About the Candidate ──────────────────────────────────── --}}
    @php $pol = $campaign->politician; @endphp
    @if($pol)
    <div x-data="{ bioOpen: false }" class="mt-5 bg-slate-800/60 border border-slate-700/60 rounded-2xl overflow-hidden">

        {{-- Header row: avatar + key facts --}}
        <div class="flex items-start gap-4 p-5">
            {{-- Avatar --}}
            @if($pol->profile_photo_url)
                <img src="{{ $pol->profile_photo_url }}"
                     alt="{{ $pol->full_name }}"
                     class="w-14 h-14 rounded-full ring-2 ring-slate-600 object-cover shrink-0">
            @else
                <div class="w-14 h-14 rounded-full bg-slate-700 border border-slate-600 flex items-center justify-center shrink-0">
                    <span class="text-lg font-bold text-slate-300 select-none">
                        {{ strtoupper(mb_substr($pol->full_name ?? 'P', 0, 2)) }}
                    </span>
                </div>
            @endif

            {{-- Name / office / location --}}
            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-base font-bold text-white leading-tight truncate">{{ $pol->full_name }}</h2>

                    @if($pol->verified_official)
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-900/50 text-emerald-300 border border-emerald-500/30">
                            <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M6.267 3.455a3.066 3.066 0 001.745-.723 3.066 3.066 0 013.976 0 3.066 3.066 0 001.745.723 3.066 3.066 0 012.812 2.812c.051.643.304 1.254.723 1.745a3.066 3.066 0 010 3.976 3.066 3.066 0 00-.723 1.745 3.066 3.066 0 01-2.812 2.812 3.066 3.066 0 00-1.745.723 3.066 3.066 0 01-3.976 0 3.066 3.066 0 00-1.745-.723 3.066 3.066 0 01-2.812-2.812 3.066 3.066 0 00-.723-1.745 3.066 3.066 0 010-3.976 3.066 3.066 0 00.723-1.745 3.066 3.066 0 012.812-2.812zm7.44 5.252a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            Verified Official
                        </span>
                    @endif

                    @if($pol->party_affiliation)
                        @php
                            $partyColors = [
                                'Democrat'     => 'bg-blue-900/50 text-blue-300 border-blue-500/30',
                                'Democratic'   => 'bg-blue-900/50 text-blue-300 border-blue-500/30',
                                'Republican'   => 'bg-red-900/50 text-red-300 border-red-500/30',
                                'Independent'  => 'bg-purple-900/50 text-purple-300 border-purple-500/30',
                                'Libertarian'  => 'bg-yellow-900/50 text-yellow-300 border-yellow-500/30',
                                'Green'        => 'bg-green-900/50 text-green-300 border-green-500/30',
                            ];
                            $partyColor = $partyColors[$pol->party_affiliation]
                                       ?? 'bg-slate-700/60 text-slate-300 border-slate-600/40';
                        @endphp
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium border {{ $partyColor }}">
                            {{ $pol->party_affiliation }}
                        </span>
                    @endif
                </div>

                @if($pol->political_office)
                    <p class="text-slate-300 text-sm mt-0.5">{{ $pol->political_office }}</p>
                @endif

                @php
                    $location = collect([$pol->district, $pol->city, $pol->state])->filter()->implode(', ');
                @endphp
                @if($location)
                    <p class="text-slate-500 text-xs mt-0.5 flex items-center gap-1">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        {{ $location }}
                    </p>
                @endif
            </div>
        </div>

        {{-- Bio --}}
        @if($pol->bio)
        <div class="px-5 pb-4 border-t border-slate-700/40 pt-4">
            <p class="text-slate-400 text-sm leading-relaxed" :class="bioOpen ? '' : 'line-clamp-3'">
                {{ $pol->bio }}
            </p>
            @if(mb_strlen($pol->bio) > 180)
                <button @click="bioOpen = !bioOpen"
                    class="mt-2 text-xs text-emerald-400 hover:text-emerald-300 font-medium transition">
                    <span x-text="bioOpen ? 'Show less ↑' : 'Read more ↓'">Read more ↓</span>
                </button>
            @endif
        </div>
        @endif

        {{-- Research & Transparency Links --}}
        <div class="border-t border-slate-700/40 px-5 py-4">
            <p class="text-xs font-semibold text-slate-500 uppercase tracking-widest mb-3">Research This Candidate</p>
            <div class="flex flex-wrap gap-2">

                {{-- U9itus public profile --}}
                @if($pol->slug && $pol->page_published)
                <a href="{{ route('politician.public.show', $pol->slug) }}"
                   target="_blank" rel="noopener"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-900/30 hover:bg-emerald-900/50 border border-emerald-500/30 text-emerald-300 text-xs font-medium transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    Full Profile
                </a>
                @endif

                {{-- Wikipedia --}}
                <a href="https://en.wikipedia.org/wiki/Special:Search?search={{ urlencode($pol->full_name) }}"
                   target="_blank" rel="noopener noreferrer"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700/60 hover:bg-slate-700 border border-slate-600/60 text-slate-300 hover:text-white text-xs font-medium transition">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12.09 13.119c-.936 1.932-2.217 4.548-2.853 5.728-.616 1.074-1.127.931-1.532.029-1.406-3.321-4.293-9.144-5.651-12.409-.251-.601-.441-.987-.619-1.139-.181-.15-.554-.24-1.122-.271C.103 5.033 0 4.982 0 4.898v-.455l.052-.045c.924-.005 5.401 0 5.401 0l.051.045v.434c0 .084-.103.129-.209.141-.585.03-.993.115-.993.395 0 .175.336.895.465 1.201 1.252 3.073 3.444 8.075 4.273 9.972.219.468.308.437.558.02.421-.707 1.189-2.385 1.189-2.385l-1.676-3.898c-.48-.991-.791-1.595-.912-1.805a1.54 1.54 0 00-.457-.503c-.19-.12-.516-.221-.979-.306-.083-.017-.126-.045-.126-.13v-.518l.051-.045c1.568-.005 3.752 0 3.752 0l.05.045v.506c0 .07-.057.117-.169.143-.363.056-.615.138-.715.213-.094.076-.139.171-.139.279 0 .146.129.635.387 1.196l.896 1.918c.316-.609.562-1.077.72-1.403a5.545 5.545 0 00.409-1.045c0-.109-.049-.199-.145-.273-.098-.075-.359-.155-.785-.232-.12-.019-.179-.069-.179-.134v-.496l.05-.045c1.405-.005 2.989 0 2.989 0l.052.045v.48c0 .077-.066.123-.196.143-.482.054-.832.262-1.049.508-.164.19-.432.537-.804 1.217-.107.193-.398.745-.876 1.656l1.931 4.268c.151.291.261.437.329.437.065 0 .195-.154.39-.506l3.079-5.973c.181-.35.272-.606.272-.806a.635.635 0 00-.227-.497c-.151-.121-.43-.206-.833-.25-.082-.01-.123-.054-.123-.136v-.496l.052-.044c1.277-.005 2.604 0 2.604 0l.05.044v.488c0 .074-.057.118-.175.134-.485.055-.863.292-1.135.714-.108.17-.428.727-1.249 2.167l-3.593 6.479z"/>
                    </svg>
                    Wikipedia
                </a>

                {{-- Ballotpedia --}}
                @if($pol->ballotpedia_id && ($pol->show_ballotpedia_data ?? true))
                <a href="https://ballotpedia.org/{{ rawurlencode($pol->ballotpedia_id) }}"
                   target="_blank" rel="noopener noreferrer"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700/60 hover:bg-slate-700 border border-slate-600/60 text-slate-300 hover:text-white text-xs font-medium transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Ballotpedia
                </a>
                @endif

                {{-- VoteSmart: voting record --}}
                @if($pol->votesmart_id && ($pol->show_votesmart_data ?? true))
                <a href="https://votesmart.org/candidate/{{ (int) $pol->votesmart_id }}/key-votes"
                   target="_blank" rel="noopener noreferrer"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700/60 hover:bg-slate-700 border border-slate-600/60 text-slate-300 hover:text-white text-xs font-medium transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                    </svg>
                    Voting Record
                </a>
                @endif

                {{-- OpenSecrets: campaign finance --}}
                @if($pol->opensecrets_id && ($pol->show_opensecrets_data ?? true))
                <a href="https://www.opensecrets.org/politicians/summary?cid={{ urlencode($pol->opensecrets_id) }}"
                   target="_blank" rel="noopener noreferrer"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700/60 hover:bg-slate-700 border border-slate-600/60 text-slate-300 hover:text-white text-xs font-medium transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    Campaign Finance
                </a>
                @endif

                {{-- FEC --}}
                @if($pol->fec_candidate_id && ($pol->show_fec_data ?? true))
                <a href="https://www.fec.gov/data/candidate/{{ urlencode($pol->fec_candidate_id) }}/"
                   target="_blank" rel="noopener noreferrer"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700/60 hover:bg-slate-700 border border-slate-600/60 text-slate-300 hover:text-white text-xs font-medium transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/>
                    </svg>
                    FEC Records
                </a>
                @endif

                {{-- Official website --}}
                @if($pol->website_url)
                <a href="{{ $pol->website_url }}"
                   target="_blank" rel="noopener noreferrer"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-700/60 hover:bg-slate-700 border border-slate-600/60 text-slate-300 hover:text-white text-xs font-medium transition">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9"/>
                    </svg>
                    Official Website
                </a>
                @endif

            </div>
        </div>
    </div>
    @endif
    {{-- ── /About the Candidate ─────────────────────────────────── --}}

    {{-- Report Actions --}}
    <div x-data="{ reportModal: false, messageModal: false, submitting: false }" class="mt-5">
        {{-- Action Buttons --}}
        <div class="flex items-center justify-center gap-3">
            <button @click="reportModal = true" type="button"
                class="inline-flex items-center gap-2 px-4 py-2 bg-slate-800/60 hover:bg-slate-700/60 border border-slate-700/60 rounded-lg text-slate-300 hover:text-white text-sm transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                Report Issue
            </button>

            <button @click="messageModal = true" type="button"
                class="inline-flex items-center gap-2 px-4 py-2 bg-slate-800/60 hover:bg-slate-700/60 border border-slate-700/60 rounded-lg text-slate-300 hover:text-white text-sm transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/>
                </svg>
                Message Politician
            </button>
        </div>

        {{-- Report Issue Modal --}}
        <div x-show="reportModal"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-50 flex items-center justify-center px-4 bg-black/60 backdrop-blur-sm"
            @click.self="reportModal = false"
            style="display: none;">
            
            <div class="bg-slate-800 border border-slate-700 rounded-2xl shadow-2xl max-w-md w-full p-6"
                @click.stop>
                
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-bold text-white">Report Issue</h3>
                        <p class="text-sm text-slate-400 mt-0.5">Help us improve quality</p>
                    </div>
                    <button @click="reportModal = false" class="text-slate-500 hover:text-slate-300 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="
                    if (!submitting) {
                        submitting = true;
                        const formData = new FormData($event.target);
                        fetch('{{ route('voter.watch.report-issue', $adToken->token) }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                'Accept': 'application/json'
                            },
                            body: formData
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                alert(data.message || 'Report submitted successfully!');
                                reportModal = false;
                                $event.target.reset();
                            }
                        })
                        .catch(() => alert('Failed to submit report. Please try again.'))
                        .finally(() => submitting = false);
                    }
                ">
                    <input type="hidden" name="view_session_uuid" :value="window.sessionId || ''">

                    <div class="mb-4">
                        <label for="issue-category" class="block text-sm font-medium text-slate-300 mb-2">Issue Category *</label>
                        <select name="issue_category" id="issue-category" required
                            class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition">
                            <option value="">Select a category...</option>
                            <option value="video_not_playing">Video Not Playing</option>
                            <option value="incorrect_info">Incorrect Information</option>
                            <option value="offensive_content">Offensive Content</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="mb-5">
                        <label for="issue-body" class="block text-sm font-medium text-slate-300 mb-2">Description (optional)</label>
                        <textarea name="body" id="issue-body" rows="3" maxlength="1000"
                            placeholder="Provide additional details about the issue..."
                            class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition resize-none"></textarea>
                    </div>

                    <div class="flex gap-3">
                        <button type="button" @click="reportModal = false"
                            class="flex-1 px-4 py-2 bg-slate-700/60 hover:bg-slate-600/60 border border-slate-600 rounded-lg text-slate-300 hover:text-white text-sm transition">
                            Cancel
                        </button>
                        <button type="submit" :disabled="submitting"
                            class="flex-1 px-4 py-2 bg-amber-600 hover:bg-amber-500 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg text-white font-medium text-sm transition">
                            <span x-show="!submitting">Submit Report</span>
                            <span x-show="submitting">Submitting...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Message Politician Modal --}}
        <div x-show="messageModal"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-50 flex items-center justify-center px-4 bg-black/60 backdrop-blur-sm"
            @click.self="messageModal = false"
            style="display: none;">
            
            <div class="bg-slate-800 border border-slate-700 rounded-2xl shadow-2xl max-w-md w-full p-6"
                @click.stop>
                
                <div class="flex items-start justify-between mb-4">
                    <div>
                        <h3 class="text-lg font-bold text-white">Message Politician</h3>
                        <p class="text-sm text-slate-400 mt-0.5">Send to {{ $campaign->politician->full_name ?? 'the campaign' }}</p>
                    </div>
                    <button @click="messageModal = false" class="text-slate-500 hover:text-slate-300 transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="
                    if (!submitting) {
                        submitting = true;
                        const formData = new FormData($event.target);
                        fetch('{{ route('voter.watch.message-politician', $adToken->token) }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                'Accept': 'application/json'
                            },
                            body: formData
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                alert(data.message || 'Message sent successfully!');
                                messageModal = false;
                                $event.target.reset();
                            }
                        })
                        .catch(() => alert('Failed to send message. Please try again.'))
                        .finally(() => submitting = false);
                    }
                ">
                    <input type="hidden" name="view_session_uuid" :value="window.sessionId || ''">

                    <div class="mb-5">
                        <label for="message-body" class="block text-sm font-medium text-slate-300 mb-2">Your Message *</label>
                        <textarea name="body" id="message-body" rows="5" maxlength="1000" required
                            placeholder="Share your thoughts, questions, or feedback with this politician..."
                            class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-3 py-2 text-white text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500/50 focus:border-emerald-500 transition resize-none"></textarea>
                        <p class="text-xs text-slate-500 mt-1">Your message will be sent directly to the politician's email</p>
                    </div>

                    <div class="flex gap-3">
                        <button type="button" @click="messageModal = false"
                            class="flex-1 px-4 py-2 bg-slate-700/60 hover:bg-slate-600/60 border border-slate-600 rounded-lg text-slate-300 hover:text-white text-sm transition">
                            Cancel
                        </button>
                        <button type="submit" :disabled="submitting"
                            class="flex-1 px-4 py-2 bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg text-white font-medium text-sm transition">
                            <span x-show="!submitting">Send Message</span>
                            <span x-show="submitting">Sending...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Disclaimer --}}
    <p class="mt-6 text-xs text-slate-600 text-center">This political advertisement was paid for by the campaign of {{ $campaign->politician->full_name ?? 'the sponsoring campaign' }}. Earnings are credited to your wallet upon verified completion and processed in your next batch payout.</p>

</div>

<meta name="watch-token" content="{{ $adToken->token }}">
<meta name="csrf-token" content="{{ csrf_token() }}">

@push('scripts')
<script>
(function () {
    const overlay     = document.getElementById('play-overlay');
    const progressBar = document.getElementById('progress-bar');
    const statusMsg   = document.getElementById('status-msg');
    const token       = document.querySelector('meta[name="watch-token"]').content;
    const csrf        = document.querySelector('meta[name="csrf-token"]').content;
    const duration      = {{ $duration ?? 0 }};
    const mustWatch     = {{ $mustWatch ?? 100 }};
    const isYouTube     = {{ $isYouTube ? 'true' : 'false' }};
    const videoId       = '{{ $videoId ?? '' }}';
    const dashboardUrl  = '{{ route('voter.dashboard') }}';

    let sessionId      = null;
    let heartbeatTimer = null;
    let antiSkipTimer  = null;
    let completed      = false;
    let lastTime       = 0;
    let ytPlayer       = null;

    /* ── helpers ─────────────────────────────────────────────────── */
    function showStatus(msg, type = 'info') {
        const colours = {
            info:    'bg-slate-700/50 text-slate-300',
            success: 'bg-emerald-900/50 border border-emerald-500/40 text-emerald-300',
            error:   'bg-red-900/50 border border-red-500/40 text-red-300',
        };
        statusMsg.className = 'mt-5 text-center py-4 px-6 rounded-xl ' + (colours[type] ?? colours.info);
        statusMsg.textContent = msg;
        statusMsg.classList.remove('hidden');
    }

    function post(url, data) {
        return fetch(url, {
            method:  'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body:    JSON.stringify(data),
        }).then(r => r.json());
    }

    function startHeartbeat(getCurrentTime) {
        if (heartbeatTimer) return;
        heartbeatTimer = setInterval(async () => {
            if (!sessionId || completed) return;
            const watched = Math.floor(getCurrentTime());
            try {
                const res = await post(`/voter/session/${sessionId}/progress`, { seconds_watched: watched });
                // Update progress bar from server-reported percentage if available, else calculate locally
                const pct = res.watched_pct !== undefined
                    ? Math.min(100, res.watched_pct)
                    : (duration > 0 ? Math.min(100, (watched / duration) * 100) : 0);
                progressBar.style.width = pct + '%';

                // Server auto-completed the session because threshold was reached
                if (res.auto_completed && !completed) {
                    completed = true;
                    clearInterval(heartbeatTimer);
                    clearInterval(antiSkipTimer);
                    if (res.qualified) {
                        showStatus(`\u{1F389} You earned $${parseFloat(res.payout_earned).toFixed(2)}! Payment is being processed.`, 'success');
                        statusMsg.innerHTML += ` <a href="${dashboardUrl}" class="underline text-emerald-400 ml-2">View earnings \u2192</a>`;
                    } else {
                        showStatus('You watched enough \u2014 but did not meet the full qualifying threshold. No payout this time.', 'info');
                    }
                }
            } catch (_) { /* silent — next tick will retry */ }
        }, 5000);
    }

    async function handleVideoEnded(actualPlaybackSeconds) {
        // If heartbeat already qualified and credited the session, just show a tidy message
        if (completed) {
            showStatus('\u2713 Video finished \u2014 earnings already credited to your wallet.', 'success');
            return;
        }
        if (!sessionId) return;
        completed = true;
        clearInterval(heartbeatTimer);
        clearInterval(antiSkipTimer);
        // Use actual playback time if provided, fallback to the server-side duration
        const total = Math.floor(actualPlaybackSeconds > 0 ? actualPlaybackSeconds : duration);
        try {
            const baseUrl = '{{ url("/voter/session") }}';
            const res = await post(`${baseUrl}/${sessionId}/complete`, { total_seconds_watched: total });
            if (res.already_completed) {
                // Heartbeat beat us to it — earnings already recorded
                showStatus('\u2713 Video finished \u2014 earnings already credited to your wallet.', 'success');
                statusMsg.innerHTML += ' <a href="{{ route("voter.dashboard") }}" class="underline text-emerald-400 ml-2">View earnings →</a>';
            } else if (res.qualified) {
                showStatus(`\u{1F389} You earned $${parseFloat(res.payout_earned).toFixed(2)}! Payment is being processed.`, 'success');
                statusMsg.innerHTML += ' <a href="{{ route("voter.dashboard") }}" class="underline text-emerald-400 ml-2">View earnings →</a>';
            } else {
                showStatus('Video ended \u2014 watch at least {{ $mustWatch }}% to earn a payout.', 'info');
            }
        } catch (e) {
            showStatus('Error recording completion. Contact support.', 'error');
        }
    }

    /* ── YouTube IFrame API path ─────────────────────────────────── */
    if (isYouTube) {
        // YouTube calls this global when the API script loads
        window.onYouTubeIframeAPIReady = function () {
            ytPlayer = new YT.Player('yt-player-container', {
                height: '100%',
                width:  '100%',
                videoId: videoId,
                playerVars: {
                    enablejsapi:    1,
                    rel:            0,
                    fs:             0,       // disable fullscreen button
                    modestbranding: 1,
                    playsinline:    1,
                    controls:       0,       // 🔒 FRAUD PREVENTION: Hide all controls to prevent seeking
                    disablekb:      1,       // disable keyboard controls (spacebar, arrow keys)
                    iv_load_policy: 3,       // hide video annotations
                    origin:         window.location.origin,
                },
                events: {
                    onStateChange: function (e) {
                        if (e.data === YT.PlayerState.PLAYING) {
                            // Show control blocker to prevent any clicking on video
                            document.getElementById('control-blocker').classList.remove('hidden');
                            startHeartbeat(() => ytPlayer.getCurrentTime() || 0);
                            // Anti-skip: poll every 500ms for aggressive detection
                            if (!antiSkipTimer) {
                                antiSkipTimer = setInterval(() => {
                                    if (!ytPlayer || completed) return;
                                    const t = ytPlayer.getCurrentTime() || 0;
                                    // If user somehow skips forward more than 1.5 seconds, rewind
                                    if (t > lastTime + 1.5) {
                                        console.warn('⚠️ Skip detected - rewinding');
                                        ytPlayer.seekTo(lastTime, true);
                                        ytPlayer.playVideo(); // ensure it continues playing
                                    } else {
                                        lastTime = t;
                                    }
                                }, 500);
                            }
                        } else if (e.data === YT.PlayerState.PAUSED) {
                            // Prevent manual pausing - auto-resume
                            if (!completed && ytPlayer) {
                                console.warn('⚠️ Pause detected - auto-resuming');
                                setTimeout(() => ytPlayer.playVideo(), 100);
                            }
                        } else if (e.data === YT.PlayerState.ENDED) {
                            handleVideoEnded(ytPlayer.getCurrentTime() || 0);
                        }
                    }
                }
            });
        };

        // Inject the YouTube IFrame API script
        var ytScript  = document.createElement('script');
        ytScript.src  = 'https://www.youtube.com/iframe_api';
        document.head.appendChild(ytScript);

        // Overlay click → start session then play
        overlay.addEventListener('click', async () => {
            overlay.style.display = 'none';
            try {
                const startUrl = '{{ url("/voter/watch") }}/' + encodeURIComponent(token) + '/start';
                const res = await post(startUrl, {});
                if (res.error) { showStatus(res.error, 'error'); overlay.style.display = ''; return; }
                sessionId = res.session_id;
                window.sessionId = sessionId; // Expose for report forms
                if (ytPlayer && typeof ytPlayer.playVideo === 'function') {
                    ytPlayer.playVideo();
                }
            } catch (e) {
                showStatus('Could not start session. Please try again.', 'error');
                overlay.style.display = '';
            }
        });
    }

    /* ── Native HTML5 video path ─────────────────────────────────── */
    if (!isYouTube) {
        const video = document.getElementById('ad-video');
        let nativeLastTime = 0;

        // Prevent seeking on native video
        video.addEventListener('seeking', function() {
            if (!completed && nativeLastTime > 0) {
                const delta = Math.abs(this.currentTime - nativeLastTime);
                if (delta > 1.5) {
                    console.warn('⚠️ Seek detected - blocking');
                    this.currentTime = nativeLastTime;
                }
            }
        });

        // Track time and prevent skipping
        video.addEventListener('timeupdate', function() {
            if (!completed) {
                nativeLastTime = this.currentTime;
            }
        });

        // Prevent pausing
        video.addEventListener('pause', function() {
            if (!completed && video.currentTime > 0 && video.currentTime < video.duration - 1) {
                console.warn('⚠️ Pause detected - auto-resuming');
                setTimeout(() => video.play(), 100);
            }
        });

        overlay.addEventListener('click', async () => {
            overlay.style.display = 'none';
            try {
                const startUrl = '{{ url("/voter/watch") }}/' + encodeURIComponent(token) + '/start';
                const res = await post(startUrl, {});
                if (res.error) { showStatus(res.error, 'error'); overlay.style.display = ''; return; }
                sessionId = res.session_id;
                window.sessionId = sessionId; // Expose for report forms
                // Show control blocker
                document.getElementById('control-blocker').classList.remove('hidden');
                video.play();
                startHeartbeat(() => video.currentTime || 0);
            } catch (e) {
                showStatus('Could not start session. Please try again.', 'error');
                overlay.style.display = '';
            }
        });

        video.addEventListener('ended', () => handleVideoEnded(video.currentTime || 0));

        // Prevent skipping forward
        video.addEventListener('timeupdate', () => {
            if (video.currentTime > nativeLastTime + 2) {
                video.currentTime = nativeLastTime;
            } else {
                nativeLastTime = video.currentTime;
            }
        });
    }
})();
</script>
@endpush
@endsection
