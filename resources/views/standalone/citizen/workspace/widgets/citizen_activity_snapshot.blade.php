@php
    $counts = $data['counts'] ?? [];
    $max = max(1, $data['max'] ?? 1);
    $bars = [
        'Campaigns' => ['value' => $counts['campaigns'] ?? 0, 'color' => 'bg-amber-500'],
        'Blog Posts' => ['value' => $counts['posts'] ?? 0, 'color' => 'bg-indigo-500'],
        'Civic Events' => ['value' => $counts['events'] ?? 0, 'color' => 'bg-cyan-500'],
    ];
@endphp

<div class="space-y-4">
    <div class="space-y-3">
        @foreach($bars as $label => $bar)
            <div>
                <div class="flex items-center justify-between text-xs mb-1">
                    <span class="text-slate-400">{{ $label }}</span>
                    <span class="text-slate-300 font-medium">{{ $bar['value'] }}</span>
                </div>
                <div class="h-2 rounded-full bg-slate-700/50 overflow-hidden">
                    <div class="h-full {{ $bar['color'] }} rounded-full" style="width: {{ max(4, round(($bar['value'] / $max) * 100)) }}%"></div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="flex items-center justify-between pt-3 border-t border-slate-700/40">
        <span class="text-xs text-slate-500 uppercase tracking-wide">Credit Balance</span>
        <span class="text-lg font-bold text-emerald-400">${{ number_format($data['credit_balance'] ?? 0, 2) }}</span>
    </div>
</div>
