@php
    // A row of summary stat cards. Pass $cards as a list of
    // ['label' => ..., 'value' => ..., 'valueClass' => 'text-white'] entries,
    // and $gridClass for the responsive column count (must be a literal
    // Tailwind class string so the JIT scanner picks it up).
    $cards     = $cards     ?? [];
    $gridClass = $gridClass ?? 'grid-cols-2 sm:grid-cols-4';
@endphp
<div class="grid {{ $gridClass }} gap-4">
    @foreach($cards as $card)
    <div class="bg-slate-800/50 border border-slate-700/60 rounded-2xl p-4">
        <p class="text-slate-400 text-xs font-medium uppercase tracking-wide">{{ $card['label'] }}</p>
        <p class="text-xl font-bold {{ $card['valueClass'] ?? 'text-white' }} mt-2">{{ $card['value'] }}</p>
    </div>
    @endforeach
</div>