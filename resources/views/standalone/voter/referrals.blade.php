@extends('layouts.app')

@section('title', 'My Referrals')

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">

    <h1 class="text-3xl font-bold text-white">My Referrals</h1>

    @if($voter)
    {{-- Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-5">
            <p class="text-slate-400 text-sm">Referral Code</p>
            <p class="text-2xl font-bold text-emerald-400 mt-1 tracking-widest">{{ $referralCode }}</p>
        </div>
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-5">
            <p class="text-slate-400 text-sm">Total Referrals</p>
            <p class="text-2xl font-bold text-white mt-1">{{ $referralCount }}</p>
        </div>
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-5">
            <p class="text-slate-400 text-sm">Referral Earnings</p>
            <p class="text-2xl font-bold text-purple-400 mt-1">${{ number_format($referralEarnings, 2) }}</p>
        </div>
    </div>

    {{-- Share Link --}}
    <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-6">
        <h2 class="text-lg font-semibold text-white mb-3">Share Your Referral Link</h2>
        <p class="text-slate-400 text-sm mb-4">
            Earn <strong class="text-emerald-400">10% commission</strong> ($0.025) on every view completed by friends you refer.
        </p>
        <div class="flex gap-2">
            <input
                id="referral-link"
                type="text"
                readonly
                value="{{ route('register.voter') }}?ref={{ $referralCode }}"
                class="flex-1 bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
            >
            <button onclick="copyLink()" class="bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2.5 rounded-lg text-sm font-medium transition whitespace-nowrap">
                Copy Link
            </button>
        </div>
        <p id="copy-confirm" class="text-emerald-400 text-sm mt-2 hidden">✓ Copied to clipboard!</p>
    </div>

    {{-- Referred voters table --}}
    @if($referredVoters->isNotEmpty())
    <div>
        <h2 class="text-lg font-semibold text-white mb-4">Referred Voters</h2>
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700 text-slate-400 text-left">
                        <th class="px-4 py-3 font-medium">Name</th>
                        <th class="px-4 py-3 font-medium">Joined</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/50">
                    @foreach($referredVoters as $referred)
                    <tr>
                        <td class="px-4 py-3 text-white">{{ $referred->full_name }}</td>
                        <td class="px-4 py-3 text-slate-400">{{ $referred->created_at?->format('M j, Y') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @else
    <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-8 text-center">
        <p class="text-slate-400">No referrals yet. Share your link to start earning commissions!</p>
    </div>
    @endif

    @else
    <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-10 text-center">
        <p class="text-slate-400">No voter profile found.</p>
    </div>
    @endif

</div>

@push('scripts')
<script>
function copyLink() {
    const input = document.getElementById('referral-link');
    navigator.clipboard.writeText(input.value).then(() => {
        document.getElementById('copy-confirm').classList.remove('hidden');
        setTimeout(() => document.getElementById('copy-confirm').classList.add('hidden'), 3000);
    });
}
</script>
@endpush
@endsection
