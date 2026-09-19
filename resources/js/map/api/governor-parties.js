/**
 * State overlays — fetches and caches all-50-states-at-once data for the
 * map's overview-zoom choropleth layers (governor party for Party Control,
 * poverty rate for Poverty Rate), and applies whichever is the active
 * colorMode to stateMeshes. Results are cached for 24h in localStorage.
 */
import * as THREE from 'three';
import { STATE_ABBR_MAP, PARTY_HEX, PARTY_INT } from '../config/constants.js';
import { govPartyByAbbr, povertyRateByAbbr, colorMode, mapMode, activeRegion, setColorMode } from '../state/map-state.js';
import { stateMeshes } from '../scene/state-meshes.js';
import { syncLayerChip } from '../state/layer-directory.js';
import { showRegionLegend, showGradientLegend, showGovernorPartyLegend, syncLegendModeSwitch } from '../ui/legend.js';

const STATE_OVERLAYS_KEY = 'u9_map_state_overlays_cache';
const STATE_OVERLAYS_TTL = 24 * 60 * 60 * 1000; // 24h

const POVERTY_LOW = new THREE.Color(0x0f2040);
const POVERTY_HIGH = new THREE.Color(0x06b6d4);

let _stateOverlaysPromise = null;

function _readCache() {
    try {
        const raw = localStorage.getItem(STATE_OVERLAYS_KEY);
        if (!raw) return null;
        const { ts, data } = JSON.parse(raw);
        if (Date.now() - ts > STATE_OVERLAYS_TTL) { localStorage.removeItem(STATE_OVERLAYS_KEY); return null; }
        return data;
    } catch { return null; }
}

function _writeCache(data) {
    try { localStorage.setItem(STATE_OVERLAYS_KEY, JSON.stringify({ ts: Date.now(), data })); } catch {}
}

export function getStatePartyColor(abbr) {
    const party = govPartyByAbbr[abbr];
    return party ? PARTY_HEX[party] || PARTY_HEX.U : PARTY_HEX.U;
}

/**
 * Fetch (or reuse cached/in-flight) all-states governor-party + poverty-rate
 * data. Idempotent — safe to call from both the Party Control and Poverty
 * Rate toggles without double-fetching.
 */
export async function ensureStateOverlays() {
    if (Object.keys(govPartyByAbbr).length || Object.keys(povertyRateByAbbr).length) {
        return { governor_parties: govPartyByAbbr, poverty_rate: povertyRateByAbbr };
    }

    const cached = _readCache();
    if (cached) {
        Object.assign(govPartyByAbbr, cached.governor_parties || {});
        Object.assign(povertyRateByAbbr, cached.poverty_rate || {});
        return cached;
    }

    if (_stateOverlaysPromise) return _stateOverlaysPromise;

    _stateOverlaysPromise = (async () => {
        try {
            const res = await fetch('/api/v1/map/state-overlays');
            if (!res.ok) return { governor_parties: govPartyByAbbr, poverty_rate: povertyRateByAbbr };
            const data = await res.json();
            Object.assign(govPartyByAbbr, data.governor_parties || {});
            Object.assign(povertyRateByAbbr, data.poverty_rate || {});
            _writeCache(data);
        } catch { /* degrade gracefully */ }
        return { governor_parties: govPartyByAbbr, poverty_rate: povertyRateByAbbr };
    })();

    return _stateOverlaysPromise;
}

/**
 * Continuous poverty-rate value -> lerped THREE.Color, normalized over the
 * min/max of whatever states currently have data. Same lerp approach
 * applyPopulationDensity() (ui/layers-panel.js) already uses for districts.
 */
function povertyColorFor(rate, min, range) {
    const t = range > 0 ? (rate - min) / range : 0;
    return POVERTY_LOW.clone().lerp(POVERTY_HIGH, t);
}

/**
 * Resolve the fill color a state mesh should have for the active colorMode
 * ('region' | 'party' | 'poverty'). Region colors come from each mesh's own
 * immutable userData.originalColor (set once at build time in
 * scene/state-meshes.js) — nothing here may write back to it, or a later
 * revert-to-region would use a corrupted value instead of the true base color.
 * @returns {(mesh) => number} hex color resolver
 */
