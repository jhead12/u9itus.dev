/**
 * Render loop — animates the scene and updates HTML overlay positions each frame.
 */
import { renderer, scene, camera, controls } from './scene/setup.js';
import { updateDistrictLabels } from './ui/labels-overlay.js';
import { citySprites, govSprites } from './ui/markers.js';
import { updateCandidateMarkers } from './ui/candidate-markers.js';
import { updatePostPins } from './ui/post-pins.js';
import { updateBusinessPins } from './ui/business-pins.js';
import { mapGroup } from './scene/setup.js';
import { rectsOverlap } from './scene/overlay-collision.js';
import * as THREE from 'three';

const _lblVec = new THREE.Vector3();

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
 * one — see call site below.
 */
function updateCityDots() {
    if (!citySprites.length && !govSprites.length) return [];
    const W = renderer.domElement.clientWidth;
    const H = renderer.domElement.clientHeight;

    const candidates = [];
    for (const dot of [...citySprites, ...govSprites]) {
        _lblVec.copy(dot.worldPos);
        _lblVec.applyMatrix4(mapGroup.matrixWorld);
        _lblVec.project(camera);
        const sx = (_lblVec.x * 0.5 + 0.5) * W;
        const sy = (-_lblVec.y * 0.5 + 0.5) * H;
        const behind = _lblVec.z > 1;
        const outside = sx < -40 || sx > W + 40 || sy < 20 || sy > H + 40;
        if (behind || outside) { dot.el.style.display = 'none'; continue; }
        candidates.push({ dot, sx, sy });
    }

    // Capital/gov markers win over regular city markers when they'd overlap
    // (the capital is the single most state-representative point); among
    // city markers, TOP_CITIES' own population-descending order (preserved
    // by this stable sort) decides who wins.
    const isGov = (d) => govSprites.includes(d);
    candidates.sort((a, b) => (isGov(b.dot) ? 1 : 0) - (isGov(a.dot) ? 1 : 0));

    // Greedy collision suppression — same approach as updateDistrictLabels()
    // below: hide anything that would overlap an already-placed, higher-
    // priority marker instead of letting two pills stack on top of each other.
    const placed = [];
    for (const { dot, sx, sy } of candidates) {
        const rect = { left: sx, right: sx + MARKER_W + MARKER_GAP, top: sy - (MARKER_H + MARKER_GAP) / 2, bottom: sy + (MARKER_H + MARKER_GAP) / 2 };
        const collides = placed.some(p => rectsOverlap(rect, p));
        if (collides) { dot.el.style.display = 'none'; continue; }
        placed.push(rect);
        dot.el.style.display = 'flex'; dot.el.style.left = sx + 'px'; dot.el.style.top = sy + 'px';
    }
    return placed;
}

export function animate() {
    requestAnimationFrame(animate);
    controls.update();
    renderer.render(scene, camera);
    // City/capital markers place first so district-rep labels can steer
    // clear of them (see updateDistrictLabels's occupied-positions param) —
    // otherwise a district label renders straight on top of a city marker in
    // dense areas, making the city's own dot look detached from its pill.
    const cityMarkerPositions = updateCityDots();
    const districtLabelPositions = updateDistrictLabels(cityMarkerPositions);
    // Candidate dots (statewide offices, clustered at the capital) steer
    // clear of city/gov/district markers too — otherwise they end up
    // stacked on top of the capital's own city pill.
    updateCandidateMarkers(districtLabelPositions);
    updatePostPins();
    updateBusinessPins();
}

export { updateDistrictLabels, updateCityDots };