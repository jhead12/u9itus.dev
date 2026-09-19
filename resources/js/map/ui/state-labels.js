/**
 * State abbreviation labels for the overview and region views.
 *
 * Plain HTML text pinned to each state's projected center, projected each
 * frame like the district labels. Bigger states win screen space when labels
 * would collide, so small northeastern states appear as the camera closes in
 * (or in region view) instead of piling up. Hidden inside a state, where
 * district labels take over.
 */
import * as THREE from 'three';
import { stateMeshes } from '../scene/state-meshes.js';
import { project } from '../scene/projection.js';
import { STATE_ABBR_MAP } from '../config/constants.js';
import { mapMode, activeRegion } from '../state/map-state.js';
import { addOverlayItem, updateOverlayPositions } from './point-overlay-factory.js';

const layer = document.getElementById('map-labels-layer');
const LABEL_Z = 0.27;
const LABEL_W = 30;
const LABEL_H = 16;

/** Where the largest polygon's bounding-box center misses the visual center. */
const CENTER_OVERRIDES = {
    Florida:   [-81.7, 28.1],
    Louisiana: [-92.3, 31.0],
};

let labels = [];

function largestMeshCenter(meshes) {
    let best = null;
    let bestArea = -1;
    const box = new THREE.Box3();
    for (const m of meshes) {
        m.geometry.computeBoundingBox();
        box.copy(m.geometry.boundingBox);
        const size = box.getSize(new THREE.Vector3());
        const area = size.x * size.y;
        if (area > bestArea) { bestArea = area; best = box.clone(); }
    }
    return { center: best.getCenter(new THREE.Vector3()), area: bestArea };
}

/** Build one label per state. Call once after the state meshes exist. */
export function buildStateLabels() {
    if (labels.length) return;
    const byState = new Map();
    for (const m of stateMeshes) {
        const list = byState.get(m.userData.name) ?? [];
        list.push(m);
        byState.set(m.userData.name, list);
    }

    for (const [name, meshes] of byState) {
        const abbr = STATE_ABBR_MAP[name];
        if (!abbr || abbr === 'DC') continue;

        const { center, area } = largestMeshCenter(meshes);
        const override = CENTER_OVERRIDES[name] && project(CENTER_OVERRIDES[name]);
        const worldPos = override
            ? new THREE.Vector3(override[0], override[1], LABEL_Z)
            : new THREE.Vector3(center.x, center.y, LABEL_Z);

        const el = document.createElement('span');
        el.className = 'state-label';
        el.textContent = abbr;
        el.setAttribute('aria-hidden', 'true');
        addOverlayItem(labels, layer, el, worldPos, { regionName: meshes[0].userData.regionName, area });
    }
}

/** Per-frame projection + collision. Cheap: ~50 labels. */
export function updateStateLabels(occupiedRects = []) {
    if (!labels.length) return [];

    const hideAll = mapMode === 'state';
    const inScope = (entry) => mapMode === 'overview' || entry.item.regionName === activeRegion;

    if (hideAll) {
        for (const entry of labels) entry.el.style.display = 'none';
        return [];
    }

    const active = labels.filter(inScope);
    for (const entry of labels) {
        if (!inScope(entry)) entry.el.style.display = 'none';
    }

    return updateOverlayPositions(active, {
        anchor: 'center',
        boxSize: { w: LABEL_W, h: LABEL_H },
        margin: { x: 20, top: 40, bottom: 40 },
        // Larger states claim their spot first.
        sortItems: (visible) => [...visible].sort((a, b) => b.entry.item.area - a.entry.item.area),
    }, occupiedRects);
}