function baseColorResolver() {
    if (colorMode === 'party') {
        return (m) => PARTY_INT[govPartyByAbbr[STATE_ABBR_MAP[m.userData.name]] || 'U'];
    }
    if (colorMode === 'poverty') {
        const vals = Object.values(povertyRateByAbbr).filter((v) => typeof v === 'number');
        const min = vals.length ? Math.min(...vals) : 0;
        const range = vals.length ? (Math.max(...vals) - min || 1) : 0;
        return (m) => {
            const rate = povertyRateByAbbr[STATE_ABBR_MAP[m.userData.name]];
            return rate == null ? m.userData.originalColor : povertyColorFor(rate, min, range).getHex();
        };
    }
    return (m) => m.userData.originalColor;
}

/** Fill color for one state mesh under the active colorMode. */
export function baseColorHex(mesh) {
    return baseColorResolver()(mesh);
}

const DIMMED_STATE = 0x1a2240;

/**
 * Recolor stateMeshes for the active colorMode. In region view, states
 * outside the active region stay dimmed so the focus survives a mode switch.
 */
export function applyOverviewColorMode() {
    // Inside a state the other states are deliberately dimmed; the chosen mode
    // is repainted when the visitor returns to the overview or a region.
    if (mapMode === 'state') return;
    const colorFor = baseColorResolver();
    for (const m of stateMeshes) {
        const outside = mapMode === 'region' && activeRegion && m.userData.regionName !== activeRegion;
        m.material.color.setHex(outside ? DIMMED_STATE : colorFor(m));
    }
}

/** Poverty-rate value range currently applied, for the gradient legend. */
export function getPovertyRange() {
    const vals = Object.values(povertyRateByAbbr).filter((v) => typeof v === 'number');
    if (!vals.length) return null;
    return { min: Math.min(...vals), max: Math.max(...vals), lowHex: '#0f2040', highHex: '#06b6d4' };
}

/** States per governor party, counted once per state (not per mesh polygon). */
function governorPartyCounts() {
    const counts = {};
    const seen = new Set();
    for (const m of stateMeshes) {
        const name = m.userData.name;
        if (seen.has(name)) continue;
        seen.add(name);
        const party = govPartyByAbbr[STATE_ABBR_MAP[name]] || 'U';
        counts[party] = (counts[party] || 0) + 1;
    }
    return counts;
}

/**
 * Show the legend matching the active colorMode. Inside a state the
 * district party legend owns the panel, so this leaves it alone.
 */
export function refreshOverviewLegend(mode = colorMode) {
    if (mapMode === 'state') return;
    if (mode === 'poverty') {
        const range = getPovertyRange();
        showGradientLegend({
            title: 'Poverty rate',
            note: 'Colors show each state’s poverty rate (Census ACS).',
            lowHex: range?.lowHex || '#0f2040',
            highHex: range?.highHex || '#06b6d4',
            minLabel: range ? `${range.min.toFixed(1)}%` : 'Low',
            maxLabel: range ? `${range.max.toFixed(1)}%` : 'High',
        });
    } else if (mode === 'party') {
        showGovernorPartyLegend(governorPartyCounts());
    } else {
        // Also clears a stale gradient legend left over from poverty mode.
        showRegionLegend();
    }
}

/**
 * Single entry point for changing the overview-zoom state-fill color mode.
 * Called from both the Layers panel chips (party/poverty) and the Controls
 * menu's "Party Colors" button, so the mutual-exclusion rule (poverty and
 * party both recolor the same stateMeshes — only one can be "on" at a time)
 * lives in exactly one place instead of being duplicated at every call site.
 * @param {'region'|'party'|'poverty'} mode
 */
export function setOverviewColorMode(mode) {
    setColorMode(mode);
    syncLayerChip('party', mode === 'party');
    syncLayerChip('poverty', mode === 'poverty');
    document.getElementById('cm-btn-party-colors')?.classList.toggle('active', mode === 'party');
    syncLegendModeSwitch();

    const finish = () => {
        applyOverviewColorMode();
        refreshOverviewLegend(mode);
    };

    if (mode === 'party' || mode === 'poverty') {
        ensureStateOverlays().then(finish);
    } else {
        finish();
    }
}
