{{-- ========================================================================
     Earnings Calculator Widget — Interactive slider-based calculator
     Shows potential earnings from ads, voter referrals, and politician bonuses
     ======================================================================== --}}
@php
    // Economic constants from config
    $payoutPerView = config('u9itus.viewer_payout_per_view', 0.25);
    $voterCommissionPercent = config('u9itus.referral_commission_percent', 10);
    $politicianCommissionPercent = config('u9itus.procurement_commission_percent', 10);
    
    // Assumptions for calculations
    $avgAdsPerReferredVoter = 3; // Assume each referred voter watches 3 ads/day
    $avgPoliticianFirstPurchase = 100; // Assume $100 average first purchase
@endphp

<div x-data="{
    // Input values
    adsPerDay: 3,
    voterReferrals: 5,
    politicianReferrals: 0,
    
    // Constants
    payoutPerView: {{ $payoutPerView }},
    voterCommissionPercent: {{ $voterCommissionPercent }},
    politicianCommissionPercent: {{ $politicianCommissionPercent }},
    avgAdsPerReferredVoter: {{ $avgAdsPerReferredVoter }},
    avgPoliticianFirstPurchase: {{ $avgPoliticianFirstPurchase }},
    
    // Computed earnings
    get dailyAdEarnings() {
        return this.adsPerDay * this.payoutPerView;
    },
    get monthlyAdEarnings() {
        return this.dailyAdEarnings * 30;
    },
    get monthlyVoterCommission() {
        // Each referral watches avgAdsPerReferredVoter ads/day
        // You earn voterCommissionPercent% of their payout
        const dailyCommissionPerReferral = this.avgAdsPerReferredVoter * this.payoutPerView * (this.voterCommissionPercent / 100);
        return this.voterReferrals * dailyCommissionPerReferral * 30;
    },
    get politicianBonus() {
        // One-time bonus per politician
        return this.politicianReferrals * this.avgPoliticianFirstPurchase * (this.politicianCommissionPercent / 100);
    },
    get totalMonthly() {
        return this.monthlyAdEarnings + this.monthlyVoterCommission;
    },
    get totalWithBonus() {
        return this.totalMonthly + this.politicianBonus;
    },
    format(num) {
        return '$' + num.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }
}" class="bg-gradient-to-br from-purple-900/30 via-slate-800/40 to-slate-800/60 border border-purple-500/20 rounded-2xl p-6 space-y-6">

    {{-- Header --}}
    <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-purple-500/20 flex items-center justify-center shrink-0">
            <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
            </svg>
        </div>
        <div>
            <h3 class="text-white font-semibold text-base">Earnings Calculator</h3>
            <p class="text-slate-400 text-xs">Estimate your potential monthly income</p>
        </div>
    </div>

    {{-- Sliders Section --}}
    <div class="space-y-5">
        
        {{-- Ads per day slider --}}
        <div>
            <div class="flex items-center justify-between mb-2">
                <span class="text-slate-300 text-sm font-medium">Ads watched per day</span>
                <span class="text-emerald-400 font-bold text-sm font-mono" x-text="adsPerDay"></span>
            </div>
            <input type="range" min="0" max="50" step="1" x-model.number="adsPerDay"
                class="w-full h-2 bg-slate-700 rounded-lg appearance-none cursor-pointer
                       [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:w-4 [&::-webkit-slider-thumb]:h-4
                       [&::-webkit-slider-thumb]:bg-emerald-500 [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:cursor-pointer
                       [&::-moz-range-thumb]:w-4 [&::-moz-range-thumb]:h-4 [&::-moz-range-thumb]:bg-emerald-500
                       [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:border-0 [&::-moz-range-thumb]:cursor-pointer">
            <div class="flex justify-between text-[10px] text-slate-500 mt-1">
                <span>0</span>
                <span>50</span>
            </div>
        </div>

        {{-- Voter referrals slider --}}
        <div>
            <div class="flex items-center justify-between mb-2">
                <span class="text-slate-300 text-sm font-medium">Voter referrals</span>
                <span class="text-emerald-400 font-bold text-sm font-mono" x-text="voterReferrals"></span>
            </div>
            <input type="range" min="0" max="100" step="1" x-model.number="voterReferrals"
                class="w-full h-2 bg-slate-700 rounded-lg appearance-none cursor-pointer
                       [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:w-4 [&::-webkit-slider-thumb]:h-4
                       [&::-webkit-slider-thumb]:bg-emerald-500 [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:cursor-pointer
                       [&::-moz-range-thumb]:w-4 [&::-moz-range-thumb]:h-4 [&::-moz-range-thumb]:bg-emerald-500
                       [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:border-0 [&::-moz-range-thumb]:cursor-pointer">
            <div class="flex justify-between text-[10px] text-slate-500 mt-1">
                <span>0</span>
                <span>100</span>
            </div>
            <p class="text-slate-500 text-[10px] mt-1.5">Assuming each referral watches {{ $avgAdsPerReferredVoter }} ads/day</p>
        </div>

        {{-- Politician referrals slider --}}
        <div>
            <div class="flex items-center justify-between mb-2">
                <span class="text-slate-300 text-sm font-medium">Politician referrals</span>
                <span class="text-amber-400 font-bold text-sm font-mono" x-text="politicianReferrals"></span>
            </div>
            <input type="range" min="0" max="10" step="1" x-model.number="politicianReferrals"
                class="w-full h-2 bg-slate-700 rounded-lg appearance-none cursor-pointer
                       [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:w-4 [&::-webkit-slider-thumb]:h-4
                       [&::-webkit-slider-thumb]:bg-amber-500 [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:cursor-pointer
                       [&::-moz-range-thumb]:w-4 [&::-moz-range-thumb]:h-4 [&::-moz-range-thumb]:bg-amber-500
                       [&::-moz-range-thumb]:rounded-full [&::-moz-range-thumb]:border-0 [&::-moz-range-thumb]:cursor-pointer">
            <div class="flex justify-between text-[10px] text-slate-500 mt-1">
                <span>0</span>
                <span>10</span>
            </div>
            <p class="text-slate-500 text-[10px] mt-1.5">One-time bonus (avg ${{ number_format($avgPoliticianFirstPurchase) }} first purchase)</p>
        </div>

    </div>

    {{-- Divider --}}
    <div class="border-t border-slate-700"></div>

    {{-- Results Section --}}
    <div class="space-y-3">
        
        {{-- Direct ad earnings --}}
        <div class="flex items-center justify-between py-2">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-500/60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.882v6.236a1 1 0 01-1.447.894L15 14M3 8a2 2 0 00-2 2v4a2 2 0 002 2h9a2 2 0 002-2v-4a2 2 0 00-2-2H3z"/>
                </svg>
                <span class="text-slate-300 text-sm">Ad views (monthly)</span>
            </div>
            <span class="text-emerald-400 font-bold text-sm font-mono" x-text="format(monthlyAdEarnings)"></span>
        </div>

        {{-- Voter commission --}}
        <div class="flex items-center justify-between py-2">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-emerald-500/60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                <span class="text-slate-300 text-sm">Voter referrals (monthly)</span>
            </div>
            <span class="text-emerald-400 font-bold text-sm font-mono" x-text="format(monthlyVoterCommission)"></span>
        </div>

        {{-- Politician bonus --}}
        <div class="flex items-center justify-between py-2">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 text-amber-500/60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-2 5h2a2 2 0 002-2v-2a2 2 0 00-2-2h-2a2 2 0 00-2 2v2a2 2 0 002 2z"/>
                </svg>
                <span class="text-slate-300 text-sm">Politician bonuses (one-time)</span>
            </div>
            <span class="text-amber-400 font-bold text-sm font-mono" x-text="format(politicianBonus)"></span>
        </div>

        {{-- Divider before totals --}}
        <div class="border-t border-slate-600/50 pt-3 mt-2"></div>

        {{-- Monthly recurring total --}}
        <div class="flex items-center justify-between py-2 bg-slate-700/30 rounded-lg px-3">
            <span class="text-white font-semibold text-sm">Monthly Recurring</span>
            <span class="text-purple-400 font-bold text-lg font-mono" x-text="format(totalMonthly)"></span>
        </div>

        {{-- Total with bonuses --}}
        <div class="flex items-center justify-between py-2 bg-purple-900/30 border border-purple-500/30 rounded-lg px-3">
            <div>
                <span class="text-white font-semibold text-sm">Total (with bonuses)</span>
                <p class="text-slate-400 text-[10px] mt-0.5">First month estimate</p>
            </div>
            <span class="text-purple-300 font-bold text-xl font-mono" x-text="format(totalWithBonus)"></span>
        </div>

    </div>

    {{-- Footer note --}}
    <div class="bg-slate-900/40 border border-slate-700/50 rounded-lg p-3">
        <p class="text-slate-400 text-[11px] leading-relaxed">
            <svg class="w-3.5 h-3.5 text-slate-500 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <strong class="text-slate-300">Estimates only.</strong> Actual earnings depend on campaign availability, referral activity, and politician spending patterns. Voter commissions are recurring monthly; politician bonuses are one-time per recruit.
        </p>
    </div>

</div>
