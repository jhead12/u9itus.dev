@extends('layouts.app')

@section('title', 'Notification Preferences')

@section('content')
<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

    <h1 class="text-3xl font-bold text-white">Notification Preferences</h1>

    @if(session('success'))
    <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm rounded-lg px-4 py-3">
        {{ session('success') }}
    </div>
    @endif

    @if($voter)
    <form action="{{ route('voter.preferences.update') }}" method="POST">
        @csrf

        <div class="bg-slate-800/50 border border-slate-700 rounded-xl divide-y divide-slate-700/50">

            {{-- Email notifications --}}
            <div class="flex items-start justify-between px-6 py-5">
                <div>
                    <p class="text-white font-medium">Email Notifications</p>
                    <p class="text-slate-400 text-sm mt-0.5">New ad opportunities, earnings summaries, payout confirmations</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer ml-6 mt-0.5">
                    <input type="checkbox" name="notify_email" value="1"
                        {{ old('notify_email', $preferences['notify_email'] ?? true) ? 'checked' : '' }}
                        class="sr-only peer">
                    <div class="w-11 h-6 bg-slate-600 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-emerald-500 rounded-full peer
                        peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                        after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all
                        peer-checked:bg-emerald-600"></div>
                </label>
            </div>

            {{-- SMS notifications --}}
            <div class="flex items-start justify-between px-6 py-5">
                <div>
                    <p class="text-white font-medium">SMS Notifications</p>
                    <p class="text-slate-400 text-sm mt-0.5">Text alerts when new ads matching your preferences are available</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer ml-6 mt-0.5">
                    <input type="checkbox" name="notify_sms" value="1"
                        {{ old('notify_sms', $preferences['notify_sms'] ?? false) ? 'checked' : '' }}
                        class="sr-only peer">
                    <div class="w-11 h-6 bg-slate-600 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-emerald-500 rounded-full peer
                        peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                        after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all
                        peer-checked:bg-emerald-600"></div>
                </label>
            </div>

            {{-- Weekly digest --}}
            <div class="flex items-start justify-between px-6 py-5">
                <div>
                    <p class="text-white font-medium">Weekly Earnings Digest</p>
                    <p class="text-slate-400 text-sm mt-0.5">A Sunday summary of your week's activity and earnings</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer ml-6 mt-0.5">
                    <input type="checkbox" name="notify_weekly_digest" value="1"
                        {{ old('notify_weekly_digest', $preferences['notify_weekly_digest'] ?? true) ? 'checked' : '' }}
                        class="sr-only peer">
                    <div class="w-11 h-6 bg-slate-600 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-emerald-500 rounded-full peer
                        peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                        after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all
                        peer-checked:bg-emerald-600"></div>
                </label>
            </div>

            {{-- Payout alerts --}}
            <div class="flex items-start justify-between px-6 py-5">
                <div>
                    <p class="text-white font-medium">Payout Alerts</p>
                    <p class="text-slate-400 text-sm mt-0.5">Notify me when my balance is ready for a payout request</p>
                </div>
                <label class="relative inline-flex items-center cursor-pointer ml-6 mt-0.5">
                    <input type="checkbox" name="notify_payout_ready" value="1"
                        {{ old('notify_payout_ready', $preferences['notify_payout_ready'] ?? true) ? 'checked' : '' }}
                        class="sr-only peer">
                    <div class="w-11 h-6 bg-slate-600 peer-focus:outline-none peer-focus:ring-2 peer-focus:ring-emerald-500 rounded-full peer
                        peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px]
                        after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all
                        peer-checked:bg-emerald-600"></div>
                </label>
            </div>

        </div>

        <div class="mt-6 flex justify-end">
            <button type="submit" class="bg-emerald-600 hover:bg-emerald-500 text-white px-6 py-2.5 rounded-lg font-medium transition">
                Save Preferences
            </button>
        </div>
    </form>

    @else
    <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-10 text-center">
        <p class="text-slate-400">No voter profile found.</p>
    </div>
    @endif

</div>
@endsection
