<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Voter Registration — {{ config('app.name', 'U9itus') }}</title>
    @include('standalone.partials.seo-head')
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
    <style>* { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="min-h-screen bg-slate-900 flex items-center justify-center px-4 py-10 antialiased">

@php
    $voterPayoutPerView = number_format((float) \App\Services\PlatformSettingsService::get('viewer_payout_per_view', null, 0.25), 2);
    $referralCommissionPct = (int) config('u9itus.referral_commission_percent', 10);
@endphp

<div class="w-full max-w-md">

    {{-- Logo --}}
    <div class="text-center mb-8">
        <a href="/" class="inline-block"><img src="{{ asset('media/u9itus-logo.svg') }}" alt="U9itus" class="h-10 mx-auto mb-2"></a>
        <p class="mt-2 text-slate-400 text-sm">Political Loyalty Ads Platform</p>
    </div>

    {{-- Header --}}
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('register') }}" class="text-slate-500 hover:text-slate-300 transition">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <h1 class="text-xl font-bold text-white flex items-center gap-2">🗳️ Voter Registration</h1>
            <p class="text-sm text-slate-400">Earn money watching political messages</p>
        </div>
    </div>

    @if(!empty($referralCode))
        <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4 mb-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-300">Referral Applied</p>
            <p class="mt-1 text-sm text-slate-200">You were invited to join U9itus with code <span class="font-semibold text-white">{{ $referralCode }}</span>.</p>
        </div>
    @endif

    {{-- Earnings callout --}}
    <div class="bg-blue-500/10 border border-blue-500/30 rounded-xl p-4 mb-4">
        <p class="text-blue-300 font-semibold text-sm mb-3 flex items-center gap-2">💰 How you earn on U9itus</p>
        <ul class="space-y-2.5">
            <li class="flex items-start gap-3">
                <span class="mt-0.5 w-5 h-5 rounded-full bg-emerald-500/20 flex items-center justify-center shrink-0">
                    <svg class="w-3 h-3 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                </span>
                <div>
                    <p class="text-slate-200 text-sm font-medium">${{ $voterPayoutPerView }} per verified view</p>
                    <p class="text-slate-400 text-xs mt-0.5">Watch political messages at your own pace. Cash out anytime above $10.</p>
                </div>
            </li>
            <li class="flex items-start gap-3">
                <span class="mt-0.5 w-5 h-5 rounded-full bg-purple-500/20 flex items-center justify-center shrink-0">
                    <svg class="w-3 h-3 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                </span>
                <div>
                    <p class="text-slate-200 text-sm font-medium">{{ $referralCommissionPct }}% commission on every referral's views<span class="text-slate-400 font-normal"> (recurring)</span></p>
                    <p class="text-slate-400 text-xs mt-0.5">Refer another voter — earn {{ $referralCommissionPct }}% of their ${{ $voterPayoutPerView }} payout every time they watch an ad.</p>
                </div>
            </li>
            <li class="flex items-start gap-3">
                <span class="mt-0.5 w-5 h-5 rounded-full bg-amber-500/20 flex items-center justify-center shrink-0">
                    <svg class="w-3 h-3 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                </span>
                <div>
                    <p class="text-slate-200 text-sm font-medium">10% residual income for Founding Members<span class="text-slate-400 font-normal"> (ongoing)</span></p>
                    <p class="text-slate-400 text-xs mt-0.5">Recruit a politician to the platform — earn 10% residual income on their spending as a Founding Member.</p>
                </div>
            </li>
        </ul>
    </div>

    <div class="bg-slate-800/60 border border-blue-500/20 rounded-2xl p-8 shadow-2xl">

        @if($errors->any())
            <div class="mb-5 p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @php
            // Pre-filled from a signed Early-bank handoff link (see
            // EarlyBankPrefillService) when the visitor already completed
            // Stripe Connect onboarding there — saves retyping identity info.
            $ebNameParts = isset($prefill['name']) ? preg_split('/\s+/', trim($prefill['name']), 2) : [];
        @endphp

        <form method="POST" action="{{ route('register.voter.submit') }}" class="space-y-4">
            @csrf

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="first_name" class="block text-sm font-medium text-slate-300 mb-1.5">First name <span class="text-red-400">*</span></label>
                    <input id="first_name" type="text" name="first_name" value="{{ old('first_name', $ebNameParts[0] ?? '') }}" required autofocus
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition"
                        placeholder="Jane" />
                </div>
                <div>
                    <label for="last_name" class="block text-sm font-medium text-slate-300 mb-1.5">Last name <span class="text-red-400">*</span></label>
                    <input id="last_name" type="text" name="last_name" value="{{ old('last_name', $ebNameParts[1] ?? '') }}" required
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition"
                        placeholder="Doe" />
                </div>
            </div>

            <div>
                <label for="email" class="block text-sm font-medium text-slate-300 mb-1.5">Email address <span class="text-red-400">*</span></label>
                <input id="email" type="email" name="email" value="{{ old('email', $prefill['email'] ?? '') }}" required
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition"
                    placeholder="you@example.com" />
            </div>

            <div class="space-y-4">
                <div>
                    <label for="password" class="block text-sm font-medium text-slate-300 mb-1.5">Password <span class="text-red-400">*</span></label>
                    <x-password-input
                        id="password"
                        name="password"
                        required
                        autocomplete="new-password"
                        btn-class="text-blue-400 hover:text-blue-300"
                        class="w-full pr-16 bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition"
                        placeholder="••••••••"
                    />
                </div>
                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-slate-300 mb-1.5">Confirm password <span class="text-red-400">*</span></label>
                    <x-password-input
                        id="password_confirmation"
                        name="password_confirmation"
                        required
                        autocomplete="new-password"
                        btn-class="text-blue-400 hover:text-blue-300"
                        class="w-full pr-16 bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition"
                        placeholder="••••••••"
                    />
                </div>
            </div>

            <div>
                <label for="phone" class="block text-sm font-medium text-slate-300 mb-1.5">Phone number</label>
                <input id="phone" type="tel" name="phone" value="{{ old('phone', $prefill['phone'] ?? '') }}"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition"
                    placeholder="+1 (555) 000-0000" />
            </div>

            {{-- Location (for ad targeting) --}}
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="state" class="block text-sm font-medium text-slate-300 mb-1.5">State</label>
                    <select id="state" name="state"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition">
                        <option value="">Select state...</option>
                        @foreach(config('u9itus.us_states', []) as $abbr => $stateName)
                            <option value="{{ $abbr }}" {{ old('state', $prefill['state'] ?? null) === $abbr ? 'selected' : '' }}>{{ $stateName }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="zip_code" class="block text-sm font-medium text-slate-300 mb-1.5">ZIP code <span class="text-red-400">*</span></label>
                    <input id="zip_code" type="text" name="zip_code" value="{{ old('zip_code', $prefill['zip'] ?? '') }}" maxlength="10" required inputmode="numeric" pattern="\d{5}(-\d{4})?"
                        class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition"
                        placeholder="78701" />
                </div>
            </div>

            {{-- Optional referral code --}}
            <div>
                <label for="referral_code" class="block text-sm font-medium text-slate-300 mb-1.5">Referral code <span class="text-slate-500">(optional)</span></label>
                <input id="referral_code" type="text" name="referral_code" value="{{ old('referral_code', $referralCode ?? request('ref')) }}"
                    class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-blue-500 transition"
                    placeholder="e.g. JANE2024" />
            </div>

            {{-- Voter Registration Status Questionnaire --}}
            <div class="bg-slate-900/60 border border-slate-600/50 rounded-xl p-4 space-y-3">
                <p class="text-slate-200 text-sm font-semibold flex items-center gap-2">
                    🗳️ Are you currently registered to vote?
                </p>
                <div class="flex flex-col gap-2">
                    <label class="flex items-center gap-3 cursor-pointer group">
                        <input type="radio" name="is_registered_voter" value="1"
                               id="registered_yes"
                               {{ old('is_registered_voter') === '1' ? 'checked' : '' }}
                               onchange="document.getElementById('register_link_box').classList.add('hidden')"
                               class="w-4 h-4 text-emerald-500 border-slate-600 bg-slate-700 focus:ring-emerald-500/50">
                        <span class="text-slate-300 text-sm group-hover:text-white transition">Yes, I am registered to vote ✅</span>
                    </label>
                    <label class="flex items-center gap-3 cursor-pointer group">
                        <input type="radio" name="is_registered_voter" value="0"
                               id="registered_no"
                               {{ old('is_registered_voter') === '0' ? 'checked' : '' }}
                               onchange="document.getElementById('register_link_box').classList.remove('hidden')"
                               class="w-4 h-4 text-red-500 border-slate-600 bg-slate-700 focus:ring-red-500/50">
                        <span class="text-slate-300 text-sm group-hover:text-white transition">No, I am not registered</span>
                    </label>
                    <label class="flex items-center gap-3 cursor-pointer group">
                        <input type="radio" name="is_registered_voter" value=""
                               id="registered_unsure"
                               {{ old('is_registered_voter') === null ? 'checked' : '' }}
                               onchange="document.getElementById('register_link_box').classList.remove('hidden')"
                               class="w-4 h-4 text-slate-400 border-slate-600 bg-slate-700 focus:ring-slate-400/50">
                        <span class="text-slate-300 text-sm group-hover:text-white transition">I'm not sure</span>
                    </label>
                </div>
                {{-- Register to vote prompt --}}
                <div id="register_link_box" class="hidden bg-blue-500/10 border border-blue-500/30 rounded-lg p-3 mt-1">
                    <p class="text-blue-300 text-xs font-medium mb-1">📋 Register to vote — it only takes a few minutes!</p>
                    <p class="text-slate-400 text-xs mb-2">
                        Being a registered voter may unlock additional campaigns in your area.
                        Use the official U.S. government voter registration portal:
                    </p>
                    <a href="https://vote.gov" target="_blank" rel="noopener noreferrer"
                       class="inline-flex items-center gap-1.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        Register at vote.gov
                    </a>
                </div>
            </div>

            {{-- ToS --}}
            <div class="flex items-start gap-3 pt-1">
                <input id="terms" type="checkbox" name="terms" required
                    class="mt-0.5 w-4 h-4 rounded border-slate-600 bg-slate-700 text-blue-500 focus:ring-blue-500/50">
                <label for="terms" class="text-sm text-slate-400">
                    I agree to the <a href="{{ route('terms') }}" class="text-blue-400 hover:text-blue-300 underline">Terms of Service</a>
                    and <a href="{{ route('privacy-policy') }}" class="text-blue-400 hover:text-blue-300 underline">Privacy Policy</a>.
                    I understand I must watch the full video to earn the ${{ $voterPayoutPerView }} reward.
                </label>
            </div>

            <button type="submit"
                class="w-full bg-blue-600 hover:bg-blue-500 text-white font-semibold py-3 rounded-lg text-sm transition-colors mt-2">
                Create Voter Account
            </button>

        </form>
    </div>

    <p class="text-center mt-6 text-slate-500 text-sm">
        Already have an account?
        <a href="{{ route('login') }}" class="text-blue-400 hover:text-blue-300 font-medium transition-colors">Sign in</a>
        &nbsp;·&nbsp;
        <a href="{{ route('register') }}" class="text-slate-400 hover:text-slate-300 transition-colors">Change role</a>
    </p>

</div>
</body>
</html>
