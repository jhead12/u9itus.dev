<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>U.S. Regional Map – {{ config('app.name', 'U9itus') }}</title>
    @include('standalone.partials.seo-head')
    <meta name="description" content="Explore an interactive 3D map of all 50 U.S. states and 435 congressional districts. Discover politicians, candidates, and civic officials for your area.">
    <link rel="canonical" href="{{ url('/map') }}">
    <meta property="og:type"        content="website">
    <meta property="og:url"         content="{{ url('/map') }}">
    <meta property="og:title"       content="U.S. Regional Map – {{ config('app.name', 'U9itus') }}">
    <meta property="og:description" content="Explore an interactive 3D map of all 50 U.S. states and 435 congressional districts. Discover politicians, candidates, and civic officials for your area.">
    <meta name="twitter:card"       content="summary">
    <meta name="twitter:title"      content="U.S. Regional Map – {{ config('app.name', 'U9itus') }}">
    <meta name="twitter:description" content="Explore an interactive 3D map of all 50 U.S. states and 435 congressional districts.">

    {{-- ── Auth-aware map context ──────────────────────────────────────────
         Lightweight meta tags read once at boot by window.U9.session.
         Guests get role=guest and a null UUID. Voter referral_code is
         exposed so client-side share helpers can attach ?ref= to outbound
         links without an extra fetch. All values are HTML-escaped.

         Behavior is gated by config('platform.map.voter_features_enabled').
         When the flag is off the entire block is omitted so the map renders
         identically to the pre-integration version. ────────────────────── --}}
    @if (config('platform.map.voter_features_enabled'))
        @auth
            @php($u9Voter = auth()->user()->voter ?? null)
            @php(
                $u9Role = auth()->user()->hasRole('admin') ? 'admin'
                    : (auth()->user()->hasRole('politician') ? 'politician'
                        : (auth()->user()->hasRole('voter') ? 'voter' : 'user'))
            )
            <meta name="csrf-token"   content="{{ csrf_token() }}">
            <meta name="u9-user-role" content="{{ $u9Role }}">
            <meta name="u9-user-uuid" content="{{ $u9Voter?->uuid ?? '' }}">
            <meta name="u9-ref-code"  content="{{ $u9Voter?->referral_code ?? '' }}">
        @else
            <meta name="u9-user-role" content="guest">
        @endauth
    @endif

    @vite(['resources/js/map/app.js'])
    {{-- map.css is bundled as a dependency of app.js (imported in resources/js/map/app.js)
         and emitted automatically via the entry's manifest `css` array. --}}
</head>
<body>

{{-- Skip-to-main for screen readers & keyboard users --}}
<a id="skip-to-main" href="#map-canvas-region">Skip to map</a>

{{-- Keyboard help overlay (? key) --}}
<div id="kb-help" role="dialog" aria-modal="true" aria-label="Keyboard shortcuts">
    <div id="kb-help-box">
        <h2>⌨ Keyboard Controls</h2>
        <table class="kb-table" aria-label="Keyboard shortcuts list">
            <tbody>
                <tr><td><kbd>Tab</kbd></td><td>Focus the map canvas</td></tr>
                <tr><td><kbd>Enter</kbd> / <kbd>Space</kbd></td><td>Open search to select a state</td></tr>
                <tr><td><kbd>↑</kbd> <kbd>↓</kbd></td><td>Tilt map (max 38°)</td></tr>
                <tr><td><kbd>+</kbd> / <kbd>=</kbd></td><td>Zoom in</td></tr>
                <tr><td><kbd>−</kbd></td><td>Zoom out</td></tr>
                <tr><td><kbd>R</kbd></td><td>Reset view</td></tr>
                <tr><td><kbd>O</kbd></td><td>Toggle offices section</td></tr>
                <tr><td><kbd>Esc</kbd></td><td>Close panel / popup</td></tr>
                <tr><td><kbd>?</kbd></td><td>Show / hide this help</td></tr>
                <tr><td colspan="2" style="padding-top:10px;padding-bottom:2px;font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#475569;">Mouse</td></tr>
                <tr><td><kbd>Shift</kbd> + drag</td><td>Pan map</td></tr>
                <tr><td>Scroll wheel</td><td>Zoom in / out</td></tr>
                <tr><td>Right-drag</td><td>Pan map</td></tr>
            </tbody>
        </table>
        <button id="kb-help-close" aria-label="Close keyboard help">Close</button>
        <button id="kb-tour-btn" aria-label="Replay feature tour"
                onclick="toggleKbHelp(false); setTimeout(() => window.startTutorial(true), 200)"
                style="margin-top:8px;width:100%;background:rgba(99,102,241,0.12);border:1px solid rgba(99,102,241,0.3);color:#a5b4fc;border-radius:8px;padding:8px;font-size:12px;cursor:pointer;">
            🗺 Replay feature tour
        </button>
    </div>
</div>

{{-- Focusable canvas region + visible focus ring --}}
<div id="map-canvas-region"
     tabindex="0"
     role="application"
     aria-label="Interactive U.S. map. Use arrow keys to rotate, + and - to zoom, Enter to search for a state, ? for keyboard help."
     aria-description="Use arrow keys to rotate, + and - to zoom, Enter to open search."></div>
<div id="kb-focus-ring" aria-hidden="true"></div>

{{-- Keyboard shortcut badge is now rendered inside #breadcrumb-bar --}}

<div id="loading">
    <svg class="spinner" width="44" height="44" viewBox="0 0 24 24" fill="none" style="color:#6366f1;">
        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="31.4" stroke-dashoffset="10" stroke-linecap="round"/>
    </svg>
    <p style="color:#475569; font-size:13px; margin-top:14px;">Loading map data…</p>
</div>

<div id="map-container" style="position:fixed; inset:0;"></div>

<div id="top-bar">
    <div style="display:flex; align-items:center; gap:12px;">
        <a href="{{ url('/') }}">U9itus</a>
        <span class="sep">|</span>
        <span class="title">U.S. Regional Map</span>
    </div>
    <div style="display:flex; gap:8px; align-items:center;">
        {{-- ── Sign-in CTA (guest only, behind the map.sign_in_cta flag) ── --}}
        @if (config('platform.map.voter_features_enabled') && config('platform.map.sign_in_cta') && auth()->guest())
            @php(
                $u9LoginUrl = url('/earn') . '?' . http_build_query(array_filter([
                    'ref'  => request()->query('ref'),
                    'from' => 'map',
                ]))
            )
            <button id="btn-signin-cta" class="top-btn"
               aria-haspopup="dialog" aria-expanded="false" aria-controls="earn-popover"
               title="Learn how to earn on U9itus">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2.5"
                     stroke-linecap="round" stroke-linejoin="round"
                     aria-hidden="true">
                    <line x1="12" y1="1" x2="12" y2="23"/>
                    <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                </svg>
            </button>
        @endif

        <button id="btn-back">← Back</button>
        <button class="top-btn" id="btn-search" title="Search states and districts (press /)"
            aria-label="Search states and districts">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <span class="btn-hover-label">Search</span>
            <span class="btn-hover-label" style="font-size:10px;color:#475569;border:1px solid #334155;border-radius:3px;padding:1px 5px;font-family:monospace;">/</span>
        </button>
        <!-- Layers multi-select panel -->
        <div id="layers-wrap">
            <button class="top-btn" id="btn-layers"
                aria-haspopup="true" aria-expanded="false" aria-controls="layers-panel"
                title="Toggle map data layers">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="12 2 2 7 12 12 22 7 12 2"/>
                    <polyline points="2 17 12 22 22 17"/>
                    <polyline points="2 12 12 17 22 12"/>
                </svg>
                <span class="btn-hover-label">Layers</span>
            </button>
            <div id="layers-panel" role="menu" aria-label="Map data layers">
                <div class="lp-section">Boundaries</div>
                <div class="lp-chips">
                    <button class="lp-chip" data-layer="districts"
                        role="menuitemcheckbox" aria-checked="false"
                        title="Overlay congressional district lines across all 50 states">
                        <span class="lp-dot"></span>Congressional Districts
                    </button>
                    <button class="lp-chip" data-layer="cities"
                        role="menuitemcheckbox" aria-checked="false"
                        title="Show incorporated city &amp; town boundaries (loads when a state is selected)">
                        <span class="lp-dot"></span>City Limits
                    </button>
                    <button class="lp-chip" data-layer="topcities"
                        role="menuitemcheckbox" aria-checked="false"
                        title="Show top cities by 2020 Census population and state government offices">
                        <span class="lp-dot"></span>Top Cities &amp; Gov
                    </button>
                </div>
                <div class="lp-section">Data Overlays</div>
                <div class="lp-chips">
                    <button class="lp-chip" data-layer="party"
                        role="menuitemcheckbox" aria-checked="false"
                        title="Color states by the party of the current governor">
                        <span class="lp-dot"></span>Party Control
                    </button>
                    <button class="lp-chip" data-layer="population"
                        role="menuitemcheckbox" aria-checked="false"
                        title="Shade congressional districts by resident population — darker = more people">
                        <span class="lp-dot"></span>Population Density
                    </button>
                </div>
            </div>
        </div>
        <!-- Controls dropdown -->
        <div id="controls-wrap">
            <button class="top-btn" id="btn-controls" aria-haspopup="true" aria-expanded="false" aria-controls="controls-menu" title="Map controls">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                    <line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="14" y2="12"/><line x1="4" y1="18" x2="18" y2="18"/>
                </svg>
                <span class="btn-hover-label">Controls</span>
            </button>
            <div id="controls-menu" role="menu" aria-label="Map controls">
                <div class="cm-section">View</div>
                <button class="cm-item" id="cm-btn-reset" role="menuitem">
                    <span>Reset View</span>
                    <span class="cm-kbd">R</span>
                </button>
                <button class="cm-item" id="cm-btn-districts" role="menuitem">
                    <span>District Boundaries</span>
                    <span class="cm-toggle" aria-hidden="true"></span>
                </button>
                <button class="cm-item" id="cm-btn-party-colors" role="menuitem" title="Color states by governor's party instead of region">
                    <span>Party Control Colors</span>
                    <span class="cm-toggle" aria-hidden="true"></span>
                </button>
                <button class="cm-item" id="cm-btn-small-cities" role="menuitem" title="Also show cities with fewer than 500K residents">
                    <span>Cities &lt; 500K</span>
                    <span class="cm-toggle" aria-hidden="true"></span>
                </button>
                <hr class="cm-divider">
                <div class="cm-section">Mouse</div>
                <div class="cm-item" style="cursor:default;pointer-events:none;">
                    <span>Pan Map</span>
                    <span class="cm-kbd">Shift + Drag</span>
                </div>
                <hr class="cm-divider">
                <div class="cm-section">Keyboard</div>
                <button class="cm-item" id="cm-btn-kb-help" role="menuitem">
                    <span>Keyboard Shortcuts</span>
                    <span class="cm-kbd">?</span>
                </button>
                <button class="cm-item" id="cm-btn-tutorial" role="menuitem">
                    <span>Replay Tutorial</span>
                    <span style="font-size:13px;">🗺</span>
                </button>
                <hr class="cm-divider">
                <div class="cm-section">Zoom</div>
                <button class="cm-item" id="cm-btn-zoomin" role="menuitem">
                    <span>Zoom In</span>
                    <span class="cm-kbd">+</span>
                </button>
                <button class="cm-item" id="cm-btn-zoomout" role="menuitem">
                    <span>Zoom Out</span>
                    <span class="cm-kbd">−</span>
                </button>
                {{-- Explore & Account --}}
                <div class="cm-section" style="margin-top:4px;border-top:1px solid rgba(99,102,241,0.15);padding-top:6px;">Explore</div>
                <a class="cm-item" role="menuitem"
                   href="{{ route('politicians.directory') }}"
                   style="text-decoration:none;">
                    <span>Browse Politicians</span>
                </a>
                <a class="cm-item" role="menuitem"
                   href="{{ route('district.lookup') }}"
                   style="text-decoration:none;">
                    <span>Find My District</span>
                </a>
                @guest
                    <div class="cm-section" style="margin-top:4px;border-top:1px solid rgba(99,102,241,0.15);padding-top:6px;">Account</div>
                    <a class="cm-item" role="menuitem"
                       href="{{ url('/login') }}?return={{ urlencode('/map') }}"
                       style="text-decoration:none;">
                        <span>Sign In</span>
                    </a>
                @endguest
            </div>
        </div>
        <!-- Hidden legacy buttons kept for JS compatibility (visible in mobile drawer) -->
        <button class="top-btn" id="btn-districts" style="display:none">District Boundaries: OFF</button>
        <button class="top-btn" id="btn-reset" style="display:none">Reset View</button>
        <!-- Mobile only: hamburger -->
        <button id="mobile-menu-btn" aria-label="Open menu" aria-expanded="false" aria-controls="mobile-menu">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                <line x1="3" y1="6" x2="21" y2="6"/>
                <line x1="3" y1="12" x2="21" y2="12"/>
                <line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
        </button>
    </div>
</div>

<!-- Mobile navigation drawer -->
<div id="mobile-menu" role="menu" aria-label="Map controls">
    <div class="mobile-menu-row">
        <button class="mobile-menu-btn" id="mob-btn-districts">
            📍 Districts<br><span style="font-size:10px;opacity:.7;">OFF</span>
        </button>
        <button class="mobile-menu-btn" id="mob-btn-reset">
            🏠 Reset View
        </button>
    </div>
</div>

<!-- Search Palette -->
<div id="search-overlay" role="dialog" aria-modal="true" aria-label="Search states and districts">
    <div id="search-box">
        <div id="search-input-wrap">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
            </svg>
            <input id="search-input" type="text" placeholder="Search state or district… e.g. &quot;California&quot;, &quot;CA-38&quot;, &quot;Texas 7&quot;" autocomplete="off" spellcheck="false">
            <span id="search-kbd">esc</span>
        </div>
        <div id="search-results" role="listbox"></div>
        <div id="search-empty">🔍 No results for that state or district</div>
        <div id="search-footer">
            <span><kbd>↵</kbd> select</span>
            <span><kbd>↑</kbd><kbd>↓</kbd> navigate</span>
            <span><kbd>esc</kbd> close</span>
            <span style="margin-left:auto;">Type a state name, abbrev., or "CA-38"</span>
        </div>
    </div>
</div>

<!-- Candidate quick-view popup -->
<div id="cand-popup" role="dialog" aria-modal="true">
    <button id="cand-popup-close" aria-label="Close">✕</button>
    <div class="popup-header">
        <div id="popup-avatar-wrap"></div>
        <div>
            <div class="popup-name" id="popup-name"></div>
            <div class="popup-office" id="popup-office"></div>
        </div>
    </div>
    <hr class="popup-divider">
    <p class="popup-bio" id="popup-bio"></p>
    <div class="popup-stats">
        <div class="popup-stat">
            <span class="popup-stat-val" id="popup-raised"></span>
            <span class="popup-stat-lbl">Raised (est.)</span>
        </div>
        <div class="popup-stat">
            <span class="popup-stat-val" id="popup-status"></span>
            <span class="popup-stat-lbl">Status</span>
        </div>
        <div class="popup-stat">
            <span class="popup-stat-val" id="popup-party-badge"></span>
            <span class="popup-stat-lbl">Party</span>
        </div>
    </div>
    <p class="popup-stance" id="popup-stance"></p>
    <div class="popup-actions">
        <a id="popup-campaign-link" href="#" class="popup-btn popup-btn-primary" target="_blank">👤 View Profile</a>
        <a id="popup-bp-link" href="#" class="popup-btn popup-btn-secondary" target="_blank" rel="noopener">Ballotpedia →</a>
    </div>
</div>

<!-- Floating district labels layer -->
<div id="map-labels-layer" aria-hidden="true"></div>

<!-- Politician profile drawer -->
<div id="pol-drawer" role="dialog" aria-modal="true" aria-labelledby="pol-drawer-name" hidden>
    <button id="pol-drawer-close" aria-label="Close politician profile">✕</button>
    <div class="pol-hero" id="pol-hero"><!-- filled by JS --></div>
    <nav class="pol-tabs" role="tablist" aria-label="Politician information tabs">
        <button class="pol-tab active" role="tab" data-tab="overview" aria-selected="true"  id="pol-tab-overview">Overview</button>
        <button class="pol-tab"        role="tab" data-tab="economy"  aria-selected="false" id="pol-tab-economy">Economy</button>
        <button class="pol-tab"        role="tab" data-tab="contact"  aria-selected="false" id="pol-tab-contact">Contact</button>
    </nav>
    <div class="pol-body" id="pol-body" role="tabpanel" aria-labelledby="pol-tab-overview"><!-- filled by JS --></div>
</div>

<div id="dist-progress">
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" style="animation:spin 1s linear infinite;color:#6366f1;vertical-align:middle;margin-right:6px;">
        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="31.4" stroke-dashoffset="10" stroke-linecap="round"/>
    </svg>
    Loading all 435 congressional districts…
</div>

<div id="breadcrumb-bar">
    <div id="breadcrumb"><span class="bc-item bc-active">Overview</span></div>
    <button id="kb-hint-badge" aria-label="Show keyboard shortcuts" title="Keyboard shortcuts (press ?)">
        <kbd>?</kbd> Shortcuts
    </button>
</div>

<div id="tooltip"></div>
<div id="district-tooltip"></div>

<div id="legend">
    <h3 role="button" tabindex="0" style="cursor:pointer;user-select:none;"
        onclick="this.closest('#legend').classList.toggle('legend-collapsed')"
        onkeydown="if(event.key==='Enter'||event.key===' ')this.click()"
        title="Tap to show/hide">
        Party Control <span id="legend-toggle-icon" style="font-size:9px;opacity:.6;">▾</span>
    </h3>
    <div id="legend-items"></div>
</div>

<div id="info-panel">
    <div class="panel-drag-handle" role="button" aria-label="Expand or collapse panel" tabindex="0"
         onclick="(function(h){
             var p=h.closest('#info-panel');
             if(p){ p.classList.toggle('expanded'); }
         })(this)"
         onkeydown="if(event.key==='Enter'||event.key===' ')this.click()"></div>
    <div id="panel-header">
        <div>
            <h2 id="panel-state" style="color:#e2e8f0; font-size:16px; font-weight:700; margin:0 0 4px; line-height:1.25;"></h2>
            <span id="panel-badge" style="display:inline-block; padding:2px 10px; border-radius:999px; font-size:10px; font-weight:600;"></span>
        </div>
        <button id="panel-close" title="Close panel">✕</button>
    </div>
    <div id="panel-states" style="margin-bottom:6px;"></div>
    <hr class="panel-divider" style="margin:8px 0 10px;">
    <p class="panel-label panel-label-toggle" id="offices-toggle" role="button" tabindex="0"
       aria-expanded="true" aria-controls="panel-candidates"
       onclick="toggleOfficesSection()"
       onkeydown="if(event.key==='Enter'||event.key===' ')toggleOfficesSection()">
        <span>Statewide Executive Offices</span>
        <i class="panel-label-chevron">&#9660;</i>
    </p>
    <div id="panel-candidates">
        <div class="panel-spinner">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" style="animation:spin 1s linear infinite;color:#6366f1;">
                <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="31.4" stroke-dashoffset="10" stroke-linecap="round"/>
            </svg>
            Loading candidates…
        </div>
    </div>
</div>

<div id="hint" style="position:fixed;bottom:28px;right:24px;z-index:50;color:#334155;font-size:11px;text-align:right;pointer-events:none;">
    Scroll to zoom &nbsp;·&nbsp; ↑↓ tilt &nbsp;·&nbsp; ←→ rotate &nbsp;·&nbsp; Click a state
</div>

{{-- ── Earn modal — true viewport-centered overlay, rendered at root level ── --}}
@if(config('platform.map.voter_features_enabled') && auth()->guest())
<div id="earn-popover" role="dialog" aria-modal="true" aria-label="How to earn on U9itus"
     onclick="if(event.target===this){(function(){var p=document.getElementById('earn-popover'),b=document.getElementById('btn-signin-cta');p.classList.remove('open');b&&b.classList.remove('active');b&&b.setAttribute('aria-expanded','false');})()}">
    <div class="ep-card">
        <p class="ep-title">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#34d399"
                 stroke-width="2.5" stroke-linecap="round" aria-hidden="true">
                <line x1="12" y1="1" x2="12" y2="23"/>
                <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
            </svg>
            Get paid to stay informed
        </p>
        <p class="ep-body">
            Watch political campaign videos from any state on this map and earn
            <strong>up to $0.50 per video</strong> — deposited directly to your account.
            Free to join, no experience needed.
        </p>
        <div class="ep-actions">
            <a class="ep-btn-primary" href="{{ $u9LoginUrl ?? url('/earn') }}" target="_blank" rel="noopener">
                Learn More
            </a>
            <button class="ep-btn-close"
                onclick="(function(){var p=document.getElementById('earn-popover'),b=document.getElementById('btn-signin-cta');p.classList.remove('open');b.classList.remove('active');b.setAttribute('aria-expanded','false');})()">
                Close
            </button>
        </div>
    </div>
</div>
@endif

<div id="tutorial-overlay" role="dialog" aria-modal="true" aria-label="Map feature tour">
    <div id="tutorial-backdrop"></div>
    <div id="tutorial-highlight" class="hidden"></div>
    <div id="tutorial-card"></div>
</div>

{{-- ════════════════════════════════════════════════════
     MAP INTERACTION ANALYTICS
     Tracks anonymous click events for UX research.
     No PII — session_id is a random localStorage UUID.
════════════════════════════════════════════════════ --}}
<script>
(function () {
    /* ── Anonymous session ID (localStorage, never tied to a user account) ── */
    let _sid = null;
    try {
        _sid = localStorage.getItem('u9_map_sid');
        if (!_sid) {
            _sid = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
                const r = Math.random() * 16 | 0;
                return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
            });
            localStorage.setItem('u9_map_sid', _sid);
        }
    } catch { _sid = 'anon'; }

    /**
     * Fire-and-forget analytics ping.
     * @param {string} eventType
     * @param {object} payload
     */
    window.__mapTrack = function (eventType, payload = {}) {
        try {
            const body = {
                session_id: _sid,
                event_type: eventType,
                referrer:   document.referrer?.slice(0, 512) || null,
                ...payload,
            };
            const url  = '/api/v1/map/interaction';
            const blob = new Blob([JSON.stringify(body)], { type: 'application/json' });
            if (navigator.sendBeacon) {
                navigator.sendBeacon(url, blob);
            } else {
                fetch(url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' },
                    body: JSON.stringify(body),
                    keepalive: true,
                }).catch(() => {});
            }
        } catch { /* analytics must never break the UX */ }
    };
})();
</script>

</body>
</html>