<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Join U9itus — Choose Your Role</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800&display=swap" rel="stylesheet" />
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
    <style>* { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="min-h-screen bg-slate-900 flex items-center justify-center px-4 py-12 antialiased">

<div class="w-full max-w-2xl">

    {{-- Logo --}}
    <div class="text-center mb-10">
        <a href="/" class="text-3xl font-light tracking-tight">
            <span class="font-bold text-white">U9</span><span class="text-emerald-400">itus</span>
        </a>
        <p class="mt-2 text-slate-400 text-sm">Political Loyalty Ads Platform</p>
    </div>

    <div class="text-center mb-8">
        <h1 class="text-2xl font-bold text-white">How will you use U9itus?</h1>
        <p class="mt-2 text-slate-400">Choose your account type to get started. Each portal is tailored to your role.</p>
    </div>

    @if(!empty($referralCode))
        <div class="mb-6 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 text-center">
            <p class="text-sm font-semibold text-emerald-300">You were invited to join U9itus.</p>
            <p class="mt-1 text-xs text-slate-300">Referral code: <span class="font-semibold text-white">{{ $referralCode }}</span></p>
        </div>
    @endif

    {{-- Role choice cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        {{-- Politician card --}}
        <a href="{{ !empty($referralCode) ? route('register.politician', ['ref' => $referralCode]) : route('register.politician') }}"
           class="group block bg-slate-800/60 border border-slate-700/50 hover:border-emerald-500/60 rounded-2xl p-8 shadow-2xl transition-all duration-200 hover:-translate-y-1 hover:shadow-emerald-500/10">
            <div class="text-5xl mb-4">🏛️</div>
            <h2 class="text-xl font-semibold text-white mb-2 group-hover:text-emerald-400 transition-colors">Politician / Candidate</h2>
            <p class="text-slate-400 text-sm mb-5 leading-relaxed">
                Create and manage political ad campaigns. Reach targeted voters with video messages and live feeds. Pay only when someone watches.
            </p>
            <ul class="space-y-2 text-sm text-slate-400 mb-6">
                <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Campaign creation &amp; management</li>
                <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> <span>$0.60 per verified view</span></li>
                <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Analytics &amp; performance tracking</li>
                <li class="flex items-center gap-2"><span class="text-emerald-400">✓</span> Geo-targeted voter reach</li>
            </ul>
            <span class="inline-flex items-center gap-2 bg-emerald-500 hover:bg-emerald-400 text-white text-sm font-semibold px-5 py-2.5 rounded-lg transition-colors">
                Register as Politician
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </span>
        </a>

        {{-- Voter card --}}
        <a href="{{ !empty($referralCode) ? route('register.voter', ['ref' => $referralCode]) : route('register.voter') }}"
           class="group block bg-slate-800/60 border border-slate-700/50 hover:border-blue-500/60 rounded-2xl p-8 shadow-2xl transition-all duration-200 hover:-translate-y-1 hover:shadow-blue-500/10">
            <div class="text-5xl mb-4">🗳️</div>
            <h2 class="text-xl font-semibold text-white mb-2 group-hover:text-blue-400 transition-colors">Voter / Citizen</h2>
            <p class="text-slate-400 text-sm mb-5 leading-relaxed">
                Watch short political messages from candidates in your area and earn money for your time. Stay informed and get paid.
            </p>
            <ul class="space-y-2 text-sm text-slate-400 mb-6">
                <li class="flex items-center gap-2"><span class="text-blue-400">✓</span> Earn $0.25 per completed view</li>
                <li class="flex items-center gap-2"><span class="text-blue-400">✓</span> Referral bonuses for invites</li>
                <li class="flex items-center gap-2"><span class="text-blue-400">✓</span> Watch on your schedule</li>
                <li class="flex items-center gap-2"><span class="text-blue-400">✓</span> Instant earnings dashboard</li>
            </ul>
            <span class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold px-5 py-2.5 rounded-lg transition-colors">
                Register as Voter
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </span>
        </a>

    </div>

    {{-- Sign-in link --}}
    <p class="text-center mt-8 text-slate-500 text-sm">
        Already have an account?
        <a href="{{ route('login') }}" class="text-emerald-400 hover:text-emerald-300 font-medium transition-colors">Sign in</a>
    </p>

    {{-- Admin note --}}
    <p class="text-center mt-3 text-slate-600 text-xs">
        Admin staff?
        <a href="{{ route('admin.login') }}" class="text-slate-500 hover:text-slate-400 underline transition-colors">Access admin portal</a>
    </p>

</div>
</body>
</html>
