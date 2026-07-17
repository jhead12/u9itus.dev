/**
 * Camera animation utilities — flyTo, flyToMeshes, flyToMeshesTopDown.
 */
import * as THREE from 'three';
import { camera, controls } from './setup.js';

const FLY_DURATION = 900;

function easeOutCubic(t) {
    return 1 - Math.pow(1 - t, 3);
}

export function flyTo(targetPos, targetLookAt, duration = FLY_DURATION) {
    const startPos  = camera.position.clone();
    const startLook  = controls.target.clone();
    const startTime = performance.now();

    function tick() {
        const elapsed = performance.now() - startTime;
        const t = Math.min(elapsed / duration, 1);
        const e = easeOutCubic(t);

        camera.position.lerpVectors(startPos, targetPos, e);
        controls.target.lerpVectors(startLook, targetLookAt, e);
        controls.update();

        if (t < 1) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
}

export function flyToMeshes(meshes, padding = 1.4) {
    if (!meshes.length) return;
    const box = new THREE.Box3();
    for (const m of meshes) {
        m.geometry.computeBoundingBox();
        box.expandByObject(m);
    }
    const center = box.getCenter(new THREE.Vector3());
    const size   = box.getSize(new THREE.Vector3());
    const dist   = Math.max(size.x, size.y) * padding;
    const pos    = new THREE.Vector3(center.x, center.y + dist * 0.9, dist);
    flyTo(pos, center);
}

export function flyToMeshesTopDown(meshes, padding = 1.4) {
    if (!meshes.length) return;
    const box = new THREE.Box3();
    for (const m of meshes) {
        m.geometry.computeBoundingBox();
        box.expandByObject(m);
    }
    const center = box.getCenter(new THREE.Vector3());
    const size   = box.getSize(new THREE.Vector3());
    const dist   = Math.max(size.x, size.y) * padding;
    const pos    = new THREE.Vector3(center.x, dist * 1.1, center.z + 0.1);
    flyTo(pos, center);
}