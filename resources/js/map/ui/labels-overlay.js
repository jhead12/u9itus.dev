/**
 * Floating district candidate labels — HTML overlays projected via camera each frame.
 */
import { STATE_ABBR_MAP, PARTY_HEX, PARTY_LABEL } from '../config/constants.js';
import { districtMeshes } from '../scene/district-overlay.js';
import { stateData } from '../state/map-state.js';
import { openDistrictPanel } from './panel-district.js';
import { addOverlayItem, removeOverlayItems, updateOverlayPositions } from './point-overlay-factory.js';

export const mapLabelsLayer = document.getElementById('map-labels-layer');
export let districtLabels = [];

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

        addOverlayItem(districtLabels, mapLabelsLayer, el, worldPos, { mesh, key: apiKey, hasName: !!name });
    }
}

export function clearDistrictLabels() {
    removeOverlayItems(districtLabels);
    districtLabels = [];
}

// Approximate on-screen footprint of a .map-label pill, for collision
// purposes only — cheaper than measuring each element's real layout size
// every frame, and close enough since all labels share the same style.
const LABEL_W = 92;
const LABEL_H = 24;
const MIN_GAP_DESKTOP = 8;
const MIN_GAP_MOBILE = 14;

// Named labels (an actual candidate/representative) win screen space over
// bare district-number placeholders when they'd overlap. Array#sort is
// stable, so ties keep their original (district mesh) order.
function sortByHasName(visible) {
    return [...visible].sort((a, b) => (b.entry.item.hasName ? 1 : 0) - (a.entry.item.hasName ? 1 : 0));
}

/**
 * @param {Array<{left,right,top,bottom}>} occupiedRects screen-space rects
 * already taken by city/capital markers this frame (from render-loop.js's
 * updateCityDots()) — seeded into the collision list below so a district
 * label never renders on top of a city marker, which used to make the
 * city's own dot look detached from its name pill in dense areas.
 */
export function updateDistrictLabels(occupiedRects = []) {
    return updateOverlayPositions(districtLabels, {
        // Each label projects via its own district mesh's transform, not the
        // shared mapGroup — mesh.matrixWorld differs per district.
        getTransformNode: (item) => item.mesh,
        anchor: 'center', // .map-label is center-anchored (translate(-50%,-50%) — map.css)
        boxSize: (canvasWidth) => {
            const gap = canvasWidth < 500 ? MIN_GAP_MOBILE : MIN_GAP_DESKTOP;
            return { w: LABEL_W + gap, h: LABEL_H + gap };
        },
        margin: { x: 60, top: 40, bottom: 60 },
        sortItems: sortByHasName,
    }, occupiedRects);
    // Return value chains into render-loop.js's candidate-marker collision
    // avoidance off the combined city+district occupied set.
}