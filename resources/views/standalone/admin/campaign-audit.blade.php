@extends('standalone.layouts.dashboard')

@section('title', 'Campaign Audit Log')
@section('page-title', 'Campaign Audit Log')

@section('content')
<div class="max-w-3xl mx-auto">

    <div class="mb-6 flex items-center gap-4">
        <a href="{{ route('admin.campaigns.edit', $campaign) }}" class="text-sm text-slate-400 hover:text-white transition">← Back to edit</a>
        <a href="{{ route('admin.campaigns.pending') }}" class="text-sm text-slate-400 hover:text-white transition">Pending campaigns</a>
    </div>

    {{-- Campaign header --}}
    <div class="mb-6 bg-slate-800/50 border border-slate-700/50 rounded-xl px-5 py-4">
        <h2 class="text-sm font-semibold text-white">{{ $campaign->title }}</h2>
        <p class="text-xs text-slate-400 mt-0.5">
            {{ $campaign->politician?->full_name ?? '—' }}
            @if($campaign->politician?->user?->email) · {{ $campaign->politician->user->email }} @endif
        </p>
        <div class="flex gap-2 mt-2">
            <span class="text-xs px-2 py-0.5 rounded-full bg-slate-700 text-slate-300">
                {{ ucwords(str_replace('_', ' ', $campaign->status?->value ?? $campaign->status)) }}
            </span>
            <span class="text-xs px-2 py-0.5 rounded-full bg-slate-700 text-slate-300">
                {{ ucfirst($campaign->approval_status?->value ?? $campaign->approval_status) }}
            </span>
        </div>
    </div>

    {{-- Log entries --}}
    @if($auditLogs->isEmpty())
    <div class="bg-slate-800/40 border border-slate-700/40 rounded-xl px-5 py-12 text-center">
        <p class="text-sm text-slate-500">No audit records for this campaign yet.</p>
    </div>
    @else
    <div class="space-y-3">
        @foreach($auditLogs as $log)
        @php $color = $log->actionColor(); @endphp
        <div class="bg-slate-800/40 border border-slate-700/40 rounded-xl px-5 py-4">
            <div class="flex items-center gap-3 flex-wrap">
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full
                    @if($color === 'emerald') bg-emerald-500/15 text-emerald-400
                    @elseif($color === 'red')  bg-red-500/15 text-red-400
                    @elseif($color === 'amber') bg-amber-500/15 text-amber-400
                    @else bg-slate-700 text-slate-300 @endif">
                    {{ $log->actionLabel() }}
                </span>
                <span class="text-xs text-slate-400">
                    by <span class="text-slate-200">{{ $log->admin?->name ?? 'Admin' }}</span>
                </span>
                <span class="text-xs text-slate-500 ml-auto" title="{{ $log->created_at->toDateTimeString() }}">
                    {{ $log->created_at->diffForHumans() }}
                </span>
            </div>

            @if($log->reason)
            <p class="text-xs text-slate-400 mt-2 bg-slate-900/40 rounded-lg px-3 py-2">
                <span class="text-slate-500">Reason: </span>{{ $log->reason }}
            </p>
            @endif

            @if($log->changes)
            <div class="mt-3 rounded-lg overflow-hidden border border-slate-700/40">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="bg-slate-900/60">
                            <th class="text-left px-3 py-1.5 text-slate-500 font-semibold uppercase tracking-wide">Field</th>
                            <th class="text-left px-3 py-1.5 text-slate-500 font-semibold uppercase tracking-wide">Before</th>
                            <th class="text-left px-3 py-1.5 text-slate-500 font-semibold uppercase tracking-wide">After</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-700/30">
                        @foreach($log->changes as $field => $change)
                        <tr>
                            <td class="px-3 py-1.5 text-slate-300">{{ str_replace('_', ' ', $field) }}</td>
                            <td class="px-3 py-1.5 text-red-400/80">{{ is_array($change['old']) ? implode(', ', $change['old']) : ($change['old'] ?? '—') }}</td>
                            <td class="px-3 py-1.5 text-emerald-400/80">{{ is_array($change['new']) ? implode(', ', $change['new']) : ($change['new'] ?? '—') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </div>
        @endforeach
    </div>

    <div class="mt-4">
        {{ $auditLogs->links() }}
    </div>
    @endif

</div>
@endsection
