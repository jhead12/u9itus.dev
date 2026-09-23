@php
    $campaigns = $data['campaigns'] ?? collect();
    $statusColors = [
        'draft' => 'bg-slate-500/10 text-slate-400 border-slate-500/30',
        'pending_approval' => 'bg-amber-500/10 text-amber-400 border-amber-500/30',
        'scheduled' => 'bg-cyan-500/10 text-cyan-400 border-cyan-500/30',
        'active' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
        'paused' => 'bg-slate-500/10 text-slate-400 border-slate-500/30',
        'completed' => 'bg-indigo-500/10 text-indigo-400 border-indigo-500/30',
        'cancelled' => 'bg-red-500/10 text-red-400 border-red-500/30',
    ];
@endphp

@if($campaigns->isEmpty())
    <p class="text-sm text-slate-500">No campaigns yet.
        <a href="{{ route('citizen.campaigns.create') }}" class="text-amber-400 hover:underline">Start one</a>.
    </p>
@else
    <ul class="space-y-3">
        @foreach($campaigns as $campaign)
            @php
                $statusValue = $campaign->status?->value ?? (string) $campaign->status;
                $target = max(1, (int) $campaign->total_views_requested);
                $progress = min(100, round(((int) $campaign->views_completed / $target) * 100));
            @endphp
            <li>
                <div class="flex items-center justify-between gap-2 mb-1">
                    <a href="{{ route('citizen.campaigns.show', $campaign->id) }}" class="text-sm text-slate-200 hover:text-amber-400 transition truncate">
                        {{ $campaign->title }}
                    </a>
                    <span class="shrink-0 text-[11px] font-medium px-2 py-0.5 rounded-full border {{ $statusColors[$statusValue] ?? $statusColors['draft'] }}">
                        {{ str_replace('_', ' ', $statusValue) }}
                    </span>
                </div>
                <div class="h-1.5 rounded-full bg-slate-700/50 overflow-hidden">
                    <div class="h-full bg-amber-500 rounded-full" style="width: {{ $progress }}%"></div>
                </div>
                <p class="text-[11px] text-slate-500 mt-1">
                    {{ number_format($campaign->views_completed) }} / {{ number_format($campaign->total_views_requested) }} views
                </p>
            </li>
        @endforeach
    </ul>
@endif
