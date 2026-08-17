/**
 * Ordered registry of point-overlay layers (city/gov markers, district
 * labels, candidate dots, business/post pins). The per-frame collision
 * priority order used to be hardcoded by call sequence in render-loop.js's
 * animate(), and the per-mode-transition build/clear lists were hardcoded in
 * navigation/mode-transitions.js. Both now walk this registry instead, so
 * adding a new point-overlay layer means registering it here, not editing
 * those two files (or app.js/layers-panel.js for the toggle wiring — see
 * state/layer-directory.js for that half).
 *
 * Priority bands (lower runs first, and its placed rects are what
 * lower-priority layers steer clear of):
 *   0-9   primary geo markers (city/gov)
 *   10-19 derived-position labels (district labels)
 *   20-29 clustered dots (candidates)
 *   30+   content pins (collision: 'none' — self-clip to viewport only)
 */
import { ACTIVE_LAYERS } from '../state/map-state.js';
import { buildDistrictLabels, clearDistrictLabels, updateDistrictLabels } from '../ui/labels-overlay.js';
import { buildCityMarkers, clearCityMarkers, buildGovMarkers, clearGovMarkers, updateCityDots } from '../ui/markers.js';
import { buildCandidateMarkers, clearCandidateMarkers, updateCandidateMarkers } from '../ui/candidate-markers.js';
import { updateBusinessPins } from '../ui/business-pins.js';
import { updatePostPins } from '../ui/post-pins.js';

const overlays = [];

/**
 * @param {Object} def
 * @param {string} [def.key] - ACTIVE_LAYERS key gating def.build; ignored (and unnecessary) when alwaysOn
 * @param {number} def.priority - lower runs first in the per-frame update loop
 * @param {boolean} [def.alwaysOn=false] - true for layers not gated by a toggle (district labels: tied to state drill-down instead)
 * @param {(stateName: string) => void} [def.build] - called on state-enter when active; omit for layers with no per-state build step (content pins are viewport-fetched instead, and persist across mode transitions)
 * @param {() => void} [def.clear] - called on mode-exit; omit for layers that persist across mode transitions
 * @param {(occupiedRects: Array) => Array|void} def.update - the per-frame projection/collision/DOM-write step
 */
function registerOverlay(def) {
    overlays.push(def);
    overlays.sort((a, b) => a.priority - b.priority);
}

registerOverlay({
    key: 'topcities', priority: 0,
    build: (stateName) => { buildCityMarkers(stateName); buildGovMarkers(stateName); },
    clear: () => { clearCityMarkers(); clearGovMarkers(); },
    update: updateCityDots,
});
registerOverlay({
    priority: 10, alwaysOn: true,
    build: buildDistrictLabels,
    clear: clearDistrictLabels,
    update: updateDistrictLabels,
});
registerOverlay({
    key: 'candidates', priority: 20,
    build: buildCandidateMarkers,
    clear: clearCandidateMarkers,
    update: updateCandidateMarkers,
});
// Business/post pins are viewport-fetched, not tied to state drill-down —
// no build/clear here; refreshBusinessPins()/refreshPostPins() are wired to
// their own layer toggle directly (see ui/layers-panel.js), and pins persist
// across overview/region/state navigation since they're not state-specific.
registerOverlay({ priority: 30, update: updateBusinessPins });
registerOverlay({ priority: 31, update: updatePostPins });

/** Called once per animation frame from render-loop.js. */
export function updateOverlays() {
    let rects = [];
    for (const layer of overlays) {
        const placed = layer.update(rects);
        if (placed?.length) rects = rects.concat(placed);
    }
}

/** Called on state-enter (mode-transitions.js) for every active layer with a build step. */
export function buildActiveOverlays(stateName) {
    for (const layer of overlays) {
        if (layer.build && (layer.alwaysOn || ACTIVE_LAYERS.has(layer.key))) layer.build(stateName);
    }
}

/** Called on mode-exit (mode-transitions.js) for every layer with a clear step. */
export function clearAllOverlays() {
    for (const layer of overlays) {
        if (layer.clear) layer.clear();
    }
}
