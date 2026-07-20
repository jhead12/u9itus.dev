/**
 * Congressional district overlay — fetches from TIGERweb API, renders as ExtrudeGeometry meshes.
 */
import * as THREE from 'three';
import { mapGroup } from './setup.js';
import { buildShapeFromRings } from './projection.js';
import { STATE_ABBR_MAP, STATE_FIPS, DISTRICT_COUNTS, PARTY_INT, PARTY_HEX, PARTY_LABEL, DISTRICT_PARTY_MAP } from '../config/constants.js';
import { DISTRICT_CONFIG, districtCache } from '../state/map-state.js';
import { stateMeshes } from './state-meshes.js';
import { flyToMeshesTopDown } from './camera-animation.js';
import { getTigerwebUrl } from '../api/district-config.js';
import { idbGet, idbSet } from '../utils/idb-cache.js';

// TIGERweb GeoJSON is stable for an entire Congress (~2 years).
const TIGER_IDB_TTL = 30 * 24 * 60 * 60 * 1000; // 30 days

export let districtGroup = null;
export let districtMeshes = [];
export let hoveredDistrict = null;

/** Setter for cross-module reassignment of hoveredDistrict */
export function setHoveredDistrict(v) { hoveredDistrict = v; }

export function clearDistricts() {
    if (districtGroup) { mapGroup.remove(districtGroup); districtGroup = null; }
    districtMeshes = []; hoveredDistrict = null;
}

export function resetDistrictSelection() {
    for (const d of districtMeshes) {
        d.material.color.setHex(d.userData.originalColor);
        d.material.opacity = 0.88;
        d.position.z = 0.255;
    }
}

export function flyToDistrictTopDown(mesh) {
    flyToMeshesTopDown([mesh], 2.6);
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
        inSR: '4326',
        outSR: '4326',
    });

    let data;
    for (let attempt = 0; attempt < 2; attempt++) {
        try {
            const res = await fetch(`${getTigerwebUrl()}?${params}`, { cache: 'no-store' });
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
            mesh.userData = { districtNum: distNum, districtLabel: label, stateName, regionHex, party, partyHex: PARTY_HEX[party], originalColor: colorInt };
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