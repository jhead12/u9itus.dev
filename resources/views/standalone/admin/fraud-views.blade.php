@extends('standalone.layouts.dashboard')

@section('title', 'Flagged Views')
@section('page-title', 'Flagged View Sessions')

@section('content')
<div class="space-y-6">

    @if(session('success'))
    <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm rounded-lg px-4 py-3">
        {{ session('success') }}
    </div>
    @endif

    <div>
        <a href="{{ route('admin.fraud.index') }}" class="text-sm text-slate-400 hover:text-white transition">← Back to fraud dashboard</a>
    </div>

    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-700/50">
            <h3 class="text-sm font-semibold text-white">All Flagged Sessions</h3>
            <p class="text-xs text-slate-500 mt-0.5">{{ $sessions->total() }} sessions with fraud score &gt; 50</p>
        </div>

        @if($sessions->isEmpty())
        <div class="px-5 py-10 text-center">
            <p class="text-sm text-slate-500">No flagged sessions found.</p>
        </div>
        @else
        <div class="divide-y divide-slate-700/30">
            @foreach($sessions as $session)
            <div class="px-5 py-4 space-y-3">
                {{-- Session info row --}}
                <div class="flex flex-col sm:flex-row sm:items-start gap-4">
                    <div class="flex-1 min-w-0 space-y-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-xs font-mono text-slate-400">#{{ $session->id }}</span>
                            <span class="text-xs font-bold {{ $session->fraud_score > 80 ? 'text-red-400' : 'text-amber-400' }}">
                                Score: {{ number_format($session->fraud_score, 1) }}
                            </span>
                            @php $st = $session->status instanceof \BackedEnum ? $session->status->value : $session->status; @endphp
                            <span class="text-xs px-2 py-0.5 rounded-full bg-slate-700 text-slate-300">{{ $st }}</span>
                            @if($session->review_action)
                            <span class="text-xs px-2 py-0.5 rounded-full {{ $session->review_action === 'cleared' ? 'bg-emerald-500/10 text-emerald-400' : ($session->review_action === 'voided' ? 'bg-red-500/10 text-red-400' : 'bg-slate-700 text-slate-400') }}">
                                Reviewed: {{ $session->review_action }}
                            </span>
                            @endif
                        </div>
                        <p class="text-xs text-slate-400">
                            Voter: <span class="text-slate-300">{{ $session->voter?->user?->email ?? 'ID #' . $session->voter_id }}</span>
                            @if($session->campaign)
                             · Campaign: <span class="text-slate-300">{{ $session->campaign->title }}</span>
                            @endif
                        </p>
                        @if($session->fraud_flags && count((array) $session->fraud_flags) > 0)
                        <div class="flex gap-1 flex-wrap">
                            @foreach((array) $session->fraud_flags as $flag)
                            <span class="text-xs px-1.5 py-0.5 rounded bg-red-900/30 text-red-400 border border-red-800/30">{{ $flag }}</span>
                            @endforeach
                        </div>
                        @endif
                        <p class="text-xs text-slate-500">
                            Payout: ${{ number_format($session->voter_payout_amount, 2) }}
                            · {{ $session->created_at->format('M j, Y g:i a') }}
                        </p>
                    </div>

                    {{-- Review actions --}}
                    @if(!$session->review_action)
                    <div class="flex flex-col gap-1.5 shrink-0">
                        <form method="POST" action="{{ route('admin.fraud.review', $session->id) }}">
                            @csrf
                            <input type="hidden" name="action" value="cleared">
                            <button type="submit"
                                class="w-full px-3 py-1.5 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/20 text-xs font-semibold transition">
                                ✓ Clear (False Positive)
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.fraud.review', $session->id) }}">
                            @csrf
                            <input type="hidden" name="action" value="voided">
                            <button type="submit"
                                class="w-full px-3 py-1.5 rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/20 text-xs font-semibold transition">
                                ✗ Void (Confirmed Fraud)
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.fraud.review', $session->id) }}">
                            @csrf
                            <input type="hidden" name="action" value="confirmed">
                            <button type="submit"
                                class="w-full px-3 py-1.5 rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-300 text-xs font-semibold transition">
                                ⚑ Confirm Flag
                            </button>
                        </form>
                        @if($session->voter)
                        <form method="POST" action="{{ route('admin.fraud.clear-voter', $session->voter_id) }}">
                            @csrf
                            <button type="submit"
                                class="w-full px-3 py-1.5 rounded-lg bg-blue-500/10 hover:bg-blue-500/20 text-blue-400 border border-blue-500/20 text-xs font-semibold transition">
                                Clear Voter Flag
                            </button>
                        </form>
                        @endif
                    </div>
                    @else
                    <div class="text-xs text-slate-500 shrink-0">
                        Reviewed {{ $session->reviewed_at?->diffForHumans() }}
                    </div>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
        <div class="px-5 py-4 border-t border-slate-700/50">
            {{ $sessions->links() }}
        </div>
        @endif
    </div>

</div>
@endsection
