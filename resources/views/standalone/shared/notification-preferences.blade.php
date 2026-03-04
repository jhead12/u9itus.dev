<x-standalone-layout>
    <x-slot name="header">
        <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-white">
            Notification Preferences
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    @if (session('success'))
                        <div class="mb-4 p-4 bg-green-100 dark:bg-green-900/20 border border-green-400 dark:border-green-700 text-green-700 dark:text-green-300 rounded">
                            {{ session('success') }}
                        </div>
                    @endif

                    <p class="mb-6 text-sm text-gray-600 dark:text-gray-400">
                        Choose how you'd like to be notified about different events. You can customize your notification preferences for each channel.
                    </p>

                    <form method="POST" action="{{ route('notification-preferences.update') }}">
                        @csrf
                        @method('PUT')

                        <div class="space-y-8">
                            <!-- Email Notifications -->
                            <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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
                                </div>
                            </div>

                            <!-- In-App Notifications -->
                            <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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

                            <!-- Push Notifications -->
                            <div class="border-b border-gray-200 dark:border-gray-700 pb-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                    </svg>
                                    Push Notifications
                                    <span class="ml-2 text-xs bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300 px-2 py-1 rounded">Coming Soon</span>
                                </h3>
                                <div class="space-y-3">
                                    <x-preference-checkbox name="push_campaign_status" label="Campaign Status Changes" :checked="$preferences->push_campaign_status" :disabled="true" />
                                    <x-preference-checkbox name="push_payout_processed" label="Payout Processed" :checked="$preferences->push_payout_processed" :disabled="true" />
                                    <x-preference-checkbox name="push_low_balance" label="Low Balance Alerts" :checked="$preferences->push_low_balance" :disabled="true" />
                                    <x-preference-checkbox name="push_fraud_alert" label="Fraud Alerts" :checked="$preferences->push_fraud_alert" :disabled="true" />
                                    <x-preference-checkbox name="push_system_announcements" label="System Announcements" :checked="$preferences->push_system_announcements" :disabled="true" />
                                </div>
                            </div>

                            <!-- SMS Notifications -->
                            <div class="pb-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4 flex items-center">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke- linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                                    </svg>
                                    SMS Notifications
                                    <span class="ml-2 text-xs bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300 px-2 py-1 rounded">Coming Soon</span>
                                </h3>
                                
                                <div class="mb-4">
                                    <label for="phone_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                        Phone Number
                                    </label>
                                    <input 
                                        type="tel" 
                                        name="phone_number" 
                                        id="phone_number" 
                                        value="{{ old('phone_number', $preferences->phone_number) }}"
                                        class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100"
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
                            <a href="{{ url()->previous() }}" class="px-4 py-2 text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white">
                                Cancel
                            </a>
                            <button type="submit" class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg transition">
                                Save Preferences
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-standalone-layout>
