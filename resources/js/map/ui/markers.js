/**
 * City markers, government/capital markers, and city boundary overlays.
 */
import * as THREE from 'three';
import { TOP_CITIES, STATE_CAPITALS, fmtPop } from '../config/city-data.js';
import { STATE_ABBR_MAP, STATE_FIPS, PARTY_HEX, PARTY_LABEL } from '../config/constants.js';
import { project } from '../scene/projection.js';
import { mapGroup } from '../scene/setup.js';
import { mapLabelsLayer, districtLabels } from './labels-overlay.js';
import { stateData, activeState, showSmallCities, ACTIVE_LAYERS } from '../state/map-state.js';
import { openPolDrawer } from './politician-drawer.js';
import { trackEvent } from '../api/interaction.js';
import { addOverlayItem, removeOverlayItems, updateOverlayPositions } from './point-overlay-factory.js';

export let citySprites = [];
export let govSprites = [];
let cityGroup = null;

// Approximate on-screen footprint of a city/gov marker pill, for collision
// purposes only — same "close enough" approach labels-overlay.js already
// uses for district labels rather than measuring real layout every frame.
// Anchored at the LEFT edge (matches .city-marker/.gov-marker's
// translate(0,-50%) — see map.css), not the center, so the dot itself sits
// at the true geo point.
const MARKER_W = 90;
const MARKER_H = 22;
const MARKER_GAP = 6;

/**
 * @returns {Array<{left,right,top,bottom}>} screen-space rects of every
 * currently-visible city/capital marker, so updateDistrictLabels() (and, by
 * extension, updateCandidateMarkers()) can avoid placing anything on top of
 * one — see scene/overlay-stack.js's per-frame priority ordering.
 */
export function updateCityDots() {
    const isGov = (entry) => govSprites.includes(entry);
    return updateOverlayPositions([...citySprites, ...govSprites], {
        anchor: 'left',
        boxSize: { w: MARKER_W + MARKER_GAP, h: MARKER_H + MARKER_GAP },
        margin: { x: 40, top: 20, bottom: 40 },
        // Capital/gov markers win over regular city markers when they'd
        // overlap (the capital is the single most state-representative
        // point); among city markers, TOP_CITIES' own population-descending
        // order (preserved by this stable sort) decides who wins.
        sortItems: (visible) => [...visible].sort((a, b) => (isGov(b.entry) ? 1 : 0) - (isGov(a.entry) ? 1 : 0)),
    });
}

const TIGERWEB_PLACES_URL =
    'https://tigerweb.geo.census.gov/arcgis/rest/services/TIGERweb/Places_CouSub_ConCity_SubMCD/MapServer/28/query';

export const cityBoundaryCache = {};

export function buildCityMarkers(stateName) {
    clearCityMarkers();
    const abbr = STATE_ABBR_MAP[stateName];
    const cities = TOP_CITIES[abbr];
    if (!cities?.length) return;

    for (const [name, lat, lng, popK] of cities) {
        if (!showSmallCities && popK < 500) continue;
        const xy = project([lng, lat]);
        if (!xy) continue;
        const worldPos = new THREE.Vector3(xy[0], xy[1], 0.35);

        const el = document.createElement('button');
        el.className = 'city-marker';
        el.setAttribute('aria-label', `${name} — ${fmtPop(popK)} residents`);
        el.innerHTML =
            `<span class="city-dot-core"></span>` +
            `<span class="city-name-tag">${name}</span>`;
        el.addEventListener('click', (e) => {
            e.stopPropagation();
            openCityDrawer(name, popK, activeState, worldPos, lat, lng);
        });
        addOverlayItem(citySprites, mapLabelsLayer, el, worldPos, { name, popK });
    }
}

export function clearCityMarkers() {
    removeOverlayItems(citySprites);
    citySprites = [];
}

export function openCityDrawer(name, popK, stateName, pinPos, lat = null, lng = null) {
    trackEvent('city_marker_click', {
        state: activeState || null, state_abbr: activeState ? STATE_ABBR_MAP[activeState] : null,
        meta: { cityName: name, cityPop: popK },
    });
    const nearby = nearestDistricts(pinPos, 3);
    const nearKey = nearby[0]?.key ?? null;
    const nearbyParties = nearby.map(d => {
        const cands = stateData?.house_candidates?.[d.key] ?? [];
        const seated = cands.find(c => c.status === 'seated') ?? cands[0];
        return seated?.party ?? null;
    }).filter(Boolean);
    const rCount = nearbyParties.filter(p => /^R/i.test(p)).length;
    const dCount = nearbyParties.filter(p => /^D/i.test(p)).length;
    const leaning = rCount > dCount ? 'R' : dCount > rCount ? 'D' : 'Mixed';
    const nearRep = (stateData?.house_candidates?.[nearKey] ?? []).find(c => c.status === 'seated')
        ?? (stateData?.house_candidates?.[nearKey] ?? [])[0] ?? null;
    openPolDrawer(
        { full_name: name, office: `City · ${stateName}`, party: leaning === 'Mixed' ? null : leaning },
        '#f59e0b',
        { isCityView: true, cityName: name, cityPop: popK, district: nearKey, rep: nearRep, leaning, lat, lng }
    );
}

