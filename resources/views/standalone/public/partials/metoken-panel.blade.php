{{--
    Sprint 7 — MeToken On-Chain Loyalty Panel
    Included from /p/{slug} profile page only when:
      - web3_features_enabled = true
      - politician is eligible (state Governor)
      - politician has wallet_address or metoken_address
      - MeTokenSubgraphService returned data

    Variables:
      $data — normalized array from MeTokenSubgraphService::normalize()
              Keys: address, name, symbol, total_supply, collateral_pooled_dai,
                    collateral_locked_dai, holders_count, last_mint_at,
                    basescan_url, fetched_at
--}}
<div class="metoken-panel rounded-xl border border-indigo-200 bg-gradient-to-br from-indigo-50 to-white p-6 shadow-sm"
     data-panel="metoken">

    {{-- Header --}}
    <div class="flex items-center gap-3 mb-4">
        <div class="flex h-9 w-9 items-center justify-center rounded-full bg-indigo-100">
            {{-- Chain icon --}}
            <svg class="h-5 w-5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M13.19 8.688a4.5 4.5 0 0 1 1.242 7.244l-4.5 4.5a4.5 4.5 0 0 1-6.364-6.364l1.757-1.757m13.35-.622 1.757-1.757a4.5 4.5 0 0 0-6.364-6.364l-4.5 4.5a4.5 4.5 0 0 0 1.242 7.244" />
            </svg>
        </div>
        <div>
            <h3 class="text-sm font-semibold text-gray-900">On-Chain Loyalty Token</h3>
            <p class="text-xs text-gray-500">Base L2 · Powered by MeTokens</p>
        </div>
    </div>

    {{-- Token identity --}}
    <div class="mb-4">
        <p class="text-lg font-bold text-gray-900">{{ $data['name'] ?? '—' }}</p>
        <p class="text-sm text-indigo-600 font-medium">{{ $data['symbol'] ?? '' }}</p>
    </div>

    {{-- Stats grid --}}
    <dl class="grid grid-cols-2 gap-3 mb-4 text-sm">
        @if(!empty($data['holders_count']))
        <div class="rounded-lg bg-white border border-gray-100 px-3 py-2">
            <dt class="text-xs text-gray-500 mb-0.5">Holders</dt>
            <dd class="font-semibold text-gray-900">{{ number_format($data['holders_count']) }}</dd>
        </div>
        @endif

        @if(!empty($data['total_supply']))
        <div class="rounded-lg bg-white border border-gray-100 px-3 py-2">
            <dt class="text-xs text-gray-500 mb-0.5">Total Supply</dt>
            <dd class="font-semibold text-gray-900">
                {{ number_format((float) $data['total_supply'], 2) }}
            </dd>
        </div>
        @endif

        @if(!empty($data['collateral_pooled_dai']))
        <div class="rounded-lg bg-white border border-gray-100 px-3 py-2">
            <dt class="text-xs text-gray-500 mb-0.5">Pooled (DAI)</dt>
            <dd class="font-semibold text-gray-900">
                {{ number_format((float) $data['collateral_pooled_dai'], 2) }}
            </dd>
        </div>
        @endif

        @if(!empty($data['last_mint_at']))
        <div class="rounded-lg bg-white border border-gray-100 px-3 py-2">
            <dt class="text-xs text-gray-500 mb-0.5">Last Minted</dt>
            <dd class="font-semibold text-gray-900">
                {{ \Carbon\Carbon::parse($data['last_mint_at'])->diffForHumans() }}
            </dd>
        </div>
        @endif
    </dl>

    {{-- Basescan link --}}
    @if(!empty($data['basescan_url']))
    <a href="{{ $data['basescan_url'] }}"
       target="_blank"
       rel="noopener noreferrer"
       class="inline-flex items-center gap-1.5 text-xs text-indigo-600 hover:text-indigo-800 font-medium transition-colors">
        View on Basescan
        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
        </svg>
    </a>
    @endif
</div>
