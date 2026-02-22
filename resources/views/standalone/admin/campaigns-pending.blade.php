@extends('standalone.layouts.dashboard')

@section('title', 'Pending Campaigns')
@section('page-title', 'Campaign Approval Queue')

@section('content')
<div class="space-y-6">

    @if(session('success'))
    <div class="bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm rounded-lg px-4 py-3">
        {{ session('success') }}
    </div>
    @endif

    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-700/50">
            <h3 class="text-sm font-semibold text-white">Campaigns Awaiting Review</h3>
            <p class="text-xs text-slate-500 mt-0.5">{{ $campaigns->total() }} campaign(s) pending approval</p>
        </div>

        @if($campaigns->isEmpty())
        <div class="px-5 py-12 text-center">
            <svg class="w-10 h-10 text-slate-600 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            <p class="text-sm text-slate-500">No campaigns pending approval.</p>
        </div>
        @else
        <div class="divide-y divide-slate-700/30">
            @foreach($campaigns as $campaign)
            <div class="px-5 py-4">
                <div class="flex flex-col sm:flex-row sm:items-start gap-4">
                    <div class="flex-1 min-w-0">
                        <h4 class="text-sm font-semibold text-white">{{ $campaign->title }}</h4>
                        <p class="text-xs text-slate-400 mt-0.5">
                            {{ $campaign->politician?->full_name ?? '—' }}
                            @if($campaign->politician?->political_office) · {{ $campaign->politician->political_office }} @endif
                            @if($campaign->politician?->state) · {{ $campaign->politician->state }} @endif
                        </p>
                        <div class="flex flex-wrap gap-3 mt-2 text-xs text-slate-500">
                            <span>Budget: <span class="text-white">${{ number_format($campaign->total_budget ?? 0, 2) }}</span></span>
                            <span>Target views: <span class="text-white">{{ number_format($campaign->target_views ?? 0) }}</span></span>
                            <span>Submitted: <span class="text-white">{{ $campaign->created_at->diffForHumans() }}</span></span>
                        </div>
                        @if($campaign->description)
                        <p class="text-xs text-slate-400 mt-2 line-clamp-2">{{ $campaign->description }}</p>
                        @endif
                    </div>
                    <div class="flex gap-2 shrink-0">
                        <form method="POST" action="{{ route('admin.campaigns.approve', $campaign) }}">
                            @csrf
                            <button type="submit"
                                class="px-3 py-1.5 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-white text-xs font-semibold transition">
                                Approve
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.campaigns.reject', $campaign) }}">
                            @csrf
                            <input type="hidden" name="reason" value="Does not meet content guidelines">
                            <button type="submit"
                                class="px-3 py-1.5 rounded-lg bg-red-500/10 hover:bg-red-500/20 text-red-400 text-xs font-semibold transition border border-red-500/20">
                                Reject
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        <div class="px-5 py-4 border-t border-slate-700/50">
            {{ $campaigns->links() }}
        </div>
        @endif
    </div>

</div>
@endsection
