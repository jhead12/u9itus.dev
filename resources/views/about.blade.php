<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>About Us — {{ config('app.name', 'U9itus') }}</title>
    <meta name="description" content="Learn about Unite Us — a people-first platform designed to pay individuals for their attention and restore economic balance to communities.">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800&display=swap" rel="stylesheet" />

    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif

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

    <!-- Navigation -->
    <nav class="fixed w-full z-50 bg-slate-900/90 backdrop-blur-lg border-b border-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <a href="{{ url('/') }}" class="flex items-center space-x-2">
                    <div class="text-3xl font-light tracking-tight">
                        <span class="font-bold">U9</span><span class="text-emerald-400">itus</span>
                    </div>
                </a>

                <div class="hidden md:flex items-center space-x-8">
                    <a href="{{ url('/') }}#platform" class="text-slate-300 hover:text-white transition text-sm font-medium">Platform</a>
                    <a href="{{ url('/') }}#revenue" class="text-slate-300 hover:text-white transition text-sm font-medium">Revenue</a>
                    <a href="{{ url('/') }}#how-it-works" class="text-slate-300 hover:text-white transition text-sm font-medium">How It Works</a>
                    <a href="{{ route('about') }}" class="text-emerald-400 text-sm font-medium">About Us</a>
                </div>

                @if (Route::has('login'))
                    <div class="flex items-center space-x-4">
                        @auth
                            <a href="{{ url('/dashboard') }}"
                               class="inline-flex items-center justify-center px-5 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-emerald-500 to-teal-500 rounded-lg hover:from-emerald-600 hover:to-teal-600 transition shadow-lg shadow-emerald-500/30">
                                <span class="mr-2">👤</span> Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="text-slate-300 hover:text-white transition text-sm font-medium">Sign In</a>
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}"
                                   class="inline-flex items-center justify-center px-5 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-emerald-500 to-teal-500 rounded-lg hover:from-emerald-600 hover:to-teal-600 transition shadow-lg shadow-emerald-500/30">
                                    Get Started
                                </a>
                            @endif
                        @endauth
                    </div>
                @endif
            </div>
        </div>
    </nav>

    <!-- Hero -->
    <section class="relative min-h-[60vh] flex items-center justify-center overflow-hidden pt-20">
        <div class="absolute inset-0 bg-gradient-to-br from-slate-900 via-slate-800 to-teal-900"></div>
        <div class="absolute inset-0 bg-gradient-to-tr from-emerald-500/10 via-transparent to-teal-500/10"></div>
        <div class="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.02)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.02)_1px,transparent_1px)] bg-[size:72px_72px]"></div>

        <div class="relative z-10 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-24 text-center space-y-6">
            <div class="animate-fade-in-up opacity-0">
                <span class="inline-block px-4 py-1.5 bg-emerald-500/10 border border-emerald-500/20 rounded-full text-emerald-400 text-sm font-medium mb-6">
                    — ABOUT US
                </span>
            </div>
            <h1 class="animate-fade-in-up delay-100 opacity-0 text-5xl sm:text-6xl font-bold tracking-tight">
                Unite Us <span class="bg-gradient-to-r from-emerald-400 to-teal-400 bg-clip-text text-transparent">Revolution</span>
            </h1>
            <p class="animate-fade-in-up delay-200 opacity-0 text-xl text-slate-300 max-w-2xl mx-auto leading-relaxed">
                A people-first advertising system designed to pay individuals for their attention — restoring income, dignity, and economic balance to communities nationwide.
            </p>
        </div>
    </section>

    <!-- Alert Banner -->
    <section class="bg-red-900/30 border-y border-red-500/30 py-6">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <p class="text-red-300 font-semibold text-lg mb-2">
                🚨 Alert: New laws are headed to the Senate to eliminate voting rights.
            </p>
            <p class="text-slate-300 text-sm mb-4">
                We are attempting to do something about it with the Unite Us Program. Help us make a difference.
            </p>
            <a href="https://youtu.be/NYsxHXM2TWE?si=q24O1_e5_n6-WfFS"
               target="_blank" rel="noopener noreferrer"
               class="inline-flex items-center justify-center px-5 py-2 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 rounded-lg transition">
                Watch & Share
                <svg class="ml-2 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                </svg>
            </a>
        </div>
    </section>

    <!-- What is Unite Us -->
    <section class="relative py-24 bg-slate-900">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <span class="text-emerald-400 font-semibold text-sm tracking-wider uppercase">— Our Mission</span>
                <h2 class="mt-4 text-4xl sm:text-5xl font-bold">
                    What is the <span class="text-emerald-400">Unite Us Program?</span>
                </h2>
            </div>

            <div class="grid md:grid-cols-2 gap-12 items-center">
                <div class="space-y-6 text-slate-300 leading-relaxed">
                    <p>
                        Unite Us is a <strong class="text-white">people-first advertising system</strong> designed to pay individuals for their attention — helping advertisers reach real, motivated viewers, not bots, not algorithms, not ignored impressions.
                    </p>
                    <p>
                        Unlike other pay-to-view programs, motivated Unite Us ad viewers can earn just as much income as the program owners with far less responsibility. We are not designed like most billionaire-owned, multilevel programs that require ad viewers to recruit huge downlines in order to earn money, or where the owners make a king's ransom while others only earn peanuts.
                    </p>
                    <p>
                        Unite Us revisits the <strong class="text-white">Barter Trade System</strong> of the past — the idea that community members once relied on one another for survival. If you had pork and I had beans, then we both had "pork n beans." This sentiment has been lost in current times, causing communities to fail. Unite Us restores it.
                    </p>
                </div>

                <div class="space-y-4">
                    <div class="relative bg-gradient-to-br from-slate-800 to-slate-900 rounded-2xl p-8 border border-slate-700">
                        <div class="absolute top-0 right-0 w-48 h-48 bg-emerald-500/5 rounded-full blur-3xl"></div>
                        <div class="relative space-y-5">
                            <div class="flex items-start space-x-4">
                                <div class="flex-shrink-0 w-10 h-10 bg-emerald-500/20 rounded-lg flex items-center justify-center text-emerald-400 font-bold">1</div>
                                <div>
                                    <h4 class="font-semibold text-white">Corporations spend billions on advertising</h4>
                                    <p class="text-sm text-slate-400 mt-1">None of that money goes to the people who are required to watch, process, and absorb those promotions.</p>
                                </div>
                            </div>
                            <div class="flex items-start space-x-4">
                                <div class="flex-shrink-0 w-10 h-10 bg-teal-500/20 rounded-lg flex items-center justify-center text-teal-400 font-bold">2</div>
                                <div>
                                    <h4 class="font-semibold text-white">Billionaire-owned media keeps all the revenue</h4>
                                    <p class="text-sm text-slate-400 mt-1">The majority of ad revenue is recycled continuously among very wealthy people, and little is returned to communities.</p>
                                </div>
                            </div>
                            <div class="flex items-start space-x-4">
                                <div class="flex-shrink-0 w-10 h-10 bg-blue-500/20 rounded-lg flex items-center justify-center text-blue-400 font-bold">3</div>
                                <div>
                                    <h4 class="font-semibold text-white">Unite Us challenges that model</h4>
                                    <p class="text-sm text-slate-400 mt-1">Robots do not make purchases — people do. Unite Us restores balance by returning advertising dollars to the community.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Why Is It Necessary -->
    <section class="relative py-24 bg-gradient-to-b from-slate-900 to-slate-800">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <span class="text-emerald-400 font-semibold text-sm tracking-wider uppercase">— The Problem</span>
                <h2 class="mt-4 text-4xl sm:text-5xl font-bold">
                    Why Is Unite Us <span class="text-emerald-400">Necessary?</span>
                </h2>
            </div>

            <div class="grid md:grid-cols-3 gap-8 mb-16">
                <div class="relative bg-slate-800/50 rounded-xl p-8 border border-slate-700 hover:border-emerald-500/50 transition group text-center">
                    <div class="text-5xl mb-4">🤖</div>
                    <h3 class="text-xl font-bold mb-3">AI &amp; Job Losses</h3>
                    <p class="text-slate-300 text-sm leading-relaxed">Artificial intelligence has increased productivity but also caused unprecedented job losses across all sectors — including government and professional careers once considered secure.</p>
                </div>

                <div class="relative bg-slate-800/50 rounded-xl p-8 border border-slate-700 hover:border-teal-500/50 transition group text-center">
                    <div class="text-5xl mb-4">🏘️</div>
                    <h3 class="text-xl font-bold mb-3">Community Collapse</h3>
                    <p class="text-slate-300 text-sm leading-relaxed">Entire communities are struggling because income has been removed faster than alternatives have been created. The wealthy are concerned with bottom lines, not employing humans.</p>
                </div>

                <div class="relative bg-slate-800/50 rounded-xl p-8 border border-slate-700 hover:border-blue-500/50 transition group text-center">
                    <div class="text-5xl mb-4">📺</div>
                    <h3 class="text-xl font-bold mb-3">Uncompensated Attention</h3>
                    <p class="text-slate-300 text-sm leading-relaxed">People already view hundreds of ads daily on TV, in the news, and on social media — and currently earn nothing for it. Yet ad spend reaches into the billions every year.</p>
                </div>
            </div>

            <!-- Core Idea Callout -->
            <div class="relative bg-gradient-to-r from-emerald-500/10 to-teal-500/10 rounded-2xl p-10 border border-emerald-500/20 text-center overflow-hidden">
                <div class="absolute top-0 left-0 text-[180px] font-bold text-emerald-500/5 leading-none select-none">"</div>
                <div class="relative z-10">
                    <span class="text-emerald-400 font-semibold text-sm tracking-wider uppercase">— The Core Idea</span>
                    <h3 class="mt-4 text-3xl sm:text-4xl font-bold leading-snug">
                        If advertisers want your attention,<br/>
                        <span class="bg-gradient-to-r from-emerald-400 to-teal-400 bg-clip-text text-transparent">why should you pay them for it?</span>
                    </h3>
                    <p class="mt-6 text-xl text-slate-300 max-w-2xl mx-auto">
                        They should be paying <em>you</em> to listen. Viewing advertising is labor. Unite Us ensures that this labor is compensated.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section class="relative py-24 bg-slate-900">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <span class="text-emerald-400 font-semibold text-sm tracking-wider uppercase">— The Model</span>
                <h2 class="mt-4 text-4xl sm:text-5xl font-bold">
                    How Unite Us <span class="text-emerald-400">Works</span>
                </h2>
            </div>

            <div class="grid md:grid-cols-2 gap-8 mb-12">
                <!-- For Viewers -->
                <div class="group relative bg-gradient-to-br from-slate-800 to-slate-900 rounded-2xl p-8 border border-slate-700 hover:border-teal-500/50 transition duration-300 overflow-hidden">
                    <div class="absolute top-0 right-0 w-64 h-64 bg-teal-500/5 rounded-full blur-3xl group-hover:bg-teal-500/10 transition"></div>
                    <div class="relative">
                        <div class="w-16 h-16 bg-gradient-to-br from-teal-500 to-blue-500 rounded-xl flex items-center justify-center text-3xl mb-6 shadow-lg shadow-teal-500/30">
                            👁️
                        </div>
                        <h3 class="text-2xl font-bold mb-2">For Viewers</h3>
                        <p class="text-emerald-400 text-sm font-medium mb-4">There is no cost to participate.</p>
                        <p class="text-slate-300 text-sm leading-relaxed mb-6">
                            We understand how difficult it is to raise enough money for a meal under some circumstances. We would not expect many to pay to get started. Your participation is a down payment on a better future for you and your family.
                        </p>
                        <ul class="space-y-3">
                            <li class="flex items-center text-slate-300 text-sm">
                                <svg class="w-5 h-5 mr-3 flex-shrink-0 text-teal-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                Paid to watch short promotional ads (as brief as 10 seconds)
                            </li>
                            <li class="flex items-center text-slate-300 text-sm">
                                <svg class="w-5 h-5 mr-3 flex-shrink-0 text-teal-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                All you need is a smartphone or computer
                            </li>
                            <li class="flex items-center text-slate-300 text-sm">
                                <svg class="w-5 h-5 mr-3 flex-shrink-0 text-teal-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                Participate from home, your car, or anywhere
                            </li>
                            <li class="flex items-center text-slate-300 text-sm">
                                <svg class="w-5 h-5 mr-3 flex-shrink-0 text-teal-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                Payments tied directly to ad views — no guessing, no manipulation
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- For Advertisers -->
                <div class="group relative bg-gradient-to-br from-slate-800 to-slate-900 rounded-2xl p-8 border border-slate-700 hover:border-emerald-500/50 transition duration-300 overflow-hidden">
                    <div class="absolute top-0 right-0 w-64 h-64 bg-emerald-500/5 rounded-full blur-3xl group-hover:bg-emerald-500/10 transition"></div>
                    <div class="relative">
                        <div class="w-16 h-16 bg-gradient-to-br from-emerald-500 to-teal-500 rounded-xl flex items-center justify-center text-3xl mb-6 shadow-lg shadow-emerald-500/30">
                            📣
                        </div>
                        <h3 class="text-2xl font-bold mb-2">For Advertisers</h3>
                        <p class="text-emerald-400 text-sm font-medium mb-4">Real people. Real attention. Real results.</p>
                        <p class="text-slate-300 text-sm leading-relaxed mb-6">
                            Your ads are viewed by real people who are compensated to pay attention, resulting in higher engagement, stronger brand loyalty, and better outcomes.
                        </p>
                        <ul class="space-y-3">
                            <li class="flex items-center text-slate-300 text-sm">
                                <svg class="w-5 h-5 mr-3 flex-shrink-0 text-emerald-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                Higher engagement and stronger brand loyalty
                            </li>
                            <li class="flex items-center text-slate-300 text-sm">
                                <svg class="w-5 h-5 mr-3 flex-shrink-0 text-emerald-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                Demonstrate goodwill by giving back to the community
                            </li>
                            <li class="flex items-center text-slate-300 text-sm">
                                <svg class="w-5 h-5 mr-3 flex-shrink-0 text-emerald-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                Access motivated consumers with restored purchasing power
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Earnings Example -->
            <div class="bg-gradient-to-br from-teal-900/40 to-emerald-900/40 rounded-2xl p-8 border-2 border-teal-500/30 mb-8">
                <h3 class="text-2xl font-bold mb-2 text-center">Earnings Example</h3>
                <p class="text-slate-300 text-center text-sm mb-8">If an ad viewer earns $0.25 per 10-second ad:</p>
                <div class="grid sm:grid-cols-3 gap-4 text-center">
                    <div class="bg-slate-800/60 rounded-xl p-6">
                        <div class="text-3xl font-bold text-emerald-400">$90</div>
                        <div class="text-slate-400 text-sm mt-2">per hour<br/>(360 ads × $0.25)</div>
                    </div>
                    <div class="bg-slate-800/60 rounded-xl p-6 border border-teal-500/30">
                        <div class="text-3xl font-bold text-teal-400">$720</div>
                        <div class="text-slate-400 text-sm mt-2">per day<br/>(8 hours)</div>
                    </div>
                    <div class="bg-slate-800/60 rounded-xl p-6">
                        <div class="text-3xl font-bold text-blue-400">$15,200</div>
                        <div class="text-slate-400 text-sm mt-2">per month<br/>(5 days/wk × 4.2 wks)</div>
                    </div>
                </div>
                <p class="text-xs text-slate-400 text-center mt-6 italic">
                    Many of us already see the equivalent of 360 product promotions every hour watching our favorite TV shows. What if you could be paid $0.25 each time?
                </p>
            </div>

            <!-- Residual Income -->
            <div class="group relative bg-gradient-to-br from-slate-800 to-slate-900 rounded-2xl p-8 border border-purple-500/30 overflow-hidden">
                <div class="absolute top-0 right-0 w-64 h-64 bg-purple-500/5 rounded-full blur-3xl"></div>
                <div class="relative">
                    <div class="w-16 h-16 bg-gradient-to-br from-purple-500 to-pink-500 rounded-xl flex items-center justify-center text-3xl mb-6 shadow-lg shadow-purple-500/30">
                        ♻️
                    </div>
                    <h3 class="text-2xl font-bold mb-4">Residual Income Opportunity</h3>
                    <div class="grid md:grid-cols-2 gap-8">
                        <ul class="space-y-3">
                            <li class="flex items-start text-slate-300 text-sm">
                                <svg class="w-5 h-5 mr-3 mt-0.5 flex-shrink-0 text-purple-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                Earn <strong class="text-white">10% residual income</strong> by introducing advertisers to the Unite Us platform.
                            </li>
                            <li class="flex items-start text-slate-300 text-sm">
                                <svg class="w-5 h-5 mr-3 mt-0.5 flex-shrink-0 text-purple-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                As long as that advertiser continues running ads, you continue earning.
                            </li>
                        </ul>
                        <ul class="space-y-3">
                            <li class="flex items-start text-slate-300 text-sm">
                                <svg class="w-5 h-5 mr-3 mt-0.5 flex-shrink-0 text-pink-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                Ad viewers also earn <strong class="text-white">10% of the revenue</strong> earned by other ad viewers they directly refer.
                            </li>
                            <li class="flex items-start text-slate-300 text-sm">
                                <svg class="w-5 h-5 mr-3 mt-0.5 flex-shrink-0 text-pink-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                Creates long-term income even if traditional employment disappears.
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Why It Changes Everything -->
    <section class="relative py-24 bg-gradient-to-b from-slate-800 to-slate-900">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <span class="text-emerald-400 font-semibold text-sm tracking-wider uppercase">— The Impact</span>
                <h2 class="mt-4 text-4xl sm:text-5xl font-bold">
                    Why Unite Us <span class="text-emerald-400">Changes Everything</span>
                </h2>
                <p class="mt-6 text-xl text-slate-300 max-w-3xl mx-auto">
                    Advertising is the largest, most powerful economic force on the planet — and it has never fairly compensated the public. Until now.
                </p>
            </div>

            <div class="grid sm:grid-cols-2 md:grid-cols-4 gap-6 mb-16">
                <div class="relative bg-slate-800/50 rounded-xl p-6 border border-slate-700 text-center">
                    <div class="text-4xl mb-3">💪</div>
                    <h4 class="font-bold text-white mb-2">People Reclaim Power</h4>
                    <p class="text-slate-400 text-sm">Economic power shifts back to the individuals who drive consumer spending.</p>
                </div>
                <div class="relative bg-slate-800/50 rounded-xl p-6 border border-slate-700 text-center">
                    <div class="text-4xl mb-3">🏙️</div>
                    <h4 class="font-bold text-white mb-2">Communities Regain Income</h4>
                    <p class="text-slate-400 text-sm">Ad dollars flow back into local communities instead of billionaire media empires.</p>
                </div>
                <div class="relative bg-slate-800/50 rounded-xl p-6 border border-slate-700 text-center">
                    <div class="text-4xl mb-3">📊</div>
                    <h4 class="font-bold text-white mb-2">Advertisers Get Results</h4>
                    <p class="text-slate-400 text-sm">Motivated viewers deliver higher engagement and better ROI than passive impressions.</p>
                </div>
                <div class="relative bg-slate-800/50 rounded-xl p-6 border border-slate-700 text-center">
                    <div class="text-4xl mb-3">🌱</div>
                    <h4 class="font-bold text-white mb-2">Sustainable Economy</h4>
                    <p class="text-slate-400 text-sm">A healthier economic cycle where consumer purchasing power is continuously restored.</p>
                </div>
            </div>

            <!-- Solves the Problem -->
            <div class="relative bg-gradient-to-r from-emerald-500/10 to-teal-500/10 rounded-2xl p-10 border border-emerald-500/20 text-center">
                <h3 class="text-3xl font-bold mb-4">Unite Us Solves the Problem</h3>
                <p class="text-lg text-slate-300 max-w-3xl mx-auto leading-relaxed mb-6">
                    Unite Us restores what AI and mass layoffs have taken away: <strong class="text-white">income, dignity, and choice</strong>. When people are paid for their attention, they regain the ability to survive, spend, and thrive — and advertisers benefit from an audience that actually listens.
                </p>
                <p class="text-2xl font-bold text-emerald-400">Humans must UNITE to survive.</p>
            </div>
        </div>
    </section>

    <!-- Who Can Join -->
    <section class="relative py-24 bg-slate-900">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <span class="text-emerald-400 font-semibold text-sm tracking-wider uppercase">— Membership</span>
                <h2 class="mt-4 text-4xl sm:text-5xl font-bold">
                    Who Can <span class="text-emerald-400">Join?</span>
                </h2>
                <p class="mt-4 text-slate-400 text-sm">(Currently available in the United States only.)</p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <div class="relative bg-gradient-to-br from-slate-800 to-slate-900 rounded-2xl p-8 border border-slate-700 hover:border-teal-500/50 transition duration-300 text-center">
                    <div class="w-16 h-16 bg-gradient-to-br from-teal-500 to-blue-500 rounded-xl flex items-center justify-center text-3xl mx-auto mb-6 shadow-lg shadow-teal-500/30">
                        👤
                    </div>
                    <h3 class="text-xl font-bold mb-3">Viewers</h3>
                    <p class="text-slate-300 text-sm leading-relaxed">Anyone in the United States with a smartphone or computer. No cost to sign up. Start earning immediately.</p>
                </div>

                <div class="relative bg-gradient-to-br from-slate-800 to-slate-900 rounded-2xl p-8 border border-emerald-500/40 text-center shadow-lg shadow-emerald-500/10">
                    <div class="w-16 h-16 bg-gradient-to-br from-emerald-500 to-teal-500 rounded-xl flex items-center justify-center text-3xl mx-auto mb-6 shadow-lg shadow-emerald-500/30">
                        📢
                    </div>
                    <h3 class="text-xl font-bold mb-3">Advertisers</h3>
                    <p class="text-slate-300 text-sm leading-relaxed">Businesses, politicians, organizations, and individuals. Reach motivated consumers who are paid to pay attention to your message.</p>
                </div>

                <div class="relative bg-gradient-to-br from-slate-800 to-slate-900 rounded-2xl p-8 border border-slate-700 hover:border-purple-500/50 transition duration-300 text-center">
                    <div class="w-16 h-16 bg-gradient-to-br from-purple-500 to-pink-500 rounded-xl flex items-center justify-center text-3xl mx-auto mb-6 shadow-lg shadow-purple-500/30">
                        🤝
                    </div>
                    <h3 class="text-xl font-bold mb-3">Promoters</h3>
                    <p class="text-slate-300 text-sm leading-relaxed">Anyone seeking residual income and a higher ROI. Earn 10% by referring advertisers or other viewers to the platform.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact -->
    <section class="relative py-24 bg-gradient-to-b from-slate-800 to-slate-900">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <span class="text-emerald-400 font-semibold text-sm tracking-wider uppercase">— Contact Us</span>
            <h2 class="mt-4 text-4xl sm:text-5xl font-bold mb-4">
                We Are <span class="text-emerald-400">Real People</span>
            </h2>
            <p class="text-xl text-slate-300 mb-10">
                Not automated systems. Let us know what you think — chat with us or leave a comment.
            </p>

            <div class="flex flex-col sm:flex-row items-center justify-center gap-4 mb-12">
                <a href="https://docs.google.com/forms/d/e/1FAIpQLScWgfrJPf1HCxFUsHYH8pOdhKPV4wjDkRw_STP5xkKHINvD7w/viewform?usp=header"
                   target="_blank" rel="noopener noreferrer"
                   class="inline-flex items-center justify-center px-8 py-4 text-lg font-semibold text-white bg-gradient-to-r from-emerald-500 to-teal-500 rounded-xl hover:from-emerald-600 hover:to-teal-600 transition shadow-2xl shadow-emerald-500/40 hover:-translate-y-0.5 transform">
                    Let's Unite! Share Your Thoughts
                    <svg class="ml-2 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                    </svg>
                </a>
            </div>

            <div class="flex items-center justify-center gap-6">
                <a href="https://www.facebook.com/cakeee123" target="_blank" rel="noopener noreferrer"
                   class="flex items-center space-x-2 text-slate-400 hover:text-blue-400 transition text-sm">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                    <span>Facebook</span>
                </a>
                <a href="https://www.youtube.com/" target="_blank" rel="noopener noreferrer"
                   class="flex items-center space-x-2 text-slate-400 hover:text-red-400 transition text-sm">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M23.495 6.205a3.007 3.007 0 0 0-2.088-2.088c-1.87-.501-9.396-.501-9.396-.501s-7.507-.01-9.396.501A3.007 3.007 0 0 0 .527 6.205a31.247 31.247 0 0 0-.522 5.805 31.247 31.247 0 0 0 .522 5.783 3.007 3.007 0 0 0 2.088 2.088c1.868.502 9.396.502 9.396.502s7.506 0 9.396-.502a3.007 3.007 0 0 0 2.088-2.088 31.247 31.247 0 0 0 .5-5.783 31.247 31.247 0 0 0-.5-5.805zM9.609 15.601V8.408l6.264 3.602z"/></svg>
                    <span>YouTube</span>
                </a>
            </div>

            <div class="mt-12 pt-8 border-t border-slate-700 text-slate-400 text-sm">
                <p>Produced by: <strong class="text-slate-300">Head Enterprises, JEldon LLC, and HeadisHere</strong></p>
            </div>
        </div>
    </section>

    <!-- CTA -->
    @guest
    <section class="relative py-20 bg-slate-900">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-4xl font-bold mb-6">Ready to Join the Revolution?</h2>
            <p class="text-xl text-slate-300 mb-10 max-w-2xl mx-auto">
                Sign up FREE today. No cost. No catch. Start earning for your attention.
            </p>
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="{{ route('register') }}"
                   class="inline-flex items-center justify-center px-8 py-4 text-lg font-semibold text-white bg-gradient-to-r from-emerald-500 to-teal-500 rounded-xl hover:from-emerald-600 hover:to-teal-600 transition shadow-2xl shadow-emerald-500/40 hover:-translate-y-0.5 transform">
                    Create Free Account
                    <svg class="ml-2 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                    </svg>
                </a>
                <a href="{{ route('login') }}"
                   class="inline-flex items-center justify-center px-8 py-4 text-lg font-semibold text-white bg-slate-800 border border-slate-700 rounded-xl hover:bg-slate-700 transition">
                    Sign In
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
                        The transparent platform connecting politicians with engaged voters through paid video messages.
                    </p>
                </div>

                <div>
                    <h3 class="font-semibold mb-4">Platform</h3>
                    <ul class="space-y-2 text-slate-400 text-sm">
                        <li><a href="{{ url('/') }}#platform" class="hover:text-white transition">How It Works</a></li>
                        <li><a href="{{ url('/') }}#revenue" class="hover:text-white transition">Revenue Model</a></li>
                        <li><a href="{{ url('/') }}#how-it-works" class="hover:text-white transition">Features</a></li>
                        <li><a href="{{ route('about') }}" class="hover:text-white transition">About Us</a></li>
                    </ul>
                </div>

                <div>
                    <h3 class="font-semibold mb-4">Legal</h3>
                    <ul class="space-y-2 text-slate-400 text-sm">
                        <li><a href="https://www.ageofmentality.com/story/privacy-policy-for-age-of-mentality" target="_blank" rel="noopener noreferrer" class="hover:text-white transition">Privacy Policy</a></li>
                        <li><a href="#" class="hover:text-white transition">Terms of Service</a></li>
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