function nearestDistricts(worldPos, n = 3) {
    if (!districtLabels.length) return [];
    // District-label entries are { el, worldPos, item: { mesh, key, hasName } }
    // (point-overlay-factory.js's shape) — flatten .item.key back to .key so
    // callers (openCityDrawer's nearby[0]?.key) don't need to know that.
    return [...districtLabels]
        .map(lbl => ({ ...lbl, ...lbl.item, dist: worldPos.distanceTo(lbl.worldPos) }))
        .sort((a, b) => a.dist - b.dist)
        .slice(0, n);
}

export { nearestDistricts };

export function buildGovMarkers(stateName) {
    clearGovMarkers();
    const abbr = STATE_ABBR_MAP[stateName];
    const cap = STATE_CAPITALS[abbr];
    if (!cap) return;
    const [city, lat, lng] = cap;
    const xy = project([lng, lat]);
    if (!xy) return;

    const worldPos = new THREE.Vector3(xy[0], xy[1], 0.38);
    const el = document.createElement('button');
    el.className = 'gov-marker';
    el.setAttribute('aria-label', `${city} — State Capital`);
    el.innerHTML =
        `<span class="gov-dot-core"></span>` +
        `<span class="gov-name-tag">★ ${city}</span>`;
    el.addEventListener('click', (e) => {
        e.stopPropagation();
        const officials = stateData?.city_officials?.[city] ?? [];
        const governor = stateData?.offices?.find?.(o => /governor/i.test(o.office))?.candidates?.[0] ?? null;
        const rep = governor ?? officials[0] ?? null;
        trackEvent('gov_marker_click', {
            state: activeState || null, state_abbr: activeState ? STATE_ABBR_MAP[activeState] : null,
            meta: { cityName: city, isCapital: true },
        });
        openPolDrawer(
            rep ? { ...rep, office: rep.political_office || rep.office || 'Governor' }
                : { full_name: city, office: `State Capital · ${stateName}`, party: null },
            '#06b6d4',
            { population: null, cityName: city, isCapital: true }
        );
    });
    addOverlayItem(govSprites, mapLabelsLayer, el, worldPos, { city, stateName });
}

export function clearGovMarkers() {
    removeOverlayItems(govSprites);
    govSprites = [];
}

export function clearCityLayer() {
    if (cityGroup) { mapGroup.remove(cityGroup); cityGroup = null; }
}

export async function loadCityBoundaries(stateName) {
    clearCityLayer();
    if (!ACTIVE_LAYERS.has('cities')) return;
    const fips = STATE_FIPS[stateName];
    if (!fips) return;
    if (cityBoundaryCache[fips]) {
        cityGroup = cityBoundaryCache[fips];
        mapGroup.add(cityGroup);
        return;
    }
    const params = new URLSearchParams({
        where: `STATEFP='${fips}'`,
        outFields: 'NAME',
        returnGeometry: 'true',
        f: 'geojson',
        geometryPrecision: '2',
        inSR: '4326',
        outSR: '4326',
    });
    let data;
    try {
        const res = await fetch(`${TIGERWEB_PLACES_URL}?${params}`, { cache: 'no-store' });
        data = await res.json();
    } catch (e) {
        console.warn('[city-layer] fetch failed:', e.message);
        return;
    }
    if (!data?.features?.length) return;
    const grp = new THREE.Group();
    const cityColor = new THREE.Color(0xfbbf24);
    for (const feat of data.features) {
        const polys = feat.geometry?.type === 'MultiPolygon'
            ? feat.geometry.coordinates
            : [feat.geometry.coordinates];
        for (const poly of polys) {
            for (const ring of poly) {
                const pts = [];
                for (const coord of ring) {
                    const p = project(coord);
                    if (p) pts.push(new THREE.Vector3(p[0], p[1], 0.262));
                }
                if (pts.length < 3) continue;
                const geo = new THREE.BufferGeometry().setFromPoints(pts);
                grp.add(new THREE.Line(geo,
                    new THREE.LineBasicMaterial({ color: cityColor, transparent: true, opacity: 0.52 })));
            }
        }
    }
    cityBoundaryCache[fips] = grp;
    cityGroup = grp;
    mapGroup.add(cityGroup);
}