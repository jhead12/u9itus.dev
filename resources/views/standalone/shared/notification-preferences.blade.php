@extends('standalone.layouts.dashboard')

@section('title', 'Notification Preferences')
@section('page-title', 'Notification Preferences')

@section('content')
<div class="space-y-6">

    @if (session('success'))
        <div class="px-5 py-3 bg-emerald-500/10 border border-emerald-500/30 rounded-xl text-sm text-emerald-300">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6">
        <p class="text-sm text-slate-400 mb-6">
            Choose how you'd like to be notified about different events. You can customize your notification preferences for each channel.
        </p>

        <form method="POST" action="{{ route('notification-preferences.update') }}">
            @csrf
            @method('PUT')

            <div class="space-y-8">
                {{-- Email Notifications --}}
                <div class="border-b border-slate-700/50 pb-6">
                    <h3 class="text-base font-semibold text-white mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        Email Notifications
                    </h3>
                    <div class="space-y-3">
                        <x-preference-checkbox name="email_campaign_status" label="Campaign Status Changes" :checked="$preferences->email_campaign_status" />
                        <x-preference-checkbox name="email_payout_processed" label="Payout Processed" :checked="$preferences->email_payout_processed" />
                        <x-preference-checkbox name="email_low_balance" label="Low Balance Alerts" :checked="$preferences->email_low_balance" />
                        <x-preference-checkbox name="email_fraud_alert" label="Fraud Alerts" :checked="$preferences->email_fraud_alert" />
                        <x-preference-checkbox name="email_system_announcements" label="System Announcements" :checked="$preferences->email_system_announcements" />
                        <x-preference-checkbox name="email_boundary_digest" label="Weekly Saved Places Digest" :checked="$preferences->email_boundary_digest" />
                    </div>
                </div>

                {{-- In-App Notifications --}}
                <div class="border-b border-slate-700/50 pb-6">
                    <h3 class="text-base font-semibold text-white mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        In-App Notifications
                    </h3>
                    <div class="space-y-3">
                        <x-preference-checkbox name="inapp_campaign_status" label="Campaign Status Changes" :checked="$preferences->inapp_campaign_status" />
                        <x-preference-checkbox name="inapp_payout_processed" label="Payout Processed" :checked="$preferences->inapp_payout_processed" />
                        <x-preference-checkbox name="inapp_low_balance" label="Low Balance Alerts" :checked="$preferences->inapp_low_balance" />
                        <x-preference-checkbox name="inapp_fraud_alert" label="Fraud Alerts" :checked="$preferences->inapp_fraud_alert" />
                        <x-preference-checkbox name="inapp_system_announcements" label="System Announcements" :checked="$preferences->inapp_system_announcements" />
                    </div>
                </div>

                {{-- Push Notifications --}}
                <div class="border-b border-slate-700/50 pb-6">
                    <h3 class="text-base font-semibold text-white mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                        Push Notifications
                        <span class="text-[11px] bg-amber-500/15 text-amber-300 px-2 py-0.5 rounded">Coming Soon</span>
                    </h3>
                    <div class="space-y-3">
                        <x-preference-checkbox name="push_campaign_status" label="Campaign Status Changes" :checked="$preferences->push_campaign_status" :disabled="true" />
                        <x-preference-checkbox name="push_payout_processed" label="Payout Processed" :checked="$preferences->push_payout_processed" :disabled="true" />
                        <x-preference-checkbox name="push_low_balance" label="Low Balance Alerts" :checked="$preferences->push_low_balance" :disabled="true" />
                        <x-preference-checkbox name="push_fraud_alert" label="Fraud Alerts" :checked="$preferences->push_fraud_alert" :disabled="true" />
                        <x-preference-checkbox name="push_system_announcements" label="System Announcements" :checked="$preferences->push_system_announcements" :disabled="true" />
                    </div>
                </div>

                {{-- SMS Notifications --}}
                <div>
                    <h3 class="text-base font-semibold text-white mb-4 flex items-center gap-2">
                        <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                        </svg>
                        SMS Notifications
                        <span class="text-[11px] bg-amber-500/15 text-amber-300 px-2 py-0.5 rounded">Coming Soon</span>
                    </h3>

                    <div class="mb-4">
                        <label for="phone_number" class="block text-sm font-medium text-slate-300 mb-2">
                            Phone Number
                        </label>
                        <input
                            type="tel"
                            name="phone_number"
                            id="phone_number"
                            value="{{ old('phone_number', $preferences->phone_number) }}"
                            class="w-full px-4 py-2 border border-slate-700 rounded-lg bg-slate-900/60 text-white placeholder-slate-600 focus:outline-none focus:ring-2 focus:ring-indigo-500/50"
                            placeholder="+1 (555) 123-4567"
                            disabled
                        >
                    </div>

                    <div class="space-y-3">
                        <x-preference-checkbox name="sms_campaign_status" label="Campaign Status Changes" :checked="$preferences->sms_campaign_status" :disabled="true" />
                        <x-preference-checkbox name="sms_payout_processed" label="Payout Processed" :checked="$preferences->sms_payout_processed" :disabled="true" />
                        <x-preference-checkbox name="sms_low_balance" label="Low Balance Alerts" :checked="$preferences->sms_low_balance" :disabled="true" />
                        <x-preference-checkbox name="sms_fraud_alert" label="Fraud Alerts" :checked="$preferences->sms_fraud_alert" :disabled="true" />
                        <x-preference-checkbox name="sms_system_announcements" label="System Announcements" :checked="$preferences->sms_system_announcements" :disabled="true" />
                    </div>
                </div>
            </div>

            <div class="mt-6 flex items-center justify-end gap-4">
                <a href="{{ url()->previous() }}" class="px-4 py-2 text-sm text-slate-300 hover:text-white transition">
                    Cancel
                </a>
                <button type="submit" class="px-5 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium rounded-lg transition">
                    Save Preferences
                </button>
            </div>
        </form>
    </div>

</div>
@endsection