@extends('standalone.layouts.dashboard')

@section('title', $campaign->title)
@section('page-title', 'Campaign Detail')

@section('content')
<div class="space-y-6">

    @php
        $rawStatus     = $campaign->getRawOriginal('status') ?? $campaign->status?->value ?? 'draft';
        $isDraft       = in_array($rawStatus, ['draft', 'cancelled'], true);
        $isPending     = $rawStatus === 'pending_approval';
        $isBallot      = $campaign->isBallotIssue();
        $statusColors  = [
            'draft'            => 'text-slate-400 bg-slate-700/60',
            'pending_approval' => 'text-blue-400 bg-blue-500/10',
            'active'           => 'text-emerald-400 bg-emerald-500/10',
            'paused'           => 'text-yellow-400 bg-yellow-500/10',
            'scheduled'        => 'text-purple-400 bg-purple-500/10',
            'completed'        => 'text-slate-300 bg-slate-700/40',
            'cancelled'        => 'text-red-400 bg-red-500/10',
        ];
        $statusColor = $statusColors[$rawStatus] ?? 'text-slate-400 bg-slate-700/60';
    @endphp

    {{-- Flash messages --}}
    @if(session('success'))
    <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-5 py-3 text-sm text-emerald-300">{{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div class="rounded-xl border border-red-500/30 bg-red-500/10 p-4">
        <ul class="list-disc pl-5 text-xs text-red-200 space-y-1">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
    @endif

    {{-- Back + actions --}}
    <div class="flex items-center gap-3 flex-wrap">
        <a href="{{ route('citizen.campaigns.index') }}" class="text-sm text-slate-400 hover:text-white transition">← Campaigns</a>
        <div class="flex-1"></div>

        @if($isDraft)
            <a href="{{ route('citizen.campaigns.edit', $campaign) }}"
               class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-300 hover:text-white bg-slate-700/50 hover:bg-slate-700 rounded-lg px-4 py-2 transition">
                Edit
            </a>
        @endif

        @if($isDraft && ($campaign->media_url || $campaign->live_feed_url))
            @if($isBallot && empty($campaign->pac_registration_id))
                <span class="text-xs text-amber-400 bg-amber-500/10 border border-amber-500/20 rounded-lg px-3 py-2">
                    Add PAC ID before submitting
                </span>
            @else
                <form method="POST" action="{{ route('citizen.campaigns.submit-review', $campaign) }}" class="inline">
                    @csrf
                    <button type="submit"
                        class="text-sm font-medium text-blue-400 hover:text-blue-300 bg-blue-500/10 hover:bg-blue-500/20 rounded-lg px-4 py-2 transition">
                        Submit for Review
                    </button>
                </form>
            @endif
        @endif

        @if($isDraft)
            <form method="POST" action="{{ route('citizen.campaigns.destroy', $campaign) }}" class="inline"
                  onsubmit="return confirm('Delete this campaign? This cannot be undone.')">
                @csrf @method('DELETE')
                <button type="submit" class="text-xs text-red-400 hover:text-red-300 transition">Delete</button>
            </form>
        @endif
    </div>

    {{-- Title + status --}}
    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6">
        <div class="flex items-start gap-3">
            <div class="flex-1 min-w-0">
                <h2 class="text-white font-semibold text-lg truncate">{{ $campaign->title }}</h2>
                <div class="flex items-center gap-2 mt-1 flex-wrap">
                    <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium {{ $statusColor }}">
                        {{ ucfirst(str_replace('_', ' ', $rawStatus)) }}
                    </span>
                    @if($isBallot)
                        <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium text-amber-400 bg-amber-500/10 border border-amber-500/20">
                            Ballot Issue
                        </span>
                    @endif
                    @if($campaign->pac_registration_id)
                        <span class="text-xs text-slate-500">PAC: {{ $campaign->pac_registration_id }}</span>
                    @endif
                </div>
            </div>
        </div>

        @if($campaign->message_summary)
        <p class="text-slate-400 text-sm mt-4">{{ $campaign->message_summary }}</p>
        @endif
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        @php
            $stats = [
                ['label' => 'Views', 'value' => $campaign->views_completed . ' / ' . $campaign->total_views_requested],
                ['label' => 'Rate/View', 'value' => '$' . number_format($campaign->revenue_per_view, 2)],
                ['label' => 'Budget', 'value' => '$' . number_format($campaign->total_budget, 2)],
                ['label' => 'Spent', 'value' => '$' . number_format($campaign->amount_spent, 2)],
            ];
        @endphp
        @foreach($stats as $stat)
        <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-4 text-center">
            <div class="text-white font-semibold text-lg">{{ $stat['value'] }}</div>
            <div class="text-slate-400 text-xs mt-0.5">{{ $stat['label'] }}</div>
        </div>
        @endforeach
    </div>

    {{-- Campaign details --}}
    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6 space-y-3">
        <h3 class="text-sm font-semibold text-slate-200 mb-3">Details</h3>

        @php
            $adTypeLabels = [
                'local_business'       => 'Local Business',
                'community_notice'     => 'Community Notice',
                'ballot_issue'         => 'Ballot Issue',
                'general_announcement' => 'General Announcement',
            ];
            $rawAdType = $campaign->getRawOriginal('citizen_ad_type') ?? $campaign->citizen_ad_type?->value ?? '';
        @endphp

        <dl class="space-y-2 text-sm">
            <div class="flex justify-between">
                <dt class="text-slate-400">Ad Type</dt>
                <dd class="text-white font-medium">{{ $adTypeLabels[$rawAdType] ?? $rawAdType }}</dd>
            </div>
            <div class="flex justify-between">
                <dt class="text-slate-400">Campaign Type</dt>
                <dd class="text-white font-medium">{{ ucfirst(str_replace('_', ' ', $campaign->getRawOriginal('campaign_type') ?? '')) }}</dd>
            </div>
            @if($campaign->target_zip)
            <div class="flex justify-between">
                <dt class="text-slate-400">Target ZIP</dt>
                <dd class="text-white font-medium">{{ $campaign->target_zip }}{{ $campaign->target_zip_radius ? ' (±' . $campaign->target_zip_radius . ' mi)' : '' }}</dd>
            </div>
            @endif
            @if($campaign->daily_view_cap)
            <div class="flex justify-between">
                <dt class="text-slate-400">Daily View Cap</dt>
                <dd class="text-white font-medium">{{ number_format($campaign->daily_view_cap) }}/day</dd>
            </div>
            @endif
            @if($campaign->media_url)
            <div class="flex justify-between gap-4">
                <dt class="text-slate-400 shrink-0">Video</dt>
                <dd class="text-white font-medium text-right truncate">{{ $campaign->media_url }}</dd>
            </div>
            @elseif(!$campaign->media_url && !$campaign->live_feed_url)
            <div class="rounded-lg bg-amber-500/5 border border-amber-500/20 px-3 py-2 text-xs text-amber-300">
                No video yet — upload one before submitting for review.
            </div>
            @endif
        </dl>
    </div>

    {{-- Video upload (draft only) --}}
    @if($isDraft)
    <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-6">
        <h3 class="text-sm font-semibold text-slate-200 mb-4">Upload Video</h3>
        @php
            $allowMovUploads = preg_match('/\b(iPhone|iPad|iPod)\b/i', request()->userAgent() ?? '') === 1;
            $videoAcceptTypes = $allowMovUploads ? 'video/mp4,video/webm,video/quicktime' : 'video/mp4,video/webm';
        @endphp
        <form method="POST" action="{{ route('citizen.campaigns.upload-video', $campaign) }}" enctype="multipart/form-data" class="space-y-3">
            @csrf
            <input type="file" name="video" accept="{{ $videoAcceptTypes }}" required
                class="block w-full text-sm text-slate-400 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-amber-500/20 file:text-amber-300 file:text-xs file:font-medium hover:file:bg-amber-500/30 cursor-pointer" />
            @error('video')<p class="text-xs text-red-400 mt-1">{{ $message }}</p>@enderror
            <button type="submit" class="bg-amber-500 hover:bg-amber-400 text-slate-900 font-semibold rounded-lg px-5 py-2.5 text-sm transition-colors">
                Upload
            </button>
        </form>
    </div>
    @endif

</div>
@endsection
