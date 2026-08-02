<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Citizen Registration — {{ config('app.name', 'U9itus') }}</title>
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
    $citizenRatePerView     = number_format((float) \App\Services\PlatformSettingsService::get('citizen_revenue_per_view', null, 0.60), 2);
    $ballotIssueRatePerView = number_format((float) \App\Services\PlatformSettingsService::get('ballot_issue_revenue_per_view', null, 1.00), 2);
@endphp

<div class="w-full max-w-lg">

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
            <h1 class="text-xl font-bold text-white flex items-center gap-2">🏘️ Citizen Registration</h1>
            <p class="text-sm text-slate-400">Promote your business, community notice, or local cause</p>
        </div>
    </div>

    @if(!empty($referralCode))
        <div class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 mb-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-amber-300">Referral Applied</p>
            <p class="mt-1 text-sm text-slate-200">You were invited to join U9itus with code <span class="font-semibold text-white">{{ $referralCode }}</span>.</p>
        </div>
    @endif

    <div class="bg-slate-800/60 border border-amber-500/20 rounded-2xl p-8 shadow-2xl">

        @if($errors->any())
            <div class="mb-5 p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('register.citizen.submit') }}" class="space-y-4">
            @csrf

            {{-- Capture referral code from ?ref= query param --}}
            <input type="hidden" name="referral_code" value="{{ old('referral_code', $referralCode ?? request()->query('ref')) }}">

            <div class="pb-4 border-b border-slate-700/50">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-widest mb-4">Account Credentials</p>

                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="first_name" class="block text-sm font-medium text-slate-300 mb-1.5">First name <span class="text-red-400">*</span></label>
                            <input id="first_name" type="text" name="first_name" value="{{ old('first_name') }}" required autofocus
                                class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition"
                                placeholder="Jane" />
                        </div>
                        <div>
                            <label for="last_name" class="block text-sm font-medium text-slate-300 mb-1.5">Last name <span class="text-red-400">*</span></label>
                            <input id="last_name" type="text" name="last_name" value="{{ old('last_name') }}" required
                                class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition"
                                placeholder="Doe" />
                        </div>
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-slate-300 mb-1.5">Email address <span class="text-red-400">*</span></label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required
                            class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition"
                            placeholder="you@business.com" />
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label for="password" class="block text-sm font-medium text-slate-300 mb-1.5">Password <span class="text-red-400">*</span></label>
                            <x-password-input
                                id="password"
                                name="password"
                                required
                                autocomplete="new-password"
                                class="w-full pr-16 bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition"
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
                                class="w-full pr-16 bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition"
                                placeholder="••••••••"
                            />
                        </div>
                    </div>

                    <div>
                        <label for="phone" class="block text-sm font-medium text-slate-300 mb-1.5">Phone number <span class="text-red-400">*</span></label>
                        <input id="phone" type="tel" name="phone" value="{{ old('phone') }}" required
                            class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition"
                            placeholder="+1 (555) 000-0000" />
                    </div>
                </div>
            </div>

            {{-- Citizen details --}}
            <div class="pt-2">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-widest mb-4">Citizen Information</p>

                <div class="space-y-4">
                    <div>
                        <label for="business_name" class="block text-sm font-medium text-slate-300 mb-1.5">Business / organization name</label>
                        <input id="business_name" type="text" name="business_name" value="{{ old('business_name') }}"
                            class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition"
                            placeholder="e.g. Jane's Bakery (optional)" />
                    </div>

                    <div>
                        <label for="address_line_1" class="block text-sm font-medium text-slate-300 mb-1.5">Address line 1 <span class="text-red-400">*</span></label>
                        <input id="address_line_1" type="text" name="address_line_1" value="{{ old('address_line_1') }}" required
                            class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition"
                            placeholder="123 Main St" />
                    </div>

                    <div>
                        <label for="address_line_2" class="block text-sm font-medium text-slate-300 mb-1.5">Address line 2</label>
                        <input id="address_line_2" type="text" name="address_line_2" value="{{ old('address_line_2') }}"
                            class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition"
                            placeholder="Suite 200 (optional)" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="state" class="block text-sm font-medium text-slate-300 mb-1.5">State <span class="text-red-400">*</span></label>
                            <select id="state" name="state" required
                                class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition">
                                <option value="">Select state...</option>
                                @foreach(config('u9itus.us_states', []) as $abbr => $stateName)
                                    <option value="{{ $abbr }}" {{ old('state') === $abbr ? 'selected' : '' }}>{{ $stateName }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="city" class="block text-sm font-medium text-slate-300 mb-1.5">City <span class="text-red-400">*</span></label>
                            <input id="city" type="text" name="city" value="{{ old('city') }}" required
                                class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition"
                                placeholder="e.g. Austin" />
                        </div>
                    </div>

                    <div>
                        <label for="zip" class="block text-sm font-medium text-slate-300 mb-1.5">ZIP code <span class="text-red-400">*</span></label>
                        <input id="zip" type="text" name="zip" value="{{ old('zip') }}" required
                            class="w-full bg-slate-900/60 border border-slate-700 rounded-lg px-4 py-2.5 text-white text-sm placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-amber-500/50 focus:border-amber-500 transition"
                            placeholder="78701" />
                    </div>
                </div>
            </div>

            {{-- ToS --}}
            <div class="flex items-start gap-3 pt-2">
                <input id="terms" type="checkbox" name="terms" required
                    class="mt-0.5 w-4 h-4 rounded border-slate-600 bg-slate-700 text-amber-500 focus:ring-amber-500/50">
                <label for="terms" class="text-sm text-slate-400">
                    I agree to the <a href="{{ route('terms') }}" class="text-amber-400 hover:text-amber-300 underline">Terms of Service</a>
                    and <a href="{{ route('privacy-policy') }}" class="text-amber-400 hover:text-amber-300 underline">Privacy Policy</a>.
                    I understand that <strong class="text-slate-300">campaigns cost ${{ $citizenRatePerView }} per verified view (${{ $ballotIssueRatePerView }} for ballot issues)</strong>, standard ads auto-approve once my identity is verified, and ballot-issue campaigns always require admin review.
                </label>
            </div>

            <button type="submit"
                class="w-full bg-amber-500 hover:bg-amber-400 text-white font-semibold py-3 rounded-lg text-sm transition-colors mt-2">
                Create Citizen Account
            </button>

        </form>
    </div>

    <p class="text-center mt-6 text-slate-500 text-sm">
        Already have an account?
        <a href="{{ route('login') }}" class="text-amber-400 hover:text-amber-300 font-medium transition-colors">Sign in</a>
        &nbsp;·&nbsp;
        <a href="{{ route('register') }}" class="text-slate-400 hover:text-slate-300 transition-colors">Change role</a>
    </p>

</div>

</body>
</html>
