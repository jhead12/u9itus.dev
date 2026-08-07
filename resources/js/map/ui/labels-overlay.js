/**
 * Floating district candidate labels — HTML overlays projected via camera each frame.
 */
import * as THREE from 'three';
import { STATE_ABBR_MAP, PARTY_HEX, PARTY_LABEL } from '../config/constants.js';
import { districtMeshes } from '../scene/district-overlay.js';
import { stateData } from '../state/map-state.js';
import { camera, renderer, leftInset } from '../scene/setup.js';
import { openDistrictPanel } from './panel-district.js';
import { rectsOverlap } from '../scene/overlay-collision.js';

export const mapLabelsLayer = document.getElementById('map-labels-layer');
export let districtLabels = [];
const _lblVec = new THREE.Vector3();

export function buildDistrictLabels(stateName) {
    clearDistrictLabels();
    const abbr = STATE_ABBR_MAP[stateName];
    if (!abbr || !districtMeshes.length) return;

    const seen = new Set();
    for (const mesh of districtMeshes) {
        const distNum = mesh.userData.districtNum;
        const apiKey = distNum === 'AL' ? `${abbr}-AL` : `${abbr}-${distNum}`;
        if (seen.has(apiKey)) continue;
        seen.add(apiKey);

        mesh.geometry.computeBoundingSphere();
        const worldPos = mesh.geometry.boundingSphere.center.clone();
        worldPos.z += 0.18;

        const cands = stateData?.house_candidates?.[apiKey] ?? [];
        const seated = cands.find(c => c.status === 'seated') ?? cands[0] ?? null;
        const name = seated?.full_name ?? '';
        const party = mesh.userData.party || 'U';
        const dotClr = PARTY_HEX[party] || '#a7b4c7';

        const el = document.createElement('button');
        el.className = 'map-label';
        el.setAttribute('aria-label', `${apiKey}${name ? ' — ' + name : ''}`);
        el.innerHTML =
            `<span class="ml-dot" style="background:${dotClr}"></span>` +
            `<span class="ml-name">${name || apiKey}</span>` +
            `<span class="ml-dist">${apiKey}</span>`;

        el.addEventListener('click', () => {
            // Always open the district panel — same as clicking the underlying
            // map shape. It's where cities, ballot measures, local election
            // news, and the polling-place lookup all live; none of that exists
            // on the politician drawer. The seated officeholder (when there is
            // one) is rendered right at the top of that panel as its own
            // candidate-card, one click away from the same drawer this used to
            // open directly — so nothing here becomes harder to reach, it's
            // just no longer the only thing reachable.
            openDistrictPanel(mesh.userData.districtNum, mesh.userData.districtLabel, mesh.userData.stateName, mesh.userData.regionHex, mesh.userData.party);
        });

        mapLabelsLayer.appendChild(el);
        districtLabels.push({ el, worldPos, mesh, key: apiKey, hasName: !!name });
        requestAnimationFrame(() => el.classList.add('visible'));
    }
}

export function clearDistrictLabels() {
    for (const lbl of districtLabels) lbl.el.remove();
    districtLabels = [];
}

// Approximate on-screen footprint of a .map-label pill, for collision
// purposes only — cheaper than measuring each element's real layout size
// every frame, and close enough since all labels share the same style.
const LABEL_W = 92;
const LABEL_H = 24;
const MIN_GAP_DESKTOP = 8;
const MIN_GAP_MOBILE = 14;
const _visible = [];

/**
 * @param {Array<{left,right,top,bottom}>} occupiedRects screen-space rects
 * already taken by city/capital markers this frame (from render-loop.js's
 * updateCityDots()) — seeded into the collision list below so a district
 * label never renders on top of a city marker, which used to make the
 * city's own dot look detached from its name pill in dense areas.
 */
export function updateDistrictLabels(occupiedRects = []) {
    if (!districtLabels.length) return;
    const W = renderer.domElement.clientWidth;
    const H = renderer.domElement.clientHeight;
    const gap = W < 500 ? MIN_GAP_MOBILE : MIN_GAP_DESKTOP;
    const minDx = LABEL_W + gap;
    const minDy = LABEL_H + gap;

    _visible.length = 0;
    for (const lbl of districtLabels) {
        _lblVec.copy(lbl.worldPos);
        _lblVec.applyMatrix4(lbl.mesh.matrixWorld);
        _lblVec.project(camera);
        const sx = (_lblVec.x * 0.5 + 0.5) * W;
        const sy = (-_lblVec.y * 0.5 + 0.5) * H;
        const behind = _lblVec.z > 1;
        const outside = sx < -60 || sx > W + 60 || sy < 40 || sy > H + 60;
        if (behind || outside) {
            lbl.el.style.display = 'none';
        } else {
            _visible.push({ lbl, sx, sy });
        }
    }

    // Named labels (an actual candidate/representative) win screen space over
    // bare district-number placeholders when they'd overlap. Array#sort is
    // stable, so ties keep their original (district mesh) order.
    _visible.sort((a, b) => (b.lbl.hasName ? 1 : 0) - (a.lbl.hasName ? 1 : 0));

    // Greedy collision suppression: dense areas (e.g. LA, the Bay Area) used
    // to pile every district's label on top of each other since projection
    // alone doesn't account for on-screen crowding. Hide anything that would
    // overlap an already-placed, higher-priority label instead of stacking it.
    const placed = [...occupiedRects];
    for (const { lbl, sx, sy } of _visible) {
        // .map-label is center-anchored (translate(-50%,-50%) — map.css),
        // unlike .city-marker/.gov-marker's left-edge anchor above.
        const rect = { left: sx - minDx / 2, right: sx + minDx / 2, top: sy - minDy / 2, bottom: sy + minDy / 2 };
        const collides = placed.some(p => rectsOverlap(rect, p));
        if (collides) {
            lbl.el.style.display = 'none';
            continue;
        }
        placed.push(rect);
        lbl.el.style.display = 'flex';
        // sx/sy and the collision rects above are canvas-local; #map-labels-layer
        // is viewport-fixed, so only the final DOM write needs the canvas's
        // horizontal offset (leftInset()) added back in.
        lbl.el.style.left = (sx + leftInset()) + 'px';
        lbl.el.style.top = sy + 'px';
    }
    // Returned so render-loop.js can chain candidate-marker collision
    // avoidance off the combined city+district occupied set.
    return placed;
}