<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="google-site-verification" content="gqW0SoY5hfBu8rcPBi_HMR-nCbSNdtoFj-XREjjEcmQ">
    <title>U9itus — See Who's Running in Your District.</title>
    <meta name="description" content="U9itus is the Virtual Town Hall where candidates pay $1.00 to earn your full attention — and you keep $0.50. Find who's running in your district, verify their record with public data, and get paid to engage with democracy.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ config('app.url') }}">
    <meta property="og:title" content="U9itus — See Who's Running in Your District">
    <meta property="og:description" content="Find every candidate in your district. Watch their message. Verify their record with FEC, OpenSecrets, Ballotpedia &amp; Vote Smart. Get paid $0.50 for your full attention.">
    <meta property="og:image" content="{{ asset('images/og-default.png') }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="U9itus — The Virtual Town Hall">
    <meta name="twitter:description" content="Candidates pay to earn your attention. You keep $0.50 per full view. Verify every claim with public data.">
    <meta name="twitter:image" content="{{ asset('images/og-default.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800&display=swap" rel="stylesheet" />
    
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/home/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
        <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @endif
    {{-- Animation styles always included regardless of asset pipeline --}}
    <style>
        * { font-family: 'Inter', sans-serif; }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up {
            animation: fadeInUp 0.8s ease-out forwards;
        }
        .delay-100 { animation-delay: 0.1s; }
        .delay-200 { animation-delay: 0.2s; }
        .delay-300 { animation-delay: 0.3s; }
        .delay-400 { animation-delay: 0.4s; }
    </style>
