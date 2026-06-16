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
    <nav class="sticky top-0 z-40 bg-slate-900/80 backdrop-blur-md border-b border-slate-700/50">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 flex items-center justify-between h-14">
            <a href="{{ url('/') }}" class="flex items-center space-x-1 text-lg font-bold hover:opacity-80 transition">
                <span class="text-white">U9</span><span class="text-emerald-400">itus</span>
            </a>
            <div class="flex items-center gap-3">
                @auth
                    <a href="{{ route('dashboard') }}" class="text-sm text-slate-300 hover:text-white transition">Dashboard</a>
                @else
                    <a href="{{ route('register.voter') }}" class="p13-btn-primary text-xs font-semibold px-4 py-2 rounded-lg transition">
                        Create Free Account
                    </a>
                    <a href="{{ route('login') }}" class="text-sm text-slate-300 hover:text-white transition">Sign In</a>
                @endauth
            </div>
        </div>
    </nav>

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
                               class="w-full bg-slate-800/80 border border-slate-700 text-slate-200 text-sm rounded-lg px-3 py-2.5 focus:outline-none" />
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
                    <span class="text-xs text-slate-500">{{ $runningCampaigns->count() }} live now</span>
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
                    <span class="text-xs text-slate-500">{{ $pastCampaigns->count() }} in archive</span>
                </div>
                <div class="grid sm:grid-cols-2 gap-6">
                    @foreach($pastCampaigns as $campaign)
                        @include('standalone.public.partials.campaign-preview-card', ['campaign' => $campaign])
                    @endforeach
                </div>
            </div>
            @endif

            <p class="mt-5 text-center text-sm text-slate-400">
                <a href="{{ auth()->check() ? route('dashboard') : route('register.voter') }}" class="p13-accent hover:underline font-medium">
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
                            <p class="text-xs text-slate-500">Campaign: {{ $entry->campaign->title ?? 'Campaign' }}</p>
                            <p class="text-xs text-slate-500">Published {{ optional($entry->published_at ?? $entry->updated_at)->format('M j, Y') }}</p>
                        </div>

                        <div class="rounded-lg border border-slate-700/50 bg-slate-900/40 px-4 py-3 mb-3">
                            <p class="text-[11px] uppercase tracking-wide text-slate-500 mb-1">Voter Question</p>
                            <p class="text-xs text-slate-500 mb-2">{{ $entry->public_alias ?: 'Verified Voter' }}</p>
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

        {{-- Sprint 4: Dig Deeper research section --}}
        @if(!empty($digDeeperData['panels'] ?? []) && $politician->verification_status === 'verified')
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
                        {{ $digDeeperData['available_sources_count'] }} / {{ $digDeeperData['enabled_sources_count'] }}
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
        {{-- $newsArticles, $sourceMap, $activeProviders, $articlesJson passed from controller --}}
        @if($newsArticles->isNotEmpty())
        <section
            x-data="{
                articles: {{ $articlesJson }},
                selected: [],
                get filtered() {
                    if (this.selected.length === 0) return this.articles;
                    return this.articles.filter(a => this.selected.includes(a.provider));
                },
                toggle(id) {
                    if (this.selected.includes(id)) {
                        this.selected = this.selected.filter(s => s !== id);
                    } else {
                        this.selected.push(id);
                    }
                },
                isActive(id) { return this.selected.includes(id); }
            }"
        >
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <h2 class="text-xl font-bold text-white flex items-center gap-2">
                    <span class="w-1 h-6 rounded-full inline-block flex-shrink-0" style="background:var(--p13-accent,#f59e0b)"></span>
                    In the News
                </h2>
                <span class="text-xs text-slate-500">
                    Select sources to filter &nbsp;·&nbsp;
                    <button type="button" @click="selected = []" class="text-emerald-400 hover:underline">Show all</button>
                </span>
            </div>

            {{-- Source-picker pills --}}
            <div class="flex flex-wrap gap-2 mb-5">
                {{-- "All" shortcut --}}
                <button
                    type="button"
                    @click="selected = []"
                    :class="selected.length === 0
                        ? 'bg-emerald-600 border-emerald-500 text-white'
                        : 'bg-slate-800/60 border-slate-700/60 text-slate-400 hover:border-slate-600 hover:text-white'"
                    class="inline-flex items-center gap-1.5 text-xs font-medium border rounded-full px-3 py-1 transition">
                    All
                </button>

                @foreach($activeProviders as $providerId)
                @php
                    $srcMeta = $sourceMap[$providerId] ?? ['label' => $providerId, 'icon' => '📰'];
                @endphp
                <button
                    type="button"
                    @click="toggle('{{ $providerId }}')"
                    :class="isActive('{{ $providerId }}')
                        ? 'bg-emerald-600 border-emerald-500 text-white'
                        : 'bg-slate-800/60 border-slate-700/60 text-slate-400 hover:border-slate-600 hover:text-white'"
                    class="inline-flex items-center gap-1.5 text-xs font-medium border rounded-full px-3 py-1 transition">
                    <span>{{ $srcMeta['icon'] }}</span>
                    <span>{{ $srcMeta['label'] }}</span>
                </button>
                @endforeach
            </div>

            {{-- Article list (filtered by Alpine) --}}
            <div class="space-y-3">
                <template x-for="article in filtered" :key="article.id">
                    <a :href="article.source_url" target="_blank" rel="noopener noreferrer"
                       class="flex gap-4 bg-slate-800/40 border border-slate-700/40 hover:border-slate-600/60 rounded-xl p-4 transition group">
                        <template x-if="article.image_url">
                            <img :src="article.image_url" alt=""
                                 class="w-20 h-16 object-cover rounded-lg flex-shrink-0 bg-slate-700"
                                 loading="lazy" @error="$el.style.display='none'">
                        </template>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-medium text-slate-200 group-hover:text-white line-clamp-2 leading-snug transition"
                               x-text="article.headline"></p>
                            <p class="mt-1 text-xs text-slate-500 flex items-center gap-2">
                                <span class="font-medium text-slate-400" x-text="article.source_name"></span>
                                <span x-show="article.source_name && article.published_at">·</span>
                                <span x-text="article.published_at"></span>
                            </p>
                            <p class="mt-1 text-xs text-slate-500 line-clamp-2" x-text="article.snippet"></p>
                        </div>
                        <span class="text-slate-600 group-hover:text-slate-400 flex-shrink-0 self-center transition">↗</span>
                    </a>
                </template>

                {{-- Empty state when a filter returns no results --}}
                <p x-show="filtered.length === 0"
                   class="text-sm text-slate-500 text-center py-6 bg-slate-800/30 border border-slate-700/40 rounded-xl">
                    No articles from the selected source(s) yet. Try another or
                    <button type="button" @click="selected = []" class="text-emerald-400 hover:underline">show all</button>.
                </p>
            </div>
        </section>
        @endif

        {{-- ── Follow the Money (OpenSecrets / FEC donor data) ────────────── --}}
        @php
            $donorData   = $transparencyData['opensecrets'] ?? null;
            $fecData     = $transparencyData['fec'] ?? null;
            $topDonors   = $donorData['sections']['top_contributors']['items'] ?? [];
            $topIndustries = $donorData['sections']['top_industries']['items'] ?? [];
            $fecSummary  = $fecData['sections']['summary'] ?? null;
        @endphp
        @if(!empty($topDonors) || !empty($topIndustries) || $fecSummary)
        <section>
            <h2 class="text-xl font-bold text-white mb-4 flex items-center gap-2">
                <span class="w-1 h-6 rounded-full inline-block" style="background:var(--p13-accent,#f59e0b)"></span>
                Follow the Money
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

                {{-- FEC totals banner --}}
                @if($fecSummary)
                <div class="sm:col-span-2 bg-slate-800/40 border border-slate-700/40 rounded-xl p-5">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-3">
                        FEC Filing Summary
                        @if(!empty($fecData['source_url']))
                            · <a href="{{ $fecData['source_url'] }}" target="_blank" rel="noopener" class="text-emerald-400 hover:underline">View on FEC.gov ↗</a>
                        @endif
                    </p>
                    <dl class="flex flex-wrap gap-6">
                        @if(!empty($fecSummary['total_receipts']))
                        <div>
                            <dt class="text-xs text-slate-500">Total Raised</dt>
                            <dd class="text-lg font-bold text-white">${{ number_format($fecSummary['total_receipts']) }}</dd>
                        </div>
                        @endif
                        @if(!empty($fecSummary['total_disbursements']))
                        <div>
                            <dt class="text-xs text-slate-500">Total Spent</dt>
                            <dd class="text-lg font-bold text-white">${{ number_format($fecSummary['total_disbursements']) }}</dd>
                        </div>
                        @endif
                        @if(!empty($fecSummary['cash_on_hand_end_period']))
                        <div>
                            <dt class="text-xs text-slate-500">Cash on Hand</dt>
                            <dd class="text-lg font-bold text-emerald-400">${{ number_format($fecSummary['cash_on_hand_end_period']) }}</dd>
                        </div>
                        @endif
                    </dl>
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
                            @if(!empty($donor['total']))
                            <span class="text-sm font-semibold text-white tabular-nums">${{ number_format($donor['total']) }}</span>
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
                            @if(!empty($industry['total']))
                            <span class="text-sm font-semibold text-white tabular-nums">${{ number_format($industry['total']) }}</span>
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
    <footer class="border-t border-slate-800 py-8 text-center text-sm text-slate-500">
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

</body>
</html>
