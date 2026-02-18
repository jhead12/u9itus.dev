@extends('layouts.voter')

@section('title', 'My Referrals')

@section('content')
<div class="px-4 sm:px-6 lg:px-8 py-8 max-w-4xl mx-auto space-y-7">

    <div>
        <h1 class="text-2xl font-bold text-white">Referrals</h1>
        <p class="text-slate-400 text-sm mt-0.5">Earn 10% commission on every view your referred voters complete</p>
    </div>

    @if($voter)
    {{-- Stats --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl p-5">
            <p class="text-slate-400 text-xs font-medium uppercase tracking-wide">Referral Code</p>
            <p class="text-2xl font-bold text-emerald-400 mt-2 tracking-widest font-mono">{{ $voter->referral_code }}</p>
            <p class="text-slate-500 text-xs mt-1">Share with friends</p>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl p-5">
            <p class="text-slate-400 text-xs font-medium uppercase tracking-wide">Total Referrals</p>
            <p class="text-2xl font-bold text-white mt-2">{{ $referrals->count() }}</p>
            <p class="text-slate-500 text-xs mt-1">Registered voters</p>
        </div>
        <div class="bg-slate-800/50 border border-slate-700 rounded-xl p-5">
            <p class="text-slate-400 text-sm">Referral Earnings</p>
            <p class="text-2xl font-bold text-purple-400 mt-1">${{ number_format($totalReferralEarnings, 2) }}</p>
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
                value="{{ route('register.voter') }}?ref={{ $voter->referral_code }}"
                class="flex-1 bg-slate-900 border border-slate-600 rounded-lg px-4 py-2.5 text-slate-300 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
            >
            <button onclick="copyLink()" class="bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2.5 rounded-lg text-sm font-medium transition whitespace-nowrap">
                Copy Link
            </button>
        </div>
        <p id="copy-confirm" class="text-emerald-400 text-sm mt-2 hidden">✓ Copied to clipboard!</p>
    </div>

    {{-- Referred voters table --}}
    @if($referrals->isNotEmpty())
    <div>
        <h2 class="text-base font-semibold text-white mb-4">Referred Voters ({{ $referrals->count() }})</h2>
        <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700/60 text-slate-500 text-xs uppercase tracking-wide text-left">
                        <th class="px-5 py-3 font-medium">Name</th>
                        <th class="px-5 py-3 font-medium">Joined</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/40">
                    @foreach($referrals as $referred)
                    <tr class="hover:bg-slate-700/20 transition">
                        <td class="px-5 py-4">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-slate-700 flex items-center justify-center text-slate-300 text-xs font-bold shrink-0">
                                    {{ strtoupper(substr($referred->full_name ?? '?', 0, 1)) }}
                                </div>
                                <span class="text-white">{{ $referred->full_name ?? 'Anonymous' }}</span>
                            </div>
                        </td>
                        <td class="px-5 py-4 text-slate-400 text-sm">{{ $referred->created_at?->format('M j, Y') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @else
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl p-10 text-center">
        <div class="w-12 h-12 rounded-full bg-slate-700/50 flex items-center justify-center mx-auto mb-4">
            <svg class="w-6 h-6 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
        </div>
        <p class="text-slate-400 text-sm font-medium">No referrals yet</p>
        <p class="text-slate-600 text-xs mt-1">Share your link above to start earning commissions!</p>
    </div>
    @endif

    @else
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl p-10 text-center">
        <p class="text-slate-400">No voter profile found. Contact support.</p>
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