</head>
<body class="bg-slate-900 text-white antialiased">
    @php
        $activeReferralCode = !empty($referralCode) ? strtoupper(trim($referralCode)) : null;
    @endphp

    <!-- Navigation -->
    <nav class="fixed w-full z-50 bg-slate-900/90 backdrop-blur-lg border-b border-slate-800"
         x-data="{ open: false }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16 sm:h-20">
                <div class="flex items-center space-x-2">
                    <div class="text-2xl sm:text-3xl font-light tracking-tight">
                        <span class="font-bold">U9</span><span class="text-emerald-400">itus</span>
                    </div>
                </div>
                
                {{-- Desktop nav links --}}
                <div class="hidden md:flex items-center space-x-8">
                    <a href="{{ route('us.map') }}" class="text-slate-300 hover:text-white transition text-sm font-medium flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                        Explore Map
                    </a>
                    <a href="{{ route('district.lookup') }}" class="text-slate-300 hover:text-white transition text-sm font-medium">Find My District</a>
                    <a href="{{ route('about') }}" class="text-slate-300 hover:text-white transition text-sm font-medium">About Us</a>
                    <a href="{{ route('politicians.directory') }}" class="text-slate-300 hover:text-white transition text-sm font-medium">Browse Candidates</a>
                </div>
                
                <div class="flex items-center gap-3">
                    @if (Route::has('login'))
                        @auth
                            <a href="{{ url('/dashboard') }}"
                               class="inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-emerald-500 to-teal-500 rounded-lg hover:from-emerald-600 hover:to-teal-600 transition shadow-lg shadow-emerald-500/30">
                                <span class="mr-1.5">👤</span> Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}"
                               class="text-slate-300 hover:text-white transition text-sm font-medium">
                                Sign In
                            </a>
                            @if (Route::has('register'))
                                <a href="{{ $activeReferralCode ? route('register', ['ref' => $activeReferralCode]) : route('register') }}"
                                   class="hidden sm:inline-flex items-center justify-center px-4 py-2 text-sm font-medium text-white bg-gradient-to-r from-emerald-500 to-teal-500 rounded-lg hover:from-emerald-600 hover:to-teal-600 transition shadow-lg shadow-emerald-500/30">
                                    Get Started
                                </a>
                            @endif
                        @endauth
                    @endif

                    {{-- Mobile hamburger --}}
                    <button @@click="open = !open"
                            class="md:hidden p-2 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition"
                            :aria-expanded="open.toString()"
                            aria-label="Toggle menu">
                        <svg x-show="!open" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                        </svg>
                        <svg x-show="open" x-cloak class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        {{-- Mobile menu drawer --}}
        <div x-show="open" x-cloak x-transition
             class="md:hidden border-t border-slate-800 bg-slate-900/95 backdrop-blur-lg">
            <div class="px-4 py-3 space-y-1">
                <a href="{{ route('us.map') }}" @@click="open=false"
                   class="block px-3 py-2.5 rounded-lg text-sm font-medium text-slate-300 hover:text-white hover:bg-slate-800 transition flex items-center gap-2">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                    Explore Map
                </a>
                <a href="{{ route('district.lookup') }}" @@click="open=false"
                   class="block px-3 py-2.5 rounded-lg text-sm font-medium text-slate-300 hover:text-white hover:bg-slate-800 transition">Find My District</a>
                <a href="{{ route('about') }}" @@click="open=false"
                   class="block px-3 py-2.5 rounded-lg text-sm font-medium text-slate-300 hover:text-white hover:bg-slate-800 transition">About Us</a>
                <a href="{{ route('politicians.directory') }}" @@click="open=false"
                   class="block px-3 py-2.5 rounded-lg text-sm font-medium text-slate-300 hover:text-white hover:bg-slate-800 transition">Browse Candidates</a>
                @if(Route::has('register'))
                    @guest
                    <div class="pt-2 pb-1">
                        <a href="{{ $activeReferralCode ? route('register', ['ref' => $activeReferralCode]) : route('register') }}"
                           class="block w-full text-center px-4 py-2.5 text-sm font-semibold text-white bg-gradient-to-r from-emerald-500 to-teal-500 rounded-lg hover:from-emerald-600 hover:to-teal-600 transition">
                            Get Started
                        </a>
                    </div>
                    @endguest
                @endif
            </div>
        </div>
    </nav>

    <!-- Hero Section (compact — real candidates load immediately below) -->
    <section class="relative overflow-hidden">
        <!-- Gradient Background -->
        <div class="absolute inset-0 bg-gradient-to-br from-slate-900 via-slate-800 to-teal-900"></div>

        <!-- Animated Gradient Overlay -->
        <div class="absolute inset-0 bg-gradient-to-tr from-emerald-500/10 via-transparent to-teal-500/10"></div>

        <!-- Grid Pattern -->
        <div class="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.02)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.02)_1px,transparent_1px)] bg-[size:72px_72px]"></div>

        <!-- Content -->
        <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-20 text-center">
            <div class="space-y-6">
                <div class="animate-fade-in-up opacity-0 mt-4 sm:mt-6">
                    <span class="inline-block px-4 py-1.5 bg-emerald-500/10 border border-emerald-500/20 rounded-full text-emerald-400 text-sm font-medium mb-4">
                        — SEE WHO'S RUNNING NEAR YOU
                    </span>
                </div>

                <h1 class="animate-fade-in-up delay-100 opacity-0 text-4xl sm:text-5xl lg:text-6xl font-bold tracking-tight">
                    Who Wants to Represent<br/>
                    <span class="bg-gradient-to-r from-emerald-400 to-teal-400 bg-clip-text text-transparent">You?</span>
                </h1>

                <p class="animate-fade-in-up delay-200 opacity-0 text-lg sm:text-xl text-slate-300 max-w-3xl mx-auto leading-relaxed">
                    Real candidates from your district, right below — no ZIP required to look. Watch their message, verify every claim with public data, and get paid for the time you spend understanding it.
                </p>

                @if ($activeReferralCode)
                    <div class="animate-fade-in-up delay-200 opacity-0 max-w-3xl mx-auto rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-6 py-5">
                        <p class="text-xs uppercase tracking-wide font-semibold text-emerald-300">Referral Program</p>
                        <p class="mt-2 text-base text-slate-200">You were invited by a U9itus member. Explore how the platform works before creating your account.</p>
                        <p class="mt-2 text-sm text-slate-300">Referral code: <span class="font-semibold text-white">{{ $activeReferralCode }}</span></p>
                        <div class="mt-4 flex flex-col sm:flex-row gap-3 justify-center">
                            <a href="{{ route('register.voter', ['ref' => $activeReferralCode]) }}" class="inline-flex items-center justify-center px-5 py-2.5 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-500 rounded-lg transition">Join as Voter</a>
                            <a href="{{ route('register.politician', ['ref' => $activeReferralCode]) }}" class="inline-flex items-center justify-center px-5 py-2.5 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-500 rounded-lg transition">Join as Politician</a>
                        </div>
                    </div>
                @endif

                <div class="animate-fade-in-up delay-300 opacity-0 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 items-stretch justify-center gap-4 pt-4">
                    <a href="{{ route('district.lookup') }}"
                       class="inline-flex items-center justify-center px-8 py-4 text-lg font-semibold text-white bg-gradient-to-r from-emerald-500 to-teal-500 rounded-xl hover:from-emerald-600 hover:to-teal-600 transition shadow-2xl shadow-emerald-500/40 hover:shadow-emerald-500/60 hover:-translate-y-0.5 transform">
                        Find My District
                        <svg class="ml-2 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                        </svg>
                    </a>

                    <a href="{{ route('politicians.directory') }}"
                       class="inline-flex items-center justify-center px-8 py-4 text-lg font-semibold text-white bg-slate-800 border border-slate-700 rounded-xl hover:bg-slate-700 transition">
                        Browse Candidates
                    </a>

                    <a href="{{ route('us.map') }}"
                       class="inline-flex items-center justify-center px-8 py-4 text-lg font-semibold text-white bg-slate-800/60 border border-indigo-500/40 rounded-xl hover:border-indigo-400/70 hover:bg-indigo-900/30 transition group">
                        <svg class="w-5 h-5 mr-2 text-indigo-400 group-hover:text-indigo-300 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                        <span class="text-indigo-300 group-hover:text-white transition">Explore the Map</span>
                    </a>

                    @guest
                        <a href="{{ $activeReferralCode ? route('register', ['ref' => $activeReferralCode]) : route('register') }}"
                           class="inline-flex items-center justify-center px-8 py-4 text-lg font-semibold text-white bg-slate-800 border border-slate-700 rounded-xl hover:bg-slate-700 transition">
                            Create Free Account
                            <svg class="ml-2 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                            </svg>
                        </a>
                    @endguest
                </div>

                @guest
                    @if (config('platform.map.voter_features_enabled') && config('platform.map.sign_in_cta'))
                        @php
                            $u9LoginUrl = url('https://www.early-bank.com') . '?' . http_build_query(array_filter([
                                'ref'  => request()->query('ref') ?? $activeReferralCode,
                                'from' => 'home',
                            ]));
                        @endphp
                        <div class="animate-fade-in-up delay-300 opacity-0 flex justify-center pt-4">
                            <a id="btn-earn-cta" href="{{ $u9LoginUrl }}"
                               title="Get paid to watch campaign videos — up to $0.50 each"
                               class="inline-flex items-center px-4 py-2 rounded-full text-sm font-semibold text-emerald-400 bg-emerald-500/10 border border-emerald-400/40 hover:bg-emerald-500/20 transition">
                                Earn $$$ to share
                            </a>
                        </div>
                    @endif
                @endguest
            </div>
        </div>
    </section>

    @isset($featuredCandidates)
    @if($featuredCandidates->isNotEmpty())
    <!-- Featured Candidates (geo-aware, rotating) — first thing visible after the headline -->
    <section id="featured-candidates" class="relative py-16 sm:py-20 bg-gradient-to-b from-slate-900 to-slate-800/60">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <span class="text-emerald-400 font-semibold text-sm tracking-wider uppercase">
                    @if(!empty($visitorState))
                        — Candidates Near You · {{ $visitorState }}
                    @else
                        — Featured Candidates
                    @endif
                </span>
                <h2 class="mt-4 text-3xl sm:text-4xl font-bold">
                    Who's <span class="text-emerald-400">On Your Ballot</span> Right Now
                </h2>
                <p class="mt-3 text-slate-400 text-sm">Click any card to see their full profile — record, positions, and how to verify their claims.</p>
            </div>

            <div
                x-data="{
                    active: 0,
                    total: {{ $featuredCandidates->count() }},
                    timer: null,
                    start() {
                        if (this.total <= 1) return;
                        this.timer = setInterval(() => { this.active = (this.active + 1) % this.total; }, 6000);
                    },
                    stop() { if (this.timer) clearInterval(this.timer); }
                }"
                x-init="start()"
                @mouseenter="stop()"
                @mouseleave="start()"
                class="relative"
            >
                <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
                    @foreach($featuredCandidates as $index => $candidate)
                        @php
                            $news = $candidate->latest_news ?? null;
                            $office = trim((string) ($candidate->political_office ?? ''));
                            $district = trim((string) ($candidate->district ?? ''));
                            $state = trim((string) ($candidate->state ?? ''));
                            $jobTitle = $office !== '' ? $office : 'Candidate';
                            $districtLine = trim($district . ($district && $state ? ', ' : '') . $state);
                            $cardHref = route('politician.public.show', $candidate->slug);
                        @endphp
                        <a href="{{ $cardHref }}"
                           :class="active === {{ $index }} ? 'ring-2 ring-emerald-500/60 scale-[1.01]' : 'opacity-90 hover:opacity-100'"
                           class="group block bg-slate-800/70 border border-slate-700 hover:border-emerald-500/50 rounded-2xl overflow-hidden transition transform duration-300">
                            <div class="aspect-[16/10] relative bg-gradient-to-br from-slate-700 to-slate-900">
                                @if(!empty($candidate->profile_photo_url))
                                    <img src="{{ $candidate->profile_photo_url }}"
                                         alt="{{ $candidate->full_name }}"
                                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" />
                                @else
                                    <div class="w-full h-full flex items-center justify-center text-5xl font-bold text-slate-600">
                                        {{ strtoupper(substr((string) $candidate->full_name, 0, 1)) }}
                                    </div>
                                @endif
                                @if($candidate->verified_official)
                                <div class="absolute top-3 right-3 bg-emerald-500 rounded-full p-1.5">
                                    <svg class="w-3.5 h-3.5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                                @endif
                                @if($state)
                                <div class="absolute bottom-3 left-3 flex items-center gap-1 bg-slate-900/80 backdrop-blur-sm rounded-full px-2 py-0.5 text-[10px] text-indigo-300 font-medium">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                    {{ $state }}
                                </div>
                                @endif
                            </div>
                            <div class="p-5">
                                <h3 class="text-white font-semibold text-lg group-hover:text-emerald-400 transition truncate">
                                    {{ $candidate->full_name }}
                                </h3>
                                <p class="text-emerald-400 text-xs font-medium uppercase tracking-wider mt-1 truncate">
                                    {{ $jobTitle }}
                                </p>
                                @if($districtLine !== '')
                                    <p class="text-slate-400 text-xs mt-1 truncate">{{ $districtLine }}</p>
                                @endif

                                <div class="mt-4 pt-4 border-t border-slate-700/60">
                                    @if($news)
                                        <p class="text-[11px] uppercase tracking-wide text-slate-500 mb-1">Recent News</p>
                                        <p class="text-slate-300 text-sm line-clamp-3">
                                            {{ \Illuminate\Support\Str::limit($news->headline ?? '', 140) }}
                                        </p>
                                        @if(!empty($news->source_name))
                                            <p class="text-slate-500 text-[11px] mt-2">{{ $news->source_name }}</p>
                                        @endif
                                    @else
                                        <p class="text-slate-500 text-sm italic">No recent news available — view profile for full transparency record.</p>
                                    @endif
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>

                @if($featuredCandidates->count() > 1)
                <div class="flex items-center justify-center gap-2 mt-8">
                    @foreach($featuredCandidates as $index => $candidate)
                        <button type="button"
                                @click="active = {{ $index }}"
                                :class="active === {{ $index }} ? 'bg-emerald-400 w-6' : 'bg-slate-600 w-2 hover:bg-slate-500'"
                                class="h-2 rounded-full transition-all duration-300"
                                aria-label="Show candidate {{ $index + 1 }}"></button>
                    @endforeach
                </div>
                @endif
            </div>
        </div>
    </section>
    @endif
    @endisset

    <!-- How It Works — 3-step strip -->
    <section class="bg-slate-900 border-b border-slate-800/80 py-12">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-8 text-center">
                <div class="flex flex-col items-center gap-3">
                    <div class="w-12 h-12 rounded-full bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-xl">🔍</div>
                    <div>
                        <p class="text-white font-semibold">1. Find Your Ballot</p>
                        <p class="text-slate-400 text-sm mt-1">Enter your ZIP — see every candidate running in your district in 10 seconds. No account needed.</p>
                    </div>
                </div>
                <div class="flex flex-col items-center gap-3">
                    <div class="w-12 h-12 rounded-full bg-teal-500/10 border border-teal-500/30 flex items-center justify-center text-xl">📺</div>
                    <div>
                        <p class="text-white font-semibold">2. Watch &amp; Verify</p>
                        <p class="text-slate-400 text-sm mt-1">Watch their full message. Cross-check their claims with FEC filings, donor records, and voting history.</p>
                    </div>
                </div>
                <div class="flex flex-col items-center gap-3">
                    <div class="w-12 h-12 rounded-full bg-indigo-500/10 border border-indigo-500/30 flex items-center justify-center text-xl">🙋</div>
                    <div>
                        <p class="text-white font-semibold">3. Ask &amp; Get Rewarded</p>
                        <p class="text-slate-400 text-sm mt-1">Ask them a question — they answer publicly. You keep $0.50 for the time you spent understanding your ballot.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Interactive Map Section -->
    <section id="explore-map" class="relative py-24 bg-gradient-to-b from-slate-800 to-slate-900 overflow-hidden">
        {{-- Subtle background glow --}}
        <div class="absolute inset-0 pointer-events-none">
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[800px] h-[600px] bg-indigo-500/6 blur-[140px] rounded-full"></div>
        </div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            {{-- Section header --}}
            <div class="text-center mb-12">
                <span class="text-indigo-400 font-semibold text-sm tracking-wider uppercase">— Explore the Political Landscape</span>
                <h2 class="mt-4 text-4xl sm:text-5xl font-bold text-white">
                    See Every District,
                    <span class="bg-gradient-to-r from-indigo-400 to-violet-400 bg-clip-text text-transparent">At a Glance</span>
                </h2>
                <p class="mt-4 text-xl text-slate-300 max-w-2xl mx-auto">
                    Our interactive 3-D map breaks the U.S. into regions, states, and all 435 congressional districts — colored by party — so you can find your rep in seconds.
                </p>
            </div>

            {{-- Two-column layout: feature bullets left, map preview right --}}
            <div class="grid lg:grid-cols-2 gap-12 items-center">

                {{-- Feature list --}}
                <div class="space-y-6">
                    @php
                        $mapFeatures = [
                            ['icon' => '🔴', 'color' => 'text-red-400', 'title' => 'Party coloring', 'desc' => 'Red for Republican, Blue for Democrat, Green for Independent — every district at a glance.'],
                            ['icon' => '🗺', 'color' => 'text-indigo-400', 'title' => 'Drill from region → state → district', 'desc' => 'Click any region to zoom in, then click a state to see all its congressional districts rendered flat and clear.'],
                            ['icon' => '🔍', 'color' => 'text-violet-400', 'title' => 'Instant search', 'desc' => 'Press / and type "CA-38" or "Texas" — the map flies to that district and loads its candidates automatically.'],
                            ['icon' => '📋', 'color' => 'text-emerald-400', 'title' => 'Candidates on click', 'desc' => 'Select any district to see the seated rep, challengers, and everyone running in that race — tap a name to open their profile and verify the record.'],
                        ];
                    @endphp

                    @foreach ($mapFeatures as $f)
                        <div class="flex gap-4">
                            <div class="flex-shrink-0 w-11 h-11 rounded-xl bg-slate-800 border border-slate-700 flex items-center justify-center text-xl">
                                {{ $f['icon'] }}
                            </div>
                            <div>
                                <h3 class="font-semibold text-white text-lg">{{ $f['title'] }}</h3>
                                <p class="text-slate-400 text-sm leading-relaxed mt-0.5">{{ $f['desc'] }}</p>
                            </div>
                        </div>
                    @endforeach

                    <div class="pt-4 flex flex-col sm:flex-row gap-3">
                        <a href="{{ route('us.map') }}"
                           class="inline-flex items-center justify-center px-7 py-3.5 text-base font-semibold text-white bg-gradient-to-r from-indigo-500 to-violet-500 rounded-xl hover:from-indigo-600 hover:to-violet-600 transition shadow-xl shadow-indigo-500/30 hover:-translate-y-0.5 transform">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                            Open Interactive Map
                        </a>
                        <a href="{{ route('district.lookup') }}"
                           class="inline-flex items-center justify-center px-7 py-3.5 text-base font-semibold text-slate-300 bg-slate-800 border border-slate-700 rounded-xl hover:bg-slate-700 transition">
                            Find by Address Instead
                        </a>
                    </div>
                </div>

                {{-- Map preview card --}}
                <div class="relative">
                    <div class="relative rounded-2xl overflow-hidden border border-indigo-500/20 shadow-2xl shadow-indigo-900/40 bg-slate-950">
                        {{-- Fake top bar --}}
                        <div class="flex items-center justify-between px-4 py-3 bg-slate-900/90 border-b border-slate-800">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-indigo-400 text-sm">U9itus</span>
                                <span class="text-slate-600 text-xs">|</span>
                                <span class="text-slate-500 text-xs">U.S. Regional Map</span>
                            </div>
                            <div class="flex gap-2">
                                <span class="px-2.5 py-0.5 rounded text-xs bg-indigo-900/50 text-indigo-300 border border-indigo-800">Search /</span>
                                <span class="px-2.5 py-0.5 rounded text-xs bg-slate-800 text-slate-400 border border-slate-700">Reset View</span>
                            </div>
                        </div>

                        {{-- Static SVG mini-map placeholder --}}
                        <div class="relative h-72 bg-[#06091a] flex items-center justify-center">
                            {{-- Party legend badges floating over --}}
                            <div class="absolute top-3 left-3 space-y-1.5 text-xs">
                                <div class="flex items-center gap-1.5 bg-slate-900/80 rounded px-2 py-1 backdrop-blur">
                                    <span class="w-2.5 h-2.5 rounded-sm bg-red-600 inline-block"></span><span class="text-slate-300">Republican</span>
                                </div>
                                <div class="flex items-center gap-1.5 bg-slate-900/80 rounded px-2 py-1 backdrop-blur">
                                    <span class="w-2.5 h-2.5 rounded-sm bg-blue-600 inline-block"></span><span class="text-slate-300">Democrat</span>
                                </div>
                                <div class="flex items-center gap-1.5 bg-slate-900/80 rounded px-2 py-1 backdrop-blur">
                                    <span class="w-2.5 h-2.5 rounded-sm bg-green-600 inline-block"></span><span class="text-slate-300">Independent</span>
                                </div>
                            </div>

                            {{-- Central label --}}
                            <div class="text-center space-y-3">
                                <div class="text-6xl">🗺</div>
                                <p class="text-slate-500 text-sm">3-D interactive map</p>
                                <a href="{{ route('us.map') }}"
                                   class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-500 transition shadow-lg shadow-indigo-900/50">
                                    Launch Map
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                </a>
                            </div>

                            {{-- Decorative district swatches --}}
                            <div class="absolute bottom-3 right-3 flex gap-1 opacity-50">
                                @foreach (['bg-red-700','bg-blue-700','bg-red-800','bg-blue-600','bg-red-600','bg-blue-800','bg-green-700','bg-red-700','bg-blue-700'] as $c)
                                    <div class="w-5 h-5 rounded-sm {{ $c }}"></div>
                                @endforeach
                            </div>
                        </div>

                        {{-- Stats bar --}}
                        <div class="flex divide-x divide-slate-800 border-t border-slate-800 text-center">
                            <div class="flex-1 py-2.5">
                                <div class="text-white font-bold text-sm">435</div>
                                <div class="text-slate-500 text-xs">Districts</div>
                            </div>
                            <div class="flex-1 py-2.5">
                                <div class="text-white font-bold text-sm">50</div>
                                <div class="text-slate-500 text-xs">States</div>
                            </div>
                            <div class="flex-1 py-2.5">
                                <div class="text-white font-bold text-sm">119th</div>
                                <div class="text-slate-500 text-xs">Congress</div>
                            </div>
                            <div class="flex-1 py-2.5">
                                <div class="text-white font-bold text-sm">Live</div>
                                <div class="text-slate-500 text-xs">Census data</div>
                            </div>
                        </div>
                    </div>

                    {{-- Floating hint badge --}}
                    <div class="absolute -bottom-4 -right-4 bg-indigo-600 text-white text-xs font-semibold px-3 py-1.5 rounded-full shadow-lg shadow-indigo-900/50 border border-indigo-500">
                        Press / to search any district
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Civic Identity Section -->
    <section id="civic-identity" class="relative py-24 bg-slate-900 overflow-hidden">
        <div class="absolute inset-0 pointer-events-none">
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[800px] h-[600px] bg-violet-500/6 blur-[140px] rounded-full"></div>
        </div>

        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <span class="text-violet-400 font-semibold text-sm tracking-wider uppercase">— Your Civic Identity</span>
                <h2 class="mt-4 text-4xl sm:text-5xl font-bold text-white">
                    Show What You Stand For.
                    <span class="bg-gradient-to-r from-violet-400 to-fuchsia-400 bg-clip-text text-transparent">On Your Terms.</span>
                </h2>
                <p class="mt-4 text-xl text-slate-300 max-w-2xl mx-auto">
                    Declare the issues you care about — Healthcare, Housing, Climate, and more. Every badge is private by default. You decide what the world sees.
                </p>
            </div>

            <div class="grid lg:grid-cols-2 gap-12 items-center">
                {{-- Feature list --}}
                <div class="space-y-6">
                    @php
                        $identityFeatures = [
                            ['icon' => '🏅', 'title' => 'Self-declared badges', 'desc' => 'Pick the causes you care about and add them to your profile in one click.'],
                            ['icon' => '🔒', 'title' => 'Private by default', 'desc' => 'New badges start hidden. Flip a switch when — and if — you want one public.'],
                            ['icon' => '✅', 'title' => 'Earned recognition', 'desc' => 'Watch enough campaigns on a topic and the platform grants you an earned badge automatically.'],
                            ['icon' => '⭐', 'title' => 'Follow candidates & causes', 'desc' => 'Favorite the politicians and ballot measures that matter to you, and build a profile that reflects it.'],
                        ];
                    @endphp

                    @foreach ($identityFeatures as $f)
                        <div class="flex gap-4">
                            <div class="flex-shrink-0 w-11 h-11 rounded-xl bg-slate-800 border border-slate-700 flex items-center justify-center text-xl">
                                {{ $f['icon'] }}
                            </div>
                            <div>
                                <h3 class="font-semibold text-white text-lg">{{ $f['title'] }}</h3>
                                <p class="text-slate-400 text-sm leading-relaxed mt-0.5">{{ $f['desc'] }}</p>
                            </div>
                        </div>
                    @endforeach

                    @guest
                        <div class="pt-4">
                            <a href="{{ $activeReferralCode ? route('register', ['ref' => $activeReferralCode]) : route('register') }}"
                               class="inline-flex items-center justify-center px-7 py-3.5 text-base font-semibold text-white bg-gradient-to-r from-violet-500 to-fuchsia-500 rounded-xl hover:from-violet-600 hover:to-fuchsia-600 transition shadow-xl shadow-violet-500/30 hover:-translate-y-0.5 transform">
                                Create Free Account
                                <svg class="ml-2 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                                </svg>
                            </a>
                        </div>
                    @endguest
                </div>

                {{-- "My Badges" preview card — mirrors the real profile UI --}}
                <div class="relative">
                    <div class="relative rounded-2xl overflow-hidden border border-violet-500/20 shadow-2xl shadow-violet-900/40 bg-slate-950">
                        <div class="flex items-center justify-between px-4 py-3 bg-slate-900/90 border-b border-slate-800">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-violet-400 text-sm">U9itus</span>
                                <span class="text-slate-600 text-xs">|</span>
                                <span class="text-slate-500 text-xs">My Badges</span>
                            </div>
                        </div>

                        <div class="p-5 space-y-3">
                            <div class="flex items-center justify-between gap-3 bg-slate-900/60 border border-slate-700/40 rounded-lg px-4 py-3">
                                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium text-white bg-violet-600">
                                    🏥 Healthcare Access
                                </span>
                                <div class="flex items-center gap-2 shrink-0">
                                    <span class="relative inline-flex w-9 h-5 rounded-full bg-emerald-500">
                                        <span class="absolute top-[2px] right-[2px] bg-white rounded-full h-4 w-4"></span>
                                    </span>
                                    <span class="text-[10px] text-slate-500">Public</span>
                                </div>
                            </div>
                            <div class="flex items-center justify-between gap-3 bg-slate-900/60 border border-slate-700/40 rounded-lg px-4 py-3">
                                <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium text-white bg-indigo-600">
                                    🏠 Affordable Housing
                                </span>
                                <div class="flex items-center gap-2 shrink-0">
                                    <span class="relative inline-flex w-9 h-5 rounded-full bg-slate-700">
                                        <span class="absolute top-[2px] left-[2px] bg-white rounded-full h-4 w-4"></span>
                                    </span>
                                    <span class="text-[10px] text-slate-500">Private</span>
                                </div>
                            </div>
                            <p class="text-[11px] text-slate-600 pt-1">New badges are added as private — toggle one to Public once added.</p>
                        </div>
                    </div>

                    <div class="absolute -bottom-4 -right-4 bg-violet-600 text-white text-xs font-semibold px-3 py-1.5 rounded-full shadow-lg shadow-violet-900/50 border border-violet-500">
                        You control what's visible
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Revenue Model -->
    <section id="revenue" class="relative py-24 bg-gradient-to-b from-slate-900 to-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <span class="text-emerald-400 font-semibold text-sm tracking-wider uppercase">— The Attention Economy, Inverted</span>
                <h2 class="mt-4 text-4xl sm:text-5xl font-bold">
                    Your Attention Has Always Had Value.<br/><span class="text-emerald-400">Now It Pays You.</span>
                </h2>
                <p class="mt-4 text-xl text-slate-300 max-w-2xl mx-auto">
                    Every other platform sells your attention to advertisers. Here, candidates pay $1.00 to earn your full, uninterrupted focus — and you keep $0.50 of it. The rest funds the transparency layer that fact-checks what they say.
                </p>
            </div>

            <div class="relative bg-gradient-to-br from-slate-800/50 to-slate-900/50 backdrop-blur-sm rounded-3xl p-8 md:p-12 border border-slate-700">
                <!-- Large Number -->
                <div class="absolute top-0 left-0 text-[200px] font-bold text-slate-700/5 leading-none">
                    $
                </div>
                
                <div class="relative space-y-8">
                    <div class="grid md:grid-cols-3 gap-4">
                        <div class="p-6 bg-slate-800/60 rounded-xl border border-slate-700">
                            <p class="text-emerald-300 text-sm uppercase tracking-wide">See Your Ballot Context</p>
                            <p class="text-xl font-semibold text-white mt-2">Enter an address and instantly see your district and who is running.</p>
                        </div>
                        <div class="p-6 bg-slate-800/60 rounded-xl border border-slate-700">
                            <p class="text-teal-300 text-sm uppercase tracking-wide">Hear Candidates Directly</p>
                            <p class="text-xl font-semibold text-white mt-2">Watch intro videos and issue-based answers instead of guessing from ads.</p>
                        </div>
                        <div class="p-6 bg-slate-800/60 rounded-xl border border-slate-700">
                            <p class="text-blue-300 text-sm uppercase tracking-wide">Verify What They Say</p>
                            <p class="text-xl font-semibold text-white mt-2">Cross-check claims with finance, voting, donor, and election-history sources.</p>
                        </div>
                    </div>

                    <div class="bg-gradient-to-r from-emerald-500/10 to-teal-500/10 rounded-xl p-8 border border-emerald-500/20 text-center">
                        <div class="text-sm text-emerald-400 font-semibold mb-2">INFORMED PARTICIPATION, NOT JUST ATTENTION</div>
                        <div class="text-xl font-semibold text-white">The platform rewards engagement, but the main value is understanding your upcoming elections faster.</div>
                        <div class="text-slate-300 mt-2">Compensation supports participation; it does not replace the civic value of learning who represents you.</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section id="how-it-works" class="relative py-24 bg-slate-900">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <span class="text-emerald-400 font-semibold text-sm tracking-wider uppercase">— Phase 3 & 4</span>
                <h2 class="mt-4 text-4xl sm:text-5xl font-bold">
                    Transparency Layer + <span class="text-emerald-400">Growth Loop</span>
                </h2>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <div class="relative bg-slate-800/50 rounded-xl p-8 border border-slate-700 hover:border-emerald-500/50 transition group">
                    <div class="absolute inset-0 bg-gradient-to-br from-emerald-500/0 to-emerald-500/5 rounded-xl opacity-0 group-hover:opacity-100 transition"></div>
                    <div class="relative">
                        <div class="text-5xl mb-4">🛡️</div>
                        <h3 class="text-xl font-bold mb-3">FEC Data</h3>
                        <p class="text-slate-300">Campaign finance, donor records, and spending context alongside videos.</p>
                    </div>
                </div>

                <div class="relative bg-slate-800/50 rounded-xl p-8 border border-slate-700 hover:border-teal-500/50 transition group">
                    <div class="absolute inset-0 bg-gradient-to-br from-teal-500/0 to-teal-500/5 rounded-xl opacity-0 group-hover:opacity-100 transition"></div>
                    <div class="relative">
                        <div class="text-5xl mb-4">💳</div>
                        <h3 class="text-xl font-bold mb-3">Vote Smart</h3>
                        <p class="text-slate-300">Issue positions and voting history linked directly to candidate profiles.</p>
                    </div>
                </div>

                <div class="relative bg-slate-800/50 rounded-xl p-8 border border-slate-700 hover:border-blue-500/50 transition group">
                    <div class="absolute inset-0 bg-gradient-to-br from-blue-500/0 to-blue-500/5 rounded-xl opacity-0 group-hover:opacity-100 transition"></div>
                    <div class="relative">
                        <div class="text-5xl mb-4">📈</div>
                        <h3 class="text-xl font-bold mb-3">OpenSecrets</h3>
                        <p class="text-slate-300">Funding and lobbyist relationships for deeper voter verification.</p>
                    </div>
                </div>

                <div class="relative bg-slate-800/50 rounded-xl p-8 border border-slate-700 hover:border-purple-500/50 transition group">
                    <div class="absolute inset-0 bg-gradient-to-br from-purple-500/0 to-purple-500/5 rounded-xl opacity-0 group-hover:opacity-100 transition"></div>
                    <div class="relative">
                        <div class="text-5xl mb-4">⚡</div>
                        <h3 class="text-xl font-bold mb-3">Ballotpedia</h3>
                        <p class="text-slate-300">Election history, biography, and prior race context in one view.</p>
                    </div>
                </div>

                <div class="relative bg-slate-800/50 rounded-xl p-8 border border-slate-700 hover:border-yellow-500/50 transition group">
                    <div class="absolute inset-0 bg-gradient-to-br from-yellow-500/0 to-yellow-500/5 rounded-xl opacity-0 group-hover:opacity-100 transition"></div>
                    <div class="relative">
                        <div class="text-5xl mb-4">🤖</div>
                        <h3 class="text-xl font-bold mb-3">Voter Growth Loop</h3>
                        <p class="text-slate-300">Surveys, referrals, and follow-up content keep voters returning to learn more over time.</p>
                    </div>
                </div>

                <div class="relative bg-slate-800/50 rounded-xl p-8 border border-slate-700 hover:border-pink-500/50 transition group">
                    <div class="absolute inset-0 bg-gradient-to-br from-pink-500/0 to-pink-500/5 rounded-xl opacity-0 group-hover:opacity-100 transition"></div>
                    <div class="relative">
                        <div class="text-5xl mb-4">🔒</div>
                        <h3 class="text-xl font-bold mb-3">Politician Growth Loop</h3>
                        <p class="text-slate-300">Feedback informs next content so campaigns keep improving relevance.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    @guest
    <section class="relative py-24 bg-gradient-to-b from-slate-800 to-slate-900">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-4xl sm:text-5xl font-bold mb-6">
                Democracy starts with<br/><span class="text-emerald-400">knowing a name.</span>
            </h2>
            <p class="text-xl text-slate-300 mb-10 max-w-2xl mx-auto">
                Find who's running in your city. Watch their full message. Verify their record. You can do all three in under five minutes — no account required.
            </p>
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="{{ route('district.lookup') }}"
                   class="inline-flex items-center justify-center px-8 py-4 text-lg font-semibold text-white bg-gradient-to-r from-emerald-500 to-teal-500 rounded-xl hover:from-emerald-600 hover:to-teal-600 transition shadow-2xl shadow-emerald-500/40 hover:shadow-emerald-500/60 hover:-translate-y-0.5 transform">
                    Find My District
                    <svg class="ml-2 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                    </svg>
                </a>
                <a href="{{ route('politicians.directory') }}"
                   class="inline-flex items-center justify-center px-8 py-4 text-lg font-semibold text-white bg-slate-800 border border-slate-700 rounded-xl hover:bg-slate-700 transition">
                    Browse Candidates
                </a>
            </div>
        </div>
    </section>
    @endguest

    <!-- Footer -->
    <footer class="relative bg-slate-900 border-t border-slate-800 py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid md:grid-cols-3 gap-8 mb-8">
                <div>
                    <div class="text-2xl font-light tracking-tight mb-4">
                        <span class="font-bold">U9</span><span class="text-emerald-400">itus</span>
                    </div>
                    <p class="text-slate-400 text-sm">
                        Unite the politician and the voter with paid engagement and transparent accountability.
                    </p>
                </div>
                
                <div>
                    <h3 class="font-semibold mb-4">Platform</h3>
                    <ul class="space-y-2 text-slate-400 text-sm">
                        <li><a href="#featured-candidates" class="hover:text-white transition">Featured Candidates</a></li>
                        <li><a href="#civic-identity" class="hover:text-white transition">Your Badges</a></li>
                        <li><a href="#revenue" class="hover:text-white transition">Voter Value</a></li>
                        <li><a href="#how-it-works" class="hover:text-white transition">Transparency Layer</a></li>
                        <li><a href="{{ route('about') }}" class="hover:text-white transition">About Us</a></li>
                    </ul>
                </div>
                
                <div>
                    <h3 class="font-semibold mb-4">Legal</h3>
                    <ul class="space-y-2 text-slate-400 text-sm">
                        <li><a href="{{ route('privacy-policy') }}" class="hover:text-white transition">Privacy Policy</a></li>
                        <li><a href="{{ route('terms') }}" class="hover:text-white transition">Terms of Service</a></li>
                        <li><a href="#" class="hover:text-white transition">Compliance</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="border-t border-slate-800 pt-8 text-center text-slate-400 text-sm">
                <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>
