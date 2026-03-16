<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'U9itus') }} - The Virtual Town Hall</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800&display=swap" rel="stylesheet" />
    
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
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
    <!-- Navigation -->
    <nav class="fixed w-full z-50 bg-slate-900/90 backdrop-blur-lg border-b border-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-20">
                <div class="flex items-center space-x-2">
                    <div class="text-3xl font-light tracking-tight">
                        <span class="font-bold">U9</span><span class="text-emerald-400">itus</span>
                    </div>
                </div>
                
                <div class="hidden md:flex items-center space-x-8">
                    <a href="#platform" class="text-slate-300 hover:text-white transition text-sm font-medium">Journeys</a>
                    <a href="{{ route('politicians.directory') }}" class="text-slate-300 hover:text-white transition text-sm font-medium">Browse Candidates</a>
                    <a href="#revenue" class="text-slate-300 hover:text-white transition text-sm font-medium">Money Flow</a>
                    <a href="#how-it-works" class="text-slate-300 hover:text-white transition text-sm font-medium">Transparency</a>
                    <a href="{{ route('about') }}" class="text-slate-300 hover:text-white transition text-sm font-medium">About Us</a>
                </div>
                
                @if (Route::has('login'))
                    <div class="flex items-center space-x-4">
                        @auth
                            <a href="{{ url('/dashboard') }}" 
                               class="inline-flex items-center justify-center px-5 py-2.5 text-sm font-medium text-white bg-gradient-to-r from-emerald-500 to-teal-500 rounded-lg hover:from-emerald-600 hover:to-teal-600 transition shadow-lg shadow-emerald-500/30">
                                <span class="mr-2">👤</span> Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}" 
                               class="text-slate-300 hover:text-white transition text-sm font-medium">
                                Sign In
                            </a>
                            
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

    <!-- Hero Section -->
    <section class="relative min-h-screen flex items-center justify-center overflow-hidden">
        <!-- Gradient Background -->
        <div class="absolute inset-0 bg-gradient-to-br from-slate-900 via-slate-800 to-teal-900"></div>
        
        <!-- Animated Gradient Overlay -->
        <div class="absolute inset-0 bg-gradient-to-tr from-emerald-500/10 via-transparent to-teal-500/10"></div>
        
        <!-- Grid Pattern -->
        <div class="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.02)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.02)_1px,transparent_1px)] bg-[size:72px_72px]"></div>
        
        <!-- Content -->
        <div class="relative z-10 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-32 text-center">
            <div class="space-y-8">
                <div class="animate-fade-in-up opacity-0">
                    <span class="inline-block px-4 py-1.5 bg-emerald-500/10 border border-emerald-500/20 rounded-full text-emerald-400 text-sm font-medium mb-6">
                        — THE VIRTUAL TOWN HALL
                    </span>
                </div>
                
                <h1 class="animate-fade-in-up delay-100 opacity-0 text-5xl sm:text-6xl lg:text-7xl font-bold tracking-tight">
                    One Place For Voters To<br/>
                    <span class="bg-gradient-to-r from-emerald-400 to-teal-400 bg-clip-text text-transparent">Learn, Verify, and Earn</span>
                </h1>
                
                <p class="animate-fade-in-up delay-200 opacity-0 text-xl sm:text-2xl text-slate-300 max-w-3xl mx-auto leading-relaxed">
                    U9itus unites politicians and voters with paid, verified engagement and public accountability data at district level.
                </p>
                
                <div class="animate-fade-in-up delay-300 opacity-0 flex flex-col sm:flex-row items-center justify-center gap-4 pt-8">
                    @guest
                        <a href="{{ route('register') }}" 
                           class="inline-flex items-center justify-center px-8 py-4 text-lg font-semibold text-white bg-gradient-to-r from-emerald-500 to-teal-500 rounded-xl hover:from-emerald-600 hover:to-teal-600 transition shadow-2xl shadow-emerald-500/40 hover:shadow-emerald-500/60 hover:-translate-y-0.5 transform">
                            Get Started
                            <svg class="ml-2 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                            </svg>
                        </a>
                    @endguest

                    <a href="{{ route('politicians.directory') }}"
                       class="inline-flex items-center justify-center px-8 py-4 text-lg font-semibold text-white bg-slate-800 border border-slate-700 rounded-xl hover:bg-slate-700 transition">
                        Browse Candidates
                    </a>
                    
                    <a href="#platform" 
                       class="inline-flex items-center justify-center px-8 py-4 text-lg font-semibold text-white bg-slate-800 border border-slate-700 rounded-xl hover:bg-slate-700 transition">
                        See The Journey
                    </a>
                </div>
                
                <!-- Stats -->
                <div class="animate-fade-in-up delay-400 opacity-0 grid grid-cols-3 gap-8 pt-16 max-w-3xl mx-auto">
                    <div>
                        <div class="text-4xl font-bold text-emerald-400">$1.00</div>
                        <div class="text-sm text-slate-400 mt-1">Politician Pays</div>
                    </div>
                    <div>
                        <div class="text-4xl font-bold text-teal-400">$0.50</div>
                        <div class="text-sm text-slate-400 mt-1">Voter Earns</div>
                    </div>
                    <div>
                        <div class="text-4xl font-bold text-blue-400">$0.50</div>
                        <div class="text-sm text-slate-400 mt-1">Platform Keeps</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Scroll Indicator -->
        <div class="absolute bottom-8 left-1/2 -translate-x-1/2 animate-bounce">
            <a href="#platform" class="text-slate-400 hover:text-white transition">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
                </svg>
            </a>
        </div>
    </section>

    <!-- Platform Section -->
    <section id="platform" class="relative py-24 bg-slate-900">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <span class="text-emerald-400 font-semibold text-sm tracking-wider uppercase">— Phase 1 & 2</span>
                <h2 class="mt-4 text-4xl sm:text-5xl font-bold">
                    The Two Journeys Inside <span class="text-emerald-400">U9itus</span>
                </h2>
            </div>
            
            <div class="grid md:grid-cols-2 gap-8">
                <!-- Card 1 -->
                <div class="group relative bg-gradient-to-br from-slate-800 to-slate-900 rounded-2xl p-8 border border-slate-700 hover:border-emerald-500/50 transition duration-300 overflow-hidden">
                    <div class="absolute top-0 right-0 w-64 h-64 bg-emerald-500/5 rounded-full blur-3xl group-hover:bg-emerald-500/10 transition"></div>
                    <div class="relative">
                        <div class="w-16 h-16 bg-gradient-to-br from-emerald-500 to-teal-500 rounded-xl flex items-center justify-center text-3xl mb-6 shadow-lg shadow-emerald-500/30">
                            🎯
                        </div>
                        <h3 class="text-2xl font-bold mb-4">Politician Journey</h3>
                        <p class="text-slate-300 leading-relaxed mb-6">
                            Get on platform, load credits, introduce yourself, answer real voter questions, and target voters by district and governance level.
                        </p>
                        <ul class="space-y-3">
                            <li class="flex items-center text-slate-300">
                                <svg class="w-5 h-5 mr-3 text-emerald-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                P1: Create account and office profile
                            </li>
                            <li class="flex items-center text-slate-300">
                                <svg class="w-5 h-5 mr-3 text-emerald-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                P2: Load credits with transparent post-fee balance
                            </li>
                            <li class="flex items-center text-slate-300">
                                <svg class="w-5 h-5 mr-3 text-emerald-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                P3-P5: Intro video, Q&A videos, district targeting
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Card 2 -->
                <div class="group relative bg-gradient-to-br from-slate-800 to-slate-900 rounded-2xl p-8 border border-slate-700 hover:border-teal-500/50 transition duration-300 overflow-hidden">
                    <div class="absolute top-0 right-0 w-64 h-64 bg-teal-500/5 rounded-full blur-3xl group-hover:bg-teal-500/10 transition"></div>
                    <div class="relative">
                        <div class="w-16 h-16 bg-gradient-to-br from-teal-500 to-blue-500 rounded-xl flex items-center justify-center text-3xl mb-6 shadow-lg shadow-teal-500/30">
                            💰
                        </div>
                        <h3 class="text-2xl font-bold mb-4">Voter Journey</h3>
                        <p class="text-slate-300 leading-relaxed mb-6">
                            Discover candidates before signup, then watch verified campaign messages and topic-based Q&A videos to earn while staying informed.
                        </p>
                        <ul class="space-y-3">
                            <li class="flex items-center text-slate-300">
                                <svg class="w-5 h-5 mr-3 text-teal-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                V1-V2: Enter address and browse district candidates
                            </li>
                            <li class="flex items-center text-slate-300">
                                <svg class="w-5 h-5 mr-3 text-teal-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                V3-V5: Sign up, watch intro, then watch Q&A by topic
                            </li>
                            <li class="flex items-center text-slate-300">
                                <svg class="w-5 h-5 mr-3 text-teal-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                Earn from verified views and referral activity
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Revenue Model -->
    <section id="revenue" class="relative py-24 bg-gradient-to-b from-slate-900 to-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <span class="text-emerald-400 font-semibold text-sm tracking-wider uppercase">— Per Verified View</span>
                <h2 class="mt-4 text-4xl sm:text-5xl font-bold">
                    How Money <span class="text-emerald-400">Moves</span>
                </h2>
                <p class="mt-4 text-xl text-slate-300 max-w-2xl mx-auto">
                    No impressions. No CPM. Every dollar maps to a real person who watched.
                </p>
            </div>

            <div class="relative bg-gradient-to-br from-slate-800/50 to-slate-900/50 backdrop-blur-sm rounded-3xl p-8 md:p-12 border border-slate-700">
                <!-- Large Number -->
                <div class="absolute top-0 left-0 text-[200px] font-bold text-slate-700/5 leading-none">
                    $
                </div>
                
                <div class="relative space-y-8">
                    <!-- Money Flow Breakdown -->
                    <div class="grid md:grid-cols-3 gap-4">
                        <div class="p-6 bg-slate-800/60 rounded-xl border border-slate-700 text-center">
                            <p class="text-slate-400 text-sm uppercase tracking-wide">Politician Pays</p>
                            <p class="text-4xl font-bold text-white mt-2">$1.00</p>
                        </div>
                        <div class="p-6 bg-gradient-to-br from-emerald-900/30 to-teal-900/30 rounded-xl border border-emerald-500/30 text-center">
                            <p class="text-emerald-300 text-sm uppercase tracking-wide">Voter Earns</p>
                            <p class="text-4xl font-bold text-emerald-400 mt-2">$0.50</p>
                        </div>
                        <div class="p-6 bg-slate-800/60 rounded-xl border border-slate-700 text-center">
                            <p class="text-slate-400 text-sm uppercase tracking-wide">Platform Keeps</p>
                            <p class="text-4xl font-bold text-blue-400 mt-2">$0.50</p>
                        </div>
                    </div>

                    <div class="bg-gradient-to-r from-emerald-500/10 to-teal-500/10 rounded-xl p-8 border border-emerald-500/20 text-center">
                        <div class="text-sm text-emerald-400 font-semibold mb-2">VERIFIED VIEW STANDARD</div>
                        <div class="text-xl font-semibold text-white">10-second heartbeat + one-time secure token + 24-hour expiry</div>
                        <div class="text-slate-300 mt-2">Every payout is tied to confirmed watch behavior.</div>
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
                        <p class="text-slate-300">Surveys, referrals (10%), and simple cash-out thresholds keep voters engaged.</p>
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
                Ready to Get Started?
            </h2>
            <p class="text-xl text-slate-300 mb-10 max-w-2xl mx-auto">
                Join the future of political engagement. Whether you're a politician looking to reach voters or a voter ready to earn, we've got you covered.
            </p>
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                <a href="{{ route('register') }}" 
                   class="inline-flex items-center justify-center px-8 py-4 text-lg font-semibold text-white bg-gradient-to-r from-emerald-500 to-teal-500 rounded-xl hover:from-emerald-600 hover:to-teal-600 transition shadow-2xl shadow-emerald-500/40 hover:shadow-emerald-500/60 hover:-translate-y-0.5 transform">
                    Create Account
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
                        Unite the politician and the voter with paid engagement and transparent accountability.
                    </p>
                </div>
                
                <div>
                    <h3 class="font-semibold mb-4">Platform</h3>
                    <ul class="space-y-2 text-slate-400 text-sm">
                        <li><a href="#platform" class="hover:text-white transition">Journeys</a></li>
                        <li><a href="#revenue" class="hover:text-white transition">Money Flow</a></li>
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
