/**
 * Build Three.js meshes from TopoJSON state features and load the map.
 */
import * as THREE from 'three';
import * as topojson from 'topojson-client';
import { mapGroup } from './setup.js';
import { project, buildShapeFromRings } from './projection.js';
import { REGIONS, STATE_ABBR_MAP, PARTY_INT, stateToRegion } from '../config/constants.js';
import { mapMode, colorMode, ACTIVE_LAYERS } from '../state/map-state.js';

export const stateMeshes = [];

export function buildState(feat) {
    const name = feat.properties.name;
    const regionName = stateToRegion[name];
    const region = REGIONS[regionName];
    const hex = region ? region.hex : '#6366f1';
    const colorInt = parseInt(hex.slice(1), 16);

    const polys = feat.geometry.type === 'MultiPolygon'
        ? feat.geometry.coordinates
        : [feat.geometry.coordinates];

    const group = new THREE.Group();
    for (const poly of polys) {
        const shape = buildShapeFromRings(poly);
        if (!shape) continue;
        const geo = new THREE.ExtrudeGeometry(shape, { depth: 0.2, bevelEnabled: false });
        const mat = new THREE.MeshPhongMaterial({
            color: colorInt,
            transparent: true,
            opacity: 1.0,
            shininess: 15,
        });
        const mesh = new THREE.Mesh(geo, mat);
        mesh.userData = {
            name, regionName, region, hex,
            originalColor: colorInt,
            hoverColor: new THREE.Color(colorInt).lerp(new THREE.Color(0xffffff), 0.35).getHex(),
        };
        group.add(mesh);
        stateMeshes.push(mesh);
    }

    // Border outline
    for (const poly of polys) {
        const pts = [];
        for (const coord of poly[0]) {
            const p = project(coord);
            if (p) pts.push(new THREE.Vector3(p[0], p[1], 0.21));
        }
        if (pts.length > 2) {
            const lineGeo = new THREE.BufferGeometry().setFromPoints(pts);
            const lineMat = new THREE.LineBasicMaterial({ color: 0x818cf8, transparent: true, opacity: 0.35 });
            group.add(new THREE.Line(lineGeo, lineMat));
        }
    }

    return group;
}

/**
 * Fetch US TopoJSON and build state meshes.
 * Returns a promise so other modules can await data readiness.
 */
export function loadMapData() {
    const loadingEl = document.getElementById('loading');

    return fetch('https://cdn.jsdelivr.net/npm/us-atlas@3/states-10m.json')
        .then(r => { if (!r.ok) throw new Error('Network error'); return r.json(); })
        .then(us => {
            const geo = topojson.feature(us, us.objects.states);
            for (const feat of geo.features) mapGroup.add(buildState(feat));
            if (loadingEl) {
                loadingEl.style.opacity = '0';
                setTimeout(() => { loadingEl.style.display = 'none'; }, 520);
            }
            return stateMeshes;
        })
        .catch(err => {
            if (loadingEl) {
                loadingEl.innerHTML = `<p style="color:#ef4444;font-size:14px;">Failed to load map data.<br>${err.message}</p>`;
            }
            throw err;
        });
}