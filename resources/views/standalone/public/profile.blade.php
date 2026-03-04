<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $ogTitle }} — {{ config('app.name', 'U9itus') }}</title>

    {{-- Open Graph / Social Sharing --}}
    <meta property="og:type"        content="profile">
    <meta property="og:url"         content="{{ $ogUrl }}">
    <meta property="og:title"       content="{{ $ogTitle }}">
    <meta property="og:description" content="{{ $ogDescription }}">
    @if($ogImage)
    <meta property="og:image"       content="{{ $ogImage }}">
    @endif
    <meta name="twitter:card"        content="summary_large_image">
    <meta name="twitter:title"       content="{{ $ogTitle }}">
    <meta name="twitter:description" content="{{ $ogDescription }}">
    @if($ogImage)
    <meta name="twitter:image"       content="{{ $ogImage }}">
    @endif
    <meta name="description" content="{{ $ogDescription }}">

    {{-- Fonts --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800&display=swap" rel="stylesheet" />

    {{-- Vite assets --}}
    @if(file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif

    {{-- Phase 13 CSS variables (theme colors from politician's page config) --}}
    <style>
        :root { {{ $page->cssVariables() }} }
        * { font-family: 'Inter', sans-serif; }

        /* Layout preset helpers */
        .layout-classic  .hero-card { border-radius: 1rem; }
        .layout-modern   .hero-card { border-radius: 0; }
        .layout-bold     .hero-card { border-left: 6px solid var(--p13-accent, #f59e0b); border-radius: 0.5rem; }
        .layout-minimal  .hero-card { background: transparent; border: none; }

        /* Background styles */
        .bg-style-dark     { background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); }
        .bg-style-light    { background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); color: #1e293b; }
        .bg-style-gradient { background: linear-gradient(135deg, var(--p13-primary, #1e40af) 0%, #0f172a 60%); }
        .bg-style-image    { background-color: #0f172a; }

        .p13-btn-primary {
            background-color: var(--p13-primary, #1e40af);
            color: #fff;
        }
        .p13-btn-primary:hover { opacity: .88; }
        .p13-accent { color: var(--p13-accent, #f59e0b); }
        .p13-border-accent { border-color: var(--p13-accent, #f59e0b); }
    </style>
</head>
<body class="bg-style-{{ $page->background_style }} min-h-screen antialiased layout-{{ $page->layout_preset }}">

    {{-- ── Top Nav Bar ── --}}
    <nav class="sticky top-0 z-40 bg-slate-900/80 backdrop-blur-md border-b border-slate-700/50">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 flex items-center justify-between h-14">
            <a href="{{ url('/') }}" class="flex items-center space-x-1 text-lg font-bold hover:opacity-80 transition">
                <span>U9</span><span class="p13-accent">itus</span>
            </a>
            <div class="flex items-center gap-3">
                @auth
                    <a href="{{ route('dashboard') }}" class="text-sm text-slate-300 hover:text-white transition">Dashboard</a>
                @else
                    <a href="{{ route('register.voter') }}" class="p13-btn-primary text-xs font-semibold px-4 py-2 rounded-lg transition">
                        Earn Money Watching
                    </a>
                    <a href="{{ route('login') }}" class="text-sm text-slate-300 hover:text-white transition">Sign In</a>
                @endauth
            </div>
        </div>
    </nav>

    {{-- ── Hero Section ── --}}
    <section class="relative overflow-hidden">
        @if($page->hero_banner_url && $page->background_style === 'image')
            <div class="absolute inset-0 bg-cover bg-center opacity-25 pointer-events-none"
                 style="background-image:url('{{ $page->hero_banner_url }}')"></div>
        @endif
        <div class="relative max-w-5xl mx-auto px-4 sm:px-6 py-16 sm:py-20">
            <div class="hero-card flex flex-col sm:flex-row items-start gap-8 bg-slate-800/40 border border-slate-700/40 p-8">
                {{-- Avatar --}}
                <div class="flex-shrink-0">
                    @if($politician->profile_photo_url)
                        <img src="{{ $politician->profile_photo_url }}" alt="{{ $politician->full_name }}"
                             class="w-28 h-28 rounded-full ring-4 object-cover"
                             style="--tw-ring-color: var(--p13-accent, #f59e0b)" />
                    @else
                        <div class="w-28 h-28 rounded-full flex items-center justify-center text-4xl font-bold text-white"
                             style="background-color:var(--p13-primary,#1e40af)">
                            {{ strtoupper(substr($politician->full_name, 0, 1)) }}
                        </div>
                    @endif
                </div>

                {{-- Identity --}}
                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2 mb-1">
                        <h1 class="text-3xl font-extrabold text-white">{{ $politician->full_name }}</h1>
                        @if($politician->verified_official)
                            <span class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1 rounded-full bg-emerald-500/15 text-emerald-400 border border-emerald-500/20">
                                ✓ Verified Official
                            </span>
                        @endif
                    </div>

                    <p class="text-base font-medium p13-accent mb-1">
                        {{ $politician->political_office }}
                        @if($politician->governance_level)
                            · {{ ucfirst(str_replace('_', ' ', $politician->governance_level)) }}
                        @endif
                    </p>

                    @if($politician->city || $politician->state)
                        <p class="text-sm text-slate-400 mb-3">
                            📍 {{ implode(', ', array_filter([$politician->city, $politician->state])) }}
                            @if($politician->party_affiliation)
                                · {{ $politician->party_affiliation }}
                            @endif
                        </p>
                    @endif

                    {{-- Custom CTA or default voter sign-up --}}
                    @if($page->custom_cta_text && $page->custom_cta_url)
                        <a href="{{ $page->custom_cta_url }}" target="_blank" rel="noopener"
                           class="p13-btn-primary inline-flex items-center gap-2 text-sm font-semibold px-5 py-2.5 rounded-lg transition">
                            {{ $page->custom_cta_text }} →
                        </a>
                    @else
                        <a href="{{ route('register.voter') }}"
                           class="p13-btn-primary inline-flex items-center gap-2 text-sm font-semibold px-5 py-2.5 rounded-lg transition">
                            Watch &amp; Earn $0.25 per Ad →
                        </a>
                    @endif

                    @if($politician->website_url)
                        <a href="{{ $politician->website_url }}" target="_blank" rel="noopener"
                           class="ml-3 text-sm text-slate-400 hover:text-white transition underline">
                            Website ↗
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </section>

    {{-- ── Main Content ── --}}
    <main class="max-w-5xl mx-auto px-4 sm:px-6 pb-24 space-y-12">

        {{-- Bio Section --}}
        @if($page->show_bio && $politician->bio)
        <section>
            <h2 class="text-xl font-bold text-white mb-4 flex items-center gap-2">
                <span class="w-1 h-6 rounded-full inline-block p13-border-accent" style="background:var(--p13-accent,#f59e0b)"></span>
                About {{ $politician->full_name }}
            </h2>
            <div class="bg-slate-800/40 border border-slate-700/40 rounded-xl p-6">
                <p class="text-slate-300 leading-relaxed whitespace-pre-line">{{ $politician->bio }}</p>
            </div>
        </section>
        @endif

        {{-- Initiatives / Platform Section --}}
        @if($page->show_initiatives && $initiatives->isNotEmpty())
        <section>
            <h2 class="text-xl font-bold text-white mb-4 flex items-center gap-2">
                <span class="w-1 h-6 rounded-full inline-block" style="background:var(--p13-accent,#f59e0b)"></span>
                Platform &amp; Policy Positions
            </h2>
            <div class="grid sm:grid-cols-2 gap-4">
                @foreach($initiatives as $initiative)
                <div class="bg-slate-800/40 border border-slate-700/40 rounded-xl p-5 hover:border-slate-500 transition">
                    @if($initiative->icon)
                        <div class="text-2xl mb-3">{{ $initiative->icon }}</div>
                    @endif
                    <h3 class="font-semibold text-white mb-2">{{ $initiative->title }}</h3>
                    @if($initiative->description)
                        <p class="text-sm text-slate-400 leading-relaxed">{{ $initiative->description }}</p>
                    @endif
                </div>
                @endforeach
            </div>
        </section>
        @endif

        {{-- Active Campaigns Section (Phase 13 — video embeds + platform CTA) --}}
        @if($page->show_campaigns && $campaigns->isNotEmpty())
        <section>
            <div class="flex items-end justify-between mb-4">
                <h2 class="text-xl font-bold text-white flex items-center gap-2">
                    <span class="w-1 h-6 rounded-full inline-block" style="background:var(--p13-accent,#f59e0b)"></span>
                    Campaign Videos
                </h2>
                <span class="text-xs text-slate-400">
                    {{ $campaigns->count() }} active {{ \Illuminate\Support\Str::plural('message', $campaigns->count()) }}
                </span>
            </div>

            {{-- Integrated U9itus platform pitch --}}
            <div class="mb-6 flex items-center gap-4 bg-emerald-500/10 border border-emerald-500/20 rounded-xl px-5 py-4">
                <div class="text-2xl flex-shrink-0">💰</div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-emerald-400">Earn $0.25 every time you watch a message like these</p>
                    <p class="text-xs text-slate-400 mt-0.5">U9itus pays voters real money for watching political ads in full. Free to join, no card needed.</p>
                </div>
                <a href="{{ route('register.voter') }}"
                   class="flex-shrink-0 bg-emerald-500 hover:bg-emerald-400 text-white text-xs font-bold px-4 py-2 rounded-lg transition whitespace-nowrap shadow-lg">
                    Start Earning →
                </a>
            </div>

            <div class="grid sm:grid-cols-2 gap-6">
                @foreach($campaigns as $campaign)
                @php
                    $_ytId  = null;
                    $_mUrl  = $campaign->media_url ?? '';
                    if      (preg_match('/youtu\.be\/([a-zA-Z0-9_-]+)/', $_mUrl, $_m))    { $_ytId = $_m[1]; }
                    elseif  (preg_match('/[?&]v=([a-zA-Z0-9_-]+)/', $_mUrl, $_m))         { $_ytId = $_m[1]; }
                    elseif  (preg_match('/\/embed\/([a-zA-Z0-9_-]+)/', $_mUrl, $_m))       { $_ytId = $_m[1]; }
                @endphp
                <div class="bg-slate-800/40 border border-slate-700/40 rounded-xl overflow-hidden hover:border-slate-500 transition group">

                    {{-- Video embed (YouTube nocookie) or thumbnail fallback --}}
                    <div class="relative aspect-video bg-black">
                        @if($_ytId)
                            {{-- YouTube privacy-enhanced embed — publicly previewable --}}
                            <iframe
                                src="https://www.youtube-nocookie.com/embed/{{ $_ytId }}?rel=0&modestbranding=1&color=white&iv_load_policy=3"
                                title="{{ e($campaign->title) }}"
                                class="w-full h-full"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                allowfullscreen
                                loading="lazy"
                            ></iframe>
                        @elseif($campaign->thumbnail_url)
                            <img src="{{ $campaign->thumbnail_url }}" alt="{{ $campaign->title }}"
                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" />
                            <div class="absolute inset-0 flex flex-col items-center justify-center bg-black/50">
                                <div class="w-14 h-14 rounded-full bg-white/10 border-2 border-white/40 flex items-center justify-center">
                                    <svg class="w-7 h-7 text-white ml-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                </div>
                                <span class="mt-3 text-xs text-white/70 bg-black/40 px-3 py-1 rounded-full">Sign up to watch &amp; earn</span>
                            </div>
                        @else
                            <div class="w-full h-full flex flex-col items-center justify-center bg-slate-900/60">
                                <svg class="w-12 h-12 text-slate-600 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                          d="M15 10l4.553-2.853A1 1 0 0121 8.004v7.992a1 1 0 01-1.447.857L15 14M3 8a2 2 0 012-2h8a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"/>
                                </svg>
                                <span class="text-xs text-slate-500">Video · register to earn</span>
                            </div>
                        @endif

                        {{-- Floating earnings badge --}}
                        <div class="absolute top-2 right-2 bg-emerald-500 text-white text-xs font-bold px-2 py-0.5 rounded-full shadow-lg pointer-events-none select-none z-10">
                            Earn $0.25
                        </div>
                    </div>

                    <div class="p-4">
                        <h3 class="font-semibold text-white text-sm mb-1 line-clamp-2">{{ $campaign->title }}</h3>
                        @if($campaign->message_summary)
                            <p class="text-xs text-slate-400 line-clamp-2 mb-3">{{ $campaign->message_summary }}</p>
                        @endif
                        <a href="{{ route('register.voter') }}"
                           class="p13-btn-primary inline-flex items-center justify-center gap-1.5 text-xs font-semibold px-3 py-2 rounded-lg transition w-full">
                            <svg class="w-3.5 h-3.5 flex-shrink-0" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                            Watch on U9itus &amp; Earn $0.25
                        </a>
                    </div>
                </div>
                @endforeach
            </div>

            <p class="mt-5 text-center text-sm text-slate-400">
                <a href="{{ route('register.voter') }}" class="p13-accent hover:underline font-medium">
                    Create a free account to watch all of {{ $politician->full_name }}'s messages and earn real money →
                </a>
            </p>
        </section>
        @endif

        {{-- Phase 16: Public Records & Transparency --}}
        @if(!empty($transparencyData) && $politician->verification_status === 'verified')
        <section>
            <div class="flex items-start justify-between mb-4">
                <div>
                    <h2 class="text-xl font-bold text-white flex items-center gap-2">
                        <span class="w-1 h-6 rounded-full inline-block" style="background:var(--p13-accent,#f59e0b)"></span>
                        Public Records & Transparency
                    </h2>
                    <p class="text-xs text-slate-400 mt-1">Official data from trusted public sources</p>
                </div>
                <span class="inline-flex items-center gap-1.5 bg-green-900/30 border border-green-700/50 text-green-300 text-xs font-medium px-3 py-1.5 rounded-full">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    Verified Profile
                </span>
            </div>

            <div class="space-y-6">
                @foreach($transparencyData as $source => $data)
                    @if($data)
                    <div class="bg-slate-800/40 border border-slate-700/40 rounded-xl p-6">
                        <div class="flex items-start justify-between mb-4">
                            <h3 class="text-lg font-semibold text-white">{{ $data['source'] }}</h3>
                            @if(isset($data['source_url']))
                            <a href="{{ $data['source_url'] }}" target="_blank" rel="noopener" 
                               class="text-xs text-blue-400 hover:text-blue-300 transition inline-flex items-center gap-1">
                                View on {{ $data['source'] }} ↗
                            </a>
                            @endif
                        </div>

                        {{-- Financial Summary (for OpenSecrets/FEC) --}}
                        @if(isset($data['summary']) && !empty($data['summary']))
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6 p-4 bg-slate-900/40 border border-slate-700/30 rounded-lg">
                            @foreach($data['summary'] as $key => $value)
                                <div>
                                    <p class="text-xs text-slate-400 mb-1">{{ ucwords(str_replace('_', ' ', $key)) }}</p>
                                    <p class="text-sm font-semibold text-white">{{ $value }}</p>
                                </div>
                            @endforeach
                        </div>
                        @endif

                        {{-- Data Sections --}}
                        @if(isset($data['sections']) && !empty($data['sections']))
                        <div class="space-y-5">
                            @foreach($data['sections'] as $section)
                                @if(!empty($section['items']))
                                <div>
                                    <h4 class="text-sm font-semibold text-slate-300 mb-3 flex items-center justify-between">
                                        <span>{{ $section['title'] }}</span>
                                        @if(isset($section['show_more_url']))
                                        <a href="{{ $section['show_more_url'] }}" target="_blank" rel="noopener" 
                                           class="text-xs text-blue-400 hover:text-blue-300 transition">
                                            See all ↗
                                        </a>
                                        @endif
                                    </h4>
                                    <div class="space-y-2">
                                        @foreach($section['items'] as $item)
                                        <div class="bg-slate-900/30 border border-slate-700/30 rounded-lg px-4 py-3">
                                            @if(is_array($item))
                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                                                    @foreach($item as $key => $value)
                                                        @if($value && $key !== 'id' && $key !== 'pdf_url' && $key !== 'fec_url')
                                                        <div>
                                                            <span class="text-slate-500">{{ ucwords(str_replace('_', ' ', $key)) }}:</span>
                                                            <span class="text-slate-300 ml-1">{{ $value }}</span>
                                                        </div>
                                                        @endif
                                                    @endforeach
                                                    {{-- PDF/FEC links for filings --}}
                                                    @if(isset($item['pdf_url']) || isset($item['fec_url']))
                                                        <div class="col-span-full mt-1">
                                                            @if(isset($item['pdf_url']))
                                                            <a href="{{ $item['pdf_url'] }}" target="_blank" 
                                                               class="text-blue-400 hover:text-blue-300 text-xs mr-3">
                                                                View PDF ↗
                                                            </a>
                                                            @endif
                                                            @if(isset($item['fec_url']))
                                                            <a href="{{ $item['fec_url'] }}" target="_blank" 
                                                               class="text-blue-400 hover:text-blue-300 text-xs">
                                                                View on FEC ↗
                                                            </a>
                                                            @endif
                                                        </div>
                                                    @endif
                                                </div>
                                            @else
                                                <p class="text-sm text-slate-300">{{ $item }}</p>
                                            @endif
                                        </div>
                                        @endforeach
                                    </div>
                                </div>
                                @endif
                            @endforeach
                        </div>
                        @endif
                    </div>
                    @endif
                @endforeach
            </div>

            <div class="mt-4 bg-blue-900/20 border border-blue-700/30 rounded-lg p-4">
                <p class="text-xs text-slate-400">
                    <strong class="text-slate-300">Data Attribution:</strong> All information above is sourced from public government databases and independent watchdog organizations. Click the source links to verify data directly.
                </p>
            </div>
        </section>
        @endif

        {{-- Contact / Connect Section --}}
        @if($page->show_contact && ($politician->website_url || $politician->city))
        <section>
            <h2 class="text-xl font-bold text-white mb-4 flex items-center gap-2">
                <span class="w-1 h-6 rounded-full inline-block" style="background:var(--p13-accent,#f59e0b)"></span>
                Connect
            </h2>
            <div class="bg-slate-800/40 border border-slate-700/40 rounded-xl p-6 flex flex-wrap gap-4 items-center">
                @if($politician->website_url)
                    <a href="{{ $politician->website_url }}" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-2 text-sm font-medium text-slate-300 hover:text-white transition">
                        🌐 Official Website ↗
                    </a>
                @endif
                @if($politician->district)
                    <span class="text-sm text-slate-400">🗳️ District: {{ $politician->district }}</span>
                @endif
                @if($politician->governance_level)
                    <span class="text-sm text-slate-400">🏛️ {{ ucfirst(str_replace('_', ' ', $politician->governance_level)) }}</span>
                @endif
            </div>
        </section>
        @endif

    </main>

    {{-- ── Footer ── --}}
    <footer class="border-t border-slate-800 py-8 text-center text-sm text-slate-500">
        <p>
            <a href="{{ url('/') }}" class="font-bold text-slate-300 hover:text-white transition">
                U9<span class="p13-accent">itus</span>
            </a>
            — Political Loyalty Ads Platform
        </p>
        <p class="mt-1">
            <a href="{{ route('register.voter') }}" class="hover:text-slate-300 transition">Sign up as a Voter</a>
            ·
            <a href="{{ route('register.politician') }}" class="hover:text-slate-300 transition">Register as a Politician</a>
        </p>
    </footer>

    {{-- ── Sticky Earn Bar (unauthenticated visitors only) ── --}}
    @guest
    <div id="earn-bar"
         class="fixed bottom-0 inset-x-0 z-50 shadow-2xl"
         style="background:linear-gradient(90deg,#059669,#10b981);transform:translateY(120%);transition:transform .45s cubic-bezier(.4,0,.2,1)">
        <div class="max-w-5xl mx-auto px-4 py-3 flex items-center gap-3">
            <span class="text-xl flex-shrink-0 hidden sm:block">💰</span>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-bold text-white leading-tight">Get paid $0.25 to watch political ads like these</p>
                <p class="text-xs text-emerald-100 hidden sm:block">U9itus is free to join. No credit card. Start earning today.</p>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                <a href="{{ route('register.voter') }}"
                   class="bg-white text-emerald-700 font-bold text-xs sm:text-sm px-4 py-2 rounded-lg hover:bg-emerald-50 transition whitespace-nowrap shadow-md">
                    Create Free Account
                </a>
                <button onclick="var b=document.getElementById('earn-bar');b.style.transform='translateY(120%)';b.setAttribute('data-closed','1')"
                        class="text-white/60 hover:text-white transition p-1.5 rounded flex-shrink-0"
                        aria-label="Dismiss">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>
    <script>
        (function() {
            setTimeout(function() {
                var b = document.getElementById('earn-bar');
                if (b && !b.getAttribute('data-closed')) {
                    b.style.transform = 'translateY(0)';
                }
            }, 3000);
        })();
    </script>
    @endguest

</body>
</html>
