<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>District Lookup — {{ config('app.name', 'U9itus') }}</title>
    @include('standalone.partials.seo-head')
    <meta name="description" content="Enter your address to find your congressional district and view verified candidates, current officeholders, and campaign finance data for your area.">
    <link rel="canonical" href="{{ url('/district-lookup') }}">
    <meta property="og:type"        content="website">
    <meta property="og:url"         content="{{ url('/district-lookup') }}">
    <meta property="og:title"       content="District Lookup — {{ config('app.name', 'U9itus') }}">
    <meta property="og:description" content="Enter your address to find your congressional district and view verified candidates, current officeholders, and campaign finance data for your area.">
    <meta name="twitter:card"       content="summary">
    <meta name="twitter:title"      content="District Lookup — {{ config('app.name', 'U9itus') }}">
    <meta name="twitter:description" content="Find your U.S. congressional district and local candidates by entering your address.">

    @if(file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/earn-cta/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
</head>
<body class="bg-slate-950 min-h-screen antialiased text-slate-100">
    <nav class="sticky top-0 z-40 bg-slate-900/80 backdrop-blur-md border-b border-slate-700/50">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 flex items-center justify-between h-14">
            <a href="{{ url('/') }}" class="flex items-center space-x-1 text-lg font-bold hover:opacity-80 transition">
                <span class="text-white">U9</span><span class="text-emerald-400">itus</span>
            </a>
            <div class="flex items-center gap-3 text-sm">
                <a href="{{ route('politicians.directory') }}" class="text-slate-300 hover:text-white transition">Browse Candidates</a>
                @auth
                    <a href="{{ route('dashboard') }}" class="text-slate-300 hover:text-white transition">Dashboard</a>
                @else
                    @if (config('platform.map.voter_features_enabled') && config('platform.map.sign_in_cta'))
                        @php
                            $u9LoginUrl = url('https://www.early-bank.com') . '?' . http_build_query(array_filter([
                                'ref'  => request()->query('ref'),
                                'from' => 'district-lookup',
                            ]));
                        @endphp
                        <a id="btn-earn-cta" href="{{ $u9LoginUrl }}"
                           title="Get paid to watch campaign videos — up to $0.50 each"
                           class="hidden sm:inline-flex items-center px-3 py-1.5 rounded-full text-xs font-semibold text-emerald-400 bg-emerald-500/10 border border-emerald-400/40 hover:bg-emerald-500/20 transition">
                            Earn $$$ to share
                        </a>
                    @endif
                    <a href="{{ route('login') }}" class="text-slate-300 hover:text-white transition">Sign In</a>
                @endauth
            </div>
        </div>
    </nav>

    <main class="max-w-6xl mx-auto px-4 sm:px-6 py-10">
        <section class="mb-8">
            <h1 class="text-3xl sm:text-4xl font-bold text-white mb-3">Find Your District</h1>
            <p class="text-slate-400 max-w-2xl">
                Enter your home address to identify your congressional district and see candidates currently published on U9itus in your area.
            </p>
            <p class="text-slate-500 text-sm mt-2">For best results, enter a full address with street, city, and state. ZIP-only lookups may not return full district detail.</p>
        </section>

        <section class="bg-slate-900/70 border border-slate-700/50 rounded-2xl p-4 sm:p-6 mb-6">
            <form method="GET" action="{{ route('district.lookup') }}" class="flex flex-col sm:flex-row gap-3">
                <input
                    type="text"
                    name="address"
                    value="{{ $address }}"
                    placeholder="123 Main St, Los Angeles, CA 90012 or 92555"
                    class="flex-1 bg-slate-800 border border-slate-700 text-white placeholder-slate-500 rounded-lg px-4 py-3 text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500 focus:border-emerald-500"
                    required
                    maxlength="255"
                />
                <button
                    type="submit"
                    class="bg-emerald-600 hover:bg-emerald-500 text-white px-5 py-3 rounded-lg text-sm font-semibold transition"
                >
                    Lookup District
                </button>
            </form>

            @if($error)
                <p class="mt-3 text-sm text-rose-300">{{ $error }}</p>
            @endif
        </section>

        @if($lookupResult)
            <section class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
                <div class="bg-slate-900/60 border border-slate-700/50 rounded-xl p-5">
                    <p class="text-xs uppercase tracking-wider text-slate-400 mb-2">Matched Address</p>
                    <p class="text-white text-sm leading-relaxed">{{ $lookupResult['matched_address'] ?? 'N/A' }}</p>
                </div>
                <div class="bg-slate-900/60 border border-slate-700/50 rounded-xl p-5">
                    <p class="text-xs uppercase tracking-wider text-slate-400 mb-2">Resolved District</p>
                    <p class="text-white text-lg font-semibold">
                        {{ $lookupResult['district_code'] ?? 'Not available' }}
                    </p>
                    @if(!empty($lookupResult['source']))
                        @php
                            $sourceLabel = match ($lookupResult['source']) {
                                'census_geocoder' => 'Census Geocoder',
                                'google_civic' => 'Google Civic',
                                default => ucwords(str_replace('_', ' ', (string) $lookupResult['source'])),
                            };
                        @endphp
                        <p class="text-slate-400 text-xs mt-1 uppercase tracking-wide">Source: {{ $sourceLabel }}</p>
                    @endif
                    @if(!empty($lookupResult['district_label']))
                        <p class="text-slate-400 text-sm mt-1">{{ $lookupResult['district_label'] }}</p>
                    @endif
                </div>
            </section>

            {{-- ── District boundary map ─────────────────────────────────────── --}}
            @php
                // Build the ArcGIS GEOID (2-digit state FIPS + 2-digit district number)
                $stateFipsMap = [
                    'AL'=>'01','AK'=>'02','AZ'=>'04','AR'=>'05','CA'=>'06','CO'=>'08',
                    'CT'=>'09','DE'=>'10','DC'=>'11','FL'=>'12','GA'=>'13','HI'=>'15',
                    'ID'=>'16','IL'=>'17','IN'=>'18','IA'=>'19','KS'=>'20','KY'=>'21',
                    'LA'=>'22','ME'=>'23','MD'=>'24','MA'=>'25','MI'=>'26','MN'=>'27',
                    'MS'=>'28','MO'=>'29','MT'=>'30','NE'=>'31','NV'=>'32','NH'=>'33',
                    'NJ'=>'34','NM'=>'35','NY'=>'36','NC'=>'37','ND'=>'38','OH'=>'39',
                    'OK'=>'40','OR'=>'41','PA'=>'42','RI'=>'44','SC'=>'45','SD'=>'46',
                    'TN'=>'47','TX'=>'48','UT'=>'49','VT'=>'50','VA'=>'51','WA'=>'53',
                    'WV'=>'54','WI'=>'55','WY'=>'56',
                ];
                $mapState    = strtoupper((string) ($lookupResult['state'] ?? ''));
                $mapDistrict = $lookupResult['district_number'] ?? null;
                $stateFips   = $stateFipsMap[$mapState] ?? null;
                $districtNum = ($mapDistrict !== null && strtoupper((string) $mapDistrict) !== 'AL')
                    ? str_pad((string) (int) $mapDistrict, 2, '0', STR_PAD_LEFT)
                    : '00';
                $geoid       = ($stateFips && $mapDistrict !== null) ? $stateFips . $districtNum : null;
                $congressGovUrl = null;

                // ── Where to vote ────────────────────────────────────────────
                // Google Civic's voterinfo response already carries lat/lng per
                // location — normalize the three location types into one list
                // so the cards below and the map markers can share it.
                $voteLocationGroups = [
                    'polling_location' => ['label' => 'Your Polling Place', 'items' => (array) ($voterInfo['polling_locations'] ?? [])],
                    'early_vote_site'  => ['label' => 'Early Voting',        'items' => (array) ($voterInfo['early_vote_sites'] ?? [])],
                    'drop_off_location'=> ['label' => 'Ballot Drop-Off',     'items' => (array) ($voterInfo['drop_off_locations'] ?? [])],
                ];
                $hasVoteLocations = collect($voteLocationGroups)->flatMap(fn ($g) => $g['items'])->isNotEmpty();
                $mappableVoteLocations = collect($voteLocationGroups)
                    ->flatMap(fn ($g, $type) => collect($g['items'])->map(fn ($loc) => array_merge((array) $loc, [
                        'type' => $type,
                        'type_label' => $g['label'],
                    ])))
                    ->filter(fn ($loc) => is_numeric($loc['latitude'] ?? null) && is_numeric($loc['longitude'] ?? null))
                    ->values();

                if (!empty($lookupResult['source']) && $mapState && $mapDistrict) {
                    // Try to find the member's congress.gov slug from current officials
                    foreach (($currentOfficials ?? collect()) as $off) {
                        $offDistrict = $off['district_code'] ?? '';
                        if (
                            isset($off['bioguide_id']) &&
                            str_contains((string) $offDistrict, '-' . (int) $mapDistrict)
                        ) {
                            $slug = \Illuminate\Support\Str::slug($off['full_name']);
                            $congressGovUrl = 'https://www.congress.gov/member/district/' . $slug . '/' . $off['bioguide_id'];
                            break;
                        }
                    }
                }
            @endphp

            @if($geoid)
            <section class="mb-8">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                    <h2 class="text-xl font-bold text-white">District Boundary Map</h2>
                    <div class="flex items-center gap-3">
                        {{-- Deep-link to the U9itus interactive 3D map, pre-focused on this state + district --}}
                        @php
                            $u9MapUrl = route('us.map') . '?' . http_build_query(array_filter([
                                'state'    => $mapState,
                                'district' => ($mapDistrict !== null && strtoupper((string)$mapDistrict) !== 'AL')
                                                ? (int) $mapDistrict : null,
                            ]));
                        @endphp
                        <a href="{{ $u9MapUrl }}"
                           class="inline-flex items-center gap-1.5 text-xs font-semibold
                                  text-indigo-300 hover:text-indigo-200
                                  border border-indigo-500/30 hover:border-indigo-400/60
                                  bg-indigo-500/10 hover:bg-indigo-500/20
                                  rounded-full px-3 py-1.5 transition">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                                 stroke-linejoin="round" aria-hidden="true">
                                <polygon points="3 11 22 2 13 21 11 13 3 11"/>
                            </svg>
                            View on U9itus Map
                        </a>
                        <a href="https://www.congress.gov/members?q=%7B%22congress%22%3A%22119%22%2C%22state%22%3A%22{{ $mapState }}%22%7D"
                           target="_blank" rel="noopener"
                           class="text-xs text-emerald-400 hover:text-emerald-300 transition">
                            View on Congress.gov →
                        </a>
                    </div>
                </div>

                <div class="bg-slate-900/70 border border-slate-700/50 rounded-2xl overflow-hidden">
                    <div id="district-boundary-map" style="height:420px;width:100%;"></div>
                </div>

                <p class="text-slate-600 text-xs mt-2">
                    Boundary data: <a href="https://www.congress.gov" target="_blank" rel="noopener" class="hover:text-slate-400 transition">Congress.gov</a>
                    via ArcGIS Congressional Districts FeatureServer (119th Congress).
                </p>
            </section>

            <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
            <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
            <script>
            (function () {
                const GEOID = '{{ $geoid }}';

                const map = L.map('district-boundary-map', {
                    zoomControl: true,
                    scrollWheelZoom: false,
                    attributionControl: true,
                });

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                    maxZoom: 14,
                }).addTo(map);

                const serviceUrl = 'https://services2.arcgis.com/FiaPA4ga0iQKduv3/arcgis/rest/services/Congressional_Districts_v1/FeatureServer/0/query';
                const params = new URLSearchParams({
                    where: `GEOID='${GEOID}'`,
                    outFields: 'GEOID,NAME,STATE,CD119',
                    returnGeometry: 'true',
                    f: 'geojson',
                });

                fetch(`${serviceUrl}?${params}`)
                    .then(r => r.ok ? r.json() : Promise.reject(r.status))
                    .then(data => {
                        if (!data.features || data.features.length === 0) {
                            document.getElementById('district-boundary-map').innerHTML =
                                '<p class="p-4 text-slate-400 text-sm">District boundary data not available.</p>';
                            return;
                        }

                        // Outer glow layer — wide soft halo so the border reads
                        // clearly against both light and dark map tiles.
                        L.geoJSON(data, {
                            style: {
                                color: '#000000',
                                weight: 16,
                                opacity: 0.15,
                                fill: false,
                            },
                        }).addTo(map);

                        // Main boundary layer — solid black border + subtle fill.
                        const districtLayer = L.geoJSON(data, {
                            style: {
                                color: '#1a1a1a',
                                weight: 4,
                                opacity: 0.9,
                                fillColor: '#334155',
                                fillOpacity: 0.18,
                                dashArray: null,
                            },
                        }).addTo(map);

                        map.fitBounds(districtLayer.getBounds(), { padding: [32, 32] });
                    })
                    .catch(() => {
                        document.getElementById('district-boundary-map').innerHTML =
                            '<p class="p-4 text-slate-400 text-sm">Could not load district boundary.</p>';
                    });

                // ── Where-to-vote markers ────────────────────────────────────
                // Names/addresses/hours below come from the Google Civic API, a
                // third party — escape before building popup HTML so a crafted
                // location name can't inject markup into the page.
                const escapeHtml = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({
                    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
                }[c]));
                const VOTE_LOCATIONS = {!! $mappableVoteLocations->toJson() !!};
                const VOTE_MARKER_COLOR = {
                    polling_location: '#22c55e',
                    early_vote_site: '#38bdf8',
                    drop_off_location: '#f59e0b',
                };
                for (const loc of VOTE_LOCATIONS) {
                    const addr = loc.address || {};
                    const addrLine = [addr.line1, addr.city, addr.state, addr.zip].filter(Boolean).join(', ');
                    const popupHtml = `<div style="font-size:13px;line-height:1.4">`
                        + `<strong>${escapeHtml(loc.type_label)}${loc.name ? ': ' + escapeHtml(loc.name) : ''}</strong>`
                        + (addrLine ? `<br>${escapeHtml(addrLine)}` : '')
                        + (loc.polling_hours ? `<br>Hours: ${escapeHtml(loc.polling_hours)}` : '')
                        + `</div>`;
                    L.circleMarker([loc.latitude, loc.longitude], {
                        radius: 9,
                        color: '#0f172a',
                        weight: 2,
                        fillColor: VOTE_MARKER_COLOR[loc.type] || '#94a3b8',
                        fillOpacity: 0.9,
                    }).addTo(map).bindPopup(popupHtml);
                }
            }());
            </script>
            @endif
            {{-- ── End district map ──────────────────────────────────────────── --}}

            @if($hasVoteLocations)
            <section class="mb-8">
                <h2 class="text-xl font-bold text-white mb-3">Where to Vote</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    @foreach($voteLocationGroups as $type => $group)
                        @foreach($group['items'] as $location)
                            @php $addr = (array) ($location['address'] ?? []); @endphp
                            <div class="bg-slate-900/60 border border-slate-700/50 rounded-xl p-5">
                                <p class="text-xs uppercase tracking-wider text-slate-400 mb-2">{{ $group['label'] }}</p>
                                @if(!empty($location['name']))
                                    <p class="text-white font-semibold text-sm">{{ $location['name'] }}</p>
                                @endif
                                @php
                                    $addrLine = collect([$addr['line1'] ?? null, $addr['city'] ?? null, $addr['state'] ?? null, $addr['zip'] ?? null])
                                        ->filter()
                                        ->implode(', ');
                                @endphp
                                @if($addrLine !== '')
                                    <p class="text-slate-300 text-sm mt-1">{{ $addrLine }}</p>
                                @endif
                                @if(!empty($location['polling_hours']))
                                    <p class="text-slate-400 text-xs mt-2">Hours: {{ $location['polling_hours'] }}</p>
                                @endif
                                @if(!empty($location['start_date']) || !empty($location['end_date']))
                                    <p class="text-slate-400 text-xs mt-1">
                                        {{ $location['start_date'] ?? '' }}{{ !empty($location['start_date']) && !empty($location['end_date']) ? ' – ' : '' }}{{ $location['end_date'] ?? '' }}
                                    </p>
                                @endif
                            </div>
                        @endforeach
                    @endforeach
                </div>
                @if($geoid && $mappableVoteLocations->isEmpty())
                    <p class="text-slate-500 text-xs mt-3">Exact coordinates weren't available for these locations, so they aren't plotted on the map above.</p>
                @endif
            </section>
            @endif

            <section>
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-bold text-white">Candidates Running In Your Area</h2>
                    <span class="text-sm text-slate-400">{{ $candidates->count() }} found</span>
                </div>

                @if($candidates->isEmpty())
                    <div class="bg-slate-900/60 border border-slate-700/50 rounded-xl p-6">
                        <p class="text-slate-300">No published candidates were found for this district yet.</p>
                        <p class="text-slate-500 text-sm mt-1">Try another address or browse all candidates.</p>
                        <a href="{{ route('politicians.directory') }}" class="inline-block mt-4 text-emerald-400 hover:text-emerald-300 text-sm">
                            Browse all candidates ->
                        </a>
                    </div>
                @else
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($candidates as $candidate)
                            <a href="{{ route('politician.public.show', $candidate->slug) }}" class="group block bg-slate-900/60 border border-slate-700/50 hover:border-emerald-500/40 rounded-xl p-5 transition">
                                <p class="text-white font-semibold group-hover:text-emerald-300 transition">{{ $candidate->full_name }}</p>
                                <p class="text-slate-400 text-sm mt-1">{{ $candidate->political_office ?: 'Political Candidate' }}</p>

                                <div class="mt-3 space-y-1 text-xs text-slate-400">
                                    @if($candidate->district)
                                        <p>District: {{ $candidate->district }}</p>
                                    @endif
                                    @if($candidate->state || $candidate->city)
                                        <p>{{ implode(', ', array_filter([$candidate->city, $candidate->state])) }}</p>
                                    @endif
                                    <p>Active campaigns: {{ $candidate->active_campaigns_count }}</p>
                                    @if(!empty($candidate->finance_snapshot['primary']['summary']))
                                        <div class="pt-2 border-t border-slate-700/40 space-y-1">
                                            <p class="text-emerald-300">Campaign finance (open data)</p>
                                            @php
                                                $financeSummary = $candidate->finance_snapshot['primary']['summary'];
                                                $financeLabel = $candidate->finance_snapshot['primary']['label'] ?? 'Public source';
                                            @endphp
                                            @if(!empty($financeSummary['receipts']))
                                                <p>Receipts: {{ $financeSummary['receipts'] }}</p>
                                            @endif
                                            @if(!empty($financeSummary['cash_on_hand']))
                                                <p>Cash on hand: {{ $financeSummary['cash_on_hand'] }}</p>
                                            @endif
                                            @if(!empty($financeSummary['debt']))
                                                <p>Debt: {{ $financeSummary['debt'] }}</p>
                                            @endif
                                            <p>Source: {{ $financeLabel }}</p>
                                        </div>
                                    @endif
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>

            <section class="mt-10">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-bold text-white">Current Politicians For This Location</h2>
                    <span class="text-sm text-slate-400">{{ $currentOfficials->count() }} found</span>
                </div>

                @if($currentOfficials->isEmpty())
                    <div class="bg-slate-900/60 border border-slate-700/50 rounded-xl p-6">
                        <p class="text-slate-300">No current officeholders were returned for this address.</p>
                    </div>
                @else
                    @php
                        // Group officials by governance level in display order
                        $levelOrder  = ['Federal' => 0, 'State' => 1, 'County' => 2, 'City' => 3, 'Local' => 4];
                        $levelLabels = [
                            'Federal' => 'Federal Officials',
                            'State'   => 'State Officials',
                            'County'  => 'County Officials',
                            'City'    => 'City / Local Officials',
                            'Local'   => 'Local Officials',
                        ];
                        $levelColors = [
                            'Federal' => 'text-blue-400 border-blue-700/40',
                            'State'   => 'text-violet-400 border-violet-700/40',
                            'County'  => 'text-amber-400 border-amber-700/40',
                            'City'    => 'text-emerald-400 border-emerald-700/40',
                            'Local'   => 'text-slate-400 border-slate-700/40',
                        ];
                        $grouped = $currentOfficials
                            ->groupBy(fn($o) => $o['governance_level'] ?? 'Local')
                            ->sortBy(fn($_, $level) => $levelOrder[$level] ?? 99);
                    @endphp

                    @foreach($grouped as $level => $officials)
                        <div class="mb-8">
                            <h3 class="text-sm font-semibold uppercase tracking-wider mb-3 {{ ($levelColors[$level] ?? 'text-slate-400 border-slate-700/40') }}">
                                {{ $levelLabels[$level] ?? $level }}
                                <span class="ml-2 font-normal text-slate-500">({{ $officials->count() }})</span>
                            </h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                @foreach($officials as $official)
                                    @php
                                        $officialSourceLabel = match ($official['source'] ?? null) {
                                            'google_civic'           => 'Google Civic',
                                            'congress_gov'           => 'Congress.gov',
                                            'congress_legislators'   => 'Congress Legislators Import',
                                            null, ''                 => 'Local Record',
                                            default                  => ucwords(str_replace('_', ' ', (string) $official['source'])),
                                        };
                                        $borderClass = match ($level) {
                                            'Federal' => 'border-blue-700/30 hover:border-blue-500/40',
                                            'State'   => 'border-violet-700/30 hover:border-violet-500/40',
                                            'County'  => 'border-amber-700/30 hover:border-amber-500/40',
                                            'City'    => 'border-emerald-700/30 hover:border-emerald-500/40',
                                            default   => 'border-slate-700/50',
                                        };
                                    @endphp
                                    <div class="bg-slate-900/60 border {{ $borderClass }} rounded-xl p-5 transition">
                                        @if(!empty($official['profile_slug']))
                                            <a href="{{ route('politician.public.show', $official['profile_slug']) }}" class="text-white font-semibold hover:text-emerald-300 transition">{{ $official['full_name'] }}</a>
                                        @else
                                            <p class="text-white font-semibold">{{ $official['full_name'] }}</p>
                                        @endif
                                        <p class="text-slate-400 text-sm mt-1">{{ $official['political_office'] ?: 'Public Official' }}</p>

                                        <div class="mt-3 space-y-1 text-xs text-slate-400">
                                            @if(!empty($official['party_affiliation']))
                                                <p>Party: {{ $official['party_affiliation'] }}</p>
                                            @endif
                                            @if(!empty($official['district_code']))
                                                <p>District: {{ $official['district_code'] }}</p>
                                            @endif
                                            @if(!empty($official['state']))
                                                <p>{{ $official['state'] }}</p>
                                            @endif
                                            <p class="text-slate-600">Source: {{ $officialSourceLabel }}</p>
                                            @if(!empty($official['discovery_links']['wikipedia']) || !empty($official['discovery_links']['youtube']) || !empty($official['discovery_links']['cspan']))
                                                <div class="pt-1 flex flex-wrap gap-3">
                                                    @if(!empty($official['discovery_links']['wikipedia']))
                                                        <a href="{{ $official['discovery_links']['wikipedia'] }}" target="_blank" rel="noopener" class="text-emerald-400 hover:text-emerald-300">Wikipedia</a>
                                                    @endif
                                                    @if(!empty($official['discovery_links']['youtube']))
                                                        <a href="{{ $official['discovery_links']['youtube'] }}" target="_blank" rel="noopener" class="text-emerald-400 hover:text-emerald-300">YouTube</a>
                                                    @endif
                                                    @if(!empty($official['discovery_links']['cspan']))
                                                        <a href="{{ $official['discovery_links']['cspan'] }}" target="_blank" rel="noopener" class="text-emerald-400 hover:text-emerald-300">C-SPAN</a>
                                                    @endif
                                                </div>
                                            @endif
                                            @if(!empty($official['website']))
                                                <a href="{{ $official['website'] }}" target="_blank" rel="noopener" class="inline-block text-emerald-400 hover:text-emerald-300">
                                                    Official website →
                                                </a>
                                            @endif
                                            @if(!empty($official['profile_slug']))
                                                <a href="{{ route('politician.public.show', $official['profile_slug']) }}" class="inline-block text-emerald-400 hover:text-emerald-300 font-medium">
                                                    View full profile →
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                @endif
            </section>

            <section class="mt-10">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-bold text-white">Current Running Candidates (Public Records)</h2>
                    <span class="text-sm text-slate-400">{{ $runningCandidates->count() }} found</span>
                </div>

                @if($runningCandidates->isEmpty())
                    <div class="bg-slate-900/60 border border-slate-700/50 rounded-xl p-6">
                        <p class="text-slate-300">No current election-record candidates were found for this district yet.</p>
                    </div>
                @else
                    @if($topContenders->isNotEmpty())
                        <div class="mb-4">
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-amber-300 mb-2">Top Contenders</h3>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                @foreach($topContenders as $candidate)
                                    <div class="bg-amber-900/20 border border-amber-600/30 rounded-lg p-4">
                                        <p class="text-white font-semibold">{{ $candidate['full_name'] }}</p>
                                        <p class="text-slate-300 text-sm mt-1">{{ $candidate['political_office'] ?: 'Candidate' }}</p>
                                        <p class="text-xs text-slate-400 mt-2">
                                            {{ $candidate['party_affiliation'] ?: 'Party not listed' }}
                                            @if(!empty($candidate['election_date']))
                                                • Election: {{ $candidate['election_date'] }}
                                            @endif
                                        </p>
                                        @if(!empty($candidate['finance_snapshot']['summary']))
                                            <div class="pt-2 mt-2 border-t border-amber-700/40 space-y-1 text-xs text-slate-400">
                                                <p class="text-emerald-300">Campaign finance (open data)</p>
                                                @if(!empty($candidate['finance_snapshot']['summary']['receipts']))
                                                    <p>Receipts: {{ $candidate['finance_snapshot']['summary']['receipts'] }}</p>
                                                @endif
                                                @if(!empty($candidate['finance_snapshot']['summary']['cash_on_hand']))
                                                    <p>Cash on hand: {{ $candidate['finance_snapshot']['summary']['cash_on_hand'] }}</p>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($runningGrid->isNotEmpty())
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($runningGrid as $candidate)
                            <div class="bg-slate-900/60 border border-slate-700/50 rounded-xl p-5">
                                <p class="text-white font-semibold">{{ $candidate['full_name'] }}</p>
                                <p class="text-slate-400 text-sm mt-1">{{ $candidate['political_office'] ?: 'Candidate' }}</p>

                                <div class="mt-3 space-y-1 text-xs text-slate-400">
                                    @if(!empty($candidate['district']))
                                        <p>District: {{ $candidate['district'] }}</p>
                                    @endif
                                    <p>Party: {{ $candidate['party_affiliation'] ?: 'Not listed' }}</p>
                                    @if(!empty($candidate['election_date']))
                                        <p>Election date: {{ $candidate['election_date'] }}</p>
                                    @endif
                                    @if(!empty($candidate['finance_snapshot']['summary']))
                                        <div class="pt-2 border-t border-slate-700/40 space-y-1">
                                            <p class="text-emerald-300">Campaign finance (open data)</p>
                                            @if(!empty($candidate['finance_snapshot']['summary']['receipts']))
                                                <p>Receipts: {{ $candidate['finance_snapshot']['summary']['receipts'] }}</p>
                                            @endif
                                            @if(!empty($candidate['finance_snapshot']['summary']['cash_on_hand']))
                                                <p>Cash on hand: {{ $candidate['finance_snapshot']['summary']['cash_on_hand'] }}</p>
                                            @endif
                                            @if(!empty($candidate['finance_snapshot']['summary']['debt']))
                                                <p>Debt: {{ $candidate['finance_snapshot']['summary']['debt'] }}</p>
                                            @endif
                                            @if(!empty($candidate['finance_snapshot']['source_url']))
                                                <a href="{{ $candidate['finance_snapshot']['source_url'] }}" target="_blank" rel="noopener" class="inline-block text-emerald-400 hover:text-emerald-300">
                                                    Source filing ->
                                                </a>
                                            @endif
                                        </div>
                                    @endif
                                    <p>Source: {{ $candidate['source_label'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
                @endif
            </section>
        @endif
    </main>
</body>
</html>
