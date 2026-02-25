@extends('standalone.layouts.dashboard')

@section('title', $campaign->title)
@section('page-title', 'Campaign Detail')

@section('content')
<div class="space-y-6">

    @php $status = $campaign->status?->value ?? $campaign->status ?? 'draft'; @endphp

    {{-- Credit error flash (from submitForReview credit gate) --}}
    @if($errors->has('credits'))
    <div class="bg-red-500/10 border border-red-500/30 rounded-xl px-5 py-4 flex items-start gap-3">
        <svg class="w-5 h-5 text-red-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
        <div class="flex-1 text-sm text-red-300">
            {{ $errors->first('credits') }}
            <a href="{{ route('politician.billing') }}" class="ml-2 font-semibold underline hover:text-red-200">Add Credits →</a>
        </div>
    </div>
    @endif

    {{-- Proactive low-balance notice for draft campaigns --}}
    @php $isInsufficientBalance = ($status === 'draft') && ($creditBalance < ($campaign->total_budget ?? 0)); @endphp
    @if($isInsufficientBalance && !$errors->has('credits'))
    <div class="bg-amber-500/10 border border-amber-500/30 rounded-xl px-5 py-4 flex items-start gap-3">
        <svg class="w-5 h-5 text-amber-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
        <div class="flex-1 text-sm text-amber-300">
            <strong>Insufficient credits.</strong>
            This campaign requires <strong>${{ number_format($campaign->total_budget, 2) }}</strong> but your balance is <strong>${{ number_format($creditBalance, 2) }}</strong>.
            <a href="{{ route('politician.billing') }}" class="ml-1 underline hover:text-amber-200">Add ${{ number_format(max(0, $campaign->total_budget - $creditBalance), 2) }} to submit →</a>
        </div>
    </div>
    @endif

    {{-- Back + actions --}}
    <div class="flex items-center gap-3">
        <a href="{{ route('politician.campaigns.index') }}" class="text-sm text-slate-400 hover:text-white transition">← Campaigns</a>
        <div class="flex-1"></div>

        @if(in_array($status, ['draft', 'paused', 'scheduled']))
            <a href="{{ route('politician.campaigns.edit', $campaign) }}"
               class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-300 hover:text-white bg-slate-700/50 hover:bg-slate-700 rounded-lg px-4 py-2 transition">
                Edit
            </a>
        @endif

        @if($status === 'draft' && ($campaign->media_url || $campaign->live_feed_url))
            @if($isInsufficientBalance)
                {{-- Disabled submit — balance too low --}}
                <a href="{{ route('politician.billing') }}"
                   class="inline-flex items-center gap-1.5 text-sm font-medium text-amber-400 hover:text-amber-300 bg-amber-500/10 hover:bg-amber-500/20 border border-amber-500/30 rounded-lg px-4 py-2 transition"
                   title="Add credits to submit this campaign">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                    Add Credits to Submit
                </a>
            @else
                <form method="POST" action="{{ route('politician.campaigns.submit-review', $campaign) }}" class="inline">
                    @csrf
                    <button type="submit"
                        class="text-sm font-medium text-blue-400 hover:text-blue-300 bg-blue-500/10 hover:bg-blue-500/20 rounded-lg px-4 py-2 transition">
                        Submit for Review
                    </button>
                </form>
            @endif
        @endif

        @if($status === 'active')
            <form method="POST" action="{{ route('politician.campaigns.pause', $campaign) }}" class="inline">
                @csrf
                <button type="submit" class="text-sm font-medium text-yellow-400 hover:text-yellow-300 bg-yellow-500/10 hover:bg-yellow-500/20 rounded-lg px-4 py-2 transition">
                    Pause Campaign
                </button>
            </form>
        @elseif($status === 'paused')
            <form method="POST" action="{{ route('politician.campaigns.resume', $campaign) }}" class="inline">
                @csrf
                <button type="submit" class="text-sm font-medium text-emerald-400 hover:text-emerald-300 bg-emerald-500/10 hover:bg-emerald-500/20 rounded-lg px-4 py-2 transition">
                    Resume Campaign
                </button>
            </form>
        @endif

        @if(in_array($status, ['draft', 'cancelled']))
            <form method="POST" action="{{ route('politician.campaigns.destroy', $campaign) }}" class="inline"
                  onsubmit="return confirm('Delete this campaign? This cannot be undone.')">
                @csrf @method('DELETE')
                <button type="submit" class="text-sm font-medium text-red-400 hover:text-red-300 bg-red-500/10 hover:bg-red-500/20 rounded-lg px-4 py-2 transition">
                    Delete
                </button>
            </form>
        @endif
    </div>

    {{-- Title / status --}}
    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6">
        <div class="flex flex-col sm:flex-row sm:items-start gap-3">
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-lg font-semibold text-white">{{ $campaign->title }}</h1>
                    @php
                        $statusColor = match($status) {
                            'active'           => 'bg-emerald-500/15 text-emerald-400',
                            'scheduled'        => 'bg-sky-500/15 text-sky-400',
                            'paused'           => 'bg-yellow-500/15 text-yellow-400',
                            'completed'        => 'bg-slate-500/15 text-slate-300',
                            'pending_approval' => 'bg-blue-500/15 text-blue-400',
                            'cancelled'        => 'bg-red-500/15 text-red-400',
                            default            => 'bg-slate-700/50 text-slate-400',
                        };
                    @endphp
                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium {{ $statusColor }}">
                        {{ ucfirst(str_replace('_', ' ', $status)) }}
                    </span>
                </div>
                @if($campaign->message_summary)
                    <p class="text-slate-400 text-sm mt-2">{{ $campaign->message_summary }}</p>
                @endif
            </div>
        </div>
    </div>

    {{-- Stats row --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Views Completed</p>
            <p class="text-2xl font-bold text-white">{{ number_format($completedViews) }}</p>
            <p class="text-xs text-slate-500 mt-0.5">of {{ number_format($campaign->total_views_requested) }}
                @if($campaign->allow_repeat_views && $uniqueVoters > 0)
                    &nbsp;&bull;&nbsp;<span class="text-slate-400">{{ number_format($uniqueVoters) }} unique</span>
                @endif
            </p>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Budget Spent</p>
            <p class="text-2xl font-bold text-white">${{ number_format($budgetUsed, 2) }}</p>
            <p class="text-xs text-slate-500 mt-0.5">of ${{ number_format($campaign->total_budget, 2) }}</p>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Budget Remaining</p>
            <p class="text-2xl font-bold {{ $budgetLeft > 0 ? 'text-emerald-400' : 'text-red-400' }}">${{ number_format($budgetLeft, 2) }}</p>
        </div>
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-5">
            <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-1">Completion</p>
            @php $pct = $campaign->total_views_requested > 0 ? min(100, round($completedViews / $campaign->total_views_requested * 100)) : 0; @endphp
            <p class="text-2xl font-bold text-white">{{ $pct }}%</p>
            <div class="mt-2 h-1.5 bg-slate-700 rounded-full overflow-hidden">
                <div class="h-full bg-emerald-500 rounded-full transition-all" style="width: {{ $pct }}%"></div>
            </div>
        </div>
    </div>

    {{-- Campaign details --}}
    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6">
        <h2 class="text-sm font-semibold text-slate-200 mb-4">Campaign Details</h2>
        <dl class="grid sm:grid-cols-2 gap-x-8 gap-y-3 text-sm">
            <div class="flex justify-between border-b border-slate-700/40 pb-2">
                <dt class="text-slate-500">Type</dt>
                <dd class="text-slate-200">{{ ucfirst(str_replace('_', ' ', $campaign->campaign_type?->value ?? $campaign->campaign_type)) }}</dd>
            </div>
            <div class="flex justify-between border-b border-slate-700/40 pb-2">
                <dt class="text-slate-500">Governance Level</dt>
                <dd class="text-slate-200">{{ $campaign->governance_level ?? '—' }}</dd>
            </div>
            <div class="flex justify-between border-b border-slate-700/40 pb-2">
                <dt class="text-slate-500">Revenue / View</dt>
                <dd class="text-slate-200">${{ number_format($campaign->revenue_per_view, 2) }}</dd>
            </div>
            <div class="flex justify-between border-b border-slate-700/40 pb-2">
                <dt class="text-slate-500">Min. Watch Time</dt>
                <dd class="text-slate-200">{{ $campaign->min_watch_time_percent ?? 100 }}%</dd>
            </div>
            <div class="flex justify-between border-b border-slate-700/40 pb-2">
                <dt class="text-slate-500">Target States</dt>
                <dd class="text-slate-200">{{ $campaign->target_states ? implode(', ', $campaign->target_states) : 'All States' }}</dd>
            </div>
            <div class="flex justify-between border-b border-slate-700/40 pb-2">
                <dt class="text-slate-500">Approval Status</dt>
                <dd class="text-slate-200">{{ ucfirst($campaign->approval_status?->value ?? $campaign->approval_status ?? 'pending') }}</dd>
            </div>
            {{-- Phase 14 — Repeat Viewing --}}
            <div class="flex justify-between border-b border-slate-700/40 pb-2">
                <dt class="text-slate-500">Repeat Viewing</dt>
                <dd class="text-slate-200">
                    @if($campaign->allow_repeat_views)
                        <span class="inline-flex items-center gap-1 text-emerald-400">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            Enabled
                        </span>
                    @else
                        <span class="text-slate-500">Off</span>
                    @endif
                </dd>
            </div>
            @if($campaign->allow_repeat_views)
            <div class="flex justify-between border-b border-slate-700/40 pb-2">
                <dt class="text-slate-500">Cooldown</dt>
                <dd class="text-slate-200">{{ $campaign->repeat_view_cooldown_hours }}h between re-watches</dd>
            </div>
            <div class="flex justify-between border-b border-slate-700/40 pb-2">
                <dt class="text-slate-500">Max Views / Voter</dt>
                <dd class="text-slate-200">{{ $campaign->max_views_per_voter }}</dd>
            </div>
            <div class="flex justify-between border-b border-slate-700/40 pb-2">
                <dt class="text-slate-500">Unique Voters</dt>
                <dd class="text-slate-200">{{ number_format($uniqueVoters) }}</dd>
            </div>
            <div class="flex justify-between border-b border-slate-700/40 pb-2">
                <dt class="text-slate-500">Repeat Views</dt>
                <dd class="text-slate-200">{{ number_format($repeatViews) }}</dd>
            </div>
            @endif
            <div class="flex justify-between border-b border-slate-700/40 pb-2">
                <dt class="text-slate-500">Created</dt>
                <dd class="text-slate-200">{{ $campaign->created_at->format('M j, Y') }}</dd>
            </div>
            {{-- Phase 14 — Scheduling --}}
            @if($campaign->scheduled_start_at || $campaign->scheduled_end_at)
            <div class="col-span-2 bg-sky-500/10 border border-sky-500/20 rounded-lg px-4 py-3 flex flex-wrap gap-6 text-sm">
                @if($campaign->scheduled_start_at)
                <div>
                    <span class="text-sky-400 font-medium">Starts</span>
                    <span class="text-slate-200 ml-2">{{ $campaign->scheduled_start_at->format('M j, Y g:i A') }}</span>
                    @if($campaign->scheduled_start_at->isFuture())
                        <span class="ml-1 text-xs text-sky-400">(in {{ $campaign->scheduled_start_at->diffForHumans() }})</span>
                    @endif
                </div>
                @endif
                @if($campaign->scheduled_end_at)
                <div>
                    <span class="text-amber-400 font-medium">Ends</span>
                    <span class="text-slate-200 ml-2">{{ $campaign->scheduled_end_at->format('M j, Y g:i A') }}</span>
                    @if($campaign->scheduled_end_at->isFuture())
                        <span class="ml-1 text-xs text-amber-400">(in {{ $campaign->scheduled_end_at->diffForHumans() }})</span>
                    @endif
                </div>
                @endif
            </div>
            @endif
            @if($campaign->media_url)
            <div class="flex justify-between border-b border-slate-700/40 pb-2">
                <dt class="text-slate-500">Video</dt>
                <dd><a href="{{ $campaign->media_url }}" target="_blank" class="text-emerald-400 hover:underline text-xs">View File</a></dd>
            </div>
            @endif
        </dl>
    </div>

    {{-- Video Upload Panel (draft/paused only) --}}
    @if(in_array($status, ['draft', 'paused']))
    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6">
        <h2 class="text-sm font-semibold text-slate-200 mb-4">Video Upload</h2>
        @if($campaign->media_url)
            <div class="flex items-center gap-3 mb-4">
                <svg class="w-5 h-5 text-emerald-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <p class="text-sm text-slate-300">Video uploaded: <a href="{{ $campaign->media_url }}" target="_blank" class="text-emerald-400 hover:underline">View file</a></p>
            </div>
        @else
            <p class="text-sm text-slate-500 mb-4">No video uploaded yet. Upload your campaign video before submitting for review.</p>
        @endif
        <form method="POST" action="{{ route('politician.campaigns.upload-video', $campaign) }}" enctype="multipart/form-data">
            @csrf
            <div class="flex gap-3 flex-col sm:flex-row">
                <input type="file" name="video" accept="video/mp4,video/quicktime,video/webm" required
                    class="flex-1 text-sm text-slate-400 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-slate-700 file:text-slate-200 hover:file:bg-slate-600 cursor-pointer" />
                <button type="submit"
                    class="bg-slate-700 hover:bg-slate-600 text-slate-200 font-medium rounded-lg px-5 py-2 text-sm transition">
                    Upload Video
                </button>
            </div>
            <p class="text-xs text-slate-600 mt-2">Max {{ config('u9itus.max_video_size_mb', 500) }} MB · MP4, MOV, WebM · {{ config('u9itus.min_video_duration', 10) }}–{{ config('u9itus.max_video_duration', 20) }}s</p>
        </form>
    </div>
    @endif

    {{-- Recent view sessions --}}
    @if($campaign->viewSessions->isNotEmpty())
    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-700/50">
            <h3 class="text-sm font-semibold text-slate-200">Recent View Sessions</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-700/50">
                        <th class="text-left px-5 py-2.5 text-xs font-medium text-slate-500">Status</th>
                        <th class="text-left px-5 py-2.5 text-xs font-medium text-slate-500">Watch Time</th>
                        <th class="text-left px-5 py-2.5 text-xs font-medium text-slate-500">Completion</th>
                        <th class="text-left px-5 py-2.5 text-xs font-medium text-slate-500">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/30">
                    @foreach($campaign->viewSessions->take(10) as $session)
                    <tr>
                        <td class="px-5 py-3 text-slate-300">{{ ucfirst($session->status) }}</td>
                        <td class="px-5 py-3 text-slate-300">{{ $session->watch_time_seconds }}s</td>
                        <td class="px-5 py-3 text-slate-300">{{ $session->completion_percentage }}%</td>
                        <td class="px-5 py-3 text-slate-500">{{ $session->created_at?->format('M j, Y') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

</div>
@endsection
