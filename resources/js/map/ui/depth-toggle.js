/**
 * Flat / 3D toggle — switches the view mode and re-frames the camera for it.
 * In overview and region views the camera moves to the new mode's default
 * angle; inside a state it stays top-down by design.
 */
import * as THREE from 'three';
import { depthView, mapMode, activeRegion } from '../state/map-state.js';
import { setDepthView, overviewCameraPosition } from '../scene/view-mode.js';
import { flyTo, flyToMeshes } from '../scene/camera-animation.js';
import { stateMeshes } from '../scene/state-meshes.js';

export function toggleDepthView(on = !depthView) {
    setDepthView(on);
    if (mapMode === 'overview') {
        flyTo(overviewCameraPosition(), new THREE.Vector3(0, 0, 0));
    } else if (mapMode === 'region' && activeRegion) {
        flyToMeshes(stateMeshes.filter(m => m.userData.regionName === activeRegion), 1.35);
    }
}
