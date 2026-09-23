@if(empty($data['steps']))
    <p class="text-sm text-slate-500">No cleanup workflow runs recorded yet.</p>
@else
    <div class="space-y-3">
        @foreach($data['steps'] as $step)
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-sm text-slate-200 truncate">{{ str($step['step'])->replace('-', ' ')->title() }}</p>
                    <p class="text-xs text-slate-500">
                        {{ $step['last_started_at'] ? \Illuminate\Support\Carbon::parse($step['last_started_at'])->diffForHumans() : 'never run' }}
                        · {{ number_format($step['findings_count']) }} findings
                    </p>
                </div>
                <span class="text-xs px-2 py-0.5 rounded-full shrink-0 {{ $step['stale'] || $step['exit_code'] !== 0 ? 'bg-red-500/10 text-red-400' : 'bg-emerald-500/10 text-emerald-400' }}">
                    {{ $step['stale'] ? 'stale' : ($step['exit_code'] !== 0 ? 'failing' : 'healthy') }}
                </span>
            </div>
        @endforeach
    </div>
@endif
