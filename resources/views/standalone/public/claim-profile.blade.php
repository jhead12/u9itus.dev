<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Claim Profile — {{ $politician->full_name }} — {{ config('app.name', 'U9itus') }}</title>
    @include('standalone.partials.seo-head')
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800&display=swap" rel="stylesheet" />
    @if(file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
    <style>* { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-slate-950 min-h-screen antialiased text-slate-100">

    {{-- Nav --}}
    <nav class="sticky top-0 z-40 bg-slate-900/80 backdrop-blur-md border-b border-slate-700/50">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 flex items-center justify-between h-14">
            <a href="{{ url('/') }}" class="flex items-center space-x-1 text-lg font-bold hover:opacity-80 transition">
                <span class="text-white">U9</span><span class="text-emerald-400">itus</span>
            </a>
            <a href="{{ route('politician.public.show', $politician->slug) }}"
               class="text-sm text-slate-400 hover:text-white transition flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                Back to Profile
            </a>
        </div>
    </nav>

    <main class="max-w-2xl mx-auto px-4 sm:px-6 py-16">

        {{-- Header --}}
        <div class="text-center mb-10">
            <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-amber-500/15 border border-amber-500/30 mb-5">
                <span class="text-3xl">🏛</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white mb-2">Claim This Profile</h1>
            <p class="text-slate-400 text-sm max-w-md mx-auto">
                <span class="font-semibold text-amber-300">{{ $politician->full_name }}</span>'s profile was auto-generated from public election records.
                If you are this candidate or a verified campaign representative, you can claim and manage this page.
            </p>
        </div>

        {{-- How it works --}}
        <div class="bg-slate-900/60 border border-slate-700/50 rounded-2xl p-6 mb-8">
            <h2 class="text-sm font-bold text-slate-300 uppercase tracking-wider mb-4">How it works</h2>
            <ol class="space-y-3">
                @foreach([
                    ['1', 'Enter your name and campaign email address below.'],
                    ['2', "We'll email a one-time verification link to that address."],
                    ['3', 'Click the link → you\'ll be directed to create your free U9itus politician account.'],
                    ['4', 'The platform admin reviews and approves the claim, then links your account to this profile.'],
                ] as [$step, $text])
                <li class="flex items-start gap-3">
                    <span class="flex-shrink-0 w-6 h-6 rounded-full bg-amber-500/20 border border-amber-500/40 text-amber-300
                                 text-xs font-bold flex items-center justify-center">{{ $step }}</span>
                    <span class="text-sm text-slate-300">{{ $text }}</span>
                </li>
                @endforeach
            </ol>
        </div>

        {{-- Form --}}
        <div class="bg-slate-900/60 border border-slate-700/50 rounded-2xl p-6 sm:p-8">
            <form method="POST" action="{{ route('politician.profile.claim.submit', $politician->slug) }}" novalidate>
                @csrf

                {{-- Full name --}}
                <div class="mb-5">
                    <label for="full_name" class="block text-sm font-semibold text-slate-300 mb-1.5">
                        Your full name <span class="text-red-400">*</span>
                    </label>
                    <input type="text" id="full_name" name="full_name"
                           value="{{ old('full_name', $politician->full_name) }}"
                           autocomplete="name" required
                           class="w-full rounded-lg border px-4 py-2.5 text-sm bg-slate-800 text-white placeholder-slate-500 transition
                                  focus:outline-none focus:ring-2 focus:ring-amber-500
                                  @error('full_name') border-red-500 @else border-slate-600 @enderror">
                    @error('full_name')
                        <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Email --}}
                <div class="mb-6">
                    <label for="email" class="block text-sm font-semibold text-slate-300 mb-1.5">
                        Campaign email address <span class="text-red-400">*</span>
                    </label>
                    <input type="email" id="email" name="email"
                           value="{{ old('email') }}"
                           autocomplete="email" required
                           placeholder="you@yourcampaign.com"
                           class="w-full rounded-lg border px-4 py-2.5 text-sm bg-slate-800 text-white placeholder-slate-500 transition
                                  focus:outline-none focus:ring-2 focus:ring-amber-500
                                  @error('email') border-red-500 @else border-slate-600 @enderror">
                    @error('email')
                        <p class="mt-1.5 text-xs text-red-400">{{ $message }}</p>
                    @enderror
                    <p class="mt-1.5 text-xs text-slate-500">
                        We'll send a verification link here. Using a domain-matched campaign email (e.g. @yourcampaign.com) speeds up approval.
                    </p>
                </div>

                {{-- Disclaimer --}}
                <p class="text-xs text-slate-500 mb-6 leading-relaxed">
                    By submitting this request you confirm that you are the named candidate or an authorized representative
                    of their campaign. False claims may result in account suspension.
                </p>

                <button type="submit"
                        class="w-full inline-flex items-center justify-center gap-2 px-6 py-3 rounded-xl
                               bg-amber-500 hover:bg-amber-400 text-slate-900 font-bold text-sm transition">
                    Send Verification Email →
                </button>
            </form>
        </div>

        <p class="text-center text-xs text-slate-600 mt-8">
            Having trouble? Email
            <a href="mailto:{{ config('mail.from.address') }}"
               class="text-slate-400 hover:text-white underline transition">{{ config('mail.from.address') }}</a>
            and reference profile <span class="font-mono text-slate-500">{{ $politician->slug }}</span>.
        </p>

    </main>
</body>
</html>
