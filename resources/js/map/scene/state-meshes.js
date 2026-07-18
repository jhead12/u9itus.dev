/**
 * Build Three.js meshes from TopoJSON state features and load the map.
 */
import * as THREE from 'three';
import * as topojson from 'topojson-client';
import { mapGroup } from './setup.js';
import { buildShapeFromRings } from './projection.js';
import { REGIONS, stateToRegion } from '../config/constants.js';

export const stateMeshes = [];

export function buildState(feature) {
    const name       = feature.properties.name;
    const regionName = stateToRegion[name];
    const region     = REGIONS[regionName];
    const color      = region ? region.color : 0x334155;
    const polys      = feature.geometry.type === 'MultiPolygon'
        ? feature.geometry.coordinates
        : [feature.geometry.coordinates];

    const group = new THREE.Group();
    group.userData.stateName = name;

    for (const poly of polys) {
        const shape = buildShapeFromRings(poly);
        if (!shape) continue;
        const geo = new THREE.ExtrudeGeometry(shape, { depth: 0.25, bevelEnabled: false });
        const mat = new THREE.MeshLambertMaterial({ color });
        const mesh = new THREE.Mesh(geo, mat);
        mesh.userData = { name, regionName, region, originalColor: color };
        group.add(mesh);
        stateMeshes.push(mesh);

        // Dark border outline (EdgesGeometry includes the extruded side walls + top rim)
        const eg = new THREE.EdgesGeometry(geo, 2);
        group.add(new THREE.LineSegments(
            eg,
            new THREE.LineBasicMaterial({ color: 0x090d1f, transparent: true, opacity: 0.85 }),
        ));
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