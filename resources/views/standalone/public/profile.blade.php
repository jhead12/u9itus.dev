<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $ogTitle }} — {{ config('app.name', 'U9itus') }}</title>
    @include('standalone.partials.seo-head')

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

    {{-- Canonical URL --}}
    <link rel="canonical" href="{{ $ogUrl }}">

    {{-- Schema.org structured data — helps Google understand the page is about a politician --}}
    <script type="application/ld+json">
    {
        "@@context": "https://schema.org",
        "@@type": "Person",
        "name": "{{ addslashes($politician->full_name) }}",
        "url": "{{ $ogUrl }}",
        "description": "{{ addslashes($ogDescription) }}",
        "jobTitle": "{{ addslashes($politician->political_office ?? '') }}",
        "worksFor": {
            "@@type": "GovernmentOrganization",
            "name": "{{ addslashes(($politician->state ?? '') . ($politician->city ? ', ' . $politician->city : '')) }}"
        }
        @if($ogImage)
        ,"image": "{{ $ogImage }}"
        @endif
        @if($politician->website_url)
        ,"sameAs": ["{{ $politician->website_url }}"]
        @endif
    }
    </script>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800&display=swap" rel="stylesheet" />

    {{-- Vite assets --}}
    @if(file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/earn-cta/app.js'])
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

    @php
        $viewer = auth()->user();
        $viewerVoter = $viewer?->user_type === 'voter' ? $viewer->voter : null;
        $viewerReferralCode = $viewerVoter?->referral_code;
        $isUnverifiedProfile = $politician->verification_status !== 'verified';
        $showReferralShareModal = $viewerVoter && $viewerReferralCode && $isUnverifiedProfile;
        $referralProfileShareUrl = $showReferralShareModal
            ? route('politician.public.show', ['slug' => $politician->slug, 'ref' => $viewerReferralCode])
            : null;
        $referralPoliticianSignupUrl = $showReferralShareModal
            ? route('register.politician', ['ref' => $viewerReferralCode])
            : null;
        // Load admin-editable share copy from the templates table (with fallbacks).
        $profileShareTpl = $showReferralShareModal
            ? \App\Models\EmailTemplate::forKey('referral_profile_share')
            : null;
        $tplBindings = [
            '{{politician.name}}' => $politician->full_name,
            '{{referral_code}}'   => $viewerReferralCode ?? '',
            '{{referral_link}}'   => $referralProfileShareUrl ?? '',
            '{{platform_name}}'   => config('app.name', 'U9itus'),
        ];
        $shareSubject = $showReferralShareModal
            ? ($profileShareTpl && $profileShareTpl->is_active && $profileShareTpl->subject_override
                ? $profileShareTpl->effectiveShareTitle("Take a look at {$politician->full_name} on U9itus")
                : "Take a look at {$politician->full_name} on U9itus")
            : null;
        $socialShareMessage = $showReferralShareModal
            ? ($profileShareTpl && $profileShareTpl->is_active && $profileShareTpl->body_override
                ? $profileShareTpl->effectiveShareMessage(
                    "Take a look at {$politician->full_name}'s U9itus profile. If you join or claim the page, please use my referral link.",
                    $tplBindings
                  )
                : "Take a look at {$politician->full_name}'s U9itus profile. If you join or claim the page, please use my referral link.")
            : null;
        $shareBody = $showReferralShareModal
            ? "{$socialShareMessage}\n\nProfile:\n{$referralProfileShareUrl}\n\nDirect politician signup:\n{$referralPoliticianSignupUrl}"
            : null;
        $emailShareUrl = $showReferralShareModal
            ? 'mailto:?subject=' . rawurlencode($shareSubject) . '&body=' . rawurlencode($shareBody)
            : null;
        $xShareUrl = $showReferralShareModal
            ? 'https://twitter.com/intent/tweet?text=' . rawurlencode($socialShareMessage) . '&url=' . rawurlencode($referralProfileShareUrl)
            : null;
        $facebookShareUrl = $showReferralShareModal
            ? 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($referralProfileShareUrl) . '&quote=' . rawurlencode($socialShareMessage)
            : null;
        $whatsAppShareUrl = $showReferralShareModal
            ? 'https://api.whatsapp.com/send?text=' . rawurlencode($socialShareMessage . ' ' . $referralProfileShareUrl)
            : null;
        $telegramShareUrl = $showReferralShareModal
            ? 'https://t.me/share/url?url=' . rawurlencode($referralProfileShareUrl) . '&text=' . rawurlencode($socialShareMessage)
            : null;
    @endphp

    {{-- ── Top Nav Bar ── --}}
    @unless($embed ?? false)
    <nav class="sticky top-0 z-40 bg-slate-900/80 backdrop-blur-md border-b border-slate-700/50">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 flex items-center justify-between h-14">
            <div class="flex items-center gap-4">
                <a href="{{ url('/') }}" class="flex items-center space-x-1 text-lg font-bold hover:opacity-80 transition">
                    <span class="text-white">U9</span><span class="text-emerald-400">itus</span>
                </a>
                <a href="{{ route('politicians.directory') }}"
                   class="hidden sm:inline-flex items-center gap-1.5 text-sm text-slate-400 hover:text-white transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Browse Politicians
                </a>
            </div>
            <div class="flex items-center gap-3">
                {{-- Mobile back link --}}
                <a href="{{ route('politicians.directory') }}"
                   class="sm:hidden text-slate-400 hover:text-white transition"
                   aria-label="Browse Politicians">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                @auth
                    <a href="{{ route('dashboard') }}" class="text-sm text-slate-300 hover:text-white transition">Dashboard</a>
                @else
                    @if (config('platform.map.voter_features_enabled') && config('platform.map.sign_in_cta'))
                        @php
                            $u9LoginUrl = url('https://www.early-bank.com') . '?' . http_build_query(array_filter([
                                'ref'  => request()->query('ref'),
                                'from' => 'profile',
                            ]));
                        @endphp
                        <a id="btn-earn-cta" href="{{ $u9LoginUrl }}"
                           title="Get paid to watch campaign videos — up to $0.50 each"
                           class="hidden sm:inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold text-emerald-400 bg-emerald-500/10 border border-emerald-400/40 hover:bg-emerald-500/20 transition">
                            Earn $$$ to share
                        </a>
                    @endif
                    <a href="{{ route('register.voter') }}" class="p13-btn-primary text-xs font-semibold px-4 py-2 rounded-lg transition">
                        Create Free Account
                    </a>
                    <a href="{{ route('login') }}" class="text-sm text-slate-300 hover:text-white transition">Sign In</a>
                @endauth
            </div>
        </div>
    </nav>
    @endunless

    {{-- ── Unclaimed Profile Banner (minimizable, preference remembered) ── --}}
    @if(is_null($politician->user_id))
    <div x-data="{ minimized: localStorage.getItem('u9itus_unclaimed_banner_minimized') === '1' }"
         class="sticky top-14 z-30 border-b border-amber-500/30 bg-amber-950/70 backdrop-blur-md"
         role="alert" aria-label="Unclaimed profile notice">

        {{-- Minimized: slim single-line bar --}}
        <div x-show="minimized" x-cloak class="max-w-5xl mx-auto px-4 sm:px-6 py-1.5 flex items-center justify-between gap-3">
            <button type="button"
                    @click="minimized = false; localStorage.setItem('u9itus_unclaimed_banner_minimized', '0')"
                    class="flex items-center gap-1.5 text-xs font-semibold text-amber-200 hover:text-amber-100 transition min-w-0">
                <span class="text-amber-400" aria-hidden="true">⚠</span>
                <span class="truncate">Unclaimed profile</span>
                <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </button>
            @unless(session('claim_submitted'))
            <a href="{{ route('politician.profile.claim.show', $politician->slug) }}"
               class="flex-shrink-0 text-xs font-bold px-3 py-1 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-900 transition whitespace-nowrap">
                Claim
            </a>
            @endunless
        </div>

        {{-- Full banner --}}
        <div x-show="!minimized" class="max-w-5xl mx-auto px-4 sm:px-6 py-3 flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-6">
            <div class="flex items-start gap-3 flex-1 min-w-0">
                <span class="text-amber-400 text-xl leading-none flex-shrink-0 mt-0.5" aria-hidden="true">⚠</span>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-amber-200 leading-snug">
                        This profile is auto-generated from public records and has not been claimed.
                    </p>
                    <p class="text-xs text-amber-200/70 mt-0.5">
                        Are you {{ $politician->full_name }} or a member of their campaign team? Verify your identity and take control of this page.
                    </p>
                    @if(session('claim_submitted'))
                        <p class="text-xs font-semibold text-emerald-300 mt-1">
                            ✓ Verification email sent! Check your inbox and click the link to continue.
                        </p>
                    @endif
                    @error('claim')
                        <p class="text-xs font-semibold text-red-300 mt-1">{{ $message }}</p>
                    @enderror
                </div>
            </div>
            <div class="flex-shrink-0 flex items-center gap-2">
                @unless(session('claim_submitted'))
                <a href="{{ route('politician.profile.claim.show', $politician->slug) }}"
                   class="inline-flex items-center gap-1.5 text-xs font-bold px-4 py-2 rounded-lg
                          bg-amber-500 hover:bg-amber-400 text-slate-900 transition whitespace-nowrap">
                    🏛 Claim This Profile
                </a>
                @endunless
                <button type="button"
                        @click="minimized = true; localStorage.setItem('u9itus_unclaimed_banner_minimized', '1')"
                        class="p-1.5 rounded-lg text-amber-300/70 hover:text-amber-100 hover:bg-amber-900/40 transition"
                        aria-label="Minimize this notice">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"/></svg>
                </button>
            </div>
        </div>
    </div>
    @endif

    @if($showReferralShareModal)
    <section id="referral-share-toolbar"
             class="sticky top-14 z-30 border-b border-emerald-500/20 bg-slate-950/92 backdrop-blur-md shadow-lg shadow-black/20">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 py-3 space-y-3">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-emerald-300">Referral Toolbar</p>
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1">
                        <p class="text-sm font-semibold text-white">Share this profile without leaving the page</p>
                        <p class="text-xs text-slate-400">Code: <span class="font-mono text-emerald-300">{{ $viewerReferralCode }}</span></p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button type="button"
                            id="toolbar-copy-referral-link"
                            data-link="{{ $referralProfileShareUrl }}"
                            class="p13-btn-primary text-xs font-semibold px-3 py-2 rounded-lg whitespace-nowrap">
                        Copy Link
                    </button>
                    <button type="button"
                            id="toolbar-native-share-referral-link"
                            data-link="{{ $referralProfileShareUrl }}"
                            data-title="{{ $shareSubject }}"
                            data-text="{{ $socialShareMessage }}"
                            class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-600 text-slate-200 hover:text-white hover:border-slate-500 transition text-xs font-medium">
                        Share
                    </button>
                    <a href="{{ $emailShareUrl }}"
                       class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-sky-500/30 bg-sky-500/10 text-sky-200 hover:text-sky-100 transition text-xs font-medium whitespace-nowrap">
                        Email Draft
                    </a>
                    <button type="button"
                            id="toggle-referral-toolbar-details"
                            aria-expanded="false"
                            aria-controls="referral-toolbar-details"
                            class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-amber-500/30 bg-amber-500/10 text-amber-200 hover:text-amber-100 transition text-xs font-medium whitespace-nowrap">
                        More Options
                    </button>
                </div>
            </div>

            <p id="toolbar-copy-status" class="text-xs text-emerald-300 hidden">Referral link copied to clipboard.</p>

            <div id="referral-toolbar-details" class="hidden rounded-2xl border border-slate-800 bg-slate-900/80 p-4 space-y-4">
                <p class="text-sm text-slate-300 leading-relaxed">
                    If someone signs up from your shared link, their registration can be attributed to you.
                    This includes campaign teams and congressional candidates who claim this profile.
                </p>

                <div class="rounded-xl border border-slate-700/70 bg-slate-800/60 px-4 py-3">
                    <p class="text-xs uppercase tracking-wide text-slate-400 mb-1">Preloaded Share Message</p>
                    <p class="text-sm text-slate-200 leading-relaxed">{{ $socialShareMessage }}</p>
                </div>

                <div>
                    <label for="referral-profile-link" class="block text-xs font-semibold uppercase tracking-wide text-slate-400 mb-1.5">
                        Profile Link With Your Code
                    </label>
                    <div class="flex items-center gap-2">
                        <input id="referral-profile-link"
                               type="text"
                               readonly
                               value="{{ $referralProfileShareUrl }}"
                               class="w-full bg-slate-800/80 border border-slate-700 text-slate-200 text-sm rounded-lg px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" />
                        <a href="{{ $referralPoliticianSignupUrl }}"
                           class="inline-flex items-center gap-2 px-3 py-2.5 rounded-lg border border-amber-500/30 bg-amber-500/10 text-amber-200 hover:text-amber-100 transition text-xs font-medium whitespace-nowrap">
                            Politician Signup
                        </a>
                    </div>
                </div>

                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-2">Social Share Shortcuts</p>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ $xShareUrl }}" target="_blank" rel="noopener noreferrer"
                           class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-600 text-slate-200 hover:text-white hover:border-slate-500 transition text-sm font-medium">
                            X
                        </a>
                        <a href="{{ $facebookShareUrl }}" target="_blank" rel="noopener noreferrer"
                           class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-blue-500/30 bg-blue-500/10 text-blue-200 hover:text-blue-100 transition text-sm font-medium">
                            Facebook
                        </a>
                        <a href="{{ $whatsAppShareUrl }}" target="_blank" rel="noopener noreferrer"
                           class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-emerald-500/30 bg-emerald-500/10 text-emerald-200 hover:text-emerald-100 transition text-sm font-medium">
                            WhatsApp
                        </a>
                        <a href="{{ $telegramShareUrl }}" target="_blank" rel="noopener noreferrer"
                           class="inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-cyan-500/30 bg-cyan-500/10 text-cyan-200 hover:text-cyan-100 transition text-sm font-medium">
                            Telegram
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>
    @endif

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
                        @if(is_null($politician->user_id))
                            <span class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1 rounded-full bg-amber-500/15 text-amber-300 border border-amber-500/20">
                                Unclaimed Profile
                            </span>
                        @endif
                        @if(in_array($politician->term_status ?? 'unknown', ['retired', 'lost']))
                            <span class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1 rounded-full bg-slate-700/40 text-slate-400 border border-slate-600/40">
                                {{ $politician->term_status === 'lost' ? 'Former Candidate — Lost Election' : 'Former Member' }}
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
                        <p class="text-sm text-slate-400 mb-1">
                            📍 {{ implode(', ', array_filter([$politician->city, $politician->state])) }}
                            @if($politician->district)
                                · {{ $politician->district }}
                            @endif
                            @if($politician->party_affiliation)
                                · {{ $politician->party_affiliation }}
                            @endif
                        </p>
                    @endif

                    @if($birthDate)
                        @php
                            try {
                                $parsedBirthDate = \Carbon\Carbon::parse($birthDate);
                            } catch (\Throwable $e) {
                                $parsedBirthDate = null;
                            }
                        @endphp
                        @if($parsedBirthDate)
                            <p class="text-sm text-slate-400 mb-1">
                                🎂 Born {{ $parsedBirthDate->format('F j, Y') }} · Age {{ $parsedBirthDate->age }}
                            </p>
                        @endif
                    @endif

                    {{-- ── View on Map deep-link ─────────────────────────────────
                         Builds /map?state=CA&district=33&slug=slug-here so the 3D
                         map flies directly to this politician's state, selects their
                         congressional district (when applicable), and auto-opens
                         their candidate card in the panel. The `district` column
                         only means "congressional district number" for federal
                         (House) politicians — for state/county/local politicians it
                         holds unrelated values like a judicial "Seat 2", which is
                         NOT a congressional district and must not be sent as one. --}}
                    @if($politician->state)
                        @php
                            $mapParams = array_filter([
                                'state'    => $politician->state,
                                // Extract numeric district from formats like "CA-33", "33", "District 33"
                                // — only valid for federal (House) politicians, see note above.
                                'district' => (strtolower((string) $politician->governance_level) === 'federal' && $politician->district)
                                    ? preg_replace('/[^0-9]/', '', $politician->district) ?: null
                                    : null,
                                'slug'     => $politician->slug,
                            ]);
                            $mapDeepLink = url('/map') . '?' . http_build_query($mapParams);
                        @endphp
                        <a href="{{ $mapDeepLink }}"
                           class="inline-flex items-center gap-1.5 text-xs font-semibold
                                  text-indigo-300 hover:text-indigo-200
                                  border border-indigo-500/30 hover:border-indigo-400/60
                                  bg-indigo-500/10 hover:bg-indigo-500/20
                                  rounded-full px-3 py-1 transition mb-2"
                           title="Open U9itus 3D map and fly to {{ $politician->full_name }}'s district">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                                 aria-hidden="true">
                                <polygon points="3 11 22 2 13 21 11 13 3 11"/>
                            </svg>
                            View on Map
                        </a>
                    @endif

                    {{-- ── Follow this candidate ──────────────────────────────────
                         Reuses the same favorite-toggle partial as Causes/Ballot
                         Measures. Guests get a CTA to sign in instead of a button —
                         there's no guest-cookie path for politician follows (unlike
                         map boundary favorites). --}}
                    <div class="mb-2">
                        @auth
                            @if(auth()->user()->voter)
                                @include('standalone.voter.partials.favorite-toggle', [
                                    'isFavorited' => $isFavorited,
                                    'storeRoute' => route('voter.favorites.store', $politician->id),
                                    'destroyRoute' => route('voter.favorites.destroy', $politician->id),
                                    'followLabel' => 'Follow',
                                    'followingLabel' => 'Following',
                                ])
                            @endif
                        @else
                            <a href="{{ route('register.voter') }}"
                               class="inline-flex items-center gap-1.5 text-xs font-semibold
                                      text-emerald-300 hover:text-emerald-200
                                      border border-emerald-500/30 hover:border-emerald-400/60
                                      bg-emerald-500/10 hover:bg-emerald-500/20
                                      rounded-full px-3 py-1 transition">
                                Sign in to follow this candidate
                            </a>
                        @endauth
                    </div>

                    {{-- ── Issue-context badge chips ──────────────────────────────
                         Derived from publicBadges topics (self-declared + inferred
                         discourse badges). Each chip links to the directory filtered
                         by that topic's structured slug (?topic=…). --}}
                    @if(isset($issueContextTags) && $issueContextTags->isNotEmpty())
                        <div class="flex flex-wrap items-center gap-1.5 mb-2" data-issue-tags>
                            <span class="text-[10px] font-semibold uppercase tracking-wider text-slate-400 mr-0.5">Issues</span>
                            @foreach($issueContextTags as $tag)
                                <a href="{{ route('politicians.directory', ['topic' => $tag['slug']]) }}"
                                   class="inline-flex items-center gap-x-1 rounded-full px-2.5 py-0.5 text-[10px] font-semibold border transition-all hover:brightness-125 focus:outline-none focus:ring-2 focus:ring-offset-1"
                                   style="color:{{ $tag['color'] }};border-color:{{ $tag['color'] }}40;background-color:{{ $tag['color'] }}1a;--tw-ring-color:{{ $tag['color'] }};"
                                   title="Browse candidates focused on {{ $tag['name'] }}"
                                   data-issue-tag="{{ $tag['slug'] }}">
                                    @if(!empty($tag['icon']))
                                        <span aria-hidden="true">{{ $tag['icon'] }}</span>
                                    @else
                                        <svg class="h-1.5 w-1.5 flex-shrink-0" viewBox="0 0 6 6" aria-hidden="true" style="fill:{{ $tag['color'] }};"><circle cx="3" cy="3" r="3"/></svg>
                                    @endif
                                    {{ $tag['name'] }}
                                </a>
                            @endforeach
                        </div>
                    @endif

                    @if($termInfo)
                        @php
                            $termEndDate = $termInfo['end'] ? \Carbon\Carbon::parse($termInfo['end']) : null;
                            $isServing = $termEndDate && $termEndDate->isFuture();
                        @endphp
                        <p class="text-sm mb-3">
                            @if($isServing)
                                <span class="inline-flex items-center gap-1 text-emerald-400">
                                    🏛️ Currently Serving
                                </span>
                                <span class="text-slate-400">
                                    · Term: {{ \Carbon\Carbon::parse($termInfo['start'])->format('M Y') }} – {{ $termEndDate->format('M Y') }}
                                </span>
                            @elseif($termEndDate)
                                <span class="text-slate-400">
                                    🏛️ Served through {{ $termEndDate->format('M Y') }}
                                </span>
                            @endif
                        </p>
                    @endif

                    @if(!empty($electionDates))
                        <p class="text-sm mb-3 flex flex-wrap items-center gap-x-3 gap-y-1">
                            @foreach($electionDates as $stage)
                                @if($stage['election_date_formatted'])
                                    <span class="text-emerald-400">🗳️ {{ $stage['stage_name'] }}: {{ $stage['election_date_formatted'] }}</span>
                                @endif
                                @if($stage['filing_deadline_formatted'])
                                    <span class="text-slate-400">📋 {{ $stage['stage_name'] }} filing deadline: {{ $stage['filing_deadline_formatted'] }}</span>
                                @endif
                            @endforeach
                        </p>
                    @endif

                    @if(is_null($politician->user_id))
                        <p class="text-xs text-amber-200/90 mb-3">
                            This public profile is currently unclaimed and generated from public records. Verified campaign staff can claim and manage it after registration.
                        </p>
                    @endif

                    {{-- Custom CTA or default voter sign-up --}}
                    @if($page->custom_cta_text && $page->custom_cta_url)
                        <a href="{{ $page->custom_cta_url }}" target="_blank" rel="noopener"
                           class="p13-btn-primary inline-flex items-center gap-2 text-sm font-semibold px-5 py-2.5 rounded-lg transition">
                            {{ $page->custom_cta_text }} →
                        </a>
                    @else
                        @auth
                        <a href="{{ route('dashboard') }}"
                           class="p13-btn-primary inline-flex items-center gap-2 text-sm font-semibold px-5 py-2.5 rounded-lg transition">
                            Open Dashboard →
                        </a>
                        @else
                        <a href="{{ route('register.voter') }}"
                           @click="window.u9GuestNudge && window.u9GuestNudge.trigger($event)"
                           class="p13-btn-primary inline-flex items-center gap-2 text-sm font-semibold px-5 py-2.5 rounded-lg transition">
                            Create Free Account to Watch on U9itus →
                        </a>
                        @endauth
                    @endif

                    @php
                        $publicWebsiteUrl = $politician->website_url;
                        $publicWebsiteHost = strtolower((string) parse_url((string) $publicWebsiteUrl, PHP_URL_HOST));
                        $isUnsafeApiWebsite = $publicWebsiteHost === 'api.congress.gov';
                    @endphp
                    @if($publicWebsiteUrl && ! $isUnsafeApiWebsite)
                        <a href="{{ $publicWebsiteUrl }}" target="_blank" rel="noopener"
                           class="ml-3 text-sm text-slate-400 hover:text-white transition underline">
                            Website ↗
                        </a>
                    @elseif($publicWebsiteUrl && $isUnsafeApiWebsite)
                        <span class="ml-3 inline-flex items-center gap-1 text-sm text-slate-500 border border-slate-700/60 rounded-md px-2.5 py-1 cursor-not-allowed"
                              title="Website unavailable">
                            Website unavailable
                        </span>
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

        {{-- ── Favorite Songs ─────────────────────────────────────────
             Streaming-service embeds curated by the politician. We never
             host audio; each iframe loads from the official service so
             licensing/royalties flow normally. Section hides itself if
             the politician hasn't added any active picks.            --}}
        @php
            $songPicks = $politician->songPicks;
        @endphp
        @if($songPicks->isNotEmpty())
        <section x-data="{ open: false, activeIndex: null }">
            <button type="button"
                    @@click="open = !open"
                    :aria-expanded="open.toString()"
                    class="w-full flex items-center justify-between gap-3 mb-4 group">
                <h2 class="text-xl font-bold text-white flex items-center gap-2">
                    <span class="w-1 h-6 rounded-full inline-block" style="background:var(--p13-accent,#f59e0b)"></span>
                    <span aria-hidden="true">🎵</span>
                    {{ $politician->full_name }}'s Favorite Songs
                    <span class="text-xs font-medium text-slate-400 ml-1">({{ $songPicks->count() }})</span>
                </h2>
                <svg class="w-5 h-5 text-slate-400 transition-transform"
                     :class="open ? 'rotate-180' : ''"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <div x-show="open" x-collapse class="space-y-3">
                @foreach($songPicks as $i => $pick)
                <article class="bg-slate-800/40 border border-slate-700/40 rounded-xl p-4">
                    <header class="flex items-start justify-between gap-3 mb-3">
                        <div class="min-w-0">
                            <p class="font-semibold text-white truncate">
                                {{ $pick->track_title ?: 'Untitled track' }}
                            </p>
                            @if($pick->artist_name)
                                <p class="text-sm text-slate-400 truncate">{{ $pick->artist_name }}</p>
                            @endif
                        </div>
                        <span class="shrink-0 text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-md {{ $pick->service === 'spotify' ? 'bg-green-500/10 border border-green-500/30 text-green-300' : ($pick->service === 'apple' ? 'bg-pink-500/10 border border-pink-500/30 text-pink-300' : 'bg-red-500/10 border border-red-500/30 text-red-300') }}">
                            {{ $pick->service }}
                        </span>
                    </header>
                    @if($pick->note)
                        <blockquote class="border-l-2 border-indigo-500/50 pl-3 mb-3
                                          text-sm text-slate-400 italic leading-relaxed">
                            {{ $pick->note }}
                        </blockquote>
                    @endif
                    {{-- Lazy-load: only mount the iframe when user clicks the track row. --}}
                    @if($pick->is_explicit)
                        <p class="text-xs text-amber-400 mb-2">⚠ Contains explicit content</p>
                    @endif
                    <button type="button"
                            @@click="activeIndex === {{ $i }} ? activeIndex = null : activeIndex = {{ $i }}"
                            class="text-xs text-indigo-400 hover:text-indigo-300 font-medium">
                        <span x-show="activeIndex !== {{ $i }}">▶ Play preview</span>
                        <span x-show="activeIndex === {{ $i }}" x-cloak>Hide player</span>
                    </button>
                    <template x-if="activeIndex === {{ $i }}">
                        <div class="mt-3">
                            <iframe src="{{ $pick->embedUrl() }}"
                                    width="100%" height="{{ $pick->embedHeight() }}"
                                    frameborder="0"
                                    allow="{{ $pick->embedAllow() }}"
                                    loading="lazy"
                                    referrerpolicy="strict-origin-when-cross-origin"
                                    sandbox="allow-scripts allow-same-origin allow-popups allow-presentation"
                                    title="{{ $pick->track_title ?: 'Song preview' }}"
                                    class="rounded-lg overflow-hidden bg-slate-950"></iframe>
                        </div>
                    </template>
                </article>
                @endforeach
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

        {{-- Campaigns Section (running + past campaign archive) --}}
        @if($page->show_campaigns && ($runningCampaigns->isNotEmpty() || $pastCampaigns->isNotEmpty()))
        <section>
            <div class="flex items-end justify-between mb-4">
                <h2 class="text-xl font-bold text-white flex items-center gap-2">
                    <span class="w-1 h-6 rounded-full inline-block" style="background:var(--p13-accent,#f59e0b)"></span>
                    Campaign Videos &amp; Updates
                </h2>
                <span class="text-xs text-slate-400">
                    {{ $runningCampaigns->count() + $pastCampaigns->count() }} public {{ \Illuminate\Support\Str::plural('campaign', $runningCampaigns->count() + $pastCampaigns->count()) }}
                </span>
            </div>

            {{-- Public preview context --}}
            <div class="mb-6 flex items-center gap-4 bg-emerald-500/10 border border-emerald-500/20 rounded-xl px-5 py-4">
                <div class="text-2xl flex-shrink-0">👁️</div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-emerald-400">Guest preview mode</p>
                    <p class="text-xs text-slate-400 mt-0.5">Guests can browse current and past public campaign videos here to learn how this candidate is communicating over time.</p>
                </div>
                <a href="{{ route('register.voter') }}"
                   @click="window.u9GuestNudge && window.u9GuestNudge.trigger($event)"
                   class="flex-shrink-0 bg-emerald-500 hover:bg-emerald-400 text-white text-xs font-bold px-4 py-2 rounded-lg transition whitespace-nowrap shadow-lg">
                    Create Free Account →
                </a>
            </div>

            @if($runningCampaigns->isNotEmpty())
            <div class="mb-8">
                <div class="flex items-center justify-between gap-3 mb-4">
                    <div>
                        <h3 class="text-lg font-semibold text-white">Running Campaigns</h3>
                        <p class="text-xs text-slate-400 mt-1">Current campaign messages, live issues, and recent public-facing updates.</p>
                    </div>
                    <span class="text-xs text-slate-400">{{ $runningCampaigns->count() }} live now</span>
                </div>
                <div class="grid sm:grid-cols-2 gap-6">
                    @foreach($runningCampaigns as $campaign)
                        @include('standalone.public.partials.campaign-preview-card', ['campaign' => $campaign])
                    @endforeach
                </div>
            </div>
            @endif

            @if($pastCampaigns->isNotEmpty())
            <div>
                <div class="flex items-center justify-between gap-3 mb-4">
                    <div>
                        <h3 class="text-lg font-semibold text-white">Past Campaigns</h3>
                        <p class="text-xs text-slate-400 mt-1">Archived videos and previous campaign updates so voters can review the record over time.</p>
                    </div>
                    <span class="text-xs text-slate-400">{{ $pastCampaigns->count() }} in archive</span>
                </div>
                <div class="grid sm:grid-cols-2 gap-6">
                    @foreach($pastCampaigns as $campaign)
                        @include('standalone.public.partials.campaign-preview-card', ['campaign' => $campaign])
                    @endforeach
                </div>
            </div>
            @endif

            <p class="mt-5 text-center text-sm text-slate-400">
                <a href="{{ auth()->check() ? route('dashboard') : route('register.voter') }}"
                   @if(! auth()->check()) @click="window.u9GuestNudge && window.u9GuestNudge.trigger($event)" @endif
                   class="p13-accent hover:underline font-medium">
                    {{ auth()->check() ? 'Return to your dashboard to continue inside U9itus →' : 'Create a free account to follow candidates, save your place, and continue inside U9itus →' }}
                </a>
            </p>
        </section>
        @endif

        {{-- Public Q&A Board Section --}}
        @if($publicBoardQuestions->isNotEmpty())
        @php
            $qaHeading = (bool) config('u9itus.q_and_a.use_public_board_heading', false)
                ? (string) config('u9itus.q_and_a.public_heading_label', 'Public Q&A Board')
                : (string) config('u9itus.q_and_a.legacy_heading_label', 'Answered Questions');
        @endphp
        <section>
            <div class="flex items-end justify-between mb-4">
                <h2 class="text-xl font-bold text-white flex items-center gap-2">
                    <span class="w-1 h-6 rounded-full inline-block" style="background:var(--p13-accent,#f59e0b)"></span>
                    {{ $qaHeading }}
                </h2>
                <span class="text-xs text-slate-400">{{ $publicBoardQuestions->count() }} published questions</span>
            </div>

            <p class="text-xs text-slate-400 mb-4">Moderated voter questions with official campaign replies.</p>

            <div class="space-y-4">
                @foreach($publicBoardQuestions as $entry)
                    <article class="bg-slate-800/40 border border-slate-700/40 rounded-xl p-5">
                        <div class="flex items-center justify-between gap-3 mb-2">
                            <p class="text-xs text-slate-400">Campaign: {{ $entry->campaign->title ?? 'Campaign' }}</p>
                            <p class="text-xs text-slate-400">Published {{ optional($entry->published_at ?? $entry->updated_at)->format('M j, Y') }}</p>
                        </div>

                        <div class="rounded-lg border border-slate-700/50 bg-slate-900/40 px-4 py-3 mb-3">
                            <p class="text-[11px] uppercase tracking-wide text-slate-400 mb-1">Voter Question</p>
                            <p class="text-xs text-slate-400 mb-2">{{ $entry->public_alias ?: 'Verified Voter' }}</p>
                            <p class="text-sm text-slate-200 leading-relaxed whitespace-pre-line">{{ $entry->body }}</p>

                            @if($entry->hasReference())
                                <div class="mt-3 rounded-md border border-sky-500/25 bg-sky-500/10 px-3 py-2">
                                    <p class="text-[11px] uppercase tracking-wide text-sky-300 mb-1">Referenced Clip</p>
                                    <a href="{{ $entry->reference_url }}" target="_blank" rel="noopener"
                                       class="text-xs text-sky-100 hover:text-white underline break-all">{{ $entry->referencePlatformLabel() }} Link ↗</a>
                                    @if($entry->referenceTimeRangeLabel())
                                        <p class="text-[11px] text-sky-200/90 mt-1">Time: {{ $entry->referenceTimeRangeLabel() }}</p>
                                    @endif
                                    @if(!empty($entry->reference_note))
                                        <p class="text-[11px] text-sky-100/90 mt-1">Context: {{ $entry->reference_note }}</p>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <div class="rounded-lg border border-emerald-500/25 bg-emerald-500/10 px-4 py-3">
                            <p class="text-[11px] uppercase tracking-wide text-emerald-300 mb-1">Campaign Response</p>
                            <p class="text-sm text-emerald-100 leading-relaxed whitespace-pre-line">{{ $entry->campaign_reply ?: $entry->admin_notes }}</p>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
        @endif

        {{-- Blog Posts Section --}}
        @if($posts->isNotEmpty())
        <section>
            <div class="flex items-end justify-between mb-4">
                <h2 class="text-xl font-bold text-white flex items-center gap-2">
                    <span class="w-1 h-6 rounded-full inline-block" style="background:var(--p13-accent,#f59e0b)"></span>
                    Latest Posts
                </h2>
                <a href="{{ route('blog.author', ['type' => 'politician', 'slug' => $politician->slug]) }}" class="text-sm text-amber-400 hover:text-amber-300">
                    View all →
                </a>
            </div>
            <div class="grid sm:grid-cols-2 gap-4">
                @foreach($posts as $post)
                <article class="bg-slate-800/40 border border-slate-700/40 rounded-xl p-5 hover:border-slate-500 transition">
                    @if($post->featured_image_url)
                    <a href="{{ route('blog.show', $post) }}" class="block mb-3">
                        <img src="{{ $post->featured_image_url }}" alt="{{ $post->title }}" class="w-full h-32 object-cover rounded-lg" loading="lazy" />
                    </a>
                    @endif
                    <h3 class="font-semibold text-white mb-2">
                        <a href="{{ route('blog.show', $post) }}" class="hover:text-amber-400 transition">{{ $post->title }}</a>
                    </h3>
                    @if($post->excerpt)
                    <p class="text-sm text-slate-400 line-clamp-2">{{ $post->excerpt }}</p>
                    @endif
                    <p class="mt-3 text-xs text-slate-400">{{ $post->published_at->format('M j, Y') }}</p>
                </article>
                @endforeach
            </div>
        </section>
        @endif

        {{-- Phase 16: Public Records & Transparency --}}
        @if(!empty($transparencyData))
        <section>
            <div class="flex items-start justify-between mb-4">
                <div>
                    <h2 class="text-xl font-bold text-white flex items-center gap-2">
                        <span class="w-1 h-6 rounded-full inline-block" style="background:var(--p13-accent,#f59e0b)"></span>
                        Public Records & Transparency
                    </h2>
                    <p class="text-xs text-slate-400 mt-1">Official data from trusted public sources</p>
                </div>
                @if($politician->verification_status === 'verified')
                <span class="inline-flex items-center gap-1.5 bg-green-900/30 border border-green-700/50 text-green-300 text-xs font-medium px-3 py-1.5 rounded-full">
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    Verified Profile
                </span>
                @else
                <span class="inline-flex items-center gap-1.5 bg-slate-800/60 border border-slate-700/50 text-slate-400 text-xs font-medium px-3 py-1.5 rounded-full">
                    Public Record Data
                </span>
                @endif
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
                            @foreach($data['sections'] as $sectionKey => $section)
                                {{-- outside_spending gets its own formatted "Independent Spending" block
                                     below (currency + Support/Oppose badges) — rendering it here too would
                                     dump the raw FEC fields (unformatted totals, bare 'S'/'O' codes). --}}
                                @if($sectionKey !== 'outside_spending' && !empty($section['items']))
                                <div>
                                    <h4 class="text-sm font-semibold text-slate-300 mb-3 flex items-center justify-between">
                                        <span>{{ $section['title'] ?? ucwords(str_replace('_', ' ', is_string($sectionKey) ? $sectionKey : '')) }}</span>
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
                                                            <span class="text-slate-400">{{ ucwords(str_replace('_', ' ', $key)) }}:</span>
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

        {{-- Sprint 4: Dig Deeper research section
             (Sprint 7: also shown when only meToken data is present) --}}
        @if(!empty($digDeeperData['panels'] ?? []) || !empty($meTokenData ?? null))
        <section id="dig-deeper">
            <div class="flex items-end justify-between gap-4 mb-4">
                <div>
                    <h2 class="text-xl font-bold text-white flex items-center gap-2">
                        <span class="w-1 h-6 rounded-full inline-block" style="background:var(--p13-accent,#f59e0b)"></span>
                        Dig Deeper
                    </h2>
                    <p class="text-xs text-slate-400 mt-1">
                        Quick source snapshots with direct links to underlying public records.
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-slate-400">Sources available</p>
                    <p class="text-sm font-semibold text-white">
                        {{ $digDeeperData['available_sources_count'] ?? 0 }} / {{ $digDeeperData['enabled_sources_count'] ?? 0 }}
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach(($digDeeperData['panels'] ?? []) as $panel)
                <article class="bg-slate-800/40 border border-slate-700/40 rounded-xl p-5">
                    <div class="flex items-start justify-between gap-3 mb-3">
                        <h3 class="text-base font-semibold text-white">{{ $panel['label'] }}</h3>
                        @if(($panel['status'] ?? null) === 'available')
                            <span class="inline-flex items-center gap-1 text-[11px] bg-emerald-900/30 border border-emerald-700/50 text-emerald-300 px-2 py-1 rounded-full">Available</span>
                        @else
                            <span class="inline-flex items-center gap-1 text-[11px] bg-amber-900/30 border border-amber-700/50 text-amber-300 px-2 py-1 rounded-full">Unavailable</span>
                        @endif
                    </div>

                    @if(($panel['status'] ?? null) === 'available')
                        <p class="text-sm text-slate-300 mb-3">{{ $panel['summary'] ?? 'Source connected' }}</p>
                        <p class="text-xs text-slate-400 mb-3">{{ $panel['section_count'] ?? 0 }} detail panel(s) available.</p>

                        @if(!empty($panel['sections'] ?? []))
                        <details class="group rounded-lg border border-slate-700/30 bg-slate-900/35 px-4 py-3">
                            <summary class="cursor-pointer text-xs font-semibold text-slate-300 group-open:text-white transition">
                                View source detail panels
                            </summary>
                            <div class="mt-3 space-y-2">
                                @foreach(($panel['sections'] ?? []) as $section)
                                    @if(!empty($section['title']))
                                        <div class="text-xs text-slate-400">{{ $section['title'] }}</div>
                                    @endif
                                @endforeach
                            </div>
                        </details>
                        @endif

                        @if(!empty($panel['source_url'] ?? null))
                        <a href="{{ $panel['source_url'] }}" target="_blank" rel="noopener"
                           class="mt-3 inline-flex items-center gap-1.5 text-xs font-medium text-blue-400 hover:text-blue-300 transition">
                            View source records ↗
                        </a>
                        @endif
                    @else
                        <p class="text-sm text-slate-400">{{ $panel['unavailable_reason'] ?? 'No data available yet.' }}</p>
                    @endif
                </article>
                @endforeach

                {{-- Sprint 7 — MeToken read-only transparency panel --}}
                @if(!empty($meTokenData ?? null))
                    @include('standalone.public.partials.metoken-panel', ['data' => $meTokenData])
                @endif
            </div>
        </section>
        @endif

        {{-- Videos & Appearances Section --}}
        @php
            $storedVideos   = $politician->video_links ?? [];
            $polNameEncoded = rawurlencode($politician->full_name);

            // Helper: extract YouTube video ID from watch or short URL
            $ytIdOf = function(string $url): ?string {
                if (preg_match('/(?:youtube\.com\/watch\?(?:[^&]*&)*v=|youtu\.be\/)([A-Za-z0-9_\-]{11})/', $url, $m)) {
                    return $m[1];
                }
                return null;
            };

            $youtubeVideos = array_filter($storedVideos ?? [], fn($v) => $ytIdOf($v['url'] ?? '') !== null);
            $cspanVideos   = array_filter($storedVideos ?? [], fn($v) => str_contains($v['url'] ?? '', 'c-span.org'));
        @endphp
        <section>
            <h2 class="text-xl font-bold text-white mb-4 flex items-center gap-2">
                <span class="w-1 h-6 rounded-full inline-block" style="background:var(--p13-accent,#f59e0b)"></span>
                Videos &amp; Appearances
            </h2>

            {{-- Top This Week: real engagement-ranked clips (PoliticianViralMoment) --}}
            @if(isset($topWeeklyMoments) && $topWeeklyMoments->isNotEmpty())
            <div class="mb-6">
                <div class="flex items-center gap-2 mb-3">
                    <span class="text-lg" aria-hidden="true">🔥</span>
                    <h3 class="text-xs font-semibold text-white uppercase tracking-wide">Top This Week</h3>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @foreach($topWeeklyMoments as $moment)
                    <div class="bg-slate-800/40 border border-slate-700/40 rounded-xl overflow-hidden">
                        @if($moment->source === 'youtube')
                        <div class="aspect-video">
                            <iframe
                                src="https://www.youtube-nocookie.com/embed/{{ $moment->source_id }}"
                                title="{{ e($moment->title) }}"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                allowfullscreen
                                class="w-full h-full border-0">
                            </iframe>
                        </div>
                        @else
                        <a href="{{ $moment->url }}" target="_blank" rel="noopener"
                           class="block relative aspect-video bg-slate-900 group">
                            @if($moment->thumbnail_url)
                            <img src="{{ $moment->thumbnail_url }}" alt="{{ e($moment->title) }}" class="w-full h-full object-cover" />
                            @else
                            <div class="w-full h-full flex items-center justify-center text-4xl" aria-hidden="true">📺</div>
                            @endif
                            <span class="absolute inset-0 flex items-center justify-center bg-black/30 opacity-0 group-hover:opacity-100 transition">
                                <span class="text-white text-xs font-semibold">Watch on {{ ucfirst($moment->source) }} ↗</span>
                            </span>
                        </a>
                        @endif
                        <div class="px-3 py-2">
                            <p class="text-xs text-slate-300 line-clamp-2">{{ $moment->title }}</p>
                            <p class="text-[10px] text-slate-400 mt-1 flex items-center gap-2">
                                <span class="uppercase tracking-wide">{{ $moment->source }}</span>
                                @if($moment->view_count)
                                <span>&middot; {{ number_format($moment->view_count) }} views</span>
                                @endif
                            </p>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- Stored YouTube embeds --}}
            @if(!empty($youtubeVideos))
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                @foreach($youtubeVideos as $vid)
                @php $ytId = $ytIdOf($vid['url']); @endphp
                <div class="bg-slate-800/40 border border-slate-700/40 rounded-xl overflow-hidden">
                    <div class="aspect-video">
                        <iframe
                            src="https://www.youtube-nocookie.com/embed/{{ $ytId }}"
                            title="{{ e($vid['title'] ?? 'Campaign Video') }}"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                            allowfullscreen
                            class="w-full h-full border-0">
                        </iframe>
                    </div>
                    @if(!empty($vid['title']))
                    <p class="px-3 py-2 text-xs text-slate-400">{{ $vid['title'] }}</p>
                    @endif
                </div>
                @endforeach
            </div>
            @endif

            {{-- Stored C-SPAN links --}}
            @if(!empty($cspanVideos))
            <div class="space-y-2 mb-4">
                @foreach($cspanVideos as $vid)
                <a href="{{ $vid['url'] }}" target="_blank" rel="noopener"
                   class="flex items-center gap-3 bg-slate-800/40 border border-slate-700/40 rounded-xl px-4 py-3 hover:border-slate-600/60 transition">
                    <span class="text-lg">📺</span>
                    <span class="text-sm text-slate-300 hover:text-white transition">{{ $vid['title'] ?? 'C-SPAN Appearance' }} ↗</span>
                </a>
                @endforeach
            </div>
            @endif

            {{-- Always-present search fallbacks --}}
            <div class="bg-slate-800/40 border border-slate-700/40 rounded-xl p-4 flex flex-wrap gap-3 items-center">
                <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">Find more on:</span>
                <a href="https://www.youtube.com/results?search_query={{ $polNameEncoded }}+speech+interview"
                   target="_blank" rel="noopener"
                   class="inline-flex items-center gap-1.5 text-xs font-medium bg-red-900/30 border border-red-700/40 text-red-300 hover:text-red-200 rounded-lg px-3 py-1.5 transition">
                    ▶ YouTube Search ↗
                </a>
                @if(!empty($cspanVideos))
                <a href="https://www.c-span.org/search/?searchtype=Videos&query={{ $polNameEncoded }}"
                   target="_blank" rel="noopener"
                   class="inline-flex items-center gap-1.5 text-xs font-medium bg-blue-900/30 border border-blue-700/40 text-blue-300 hover:text-blue-200 rounded-lg px-3 py-1.5 transition">
                    📺 C-SPAN Search ↗
                </a>
                @endif
            </div>
        </section>

        @php
            $researchLinks = [];
            if (!empty($transparencyData['votesmart']['source_url'] ?? null)) {
                $researchLinks[] = ['label' => 'Vote Smart Voting Record', 'url' => $transparencyData['votesmart']['source_url']];
            }
            if (!empty($transparencyData['fec']['source_url'] ?? null)) {
                $researchLinks[] = ['label' => 'FEC Filings', 'url' => $transparencyData['fec']['source_url']];
            }
            if (!empty($transparencyData['opensecrets']['source_url'] ?? null)) {
                $researchLinks[] = ['label' => 'OpenSecrets Funding', 'url' => $transparencyData['opensecrets']['source_url']];
            }
            if (!empty($transparencyData['ballotpedia']['source_url'] ?? null)) {
                $researchLinks[] = ['label' => 'Ballotpedia Profile', 'url' => $transparencyData['ballotpedia']['source_url']];
            }
            if (!empty($politician->wikipedia_url)) {
                $researchLinks[] = ['label' => 'Wikipedia Article', 'url' => $politician->wikipedia_url];
            } elseif (!empty($politician->full_name)) {
                $researchLinks[] = ['label' => 'Wikipedia Search', 'url' => 'https://en.wikipedia.org/w/index.php?search=' . rawurlencode($politician->full_name)];
            }
            if (!empty($politician->full_name)) {
                $researchLinks[] = ['label' => 'C-SPAN Video Search', 'url' => 'https://www.c-span.org/search/?searchtype=Videos&query=' . rawurlencode($politician->full_name)];
            }
        @endphp

        {{-- Contact / Connect Section --}}
        @if($page->show_contact && ($publicWebsiteUrl || $politician->city))
        <section>
            <h2 class="text-xl font-bold text-white mb-4 flex items-center gap-2">
                <span class="w-1 h-6 rounded-full inline-block" style="background:var(--p13-accent,#f59e0b)"></span>
                Connect
            </h2>
            <div class="bg-slate-800/40 border border-slate-700/40 rounded-xl p-6 flex flex-wrap gap-4 items-center">
                @if($publicWebsiteUrl && ! $isUnsafeApiWebsite)
                    <a href="{{ $publicWebsiteUrl }}" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-2 text-sm font-medium text-slate-300 hover:text-white transition">
                        🌐 Official Website ↗
                    </a>
                @elseif($publicWebsiteUrl && $isUnsafeApiWebsite)
                    <span class="inline-flex items-center gap-2 text-sm font-medium text-slate-500 border border-slate-700/60 rounded-md px-3 py-1.5 cursor-not-allowed"
                          title="Website unavailable">
                        🌐 Website unavailable
                    </span>
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

        {{-- ── In the News ─────────────────────────────────────────────────── --}}
        {{-- $newsArticles (6 items), $newsTotal passed from controller --}}
        @if($newsArticles->isNotEmpty())
        <section>
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <h2 class="text-xl font-bold text-white flex items-center gap-2">
                    <span class="w-1 h-6 rounded-full inline-block flex-shrink-0" style="background:var(--p13-accent,#f59e0b)"></span>
                    In the News
                </h2>
                @if($newsTotal > 6)
                <a href="{{ route('politician.public.news', $politician->slug) }}"
                   class="text-xs text-emerald-400 hover:text-emerald-300 transition font-medium">
                    View all {{ number_format($newsTotal) }} articles →
                </a>
                @endif
            </div>

            @php
                $newsHero  = $newsArticles->first();
                $newsThumbs = $newsArticles->skip(1)->values();
            @endphp

            {{-- Breaking story — hero card --}}
            <a href="{{ $newsHero->source_url }}" target="_blank" rel="noopener noreferrer"
               class="group block rounded-2xl overflow-hidden border border-slate-700/50 hover:border-slate-600 bg-slate-800/40 transition mb-4">
                @if($newsHero->image_url)
                <div class="relative w-full h-48 bg-slate-700 overflow-hidden">
                    <img src="{{ $newsHero->image_url }}" alt=""
                         class="w-full h-full object-cover group-hover:scale-105 transition duration-500"
                         loading="lazy"
                         onerror="this.parentElement.style.display='none'">
                    <div class="absolute inset-0 bg-gradient-to-t from-slate-900/80 to-transparent"></div>
                    <span class="absolute top-3 left-3 text-xs font-bold uppercase tracking-wider px-2.5 py-1 rounded-full text-white"
                          style="background:var(--p13-accent,#f59e0b);color:#0f172a">Breaking Now</span>
                </div>
                @else
                <div class="w-full h-16 flex items-center gap-3 px-5"
                     style="background:linear-gradient(135deg,var(--p13-primary,#1e40af),var(--p13-accent,#f59e0b))">
                    <span class="text-xs font-bold uppercase tracking-wider text-white/80">Breaking Now</span>
                </div>
                @endif
                <div class="p-4">
                    <p class="text-base font-semibold text-white group-hover:text-emerald-300 line-clamp-2 leading-snug transition">
                        {{ $newsHero->headline }}
                    </p>
                    <p class="mt-1.5 text-xs text-slate-400 flex items-center gap-2">
                        @if($newsHero->source_name)
                            <span class="font-medium text-slate-400">{{ $newsHero->source_name }}</span>
                            <span>·</span>
                        @endif
                        <span>{{ $newsHero->published_at?->diffForHumans() }}</span>
                    </p>
                    @if($newsHero->snippet)
                    <p class="mt-1.5 text-xs text-slate-400 line-clamp-2">{{ $newsHero->snippet }}</p>
                    @endif
                </div>
            </a>

            {{-- Thumbnail grid — remaining 5 --}}
            @if($newsThumbs->isNotEmpty())
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach($newsThumbs as $article)
                <a href="{{ $article->source_url }}" target="_blank" rel="noopener noreferrer"
                   class="group flex gap-3 bg-slate-800/40 border border-slate-700/40 hover:border-slate-600/60 rounded-xl p-3 transition">
                    @if($article->image_url)
                    <img src="{{ $article->image_url }}" alt=""
                         class="w-16 h-14 object-cover rounded-lg flex-shrink-0 bg-slate-700"
                         loading="lazy"
                         onerror="this.style.display='none'">
                    @else
                    <div class="w-16 h-14 rounded-lg flex-shrink-0 flex items-center justify-center text-xl"
                         style="background:linear-gradient(135deg,var(--p13-primary,#1e40af)33,var(--p13-accent,#f59e0b)33)">
                        📰
                    </div>
                    @endif
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-medium text-slate-200 group-hover:text-white line-clamp-2 leading-snug transition">
                            {{ $article->headline }}
                        </p>
                        <p class="mt-1 text-xs text-slate-400 truncate">
                            {{ $article->source_name ? $article->source_name . ' · ' : '' }}{{ $article->published_at?->diffForHumans() }}
                        </p>
                    </div>
                </a>
                @endforeach
            </div>
            @endif

            {{-- View more footer --}}
            @if($newsTotal > 6)
            <div class="mt-4 text-center">
                <a href="{{ route('politician.public.news', $politician->slug) }}"
                   class="inline-flex items-center gap-2 text-sm font-semibold px-5 py-2.5 rounded-xl border transition
                          border-slate-700 text-slate-300 hover:border-slate-500 hover:text-white bg-slate-800/50 hover:bg-slate-800">
                    Explore the full archive
                    <span class="text-xs text-slate-400">{{ number_format($newsTotal) }} articles</span>
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                    </svg>
                </a>
            </div>
            @endif
        </section>
        @endif

        {{-- ── Endorsements (news-detected, e.g. "Governor Endorsed") ──────── --}}
        @if($endorsements->isNotEmpty())
        <section>
            <h2 class="text-xl font-bold text-white mb-4 flex items-center gap-2">
                <span class="w-1 h-6 rounded-full inline-block" style="background:var(--p13-accent,#f59e0b)"></span>
                Endorsements
            </h2>
            <div class="flex flex-wrap gap-2">
                @foreach($endorsements as $endorsement)
                    @php $endorsementHref = $endorsement->source_url; @endphp
                    @if($endorsementHref)
                        <a href="{{ $endorsementHref }}" target="_blank" rel="noopener"
                           class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1.5 rounded-full border border-emerald-500/30 bg-emerald-500/10 text-emerald-200 hover:bg-emerald-500/20">
                            {{ $endorsement->label }} Endorsed
                        </a>
                    @else
                        <span class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1.5 rounded-full border border-emerald-500/30 bg-emerald-500/10 text-emerald-200">
                            {{ $endorsement->label }} Endorsed
                        </span>
                    @endif
                @endforeach
            </div>
        </section>
        @endif

        {{-- ── Follow the Money (OpenSecrets / FEC donor data) ────────────── --}}
        @php
            $donorData          = $transparencyData['opensecrets'] ?? null;
            $fecData            = $transparencyData['fec'] ?? null;
            $topDonors          = $donorData['sections']['top_contributors']['items'] ?? [];
            $topIndustries      = $donorData['sections']['top_industries']['items'] ?? [];
            $fecSummary         = $fecData['sections']['summary'] ?? null;
            $openSecretsSummary = $donorData['sections']['summary'] ?? null;
            $outsideSpending    = $fecData['sections']['outside_spending']['items'] ?? null;
            $pacAffiliations    = $donorData['pac_affiliations'] ?? null;
            $electionCycle      = $donorData['election_cycle'] ?? $fecSummary['cycle'] ?? null;

            // Stored finance values are pre-formatted strings ("$1,234,567").
            // Render those as-is; format bare numerics. Avoids the prior bug
            // where number_format("$52,000") cast to 52 and rendered "$52".
            $fmtMoney = function ($v) {
                if ($v === null || $v === '') {
                    return null;
                }
                $s = trim((string) $v);
                return str_starts_with($s, '$') ? $s : '$' . number_format((float) $s);
            };
        @endphp
        @if(!empty($topDonors) || !empty($topIndustries) || $fecSummary || $openSecretsSummary || !empty($outsideSpending) || !empty($pacAffiliations))
        <section>
            <h2 class="text-xl font-bold text-white mb-4 flex items-center gap-2">
                <span class="w-1 h-6 rounded-full inline-block" style="background:var(--p13-accent,#f59e0b)"></span>
                Follow the Money
                @if(!empty($electionCycle))
                    <span class="text-xs font-medium text-slate-400 ml-1">{{ $electionCycle }} cycle</span>
                @endif
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                {{-- PAC affiliation chips (high-signal "who funds them") --}}
                @if(!empty($pacAffiliations))
                <div class="sm:col-span-2 bg-slate-800/40 border border-slate-700/40 rounded-xl p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-3">
                        Known PAC Affiliations
                        @if(!empty($donorData['source_url']))
                            · <a href="{{ $donorData['source_url'] }}" target="_blank" rel="noopener" class="text-emerald-400 hover:underline">OpenSecrets ↗</a>
                        @endif
                    </p>
                    <div class="flex flex-wrap gap-2">
                        @foreach($pacAffiliations as $match)
                            <span class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1.5 rounded-full border border-amber-500/30 bg-amber-500/10 text-amber-200">
                                {{ $match['label'] ?? $match['group'] ?? 'PAC' }}
                                @if(!empty($match['matched_name']))
                                    <span class="text-amber-100/70">· {{ $match['matched_name'] }}</span>
                                @endif
                                @if($fmtMoney($match['total'] ?? null))
                                    <span class="text-amber-100/70 tabular-nums">{{ $fmtMoney($match['total']) }}</span>
                                @endif
                            </span>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- FEC totals banner --}}
                @if($fecSummary)
                <div class="sm:col-span-2 bg-slate-800/40 border border-slate-700/40 rounded-xl p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-3">
                        FEC Filing Summary
                        <span class="inline-flex items-center justify-center w-3.5 h-3.5 rounded-full border border-slate-600 text-slate-400 text-[9px] font-bold normal-case tracking-normal align-middle cursor-help"
                              tabindex="0"
                              aria-label="Total Raised is all money the campaign has brought in this cycle (contributions, loans, transfers). Total Spent is money the campaign has paid out. Cash on Hand is what's left to spend right now — Total Raised minus Total Spent minus any refunds. Debt Owed is outstanding loans or unpaid bills the campaign still owes, separate from cash on hand."
                              title="Total Raised is all money the campaign has brought in this cycle (contributions, loans, transfers). Total Spent is money the campaign has paid out. Cash on Hand is what's left to spend right now — Total Raised minus Total Spent minus any refunds. Debt Owed is outstanding loans or unpaid bills the campaign still owes, separate from cash on hand.">i</span>
                        @if(!empty($fecData['source_url']))
                            · <a href="{{ $fecData['source_url'] }}" target="_blank" rel="noopener" class="text-emerald-400 hover:underline">View on FEC.gov ↗</a>
                        @endif
                    </p>
                    <dl class="flex flex-wrap gap-6">
                        @if($fmtMoney($fecSummary['receipts'] ?? null))
                        <div>
                            <dt class="text-xs text-slate-400">Total Raised</dt>
                            <dd class="text-lg font-bold text-white">{{ $fmtMoney($fecSummary['receipts']) }}</dd>
                        </div>
                        @endif
                        @if($fmtMoney($fecSummary['disbursements'] ?? null))
                        <div>
                            <dt class="text-xs text-slate-400">Total Spent</dt>
                            <dd class="text-lg font-bold text-white">{{ $fmtMoney($fecSummary['disbursements']) }}</dd>
                        </div>
                        @endif
                        @if($fmtMoney($fecSummary['cash_on_hand'] ?? null))
                        <div>
                            <dt class="text-xs text-slate-400">Cash on Hand</dt>
                            <dd class="text-lg font-bold text-emerald-400">{{ $fmtMoney($fecSummary['cash_on_hand']) }}</dd>
                        </div>
                        @endif
                        @if($fmtMoney($fecSummary['debt'] ?? null))
                        <div>
                            <dt class="text-xs text-slate-400">Debt Owed</dt>
                            <dd class="text-lg font-bold text-rose-400">{{ $fmtMoney($fecSummary['debt']) }}</dd>
                        </div>
                        @endif
                    </dl>
                </div>
                @endif

                {{-- OpenSecrets totals banner (primary finance source for non-federal candidates) --}}
                @if($openSecretsSummary)
                <div class="sm:col-span-2 bg-slate-800/40 border border-slate-700/40 rounded-xl p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-3">
                        OpenSecrets Summary
                        <span class="inline-flex items-center justify-center w-3.5 h-3.5 rounded-full border border-slate-600 text-slate-400 text-[9px] font-bold normal-case tracking-normal align-middle cursor-help"
                              tabindex="0"
                              aria-label="Total Raised is all money the campaign has brought in this cycle (contributions, loans, transfers). Total Spent is money the campaign has paid out. Cash on Hand is what's left to spend right now — Total Raised minus Total Spent minus any refunds. Debt Owed is outstanding loans or unpaid bills the campaign still owes, separate from cash on hand."
                              title="Total Raised is all money the campaign has brought in this cycle (contributions, loans, transfers). Total Spent is money the campaign has paid out. Cash on Hand is what's left to spend right now — Total Raised minus Total Spent minus any refunds. Debt Owed is outstanding loans or unpaid bills the campaign still owes, separate from cash on hand.">i</span>
                        @if(!empty($donorData['source_url']))
                            · <a href="{{ $donorData['source_url'] }}" target="_blank" rel="noopener" class="text-emerald-400 hover:underline">View on OpenSecrets ↗</a>
                        @endif
                    </p>
                    <dl class="flex flex-wrap gap-6">
                        @if($fmtMoney($openSecretsSummary['total_raised'] ?? null))
                        <div>
                            <dt class="text-xs text-slate-400">Total Raised</dt>
                            <dd class="text-lg font-bold text-white">{{ $fmtMoney($openSecretsSummary['total_raised']) }}</dd>
                        </div>
                        @endif
                        @if($fmtMoney($openSecretsSummary['total_spent'] ?? null))
                        <div>
                            <dt class="text-xs text-slate-400">Total Spent</dt>
                            <dd class="text-lg font-bold text-white">{{ $fmtMoney($openSecretsSummary['total_spent']) }}</dd>
                        </div>
                        @endif
                        @if($fmtMoney($openSecretsSummary['cash_on_hand'] ?? null))
                        <div>
                            <dt class="text-xs text-slate-400">Cash on Hand</dt>
                            <dd class="text-lg font-bold text-emerald-400">{{ $fmtMoney($openSecretsSummary['cash_on_hand']) }}</dd>
                        </div>
                        @endif
                        @if($fmtMoney($openSecretsSummary['debt'] ?? null))
                        <div>
                            <dt class="text-xs text-slate-400">Debt Owed</dt>
                            <dd class="text-lg font-bold text-rose-400">{{ $fmtMoney($openSecretsSummary['debt']) }}</dd>
                        </div>
                        @endif
                    </dl>
                </div>
                @endif

                {{-- Independent spending (FEC Schedule E — outside groups supporting/opposing) --}}
                @if(!empty($outsideSpending))
                <div class="sm:col-span-2 bg-slate-800/40 border border-slate-700/40 rounded-xl p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-3">
                        Independent Spending
                        <span class="text-slate-400 font-normal normal-case tracking-normal">· outside groups, not the campaign</span>
                        <span class="inline-flex items-center justify-center w-3.5 h-3.5 rounded-full border border-slate-600 text-slate-400 text-[9px] font-bold normal-case tracking-normal align-middle cursor-help"
                              tabindex="0"
                              aria-label="These are outside groups — PACs, Super PACs, party committees — spending their own money on ads and mailers about this race; it is not the candidate's own campaign spending, and the candidate has no say in it. Support means the group is spending to help this candidate win; Oppose means the group is spending to help defeat them. Committee IDs (e.g. C00495028) are unique identifiers the FEC assigns to each committee. Click a committee's name to view its filings on FEC.gov, or click the ID to search Google for more about the PAC."
                              title="These are outside groups — PACs, Super PACs, party committees — spending their own money on ads and mailers about this race; it is not the candidate's own campaign spending, and the candidate has no say in it. Support means the group is spending to help this candidate win; Oppose means the group is spending to help defeat them. Committee IDs (e.g. C00495028) are unique identifiers the FEC assigns to each committee. Click a committee's name to view its filings on FEC.gov, or click the ID to search Google for more about the PAC.">i</span>
                        @if(!empty($fecData['source_url']))
                            · <a href="{{ $fecData['source_url'] }}" target="_blank" rel="noopener" class="text-emerald-400 hover:underline">FEC.gov ↗</a>
                        @endif
                    </p>
                    @php
                        // Show the top spenders; cap the visible list and note how many more.
                        $shownSpending = array_slice($outsideSpending, 0, 12);
                        $hiddenSpending = max(0, count($outsideSpending) - count($shownSpending));
                    @endphp
                    <ol class="space-y-2">
                        @foreach($shownSpending as $i => $spender)
                        @php
                            // Snapshots written before committee_id became its own field only
                            // stored committee_name, which itself held the raw FEC ID whenever
                            // resolveCommitteeNames() couldn't find a real committee name for it.
                            // Recover the ID from committee_name in that case so old snapshots
                            // still render a working link instead of dead plain text.
                            $committeeId = $spender['committee_id'] ?? null;
                            $committeeName = $spender['committee_name'] ?? null;
                            if (empty($committeeId) && $committeeName && preg_match('/^[A-Z]\d{8}$/', $committeeName)) {
                                $committeeId = $committeeName;
                                $committeeName = null;
                            }
                        @endphp
                        <li class="flex items-center justify-between gap-3">
                            <span class="flex items-center gap-2 min-w-0 flex-1">
                                <span class="text-xs text-slate-400 tabular-nums w-4 shrink-0">{{ $i + 1 }}.</span>
                                @if(!empty($committeeId))
                                    @if(!empty($committeeName))
                                        <a href="https://www.fec.gov/data/committee/{{ $committeeId }}/" target="_blank" rel="noopener"
                                           class="text-sm text-slate-200 truncate underline decoration-slate-600 decoration-1 underline-offset-2 hover:text-emerald-400 hover:decoration-emerald-400"
                                           title="View this committee's filings on FEC.gov">{{ $committeeName }}</a>
                                    @endif
                                    <a href="https://www.google.com/search?q={{ urlencode($committeeId) }}" target="_blank" rel="noopener nofollow"
                                       class="shrink-0 text-sm {{ empty($committeeName) ? 'text-slate-200' : 'text-slate-400 text-xs' }} font-mono truncate underline decoration-slate-600 decoration-1 underline-offset-2 hover:text-emerald-400 hover:decoration-emerald-400"
                                       title="Search Google for FEC committee ID {{ $committeeId }}">{{ $committeeId }}</a>
                                @else
                                    <span class="text-sm text-slate-200 truncate">{{ $committeeName ?? '—' }}</span>
                                @endif
                                <span class="shrink-0 text-[10px] font-semibold uppercase tracking-wide px-1.5 py-0.5 rounded-full border {{ ($spender['support_oppose'] ?? '') === 'O'
                                    ? 'border-rose-500/40 bg-rose-500/10 text-rose-300'
                                    : 'border-emerald-500/40 bg-emerald-500/10 text-emerald-300' }}">
                                    {{ ($spender['support_oppose'] ?? '') === 'O' ? 'Oppose' : 'Support' }}
                                </span>
                            </span>
                            @if($fmtMoney($spender['total'] ?? null))
                            <span class="text-sm font-semibold text-white tabular-nums">{{ $fmtMoney($spender['total']) }}</span>
                            @endif
                        </li>
                        @endforeach
                    </ol>
                    @if($hiddenSpending > 0)
                    <p class="mt-3 text-xs text-slate-400">+ {{ $hiddenSpending }} more spender(s) — see FEC.gov for the full list.</p>
                    @endif
                    <p class="mt-3 text-xs text-slate-400">
                        Figures are sums of itemized independent-expenditure filings reported to the FEC for the {{ $electionCycle ?? '' }} cycle; a spender's full total may be higher than shown.
                    </p>
                </div>
                @endif

                {{-- Top individual donors --}}
                @if(!empty($topDonors))
                <div class="bg-slate-800/40 border border-slate-700/40 rounded-xl p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-3">Top Donors</p>
                    <ol class="space-y-2">
                        @foreach(array_slice($topDonors, 0, 5) as $i => $donor)
                        <li class="flex items-center justify-between gap-2">
                            <span class="text-xs text-slate-400 tabular-nums w-4">{{ $i + 1 }}.</span>
                            <span class="text-sm text-slate-200 flex-1 truncate">{{ $donor['name'] ?? '—' }}</span>
                            @if($fmtMoney($donor['total'] ?? null))
                            <span class="text-sm font-semibold text-white tabular-nums">{{ $fmtMoney($donor['total']) }}</span>
                            @endif
                        </li>
                        @endforeach
                    </ol>
                    @if(!empty($donorData['source_url']))
                    <a href="{{ $donorData['source_url'] }}" target="_blank" rel="noopener"
                       class="mt-3 inline-block text-xs text-emerald-400 hover:underline">Full report on OpenSecrets ↗</a>
                    @endif
                </div>
                @endif

                {{-- Top funding industries --}}
                @if(!empty($topIndustries))
                <div class="bg-slate-800/40 border border-slate-700/40 rounded-xl p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-3">Top Funding Industries</p>
                    <ol class="space-y-2">
                        @foreach(array_slice($topIndustries, 0, 5) as $i => $industry)
                        <li class="flex items-center justify-between gap-2">
                            <span class="text-xs text-slate-400 tabular-nums w-4">{{ $i + 1 }}.</span>
                            <span class="text-sm text-slate-200 flex-1 truncate">{{ $industry['industry_name'] ?? $industry['name'] ?? '—' }}</span>
                            @if($fmtMoney($industry['total'] ?? null))
                            <span class="text-sm font-semibold text-white tabular-nums">{{ $fmtMoney($industry['total']) }}</span>
                            @endif
                        </li>
                        @endforeach
                    </ol>
                </div>
                @endif

            </div>
        </section>
        @endif

        @if(!empty($researchLinks))
        <section>
            <div class="border border-slate-700/40 bg-slate-800/30 rounded-xl p-6">
                <h2 class="text-xl font-bold text-white mb-4 flex items-center gap-2">
                    <span class="w-1 h-6 rounded-full inline-block" style="background:var(--p13-accent,#f59e0b)"></span>
                    Research &amp; Records
                </h2>
                <div class="flex flex-wrap gap-3">
                    @foreach($researchLinks as $link)
                        <a href="{{ $link['url'] }}" target="_blank" rel="noopener"
                           class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-300 hover:text-white transition">
                            🔎 {{ $link['label'] }} ↗
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
        @endif

    </main>

    {{-- ── Footer ── --}}
    @unless($embed ?? false)
    <footer class="border-t border-slate-800 py-8 text-center text-sm text-slate-400">
        <p>
            <a href="{{ url('/') }}" class="font-bold text-slate-300 hover:text-white transition">
                <span class="text-white">U9</span><span class="text-emerald-400">itus</span>
            </a>
            — Political Loyalty Ads Platform
        </p>
        <p class="mt-1">
            <a href="{{ route('register.voter') }}" class="hover:text-slate-300 transition">Sign up as a Voter</a>
            ·
            <a href="{{ route('register.politician') }}" class="hover:text-slate-300 transition">Register as a Politician</a>
        </p>
    </footer>
    @endunless

    @if($showReferralShareModal)
    <script>
        (function() {
            var copyButton = document.getElementById('toolbar-copy-referral-link');
            var shareButton = document.getElementById('toolbar-native-share-referral-link');
            var toggleButton = document.getElementById('toggle-referral-toolbar-details');
            var details = document.getElementById('referral-toolbar-details');
            var copyStatus = document.getElementById('toolbar-copy-status');

            if (!copyButton || !shareButton || !toggleButton || !details) {
                return;
            }

            function showCopiedState() {
                copyStatus?.classList.remove('hidden');
                setTimeout(function() {
                    copyStatus?.classList.add('hidden');
                }, 1600);
            }

            copyButton.addEventListener('click', async function() {
                var link = copyButton.getAttribute('data-link');
                if (!link) {
                    return;
                }

                try {
                    if (navigator.clipboard && window.isSecureContext) {
                        await navigator.clipboard.writeText(link);
                    } else {
                        var input = document.getElementById('referral-profile-link');
                        if (input) {
                            input.focus();
                            input.select();
                            document.execCommand('copy');
                        }
                    }

                    showCopiedState();
                } catch (error) {
                    console.error('Failed to copy referral link:', error);
                }
            });

            shareButton.addEventListener('click', async function() {
                var link = shareButton.getAttribute('data-link');
                var title = shareButton.getAttribute('data-title') || 'U9itus Politician Profile';
                var text = shareButton.getAttribute('data-text') || 'Check out this politician profile on U9itus.';
                if (!link) {
                    return;
                }

                if (navigator.share) {
                    try {
                        await navigator.share({ title: title, text: text, url: link });
                        return;
                    } catch (error) {
                        if (error && error.name !== 'AbortError') {
                            console.error('Native share failed:', error);
                        }
                    }
                }

                copyButton.click();
            });

            toggleButton.addEventListener('click', function() {
                var isHidden = details.classList.contains('hidden');
                details.classList.toggle('hidden', !isHidden);
                toggleButton.setAttribute('aria-expanded', isHidden ? 'true' : 'false');
                toggleButton.textContent = isHidden ? 'Hide Options' : 'More Options';
            });
        })();
    </script>
    @endif

    {{-- ── Sticky Guest Mode Bar (unauthenticated visitors only) ── --}}
    @guest
    <div id="earn-bar"
         class="fixed bottom-0 inset-x-0 z-50 shadow-2xl"
         style="background:linear-gradient(90deg,#059669,#10b981);transform:translateY(120%);transition:transform .45s cubic-bezier(.4,0,.2,1)">
        <div class="max-w-5xl mx-auto px-4 py-3 flex items-center gap-3">
            <span class="text-xl flex-shrink-0 hidden sm:block">🔎</span>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-bold text-white leading-tight">You’re browsing in public preview mode</p>
                <p class="text-xs text-emerald-100 hidden sm:block">Research profiles and campaign messages here. Create a free account when you want to continue inside U9itus.</p>
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

    @guest
        <x-guest-signup-nudge />
    @endguest

    @stack('scripts')
</body>
</html>
