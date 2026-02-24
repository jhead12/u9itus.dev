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

        {{-- Active Campaigns Section --}}
        @if($page->show_campaigns && $campaigns->isNotEmpty())
        <section>
            <h2 class="text-xl font-bold text-white mb-4 flex items-center gap-2">
                <span class="w-1 h-6 rounded-full inline-block" style="background:var(--p13-accent,#f59e0b)"></span>
                Active Campaign Messages
            </h2>
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($campaigns as $campaign)
                <div class="bg-slate-800/40 border border-slate-700/40 rounded-xl overflow-hidden hover:border-slate-500 transition group">
                    {{-- Thumbnail --}}
                    @if($campaign->thumbnail_url)
                        <div class="relative aspect-video overflow-hidden">
                            <img src="{{ $campaign->thumbnail_url }}" alt="{{ $campaign->title }}"
                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" />
                            <div class="absolute inset-0 flex items-center justify-center bg-black/40 opacity-0 group-hover:opacity-100 transition">
                                <span class="text-3xl">▶</span>
                            </div>
                        </div>
                    @endif
                    <div class="p-4">
                        <h3 class="font-semibold text-white text-sm mb-1 line-clamp-2">{{ $campaign->title }}</h3>
                        @if($campaign->message_summary)
                            <p class="text-xs text-slate-400 line-clamp-2 mb-3">{{ $campaign->message_summary }}</p>
                        @endif
                        <a href="{{ route('register.voter') }}"
                           class="p13-btn-primary inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-lg transition">
                            Watch &amp; Earn $0.25
                        </a>
                    </div>
                </div>
                @endforeach
            </div>
            <p class="mt-4 text-center text-sm text-slate-400">
                <a href="{{ route('register.voter') }}" class="p13-accent hover:underline font-medium">
                    Create a free account to watch all messages and earn money →
                </a>
            </p>
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

</body>
</html>
