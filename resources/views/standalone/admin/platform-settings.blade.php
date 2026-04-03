@extends('standalone.layouts.dashboard')

@section('title', 'Platform Settings')
@section('page-title', 'Platform Settings')

@section('content')
<div class="px-6 py-8 max-w-7xl mx-auto">
    {{-- Header --}}
    <div class="flex justify-between items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-white">Platform Settings</h1>
            <p class="text-slate-400 mt-1">Adjust pricing, commissions, and thresholds without code changes</p>
        </div>
        <form action="{{ route('admin.platform-settings.clear-cache') }}" method="POST">
            @csrf
            <button type="submit" class="bg-slate-700 hover:bg-slate-600 text-white px-4 py-2 rounded-lg transition text-sm">
                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Clear Cache
            </button>
        </form>
    </div>

    {{-- Success/Error Messages --}}
    @if(session('success'))
        <div class="bg-emerald-500/10 border border-emerald-500/30 rounded-lg px-4 py-3 mb-6">
            <p class="text-emerald-400 text-sm">{{ session('success') }}</p>
        </div>
    @endif

    @if($errors->any())
        <div class="bg-red-500/10 border border-red-500/30 rounded-lg px-4 py-3 mb-6">
            @foreach($errors->all() as $error)
                <p class="text-red-400 text-sm">{{ $error }}</p>
            @endforeach
        </div>
    @endif

    {{-- Current Values Overview --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-6">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 bg-blue-500/20 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                    </svg>
                </div>
                <div>
                    <p class="text-slate-400 text-xs">Revenue Per View</p>
                    <p class="text-white text-2xl font-bold">${{ number_format($currentValues['revenue_per_view'] ?? config('u9itus.revenue_per_view', 1.00), 2) }}</p>
                </div>
            </div>
            <p class="text-slate-500 text-xs">Charged to politicians</p>
        </div>

        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-6">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 bg-emerald-500/20 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-slate-400 text-xs">Voter Payout Per View</p>
                    <p class="text-white text-2xl font-bold">${{ number_format($currentValues['viewer_payout_per_view'] ?? config('u9itus.viewer_payout_per_view', 0.50), 2) }}</p>
                </div>
            </div>
            <p class="text-slate-500 text-xs">Paid to voters</p>
        </div>

        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-6">
            <div class="flex items-center gap-3 mb-3">
                <div class="w-10 h-10 bg-purple-500/20 rounded-lg flex items-center justify-center">
                    <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                </div>
                <div>
                    <p class="text-slate-400 text-xs">Referral Commission</p>
                    <p class="text-white text-2xl font-bold">{{ number_format($currentValues['referral_commission_percent'] ?? 10, 0) }}%</p>
                </div>
            </div>
            <p class="text-slate-500 text-xs">Of voter payout</p>
        </div>
    </div>

    {{-- Active Promotions --}}
    @if($activePromotions->isNotEmpty())
<div class="bg-amber-500/10 border border-amber-500/30 rounded-xl p-6 mb-8">
            <h2 class="text-lg font-semibold text-white mb-4">Active Promotions</h2>
            <div class="space-y-3">
                @foreach($activePromotions as $promo)
                    <div class="flex items-center justify-between bg-slate-800/50 rounded-lg p-4">
                        <div class="flex-1">
                            <p class="text-white font-medium">{{ $promo->key }}</p>
                            <p class="text-slate-400 text-sm">{{ $promo->description ?? 'No description' }}</p>
                            @if($promo->user_tier)
                                <span class="inline-block mt-2 px-2 py-1 bg-blue-500/20 text-blue-400 text-xs rounded">{{ ucfirst($promo->user_tier) }}</span>
                            @endif
                        </div>
                        <div class="text-right ml-4">
                            <p class="text-white font-bold text-lg">{{ $promo->getTypedValue() }}</p>
                            <p class="text-slate-500 text-xs">Expires {{ $promo->effective_until->diffForHumans() }}</p>
                        </div>
                        <form action="{{ route('admin.platform-settings.delete') }}" method="POST" class="ml-4">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="key" value="{{ $promo->key }}">
                            <input type="hidden" name="user_tier" value="{{ $promo->user_tier }}">
                            <button type="submit" class="text-red-400 hover:text-red-300 p-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    {{-- Settings Form Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Pricing Settings --}}
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-6">
            <h2 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"/>
                </svg>
                Pricing Settings
            </h2>
            
            @include('standalone.admin.partials.setting-form', [
                'key' => 'revenue_per_view',
                'label' => 'Revenue Per View',
                'description' => 'Amount charged to politicians per completed view',
                'type' => 'number',
                'step' => '0.01',
                'category' => 'pricing'
            ])

            @include('standalone.admin.partials.setting-form', [
                'key' => 'viewer_payout_per_view',
                'label' => 'Voter Payout Per View',
                'description' => 'Amount paid to voters per completed view',
                'type' => 'number',
                'step' => '0.01',
                'category' => 'pricing'
            ])

            @include('standalone.admin.partials.setting-form', [
                'key' => 'min_payout_amount',
                'label' => 'Minimum Payout Threshold',
                'description' => 'Minimum balance before voter can request payout',
                'type' => 'number',
                'step' => '0.01',
                'category' => 'pricing'
            ])
        </div>

        {{-- Referral Settings --}}
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-6">
            <h2 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                </svg>
                Referral Commissions
            </h2>

            @include('standalone.admin.partials.setting-form', [
                'key' => 'referral_commission_percent',
                'label' => 'Voter Referral Commission %',
                'description' => 'Percentage of voter payout given to referrer (recurring)',
                'type' => 'number',
                'step' => '1',
                'category' => 'referral'
            ])

            @include('standalone.admin.partials.setting-form', [
                'key' => 'procurement_commission_percent',
                'label' => 'Politician Referral Commission %',
                'description' => 'Percentage of first purchase given to referrer (one-time)',
                'type' => 'number',
                'step' => '1',
                'category' => 'referral'
            ])
        </div>

        {{-- Fraud Prevention --}}
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-6">
            <h2 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                Fraud Prevention
            </h2>

            @include('standalone.admin.partials.setting-form', [
                'key' => 'fraud_max_views_per_day',
                'label' => 'Max Views Per Voter Per Day',
                'description' => 'Daily view limit to prevent abuse',
                'type' => 'number',
                'category' => 'fraud'
            ])

            @include('standalone.admin.partials.setting-form', [
                'key' => 'fraud_payout_hold_hours',
                'label' => 'Payout Hold Period (hours)',
                'description' => 'Verification window before payouts are released',
                'type' => 'number',
                'category' => 'fraud'
            ])
        </div>

        {{-- Video Settings --}}
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-6">
            <h2 class="text-lg font-semibold text-white mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-pink-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
                Video Settings
            </h2>

            @include('standalone.admin.partials.setting-form', [
                'key' => 'min_video_duration',
                'label' => 'Minimum Video Duration (seconds)',
                'description' => 'Shortest allowed campaign video',
                'type' => 'number',
                'category' => 'video'
            ])

            @include('standalone.admin.partials.setting-form', [
                'key' => 'max_video_duration',
                'label' => 'Maximum Video Duration (seconds)',
                'description' => 'Longest allowed campaign video',
                'type' => 'number',
                'category' => 'video'
            ])
        </div>
    </div>

    {{-- Create Promotional Setting --}}
    <div class="mt-8 bg-gradient-to-br from-amber-500/10 to-orange-500/10 border border-amber-500/30 rounded-xl p-6">
        <h2 class="text-xl font-semibold text-white mb-2 flex items-center gap-2">
            <svg class="w-6 h-6 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/>
            </svg>
            Create Limited-Time Promotion
        </h2>
        <p class="text-slate-400 text-sm mb-6">Set up time-bound pricing for special campaigns or early adopter bonuses</p>

        <form action="{{ route('admin.platform-settings.update') }}" method="POST" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Setting Key</label>
                    <select name="key" required class="w-full bg-slate-700 border border-slate-600 rounded-lg px-4 py-2 text-white">
                        <option value="revenue_per_view">Revenue Per View</option>
                        <option value="viewer_payout_per_view">Voter Payout Per View</option>
                        <option value="referral_commission_percent">Referral Commission %</option>
                        <option value="procurement_commission_percent">Procurement Commission %</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Promotional Value</label>
                    <input type="number" name="value" step="0.01" required class="w-full bg-slate-700 border border-slate-600 rounded-lg px-4 py-2 text-white">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">User Tier (optional)</label>
                    <select name="user_tier" class="w-full bg-slate-700 border border-slate-600 rounded-lg px-4 py-2 text-white">
                        <option value="">All Users</option>
                        <option value="early_adopter">Early Adopters Only</option>
                        <option value="regular">Regular Users Only</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Description</label>
                    <input type="text" name="description" placeholder="e.g., Spring 2026 Promo" class="w-full bg-slate-700 border border-slate-600 rounded-lg px-4 py-2 text-white">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Effective From</label>
                    <input type="datetime-local" name="effective_from" data-calendar-picker="datetime" class="w-full bg-slate-700 border border-slate-600 rounded-lg px-4 py-2 text-white">
                </div>

                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Effective Until</label>
                    <input type="datetime-local" name="effective_until" data-calendar-picker="datetime" class="w-full bg-slate-700 border border-slate-600 rounded-lg px-4 py-2 text-white">
                </div>
            </div>

            <button type="submit" class="w-full bg-amber-600 hover:bg-amber-500 text-white font-semibold py-3 rounded-xl transition">
                Create Promotion
            </button>
        </form>
    </div>
</div>
@endsection
