@extends('standalone.layouts.dashboard')

@section('title', 'Analytics')
@section('page-title', 'Analytics')

@section('content')
<div class="space-y-6">

    {{-- Overview stats --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Total Views</p>
            <p class="text-2xl font-bold text-white">{{ number_format($totalViews) }}</p>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Total Spent</p>
            <p class="text-2xl font-bold text-white">${{ number_format($totalSpent, 2) }}</p>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Total Budget</p>
            <p class="text-2xl font-bold text-white">${{ number_format($totalBudget, 2) }}</p>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Active Campaigns</p>
            <p class="text-2xl font-bold text-emerald-400">{{ $activeCampaigns }}</p>
        </div>
    </div>

    {{-- Campaign breakdown table --}}
    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-700/50">
            <h3 class="text-sm font-semibold text-slate-200">Campaign Breakdown</h3>
        </div>

        @if($campaigns->isEmpty())
            <p class="text-slate-500 text-sm text-center py-10">No campaigns yet.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-slate-700/50">
                            <th class="text-left px-5 py-2.5 text-xs font-medium text-slate-500">Campaign</th>
                            <th class="text-left px-5 py-2.5 text-xs font-medium text-slate-500">Status</th>
                            <th class="text-right px-5 py-2.5 text-xs font-medium text-slate-500">Views</th>
                            <th class="text-right px-5 py-2.5 text-xs font-medium text-slate-500">Target</th>
                            <th class="text-right px-5 py-2.5 text-xs font-medium text-slate-500">Spent</th>
                            <th class="text-right px-5 py-2.5 text-xs font-medium text-slate-500">Budget</th>
                            <th class="text-right px-5 py-2.5 text-xs font-medium text-slate-500">Completion</th>
                            <th class="px-5 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/30">
                        @foreach($campaigns as $campaign)
                        @php
                            $status = $campaign->status?->value ?? $campaign->status ?? 'draft';
                            $statusColor = match($status) {
                                'active'           => 'bg-emerald-500/15 text-emerald-400',
                                'paused'           => 'bg-yellow-500/15 text-yellow-400',
                                'completed'        => 'bg-slate-500/15 text-slate-300',
                                'pending_approval' => 'bg-blue-500/15 text-blue-400',
                                'cancelled'        => 'bg-red-500/15 text-red-400',
                                default            => 'bg-slate-700 text-slate-400',
                            };
                            $pct = $campaign->total_views_requested > 0
                                ? min(100, round($campaign->views_completed / $campaign->total_views_requested * 100))
                                : 0;
                        @endphp
                        <tr class="hover:bg-slate-700/20 transition">
                            <td class="px-5 py-3">
                                <p class="font-medium text-slate-200 truncate max-w-[200px]">{{ $campaign->title }}</p>
                                <p class="text-xs text-slate-500">{{ ucfirst($campaign->campaign_type?->value ?? $campaign->campaign_type) }}</p>
                            </td>
                            <td class="px-5 py-3">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium {{ $statusColor }}">
                                    {{ ucfirst(str_replace('_', ' ', $status)) }}
                                </span>
                            </td>
                            <td class="px-5 py-3 text-right text-slate-300 font-mono">{{ number_format($campaign->views_completed) }}</td>
                            <td class="px-5 py-3 text-right text-slate-400 font-mono">{{ number_format($campaign->total_views_requested) }}</td>
                            <td class="px-5 py-3 text-right text-slate-300 font-mono">${{ number_format($campaign->amount_spent, 2) }}</td>
                            <td class="px-5 py-3 text-right text-slate-400 font-mono">${{ number_format($campaign->total_budget, 2) }}</td>
                            <td class="px-5 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <div class="w-16 h-1.5 bg-slate-700 rounded-full overflow-hidden">
                                        <div class="h-full bg-emerald-500 rounded-full" style="width: {{ $pct }}%"></div>
                                    </div>
                                    <span class="text-xs text-slate-400 w-8 text-right">{{ $pct }}%</span>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-right">
                                <a href="{{ route('politician.analytics.campaign', $campaign) }}"
                                   class="text-xs text-emerald-400 hover:text-emerald-300">Detail →</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

</div>
@endsection
