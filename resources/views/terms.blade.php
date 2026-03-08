<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Subscription Terms of Service — {{ config('app.name', 'U9itus') }}</title>
    <meta name="description" content="Read the complete Subscription Terms of Service for Head Enterprises U9itus.com — covering enrollment, commissions, payments, and your rights as a subscriber.">
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

        /* ToS typography helpers */
        .tos-section { @apply mb-10; }
        .tos-h2  { @apply text-xl font-bold text-white mb-3 mt-8; }
        .tos-body { @apply text-slate-300 text-sm leading-relaxed; }
        .tos-uppercase { @apply text-slate-200 font-semibold text-sm leading-relaxed; }
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
                Subscription <span class="bg-gradient-to-r from-emerald-400 to-teal-400 bg-clip-text text-transparent">Terms of Service</span>
            </h1>
            <p class="animate-fade-in-up delay-200 opacity-0 text-slate-400 text-sm">
                Head Enterprises &bull; U9itus.com &bull; JEldon LLC &bull; HeadisHere<br/>
                Last updated: {{ date('F j, Y') }}
            </p>
        </div>
    </section>

    <!-- Quick-jump TOC -->
    <section class="bg-slate-800/50 border-y border-slate-700 py-6 sticky top-20 z-40 backdrop-blur-sm">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 overflow-x-auto">
            <nav class="flex items-center space-x-6 text-xs whitespace-nowrap">
                <span class="text-slate-500 font-semibold uppercase tracking-wide flex-shrink-0">Jump to:</span>
                <a href="#enrollment"       class="text-slate-400 hover:text-emerald-400 transition">Enrollment</a>
                <a href="#links"            class="text-slate-400 hover:text-emerald-400 transition">Using Our Links</a>
                <a href="#orders"           class="text-slate-400 hover:text-emerald-400 transition">Orders</a>
                <a href="#sponsor-bonus"    class="text-slate-400 hover:text-emerald-400 transition">Sponsor Bonus</a>
                <a href="#ad-payments"      class="text-slate-400 hover:text-emerald-400 transition">Ad Payments</a>
                <a href="#license"          class="text-slate-400 hover:text-emerald-400 transition">License</a>
                <a href="#termination"      class="text-slate-400 hover:text-emerald-400 transition">Termination</a>
                <a href="#liability"        class="text-slate-400 hover:text-emerald-400 transition">Liability</a>
                <a href="#miscellaneous"    class="text-slate-400 hover:text-emerald-400 transition">Miscellaneous</a>
            </nav>
        </div>
    </section>

    <!-- Document Body -->
    <main class="relative py-20 bg-slate-900">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

            <!-- Opening Legal Notice -->
            <div class="mb-12 p-8 bg-gradient-to-br from-slate-800 to-slate-900 rounded-2xl border border-emerald-500/30">
                <p class="text-slate-300 text-sm leading-relaxed mb-4">
                    The purpose of this Agreement (hereafter referred to as the <strong class="text-white">"Agreement"</strong>) is to set forth Head Enterprises U9itus.com Subscription Terms and Conditions.
                </p>
                <p class="text-slate-300 text-sm leading-relaxed mb-6">
                    This Agreement contains the complete terms and conditions that apply to your participation as a subscriber in the Subscription Program of Head Enterprises, and the establishment of links from your subscriber web site to our web site domain. As used in this Agreement, <strong class="text-white">"we," "us," "our,"</strong> or <strong class="text-white">"Head Enterprises"</strong> means Head Enterprises, JEldon LLC, and HeadisHere; and <strong class="text-white">"you"</strong> or <strong class="text-white">"your"</strong> means the subscriber; and <strong class="text-white">"Product"</strong> means any and all items offered for sale by us on the Head Enterprises web site.
                </p>
                <div class="p-5 bg-amber-900/30 border border-amber-500/30 rounded-xl">
                    <p class="text-amber-200 text-sm font-semibold leading-relaxed uppercase">
                        THIS IS A LEGAL AGREEMENT BETWEEN YOU AND HEAD ENTERPRISES, JELDON LLC, AND HEADISHERE. BY CLICKING THE "I AGREE" BUTTON ON THE SUBSCRIPTION APPLICATION, YOU ARE AFFIRMATIVELY STATING THAT YOU HAVE READ THE SUBSCRIPTION AGREEMENT AND UNDERSTAND THE TERMS SET FORTH HEREIN AND ARE AFFIRMATIVELY INDICATING YOUR ACCEPTANCE OF THIS SUBSCRIPTION AGREEMENT AND YOU AGREE TO BE BOUND BY THE TERMS HEREOF.
                    </p>
                </div>
            </div>

            <!-- Divider -->
            <div class="border-t border-slate-800 mb-12"></div>

            <!-- Section 1: Enrollment -->
            <div id="enrollment" class="tos-section scroll-mt-36">
                <h2 class="tos-h2">1. Enrollment in the Subscription Program</h2>
                <p class="tos-body">
                    To begin the enrollment process, you will submit a completed Subscription Application via our web site. Once your application has been approved, you will receive your subscription code and password to allow you to start marketing Head Enterprises products. We may reject your application if we determine (in our sole discretion) that your site is unsuitable as a subscriber for any reason, including, but not limited to, if your site incorporates images or content that is in any way unlawful, harmful, threatening, defamatory, obscene, harassing or racially, ethically, or otherwise objectionable; such as sites that:
                </p>
                <ul class="mt-4 space-y-2 text-slate-300 text-sm">
                    <li class="flex items-start space-x-3">
                        <svg class="w-4 h-4 mt-0.5 flex-shrink-0 text-red-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                        <span>Facilitate illegal activity or depict sexually explicit images</span>
                    </li>
                    <li class="flex items-start space-x-3">
                        <svg class="w-4 h-4 mt-0.5 flex-shrink-0 text-red-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                        <span>Promote violence or discrimination based on race, sex, religion, nationality, disability, sexual orientation, or age</span>
                    </li>
                    <li class="flex items-start space-x-3">
                        <svg class="w-4 h-4 mt-0.5 flex-shrink-0 text-red-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                        <span>Incorporate any materials that infringe or assist others to infringe on any copyright, trademark, or other intellectual property rights (collectively "Content Restrictions")</span>
                    </li>
                </ul>
            </div>

            <!-- Section 2: Using Links -->
            <div id="links" class="tos-section scroll-mt-36">
                <h2 class="tos-h2">2. Using Our Links on Your Site</h2>
                <p class="tos-body mb-4">
                    <strong class="text-white">"Link"</strong> means a hyperlink to the Head Enterprises web site that is copied and pasted from your individual password-protected subscription administration area on our site. If the HTML code is altered in any way after copying from that web page, we take no responsibility for you receiving credit for any sale. Any change you make may cause the tracking to no longer function correctly.
                </p>
                <p class="tos-body mb-4">
                    As a subscriber site, we will make available to you banners, button links, and/or text links to our web site containing Head Enterprises logo and identifying words. By using the links, you agree that you will take full responsibility in maintaining all such links. All subscriber sites shall display such graphic images prominently throughout your site as you see fit and with our consent.
                </p>
                <p class="tos-body mb-4">
                    You shall not alter, modify, or expand the links in any way without our written consent. Each link connecting users of your web site to our web site will in no way alter the look, feel, or functionality of our web site. We have the right in our sole discretion to monitor your web site at any time to determine compliance with this Agreement.
                </p>
                <p class="tos-body">
                    You are allowed to use the prices of Head Enterprises products on your web site, but you are responsible for keeping your pricing information up-to-date as Head Enterprises may post specials, discounts, or change product pricing at their sole discretion.
                </p>
            </div>

            <!-- Section 3: Order Processing -->
            <div id="orders" class="tos-section scroll-mt-36">
                <h2 class="tos-h2">3. Order Processing</h2>
                <p class="tos-body mb-4">
                    We will be responsible for providing all information necessary to allow you to make appropriate links from your web site to our web site. However, all links must be approved by Head Enterprises. We will process orders placed by customers who follow the links from your web site to the Head Enterprises web site. We reserve the right to reject orders that do not comply with certain requirements that we may periodically establish.
                </p>
                <p class="tos-body mb-4">
                    We will be solely responsible for all aspects of order processing and fulfillment, including order entry, payment processing, shipping and handling, cancellations, returns, and related customer service. We will track the volume and amount of sales generated by your web site and will make reports available for your review through your subscription account on our web site.
                </p>
                <p class="tos-body">
                    To permit accurate tracking, reporting, and fee accrual, you must ensure that the links between your web site and our web site are properly formatted. It is your sole responsibility to ensure that the links you have placed on your web site are always working properly.
                </p>
            </div>

            <!-- Section 4: Sponsor Bonus -->
            <div id="sponsor-bonus" class="tos-section scroll-mt-36">
                <h2 class="tos-h2">4. Member Sponsor Bonus</h2>
                <p class="tos-body mb-4">
                    Member Sponsor Bonus ("Bonus Rate") on trackable online sales are paid on net sales — i.e., the net is the remaining amount after deductions for sales tax, duty, shipping, handling, credit card fees, and similar charges, and not including any portion of payment made through the redemption of gift certificates, coupons, or credits.
                </p>
                <p class="tos-body mb-4">
                    The Bonus Rate is subject to change at any time, in our sole and absolute discretion. You will be notified of any change in the Bonus Rate. Bonuses will also be reduced for amounts due to credit card fraud, bad debts, cancellations, chargebacks, and credits for returned goods.
                </p>
                <p class="tos-body mb-4">
                    A sponsoring bonus will be paid only if the visitor to our web site is tracked by the system from the time of the link to the time of the sale. No sponsoring bonus will be paid if the visitor cannot be tracked by our system.
                </p>
                <p class="tos-body mb-4">
                    Sponsor bonus payment times may be changed at the discretion of Head Enterprises. This information shall be posted within the members area of our U9itus.com website.
                </p>
                <p class="tos-body">
                    Our cookies are non-expiring, so repeat visitors who do not come directly from your web site will still count toward your Bonus if the cookie is not otherwise removed by the user. For a sale to generate a commission, the customer must follow the link from your web site to our web site, purchase the Product using our online ordering system, accept delivery of the item at the shipping destination, and remit full payment to us.
                </p>
            </div>

            <!-- Section 5: Ad Viewing Payments -->
            <div id="ad-payments" class="tos-section scroll-mt-36">
                <h2 class="tos-h2">5. Ad Viewing Sales Payments</h2>
                <div class="p-5 bg-emerald-900/20 border border-emerald-500/20 rounded-xl mb-5">
                    <p class="text-emerald-300 text-sm font-semibold">Key Payment Terms</p>
                    <ul class="mt-3 space-y-2 text-sm text-slate-300">
                        <li class="flex items-start space-x-3">
                            <svg class="w-4 h-4 mt-0.5 flex-shrink-0 text-emerald-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            <span>Commissions are paid <strong class="text-white">15 days after</strong> advertisements are viewed</span>
                        </li>
                        <li class="flex items-start space-x-3">
                            <svg class="w-4 h-4 mt-0.5 flex-shrink-0 text-emerald-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            <span>All commissions paid on the <strong class="text-white">15th and 30th of each month</strong>, with a five-day cutoff prior to disbursement</span>
                        </li>
                        <li class="flex items-start space-x-3">
                            <svg class="w-4 h-4 mt-0.5 flex-shrink-0 text-emerald-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            <span>All commission payments are made through <strong class="text-white">PayPal.com</strong> unless special arrangements are made</span>
                        </li>
                        <li class="flex items-start space-x-3">
                            <svg class="w-4 h-4 mt-0.5 flex-shrink-0 text-red-400" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                            <span>Head Enterprises is <strong class="text-white">not responsible</strong> for reissuing lost or missing payments past <strong class="text-white">90 days</strong> from payment date</span>
                        </li>
                    </ul>
                </div>
                <p class="tos-body mb-4">
                    Commission on sales are paid on net sales for viewing advertisements. Payments for viewing advertisements are not eligible for a commission due to credit card fraud, bad debts, cancellations, chargebacks, and credits for returned Products. If a commission has been paid on a reversed transaction, the commission will be deducted from future payments.
                </p>
                <p class="tos-body mb-4">
                    The commission base is subject to change at any time, in our sole and absolute discretion. You will be notified of any change in the commission base.
                </p>
                <p class="tos-body">
                    You agree that you are solely responsible for all tax obligations due to all taxing authorities arising from or in connection with your participation in our Subscription Program. Head Enterprises shall not withhold any taxes of any kind from your commission checks.
                </p>
            </div>

            <!-- Section 6: Reports -->
            <div class="tos-section scroll-mt-36">
                <h2 class="tos-h2">6. Reports of Sales</h2>
                <p class="tos-body">
                    You will be given a password and have the ability to enter a password-protected web site to receive your sales statistics on a daily basis.
                </p>
            </div>

            <!-- Section 7: Policies and Pricing -->
            <div class="tos-section scroll-mt-36">
                <h2 class="tos-h2">7. Policies and Pricing</h2>
                <p class="tos-body mb-4">
                    Subscribers who sponsor other subscribers or review telephone ads through the Subscription Program will be deemed to be subscribers of Head Enterprises. Accordingly, all Head Enterprises rules, policies, and operating procedures concerning customer orders, customer service, and sales will apply to those subscribers. We may change our policies and operating procedures at any time.
                </p>
                <p class="tos-body">
                    We will determine the prices to be charged for ads viewed in the Subscription Program in accordance with our own pricing policies. Viewing prices and availability may vary from time to time. We will use commercially reasonable efforts to present accurate information, but we cannot guarantee the availability or price of any particular ad view.
                </p>
            </div>

            <!-- Section 8: License -->
            <div id="license" class="tos-section scroll-mt-36">
                <h2 class="tos-h2">8. Non-Exclusive Limited License and Use of Head Enterprises Logos and Trademarks</h2>
                <p class="tos-body mb-4">
                    We grant you a non-exclusive, non-transferable, revocable right to (i) access our web site through links solely in accordance with the terms of this Agreement, and (ii) solely in connection with such links, to use our logos, trade names, trademarks, and similar identifying material (collectively <strong class="text-white">"Marks"</strong>), solely for the purpose of selling Product on your web site for Head Enterprises. You may not alter, modify, or change the Head Enterprises logos, trademarks, or any other text content provided to you through the Head Enterprises subscription section. The use of any logos, trademarks, or text content are only extended to members in good standing in the Head Enterprises Subscription Program.
                </p>
                <p class="tos-body mb-4">
                    If you wish to use logos, trademarked items, or text content not available in the marketing section, you may not use them without prior written permission. Permission is not to be construed as Head Enterprises giving you any legal ownership or rights to these logos, trademarks, or text content. Subscribers should assume that ONLY materials directly made available from Head Enterprises to subscribers for the purpose of selling product shall be acceptable for use.
                </p>
                <p class="tos-body mb-4">
                    The rights granted to you pursuant to this section shall terminate upon the effective date of the expiration or termination of this Agreement.
                </p>
                <p class="tos-body">
                    Additionally, we reserve the right to secure the highest position in pay-per-click and pay-per-position search engines. At no time shall you submit bids or use other methods that would cause listings for your site to rank higher than Head Enterprises rankings for any of its trademarks, sales marks, service marks, registered trademarks, or registered URLs.
                </p>
            </div>

            <!-- Section 9: Publicity & Spam -->
            <div class="tos-section scroll-mt-36">
                <h2 class="tos-h2">9. Publicity, Email, and Spam Policies</h2>
                <p class="tos-body mb-4">
                    You shall not create, publish, distribute, or permit any written material that makes reference to Head Enterprises without first submitting such material to us and receiving our written consent.
                </p>
                <div class="p-5 bg-red-900/20 border border-red-500/30 rounded-xl">
                    <p class="text-red-300 text-sm font-semibold mb-2">Zero Tolerance for Spam</p>
                    <p class="text-slate-300 text-sm leading-relaxed">
                        Head Enterprises will not tolerate any forms of Spam. We will hear both sides of a Spam complaint but we will remove one subscriber before we risk all subscribers losing email privileges. In the event a subscriber is charged with spamming practices, Head Enterprises shall not be held liable for any legal action taken against said subscriber, nor be financially responsible for fines owed by said subscriber.
                    </p>
                </div>
            </div>

            <!-- Section 10: Responsibility -->
            <div class="tos-section scroll-mt-36">
                <h2 class="tos-h2">10. Responsibility for Your Site</h2>
                <p class="tos-body mb-4">
                    You will be solely responsible for the development, operation, and maintenance of your web site and for all materials that appear on your web site. We shall have no responsibility for the development, operation, and maintenance of your web site or for any materials that appear on your web site.
                </p>
                <p class="tos-body">
                    You hereby represent and warrant to us that materials posted on your web site do not violate or infringe upon the rights of any third party (including, for example, copyrights, trademarks, privacy, or other personal or proprietary rights), and that materials posted on your web site are not libelous or otherwise illegal. We will not be responsible if you use copyrighted material from another party in violation of the law.
                </p>
            </div>

            <!-- Section 11: Term & Termination -->
            <div id="termination" class="tos-section scroll-mt-36">
                <h2 class="tos-h2">11. Term of the Agreement</h2>
                <p class="tos-body mb-4">
                    The term of this Agreement will begin when your subscription application has been received by Head Enterprises through the Head Enterprises web site and you have accepted these Terms and Conditions. This Agreement will end when terminated by either party.
                </p>
                <p class="tos-body mb-4">
                    The Agreement may be terminated by Head Enterprises or the subscriber for any reason upon <strong class="text-white">thirty (30) days</strong> prior email or written notice, or immediately upon notice of any breach of the provisions of this Agreement. Upon termination you may no longer use Head Enterprises banners, images, content, trademarks, etc., on your web site, or provide hyperlinks to the Head Enterprises web site.
                </p>
                <p class="tos-body mb-4">
                    If this Agreement is terminated because you have violated the terms of this Agreement or if your web site becomes subject to the Content Restrictions, you are <strong class="text-white">not eligible</strong> to receive any commission payments, even for bonuses earned prior to the date of termination.
                </p>
                <p class="tos-body">
                    If this Agreement is terminated for any other reason, you are eligible to earn a bonus only on sponsoring and ad viewing occurring during the term of the Agreement. Bonuses earned through the date of termination will remain payable only if the related orders are not canceled or returned. We reserve the right to withhold your final payment for a reasonable time to ensure that the correct amount is paid.
                </p>
            </div>

            <!-- Section 12: Modification -->
            <div class="tos-section scroll-mt-36">
                <h2 class="tos-h2">12. Modification</h2>
                <p class="tos-body mb-4">
                    We may modify any of the terms and conditions contained in this Agreement at any time and in our sole discretion. Notice of any change by email to your address on our records, or the posting on our web site of a change notice or a new agreement, is considered sufficient notice of a modification.
                </p>
                <p class="tos-body mb-4">
                    Modifications may include, but are not limited to, changes in the scope of available commission fees, commission schedules, payment procedures, and Subscription Program rules. All such modifications shall take effect <strong class="text-white">48 hours</strong> after we serve notice as provided above, unless we indicate otherwise.
                </p>
                <p class="tos-body">
                    If any modification is unacceptable to you, your only recourse is to terminate this Agreement. Your continued participation in the Subscription Program following our posting of a change notice will constitute binding acceptance of the change.
                </p>
            </div>

            <!-- Section 13: Relationship of Parties -->
            <div class="tos-section scroll-mt-36">
                <h2 class="tos-h2">13. Relationship of Parties</h2>
                <p class="tos-body">
                    You and Head Enterprises are independent contractors, and nothing in this Agreement will create any partnership, joint venture, agency, franchise, sales representative, or employment relationship between the parties. You will have no authority to make or accept any offers or representations on our behalf. You will not make any statement, whether on your site or otherwise, that reasonably would contradict anything in this section.
                </p>
            </div>

            <!-- Section 14: Limitation of Liability -->
            <div id="liability" class="tos-section scroll-mt-36">
                <h2 class="tos-h2">14. Limitation of Liability</h2>
                <div class="p-5 bg-slate-800/60 border border-slate-600 rounded-xl">
                    <p class="tos-body mb-4">
                        We will not be liable for indirect, incidental, special, or consequential, punitive, or multiple damages, including without limitation any damages resulting from loss of use, loss of business, loss of revenue, loss of profits, or loss of data arising in connection with this Agreement, the Subscription Program, or Head Enterprises' performance of services or of any other obligations relating to the Agreement, even if we have been advised of the possibility of such damages.
                    </p>
                    <p class="tos-body">
                        Further, our aggregate liability arising with respect to this Agreement and the Subscription Program will not exceed the total Bonus paid or payable to you under this Agreement. The foregoing limitation of liability shall apply regardless of the cause of action under which such damages are sought.
                    </p>
                </div>
            </div>

            <!-- Section 15: Disclaimers -->
            <div class="tos-section scroll-mt-36">
                <h2 class="tos-h2">15. Disclaimers</h2>
                <p class="tos-body">
                    We make no express or implied warranties or representations with respect to the Subscription Program or any Product or other items sold through the Subscription Program (including, without limitation, warranties of fitness for a particular purpose, merchantability, non-infringement, or any implied warranties arising out of a course of performance, dealing, or trade usage). In addition, we make no representation that the operation of our web site will be uninterrupted or error-free, and we will not be liable for the consequences of any interruptions or errors.
                </p>
            </div>

            <!-- Section 16: Representations and Warranties -->
            <div class="tos-section scroll-mt-36">
                <h2 class="tos-h2">16. Representations and Warranties</h2>
                <p class="tos-body">
                    You hereby represent and warrant to us that this Agreement has been duly and validly executed and delivered by you and constitutes your legal, valid and binding obligation, enforceable against you in accordance with its terms; and that the execution, delivery and performance by you of this Agreement are within your legal capacity and power; have been duly authorized by all requisite action on your part; require the approval or consent of no other persons; and neither violate nor constitute a default under (i) the provision of any law, rule, regulation, order, judgment, or decree to which you are subject or which is binding upon you, or (ii) the terms of any other agreement, document, or instrument applicable to you or binding upon you.
                </p>
            </div>

            <!-- Section 17: Confidentiality -->
            <div class="tos-section scroll-mt-36">
                <h2 class="tos-h2">17. Confidentiality</h2>
                <p class="tos-body mb-4">
                    We may disclose to you certain information as a result of your participation in the Subscription Program, which information we consider to be confidential (<strong class="text-white">"Confidential Information"</strong>). Confidential Information shall include, but not be limited to, any modifications to the terms and provisions of this Subscription Program Agreement made specifically for your site, web site, business and financial information, customer and vendor lists, and pricing and sales information relating to Head Enterprises and any members of the Subscription Program other than you.
                </p>
                <p class="tos-body">
                    You agree not to disclose any Confidential Information and that such information shall remain strictly confidential and secret and shall not be utilized, directly or indirectly, by you for your own business purposes or for any other purpose, except to the extent that any such information is generally known or available to the public, or if required by law or legal process.
                </p>
            </div>

            <!-- Section 18: Indemnification -->
            <div class="tos-section scroll-mt-36">
                <h2 class="tos-h2">18. Indemnification</h2>
                <p class="tos-body">
                    You hereby agree to indemnify, defend, and hold harmless Head Enterprises, its shareholders, officers, directors, employees, agents, subscribers, successors and assigns, from and against any and all claims, demands, losses, liabilities, damages or expenses (including attorney fees and costs) of any nature whatsoever incurred or suffered by us (collectively the <strong class="text-white">"Losses"</strong>), insofar as such Losses arise out of, are related to, or are based on (i) any claim or threatened claim that our use of the Subscription Trademarks infringes on the rights of any third party; (ii) the breach of any representation or warranty made by you herein; or (iii) any claim related to your web site.
                </p>
            </div>

            <!-- Section 19: Independent Investigation -->
            <div class="tos-section scroll-mt-36">
                <h2 class="tos-h2">19. Independent Investigation</h2>
                <div class="p-5 bg-slate-800/60 border border-slate-600 rounded-xl">
                    <p class="text-slate-200 text-sm font-semibold leading-relaxed uppercase">
                        YOU ACKNOWLEDGE THAT YOU HAVE READ THIS AGREEMENT AND AGREE TO ALL ITS TERMS AND CONDITIONS. YOU UNDERSTAND THAT WE MAY AT ANY TIME (DIRECTLY OR INDIRECTLY) SOLICIT CUSTOMER REFERRALS ON TERMS THAT MAY DIFFER FROM THOSE CONTAINED IN THIS AGREEMENT OR OPERATE WEB SITES THAT ARE SIMILAR TO OR COMPETITIVE WITH YOUR WEB SITE. YOU HAVE INDEPENDENTLY EVALUATED THE DESIRABILITY OF PARTICIPATING IN THE SUBSCRIPTION PROGRAM AND ARE NOT RELYING ON ANY REPRESENTATION, GUARANTEE, OR STATEMENT OTHER THAN AS SET FORTH IN THIS AGREEMENT.
                    </p>
                </div>
            </div>

            <!-- Section 20: Miscellaneous -->
            <div id="miscellaneous" class="tos-section scroll-mt-36">
                <h2 class="tos-h2">20. Miscellaneous</h2>
                <p class="tos-body mb-4">
                    This Agreement will be governed by the laws of the United States and the State of California, without reference to rules governing choice of laws. Any action relating to this Agreement must be brought in the federal or state courts located in <strong class="text-white">Riverside, California</strong>, and you irrevocably consent to the jurisdiction of such courts.
                </p>
                <p class="tos-body mb-4">
                    You may not assign this Agreement, by operation of law or otherwise, without our prior written consent. Subject to that restriction, this Agreement will be binding on, inure to the benefit of, and enforceable against the parties and their respective successors and assigns.
                </p>
                <p class="tos-body">
                    Our failure to enforce your strict performance of any provision of this Agreement will not constitute a waiver of our right to subsequently enforce such provision or any other provision of this Agreement.
                </p>
            </div>

            <!-- Closing Legal Notice -->
            <div class="mt-16 p-8 bg-gradient-to-br from-slate-800 to-slate-900 rounded-2xl border border-amber-500/30">
                <div class="p-5 bg-amber-900/30 border border-amber-500/30 rounded-xl mb-6">
                    <p class="text-amber-200 text-sm font-semibold leading-relaxed uppercase">
                        THIS IS A LEGAL AGREEMENT BETWEEN YOU AND HEAD ENTERPRISES, JELDON LLC, AND HEADISHERE. BY CLICKING THE "I ACCEPT" BUTTON IN THE SUBSCRIPTION APPLICATION, YOU ARE AFFIRMATIVELY STATING THAT YOU HAVE READ AND UNDERSTAND THE TERMS SET FORTH HEREIN AND ARE AFFIRMATIVELY INDICATING YOUR ACCEPTANCE OF THIS SUBSCRIPTION NETWORK AGREEMENT AND YOU AGREE TO BE BOUND BY THE TERMS HEREOF.
                    </p>
                </div>
                <p class="text-slate-300 text-sm text-center mb-6">If you agree, sign up as a Head Enterprises subscriber!</p>

                @guest
                <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
                    <a href="{{ route('register') }}"
                       class="inline-flex items-center justify-center px-8 py-4 text-lg font-semibold text-white bg-gradient-to-r from-emerald-500 to-teal-500 rounded-xl hover:from-emerald-600 hover:to-teal-600 transition shadow-2xl shadow-emerald-500/40 hover:-translate-y-0.5 transform">
                        Create Free Account &amp; Accept
                        <svg class="ml-2 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                        </svg>
                    </a>
                </div>
                @endguest

                <p class="text-slate-500 text-xs text-center mt-6">
                    Produced by: Head Enterprises &bull; JEldon LLC &bull; HeadisHere
                </p>
            </div>
        </div>
    </main>

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
                        <li><a href="{{ route('privacy-policy') }}" class="hover:text-white transition">Privacy Policy</a></li>
                        <li><a href="{{ route('terms') }}" class="text-emerald-400 transition">Terms of Service</a></li>
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
