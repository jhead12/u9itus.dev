@php
    $queueRows = [
        ['label' => 'Campaign approvals', 'count' => $data['campaigns'] ?? 0, 'route' => 'admin.campaigns.pending'],
        ['label' => 'Candidate matches', 'count' => $data['candidate_matches'] ?? 0, 'route' => 'admin.candidate-matches.index'],
        ['label' => 'Data quality reviews', 'count' => $data['data_quality'] ?? 0, 'route' => 'admin.data-quality.index'],
        ['label' => 'KYC review', 'count' => $data['kyc'] ?? 0, 'route' => 'admin.kyc.index'],
    ];
@endphp
<div class="divide-y divide-slate-700/30 -my-1">
    @foreach($queueRows as $row)
        @php
            $linkable = \Illuminate\Support\Facades\Route::has($row['route']) && \App\Support\AdminAccess::canRoute(auth()->user(), $row['route']);
        @endphp
        <div class="py-2.5 flex items-center justify-between gap-3">
            @if($linkable)
                <a href="{{ route($row['route']) }}" class="text-sm text-slate-300 hover:text-emerald-400 transition">{{ $row['label'] }}</a>
            @else
                <span class="text-sm text-slate-500">{{ $row['label'] }}</span>
            @endif
            <span class="text-sm font-semibold {{ $row['count'] > 0 ? 'text-amber-400' : 'text-slate-500' }}">{{ number_format($row['count']) }}</span>
        </div>
    @endforeach
</div>
