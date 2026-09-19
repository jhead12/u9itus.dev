/**
 * Flat / 3D view mode.
 *
 * Flat is the default: states, districts and every overlay share one thin
 * surface and the camera looks almost straight down, so coastlines and small
 * states stay clean. 3D restores the full extrusion and the tilted camera.
 *
 * Depth is applied by scaling mapGroup on Z. Every mesh, pin and label height
 * in the map is defined in that group's local space and projected through
 * matrixWorld, so one scale flattens (or restores) all of them together.
 */
import * as THREE from 'three';
import { mapGroup, camera, controls } from './setup.js';
import { setStateBorderStyle } from './state-meshes.js';
import { depthView, setDepthViewState } from '../state/map-state.js';

const VIEW_KEY = 'u9_map_view_mode';
const FLAT_SCALE_Z = 0.1;
const SCALE_TWEEN_MS = 350;

/** Default overview camera position per mode (looking at the origin). */
const OVERVIEW_CAMERA = {
    flat: new THREE.Vector3(0, 2.2, 10.9),
    depth: new THREE.Vector3(0, 5.4, 10.2),
};

/** Region fly-to camera offset: [y as a share of distance, z as a share of distance]. */
const REGION_TILT = { flat: [0.12, 0.99], depth: [0.35, 0.93] };

export function overviewCameraPosition() {
    return (depthView ? OVERVIEW_CAMERA.depth : OVERVIEW_CAMERA.flat).clone();
}

export function regionTilt() {
    return depthView ? REGION_TILT.depth : REGION_TILT.flat;
}

function targetScale(on) {
    return on ? 1 : FLAT_SCALE_Z;
}

let _tween = 0;

function applyScale(on, animate) {
    cancelAnimationFrame(_tween);
    const to = targetScale(on);
    if (!animate) { mapGroup.scale.z = to; return; }
    const from = mapGroup.scale.z;
    const t0 = performance.now();
    const step = () => {
        const t = Math.min((performance.now() - t0) / SCALE_TWEEN_MS, 1);
        mapGroup.scale.z = from + (to - from) * (t * (2 - t));
        if (t < 1) _tween = requestAnimationFrame(step);
    };
    _tween = requestAnimationFrame(step);
}

function syncControls(on) {
    for (const el of document.querySelectorAll('[data-view-toggle]')) {
        el.classList.toggle('active', on);
        el.setAttribute('aria-pressed', String(on));
    }
}

/**
 * Switch between flat and 3D. Returns nothing; callers that own camera moves
 * (mode-transitions) re-frame the camera afterwards.
 */
export function setDepthView(on, { animate = true, persist = true } = {}) {
    setDepthViewState(on);
    setStateBorderStyle(on);
    applyScale(on, animate);
    syncControls(on);
    if (persist) {
        try { localStorage.setItem(VIEW_KEY, on ? '3d' : 'flat'); } catch { /* storage unavailable */ }
    }
}

/** Restore the saved mode (flat unless the visitor chose 3D) before the first frame. */
export function initViewMode() {
    let on = false;
    try { on = localStorage.getItem(VIEW_KEY) === '3d'; } catch { /* storage unavailable */ }
    setDepthView(on, { animate: false, persist: false });
    camera.position.copy(overviewCameraPosition());
    controls.update();
}
