/**
 * Congressional district overlay — fetches from TIGERweb API, renders as ExtrudeGeometry meshes.
 */
import * as THREE from 'three';
import { mapGroup } from './setup.js';
import { buildShapeFromRings } from './projection.js';
import { STATE_ABBR_MAP, STATE_FIPS, DISTRICT_COUNTS, PARTY_INT, PARTY_HEX, PARTY_LABEL, DISTRICT_PARTY_MAP } from '../config/constants.js';
import { DISTRICT_CONFIG, districtCache, stateData } from '../state/map-state.js';
import { stateMeshes } from './state-meshes.js';
import { flyToMeshesTopDown } from './camera-animation.js';
import { getTigerwebUrl } from '../api/district-config.js';
import { idbGet, idbSet } from '../utils/idb-cache.js';
import { districtCode, houseCandidatesFor, seatedMember } from '../utils/district-keys.js';
import { showSelectedDistrict, clearSelectedDistrict, hasSelectedDistrict } from './selected-district.js';

// TIGERweb GeoJSON is stable for an entire Congress (~2 years).
const TIGER_IDB_TTL = 30 * 24 * 60 * 60 * 1000; // 30 days
const BOUNDARY_TIMEOUT_MS = 15000;
/** Closest a district fly-to may get; a small urban district needs to fill a fair share of the view. */
export const DISTRICT_MIN_DIST = 0.9;

export let districtGroup = null;
export let districtMeshes = [];
export let hoveredDistrict = null;

/** Setter for cross-module reassignment of hoveredDistrict */
export function setHoveredDistrict(v) { hoveredDistrict = v; }

/** Fill opacity of an unselected district: quieter once one is selected, so the selection reads at a glance. */
export function districtRestOpacity() {
    return hasSelectedDistrict() ? 0.5 : 0.72;
}

export function clearDistricts() {
    clearSelectedDistrict();
    if (districtGroup) { mapGroup.remove(districtGroup); districtGroup = null; }
    districtMeshes = []; hoveredDistrict = null;
}

export function resetDistrictSelection() {
    clearSelectedDistrict();
    for (const d of districtMeshes) {
        d.material.color.setHex(d.userData.originalColor);
        d.material.opacity = 0.88;
        d.position.z = 0.255;
    }
}

/** Every polygon of one district (a district can be several meshes). */
export function meshesForDistrict(districtNum) {
    return districtMeshes.filter(m => m.userData.districtNum === String(districtNum));
}

/**
 * Select one district (all of its polygons): lightened fill, strong outline and
 * a persistent label, with the rest of the state quieted. The same result for a
 * map click, a list row, a label click and a candidate card. Does not move the camera.
 */
export function selectDistrict(meshes) {
    if (!meshes.length) return;
    const { stateName, districtNum, partyHex } = meshes[0].userData;
    const abbr = STATE_ABBR_MAP[stateName];
    const code = districtCode(abbr, districtNum);
    const member = seatedMember(houseCandidatesFor(stateData, abbr, districtNum));

    // Outline first: districtRestOpacity() below keys off it.
    showSelectedDistrict(meshes, { code, name: member?.full_name ?? '', partyHex });

    for (const d of districtMeshes) {
        d.material.color.setHex(d.userData.originalColor);
        d.material.opacity = districtRestOpacity();
        d.position.z = 0.255;
    }
    for (const dm of meshes) {
        const bright = new THREE.Color(dm.userData.partyHex || dm.userData.regionHex || '#6366f1')
            .lerp(new THREE.Color(0xffffff), 0.55);
        dm.material.color.setHex(bright.getHex());
        dm.material.opacity = 1.0;
        dm.position.z = 0.31;
    }
}

/**
 * Hover preview for a list row: brighten a district without selecting it, so
 * people can find a small district by scanning the list. Pass null to clear.
 */
let previewed = [];
export function previewDistrict(meshes) {
    for (const m of previewed) {
        if (m.position.z < 0.30) m.material.color.setHex(m.userData.originalColor);
    }
    previewed = meshes ?? [];
    for (const m of previewed) {
        if (m.position.z < 0.30) m.material.color.setHex(new THREE.Color(m.userData.originalColor).lerp(new THREE.Color(0xffffff), 0.45).getHex());
    }
}

export function flyToDistrictTopDown(mesh) {
    flyToMeshesTopDown([mesh], 2.6, DISTRICT_MIN_DIST);
}

