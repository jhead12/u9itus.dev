/**
 * Render loop — animates the scene and updates HTML overlay positions each frame.
 */
import { renderer, scene, camera, controls } from './scene/setup.js';
import { updateDistrictLabels } from './ui/labels-overlay.js';
import { citySprites, govSprites } from './ui/markers.js';
import { updateCandidateMarkers } from './ui/candidate-markers.js';
import { updatePostPins } from './ui/post-pins.js';
import { updateBusinessPins } from './ui/business-pins.js';
import { updateOverlayPositions } from './ui/point-overlay-factory.js';

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