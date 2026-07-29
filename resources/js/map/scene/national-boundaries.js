/**
 * National district boundary layer — fetches all 435 districts as LineSegments.
 */
import * as THREE from 'three';
import { mapGroup } from './setup.js';
import { project } from './projection.js';
import { REGIONS, STATE_FIPS, FIPS_TO_ABBR, PARTY_INT, DISTRICT_PARTY_MAP } from '../config/constants.js';
import { DISTRICT_CONFIG, mapMode, activeRegion, ACTIVE_LAYERS } from '../state/map-state.js';
import { getTigerwebUrl } from '../api/district-config.js';
import { idbGet, idbSet } from '../utils/idb-cache.js';

const NAT_TIGER_IDB_TTL = 30 * 24 * 60 * 60 * 1000; // 30 days

export const nationalDistGroups = {};
export const nationalDistLoadedFor = new Set();
export let nationalDistVisible = false;
export let nationalDistLoading = false;

function _natDistScopeKey() {
    return (mapMode === 'overview' || !activeRegion) ? '__all__' : activeRegion;
}

function _natDistFips(scopeKey) {
    if (scopeKey === '__all__') return [...new Set(Object.values(STATE_FIPS))].sort();
    const region = REGIONS[scopeKey];
    if (!region) return [];
    return region.states.map(s => STATE_FIPS[s]).filter(Boolean);
}

export function _syncNatDistVisibility() {
    if (!nationalDistVisible) return;
    if (mapMode === 'state') {
        for (const grp of Object.values(nationalDistGroups)) grp.visible = false;
        return;
    }
    const scopeKey = _natDistScopeKey();
    for (const [key, grp] of Object.entries(nationalDistGroups)) {
        grp.visible = (key === scopeKey);
    }
    if (!nationalDistLoadedFor.has(scopeKey) && !nationalDistLoading) {
        loadNationalBoundaries(scopeKey);
    }
}

function buildLineLoopsFromFeature(feat, color) {
    const lines = [];
    const polys = feat.geometry.type === 'MultiPolygon'
        ? feat.geometry.coordinates
        : [feat.geometry.coordinates];

    for (const poly of polys) {
        for (const ring of poly) {
            const pts = [];
            for (const coord of ring) {
                const p = project(coord);
                if (p) pts.push(new THREE.Vector3(p[0], p[1], 0.258));
            }
            if (pts.length < 2) continue;
            const geo = new THREE.BufferGeometry().setFromPoints(pts);
            const mat = new THREE.LineBasicMaterial({ color, transparent: true, opacity: 0.72 });
            lines.push(new THREE.Line(geo, mat));
        }
    }
    return lines;
}

const fipsToRegionHex = (() => {
    const map = {};
    for (const [regionName, region] of Object.entries(REGIONS)) {
        for (const [stateName, fips] of Object.entries(STATE_FIPS)) {
            if (region.states.includes(stateName)) {
                map[fips] = region.hex;
            }
        }
    }
    return map;
})();

async function fetchStateDistrictsLow(fips) {
    const cdField = DISTRICT_CONFIG.cd_field;
    // Check IndexedDB first — the "low" precision version shares a separate key
    // from the detailed district-overlay to avoid polluting the detailed cache.
    const idbKey = `u9_tigerweb_nat_${cdField}_${fips}`;
    const cached = await idbGet(idbKey);
    if (cached?.length) return cached;

    const params = new URLSearchParams({
        where: `STATE='${fips}'`,
        outFields: `STATE,${cdField}`,
        returnGeometry: 'true',
        f: 'geojson',
        geometryPrecision: '2',
        inSR: '4326',
        outSR: '4326',
    });
    const res = await fetch(`${getTigerwebUrl()}?${params}`);
    const data = await res.json();
    const features = data.features || [];
    if (features.length) idbSet(idbKey, features, NAT_TIGER_IDB_TTL).catch(() => {});
    return features;
}