async function loadCongressionalDistricts(fips) {
    // 1. In-memory cache (fastest — same session).
    if (districtCache[fips]) return districtCache[fips];

    // 2. IndexedDB cache — survives page reloads and is critical for low-bandwidth users.
    const idbKey = `u9_tigerweb_dist_${DISTRICT_CONFIG.cd_field}_${fips}`;
    const cached = await idbGet(idbKey);
    if (cached?.length) {
        districtCache[fips] = cached;
        return cached;
    }

    // 3. Network fetch (with one retry).
    const cdField = DISTRICT_CONFIG.cd_field;
    const params = new URLSearchParams({
        where: `STATE='${fips}'`,
        outFields: `STATE,${cdField},NAME,GEOID`,
        returnGeometry: 'true',
        f: 'geojson',
        geometryPrecision: '3',
        // Let the Census server generalise the borders (~200 m). Full-detail
        // geometry is several MB per large state (California: 6.7 MB / ~10 s vs
        // 0.3 MB / ~1 s) and 200 m is far below anything visible at map zoom.
        maxAllowableOffset: '0.002',
        inSR: '4326',
        outSR: '4326',
    });

    let data;
    for (let attempt = 0; attempt < 2; attempt++) {
        try {
            // Time-box the request: a stalled Census service must surface as an
            // error with a Retry button, not as an endless "Loading…".
            const res = await fetch(`${getTigerwebUrl()}?${params}`, { cache: 'no-store', signal: AbortSignal.timeout(BOUNDARY_TIMEOUT_MS) });
            if (!res.ok) throw new Error(`Census boundary service returned ${res.status}`);
            data = await res.json();
            if (data.features?.length) break;
        } catch (e) {
            if (attempt === 1) throw e;
            await new Promise(r => setTimeout(r, 600));
        }
    }
    const features = data?.features || [];
    if (!features.length) throw new Error(`No districts returned for FIPS ${fips}`);

    districtCache[fips] = features;
    // Persist to IndexedDB for future visits.
    idbSet(idbKey, features, TIGER_IDB_TTL).catch(() => {});
    return features;
}

export async function buildDistrictOverlay(stateName, regionHex) {
    clearDistricts();
    const fips = STATE_FIPS[stateName];
    if (!fips) return 0;

    const features = await loadCongressionalDistricts(fips);
    districtGroup = new THREE.Group();
    const abbr = STATE_ABBR_MAP[stateName];

    features.forEach((feat) => {
        const cdField = DISTRICT_CONFIG.cd_field;
        const cdRaw = String(feat.properties[cdField] ?? feat.properties['CD119'] ?? '0').padStart(2, '0');
        const isAtLarge = cdRaw === '00';
        const distNum = isAtLarge ? 'AL' : String(parseInt(cdRaw));
        const label = isAtLarge ? 'At-Large' : `District ${distNum}`;

        const partyKey = isAtLarge ? `${abbr}-AL` : `${abbr}-${distNum}`;
        const party = DISTRICT_PARTY_MAP[partyKey] || 'U';
        const shade = new THREE.Color(PARTY_INT[party]);
        const colorInt = shade.getHex();

        const polys = feat.geometry.type === 'MultiPolygon'
            ? feat.geometry.coordinates
            : [feat.geometry.coordinates];

        for (const poly of polys) {
            const shape = buildShapeFromRings(poly);
            if (!shape) continue;

            const geo = new THREE.ExtrudeGeometry(shape, { depth: 0.01, bevelEnabled: false });
            const mat = new THREE.MeshLambertMaterial({
                color: shade, transparent: true, opacity: 0.88,
            });
            const mesh = new THREE.Mesh(geo, mat);
            mesh.position.z = 0.255;
            // Raw GeoJSON rings (lon/lat), kept alongside the render mesh so
            // "which cities fall inside this district" can be computed with a
            // point-in-polygon test against real Census boundaries — see
            // utils/point-in-polygon.js.
            mesh.userData = { districtNum: distNum, districtLabel: label, stateName, regionHex, party, partyHex: PARTY_HEX[party], originalColor: colorInt, rings: poly };
            districtGroup.add(mesh);
            districtMeshes.push(mesh);

            const eg = new THREE.EdgesGeometry(geo, 1);
            const em = new THREE.LineBasicMaterial({ color: 0xffffff, transparent: true, opacity: 0.75 });
            const el = new THREE.LineSegments(eg, em);
            el.position.z = 0.255;
            el.renderOrder = 1;
            districtGroup.add(el);
        }
    });

    mapGroup.add(districtGroup);
    return features.length;
}

export { loadCongressionalDistricts };