/**
 * Toggle/boot-restore directory for all 8 map data layers — the 3 mesh
 * layers (districts, party, population) and the 5 point-overlay layers
 * (cities, topcities, candidates, content, businesses). Toggle dispatch
 * used to be a switch statement in ui/layers-panel.js; boot-restore used to
 * be a hardcoded if-chain in app.js. Both now walk this directory instead,
 * so adding a new layer's toggle/boot behavior means one registerLayer()
 * call (in ui/layers-panel.js, where each layer's build/clear/toggle
 * functions are already imported), not a new switch case or if-branch.
 *
 * This module intentionally has zero per-layer imports itself (only
 * ACTIVE_LAYERS) so it can be imported from anywhere — including
 * scene/national-boundaries.js, which used to keep its own private
 * duplicate of syncLayerChip() specifically to avoid a circular import with
 * ui/layers-panel.js.
 *
 * See scene/overlay-stack.js for the separate per-frame-update/
 * build-on-state-enter registry that the 5 point-overlay layers among
 * these also participate in — that one is about rendering, this one is
 * about the on/off switch.
 */
import { ACTIVE_LAYERS } from './map-state.js';

const directory = new Map();

/**
 * @param {string} key
 * @param {(isActive: boolean) => void} onToggle - runtime toggle handler (same behavior as the old switch case for this key)
 * @param {boolean} [restoreAtBoot=false] - true if this layer should be re-applied from a persisted ACTIVE_LAYERS on page load. Only districts/party do this today (population/cities/topcities/candidates/content/businesses all depend on activeState, which is null at boot — restoring them isn't meaningful before a state is drilled into, a pre-existing gap this directory doesn't change).
 */
export function registerLayer(key, onToggle, restoreAtBoot = false) {
    directory.set(key, { onToggle, restoreAtBoot });
}

export function getLayer(key) {
    return directory.get(key);
}

/** Sync a layer chip's visual state and the ACTIVE_LAYERS set (+ localStorage persistence). */
export function syncLayerChip(layerKey, isActive) {
    const chip = document.querySelector(`[data-layer="${layerKey}"]`);
    if (chip) {
        chip.classList.toggle('active', isActive);
        chip.setAttribute('aria-checked', String(isActive));
    }
    if (isActive) ACTIVE_LAYERS.add(layerKey);
    else ACTIVE_LAYERS.delete(layerKey);
    try { localStorage.setItem('u9map_layers', JSON.stringify([...ACTIVE_LAYERS])); } catch (_) {}
}

/** Called once at boot (after map data loads) to re-apply whichever restoreAtBoot layers were persisted active. */
export function restoreBootLayers() {
    for (const [key, layer] of directory) {
        if (layer.restoreAtBoot && ACTIVE_LAYERS.has(key)) layer.onToggle(true);
    }
}
