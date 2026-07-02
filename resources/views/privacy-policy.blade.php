<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Privacy Policy — {{ config('app.name', 'U9itus') }}</title>
    <meta name="description" content="Read the complete Privacy Policy for Head Enterprises U9itus.com — covering data collection, privacy practices, and how we protect your information.">
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
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up { animation: fadeInUp 0.6s ease-out forwards; }
        .delay-100 { animation-delay: 0.1s; }
        .delay-200 { animation-delay: 0.2s; }

        /* Privacy Policy typography helpers */
        .pp-section { @apply mb-10; }
        .pp-h2  { @apply text-xl font-bold text-white mb-3 mt-8; }
        .pp-body { @apply text-slate-300 text-sm leading-relaxed; }
        .pp-uppercase { @apply text-slate-200 font-semibold text-sm leading-relaxed; }
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
    <section class="relative pt-28 pb-16 overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-slate-900 via-slate-800 to-teal-900"></div>
        <div class="absolute inset-0 bg-gradient-to-tr from-emerald-500/10 via-transparent to-teal-500/10"></div>
        <div class="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.02)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.02)_1px,transparent_1px)] bg-[size:72px_72px]"></div>

        <div class="relative z-10 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center space-y-5">
            <div class="animate-fade-in-up opacity-0">
                <span class="inline-block px-4 py-1.5 bg-emerald-500/10 border border-emerald-500/20 rounded-full text-emerald-400 text-sm font-medium">
                    — LEGAL
                </span>
            </div>
            <h1 class="animate-fade-in-up delay-100 opacity-0 text-4xl sm:text-5xl font-bold tracking-tight">
                <span class="bg-gradient-to-r from-emerald-400 to-teal-400 bg-clip-text text-transparent">Privacy Policy</span>
            </h1>
            <p class="animate-fade-in-up delay-200 opacity-0 text-slate-400 text-sm">
                Head Enterprises &bull; U9itus.com<br/>
                Last updated: {{ date('F j, Y') }}
            </p>
        </div>
    </section>

    <!-- Quick-jump TOC -->
    <section class="bg-slate-800/50 border-y border-slate-700 py-6 sticky top-20 z-40 backdrop-blur-sm">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 overflow-x-auto">
            <nav class="flex items-center space-x-6 text-xs whitespace-nowrap">
                <a href="#data-collection" class="text-slate-400 hover:text-emerald-400 transition">Data Collection</a>
                <a href="#privacy-practices" class="text-slate-400 hover:text-emerald-400 transition">Privacy Practices</a>
                <a href="#policy-updates" class="text-slate-400 hover:text-emerald-400 transition">Policy Updates</a>
                <a href="#third-party" class="text-slate-400 hover:text-emerald-400 transition">Third Party Services</a>
            </nav>
        </div>
    </section>

    <!-- Main content -->
    <section class="py-16 bg-slate-900">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

            <!-- Opening Legal Notice -->
            <div class="pp-section bg-slate-800/50 border border-slate-700 rounded-xl p-8">
                <div class="space-y-4">
                    <p class="pp-body">
                        Head Enterprises, et al maintains this Privacy Policy to inform you of our practices regarding the collection, use, and protection of your personal information when you use our website, services, or software.
                    </p>
                    <p class="pp-body">
                        By accessing or using U9itus.com and related services, you acknowledge that you have read, understood, and agree to be bound by this Privacy Policy and consent to the practices described herein.
                    </p>
                </div>
            </div>

            <!-- Content -->
            <div class="space-y-12 mt-12">

                <!-- Data Collection and Privacy -->
                <div id="data-collection" class="pp-section">
                    <h2 class="pp-h2">
                        <span class="text-emerald-400">§1.</span> Data Collection and Privacy
                    </h2>
                    <div class="space-y-4 bg-slate-800/30 border border-slate-700/50 rounded-lg p-6">
                        <p class="pp-body">
                            <strong class="text-white">Head Enterprises, et al</strong> does not collect personally identifiable information from you except to the extent you have explicitly given such information to Head Enterprises, et al.
                        </p>
                        <p class="pp-body">
                            Head Enterprises' et al information practices are further described in its privacy policy, which is available at: <a href="{{ route('privacy-policy') }}" class="text-emerald-400 hover:text-emerald-300 underline">U9itus.com/privacy-policy</a> (the "Privacy Policy").
                        </p>
                        <p class="pp-body">
                            The Privacy Policy is an integral part of this Agreement and is expressly incorporated by reference, and by entering into this Agreement you agree to:
                        </p>
                        <ul class="list-disc list-inside space-y-2 pl-4 text-slate-300 text-sm">
                            <li>All of the terms of the Privacy Policy</li>
                            <li>Head Enterprises' et al utilization of data as described in the Privacy Policy is not an actionable breach of your privacy or publicity rights</li>
                        </ul>
                    </div>
                </div>

                <!-- Policy Updates -->
                <div id="policy-updates" class="pp-section">
                    <h2 class="pp-h2">
                        <span class="text-emerald-400">§2.</span> Policy Updates and Revisions
                    </h2>
                    <div class="space-y-4 bg-slate-800/30 border border-slate-700/50 rounded-lg p-6">
                        <p class="pp-body">
                            Head Enterprises, et al may from time-to-time update or revise the Privacy Policy.
                        </p>
                        <p class="pp-body">
                            If Head Enterprises, et al updates or revises the Privacy Policy, Head Enterprises, et al will notify you either by:
                        </p>
                        <ul class="list-disc list-inside space-y-2 pl-4 text-slate-300 text-sm">
                            <li>Email to your most recently provided email address</li>
                            <li>Posting the updated or revised Privacy Policy on the Site</li>
                            <li>Any other manner chosen by Head Enterprises, et al in its commercially reasonable discretion</li>
                        </ul>
                        <p class="pp-body mt-4">
                            <strong class="text-white">Your use of the Site, Services or Software following any such update or revision constitutes your agreement to be bound by and comply with the Privacy Policy as updated or revised.</strong>
                        </p>
                    </div>
                </div>

                <!-- Third Party Services -->
                <div id="third-party" class="pp-section">
                    <h2 class="pp-h2">
                        <span class="text-emerald-400">§3.</span> Third Party Services and Fraud Prevention
                    </h2>
                    <div class="space-y-4 bg-slate-800/30 border border-slate-700/50 rounded-lg p-6">
                        <p class="pp-body">
                            In addition, Head Enterprises, et al may engage third parties to conduct risk control and fraud detection/prevention activities.
                        </p>
                        <p class="pp-body">
                            We use Stripe for risk and identity verification services. We share personally identifying information with Stripe, which analyzes and uses it to operate and improve the services it provides to us, including for risk evaluation and identity verification. You can learn more about Stripe and read its privacy policy <a href="https://stripe.com/privacy" target="_blank" rel="noopener noreferrer" class="text-emerald-400 hover:text-emerald-300 underline">here</a>.
                        </p>

                        <div class="bg-slate-900/50 border border-emerald-500/20 rounded-lg p-5 space-y-3">
                            <p class="text-emerald-300 text-sm font-semibold">Google Analytics</p>
                            <p class="pp-body">
                                We use Google Tag Manager to manage and deploy analytics and marketing scripts on our website. Google Tag Manager itself does not collect personal data, but the tags it deploys (such as Google Analytics) may.
                            </p>
                            <p class="pp-body">
                                We use Google Analytics to understand how visitors interact with our website. Google Analytics collects information such as how often you visit, which pages you view, and what site you came from. Google may use this data to personalize ads across its network. We do not upload any personally identifiable information (such as names or email addresses) to Google Analytics.
                            </p>
                            <p class="pp-body">
                                You can opt out of Google Analytics tracking by installing the <a href="https://tools.google.com/dlpage/gaoptout" target="_blank" rel="noopener noreferrer" class="text-emerald-400 hover:text-emerald-300 underline">Google Analytics Opt-out Browser Add-on</a>. For more information, see <a href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer" class="text-emerald-400 hover:text-emerald-300 underline">Google's Privacy Policy</a>.
                            </p>
                        </div>

                        <p class="pp-body">
                            As part of such engagements, if you initiate a transaction on the Site or through the Services, Head Enterprises, et al may give such third parties access to your pertinent credit card and other personal information.
                        </p>
                        
                        <div class="bg-slate-900/50 border border-emerald-500/20 rounded-lg p-5 space-y-3">
                            <p class="text-emerald-300 text-sm font-semibold">Third Party Data Usage</p>
                            <p class="pp-body">
                                Such third parties may only use such personal information for purposes of performing risk control and fraud detection/prevention activities for us.
                            </p>
                            <p class="pp-body">
                                However, they may also convert such personal information into hashed or encoded representations of such information to be used for statistical and/or fraud prevention purposes.
                            </p>
                        </div>

                        <p class="pp-body">
                            <strong class="text-white">By initiating any such transaction, you hereby consent to the foregoing disclosure and use of your information.</strong>
                        </p>
                    </div>
                </div>

                <!-- Privacy Practices -->
                <div id="privacy-practices" class="pp-section">
                    <h2 class="pp-h2">
                        <span class="text-emerald-400">§4.</span> Our Commitment to Your Privacy
                    </h2>
                    <div class="space-y-4 bg-slate-800/30 border border-slate-700/50 rounded-lg p-6">
                        <p class="pp-body">
                            We are committed to protecting your privacy and handling your data with care and transparency. The information we collect is used solely to provide and improve our services, ensure security, and communicate with you as necessary.
                        </p>
                        <p class="pp-body">
                            We implement appropriate technical and organizational measures to protect your personal information against unauthorized or unlawful processing, accidental loss, destruction, or damage.
                        </p>
                        <p class="pp-body">
                            For questions or concerns about this Privacy Policy or our data practices, please contact us through the channels provided on our website.
                        </p>
                    </div>
                </div>

            </div>

            <!-- Footer call-to-action -->
            <div class="mt-16 bg-gradient-to-r from-emerald-500/10 to-teal-500/10 border border-emerald-500/20 rounded-xl p-8 text-center space-y-4">
                <h3 class="text-xl font-bold text-white">Questions About Our Privacy Policy?</h3>
                <p class="text-slate-300 text-sm max-w-2xl mx-auto">
                    If you have any questions or concerns about how we handle your data, please don't hesitate to reach out to our team.
                </p>
                <a href="{{ url('/') }}" class="inline-flex items-center justify-center px-6 py-3 text-sm font-medium text-white bg-gradient-to-r from-emerald-500 to-teal-500 rounded-lg hover:from-emerald-600 hover:to-teal-600 transition shadow-lg shadow-emerald-500/30">
                    Return to Home
                </a>
            </div>

        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-slate-950 border-t border-slate-800 py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-10">
                <div>
                    <div class="text-2xl font-light mb-4">
                        <span class="font-bold">U9</span><span class="text-emerald-400">itus</span>
                    </div>
                    <p class="text-slate-400 text-sm">
                        Transform your digital presence into a revenue-generating asset.
                    </p>
                </div>

                <div>
                    <h3 class="font-semibold mb-4">Platform</h3>
                    <ul class="space-y-3 text-sm text-slate-400">
                        <li><a href="{{ url('/') }}#platform" class="hover:text-white transition">Features</a></li>
                        <li><a href="{{ url('/') }}#revenue" class="hover:text-white transition">Revenue Model</a></li>
                        <li><a href="{{ url('/') }}#how-it-works" class="hover:text-white transition">How It Works</a></li>
                    </ul>
                </div>

                <div>
                    <h3 class="font-semibold mb-4">Company</h3>
                    <ul class="space-y-3 text-sm text-slate-400">
                        <li><a href="{{ route('about') }}" class="hover:text-white transition">About Us</a></li>
                    </ul>
                </div>

                <div>
                    <h3 class="font-semibold mb-4">Legal</h3>
                    <ul class="space-y-3 text-sm text-slate-400">
                        <li><a href="{{ route('privacy-policy') }}" class="hover:text-white transition">Privacy Policy</a></li>
                        <li><a href="{{ route('terms') }}" class="hover:text-white transition">Terms of Service</a></li>
                    </ul>
                </div>
            </div>

            <div class="mt-12 pt-8 border-t border-slate-800 text-center text-slate-500 text-xs">
                <p>&copy; {{ date('Y') }} Head Enterprises, U9itus.com, JEldon LLC, HeadisHere. All rights reserved.</p>
            </div>
        </div>
    </footer>

</body>
</html>
