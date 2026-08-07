/**
 * State overlays — fetches and caches all-50-states-at-once data for the
 * map's overview-zoom choropleth layers (governor party for Party Control,
 * poverty rate for Poverty Rate), and applies whichever is the active
 * colorMode to stateMeshes. Results are cached for 24h in localStorage.
 */
import * as THREE from 'three';
import { STATE_ABBR_MAP, PARTY_HEX, PARTY_INT } from '../config/constants.js';
import { govPartyByAbbr, povertyRateByAbbr, colorMode, mapMode, setColorMode } from '../state/map-state.js';
import { stateMeshes } from '../scene/state-meshes.js';
import { syncLayerChip } from '../state/layer-directory.js';
import { showRegionLegend, showGradientLegend } from '../ui/legend.js';

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
 * Recolor stateMeshes for the active colorMode ('region' | 'party' | 'poverty').
 * Reverts via each mesh's own immutable userData.originalColor (region color,
 * set once at build time in scene/state-meshes.js) — this function must never
 * write back to originalColor, or a later revert-to-region would use a
 * corrupted value instead of the mesh's true base color.
 */
export function applyOverviewColorMode() {
    let min = 0, range = 0;
    if (colorMode === 'poverty') {
        const vals = Object.values(povertyRateByAbbr).filter((v) => typeof v === 'number');
        if (vals.length) {
            min = Math.min(...vals);
            range = Math.max(...vals) - min || 1;
        }
    }

    for (const m of stateMeshes) {
        if (colorMode === 'party') {
            const abbr = STATE_ABBR_MAP[m.userData.name];
            const party = govPartyByAbbr[abbr] || 'U';
            m.material.color.setHex(PARTY_INT[party]);
        } else if (colorMode === 'poverty') {
            const abbr = STATE_ABBR_MAP[m.userData.name];
            const rate = povertyRateByAbbr[abbr];
            if (rate == null) {
                m.material.color.setHex(m.userData.originalColor);
            } else {
                m.material.color.copy(povertyColorFor(rate, min, range));
            }
        } else {
            m.material.color.setHex(m.userData.originalColor);
        }
    }
}

/** Poverty-rate value range currently applied, for the gradient legend. */
export function getPovertyRange() {
    const vals = Object.values(povertyRateByAbbr).filter((v) => typeof v === 'number');
    if (!vals.length) return null;
    return { min: Math.min(...vals), max: Math.max(...vals), lowHex: '#0f2040', highHex: '#06b6d4' };
}

function refreshOverviewLegend(mode) {
    if (mapMode !== 'overview') return; // don't clobber the state-view district legend
    if (mode === 'poverty') {
        const range = getPovertyRange();
        showGradientLegend({
            title: 'Poverty Rate',
            lowHex: range?.lowHex || '#0f2040',
            highHex: range?.highHex || '#06b6d4',
            minLabel: range ? `${range.min.toFixed(1)}%` : 'Low',
            maxLabel: range ? `${range.max.toFixed(1)}%` : 'High',
        });
    } else {
        // 'region' and 'party' both fall back to the region legend — there's
        // no per-governor-party overview legend today (showPartyLegend()
        // needs a district-composition breakdown only computed inside a
        // drilled-into state), and this also clears a stale gradient legend
        // left over from switching away from poverty mode.
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