export async function loadNationalBoundaries(scopeKey) {
    if (scopeKey === undefined) scopeKey = _natDistScopeKey();

    if (nationalDistLoadedFor.has(scopeKey)) {
        for (const [key, grp] of Object.entries(nationalDistGroups)) {
            grp.visible = (key === scopeKey);
        }
        nationalDistVisible = true;
        updateDistrictsBtn(true);
        return;
    }

    if (nationalDistLoading) return;
    nationalDistLoading = true;

    const progress = document.getElementById('dist-progress');
    progress.style.color = '#94a3b8';
    progress.style.display = 'block';

    try {
        const grp = new THREE.Group();
        mapGroup.add(grp);
        nationalDistGroups[scopeKey] = grp;

        const allFips = _natDistFips(scopeKey);
        let done = 0;
        const BATCH = 8;

        for (let i = 0; i < allFips.length; i += BATCH) {
            const batch = allFips.slice(i, i + BATCH);
            progress.innerHTML = `<svg width="13" height="13" viewBox="0 0 24 24" fill="none" style="animation:spin 1s linear infinite;color:#6366f1;vertical-align:middle;margin-right:6px;"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="31.4" stroke-dashoffset="10" stroke-linecap="round"/></svg>
                Loading districts… ${done}/${allFips.length} states`;

            await Promise.all(batch.map(async fips => {
                try {
                    const features = await fetchStateDistrictsLow(fips);
                    const stateAbbr = FIPS_TO_ABBR[fips];
                    const cdField = DISTRICT_CONFIG.cd_field;
                    for (const feat of features) {
                        const cdRaw = String(feat.properties[cdField] ?? feat.properties['CD119'] ?? '0').padStart(2, '0');
                        const dn = cdRaw === '00' ? 'AL' : String(parseInt(cdRaw));
                        const pKey = dn === 'AL' ? `${stateAbbr}-AL` : `${stateAbbr}-${dn}`;
                        const party = DISTRICT_PARTY_MAP[pKey] || 'U';
                        const col = new THREE.Color(PARTY_INT[party]).lerp(new THREE.Color(0xffffff), 0.38).getHex();
                        for (const line of buildLineLoopsFromFeature(feat, col)) {
                            grp.add(line);
                        }
                    }
                } catch { /* skip failed states silently */ }
                done++;
            }));
        }

        nationalDistLoadedFor.add(scopeKey);
        nationalDistVisible = true;
        for (const [key, g] of Object.entries(nationalDistGroups)) {
            g.visible = (key === scopeKey);
        }
        progress.style.display = 'none';
        updateDistrictsBtn(true);
    } catch (err) {
        progress.style.display = 'none';
        progress.textContent = `⚠ Failed: ${err.message}`;
        progress.style.color = '#ef4444';
        progress.style.display = 'block';
        setTimeout(() => { progress.style.display = 'none'; }, 5000);
    }
    nationalDistLoading = false;
}

export function toggleNationalBoundaries() {
    const scopeKey = _natDistScopeKey();
    if (!nationalDistLoadedFor.has(scopeKey)) {
        nationalDistVisible = true;
        loadNationalBoundaries(scopeKey);
        return;
    }
    nationalDistVisible = !nationalDistVisible;
    if (nationalDistGroups[scopeKey]) {
        nationalDistGroups[scopeKey].visible = nationalDistVisible;
    }
    const btn = document.getElementById('btn-districts');
    if (btn) { btn.textContent = `District Boundaries: ${nationalDistVisible ? 'ON' : 'OFF'}`; btn.classList.toggle('active', nationalDistVisible); }
    updateDistrictsBtn(nationalDistVisible);
}

function updateDistrictsBtn(on) {
    document.getElementById('cm-btn-districts')?.classList.toggle('active', on);
    syncLayerChip('districts', on);
}

// Local syncLayerChip (duplicated from layers-panel to avoid circular dependency)
function syncLayerChip(layerKey, isActive) {
    const chip = document.querySelector(`[data-layer="${layerKey}"]`);
    if (chip) {
        chip.classList.toggle('active', isActive);
        chip.setAttribute('aria-checked', String(isActive));
    }
    if (isActive) ACTIVE_LAYERS.add(layerKey);
    else ACTIVE_LAYERS.delete(layerKey);
    try { localStorage.setItem('u9map_layers', JSON.stringify([...ACTIVE_LAYERS])); } catch (_) {}
}