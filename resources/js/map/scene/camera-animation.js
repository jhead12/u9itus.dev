/**
 * Camera animation utilities — flyTo, flyToMeshes, flyToMeshesTopDown.
 */
import * as THREE from 'three';
import { camera, controls, W, H } from './setup.js';

function flyTo(endPos, endLook, duration = 950) {
    const startPos  = camera.position.clone();
    const startLook = controls.target.clone();
    const t0 = performance.now();
    function tick() {
        const raw = Math.min((performance.now() - t0) / duration, 1);
        // easeInOutQuad
        const t = raw < 0.5 ? 2 * raw * raw : -1 + (4 - 2 * raw) * raw;
        camera.position.lerpVectors(startPos, endPos, t);
        controls.target.lerpVectors(startLook, endLook, t);
        controls.update();
        if (raw < 1) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
}

/* Angled 3D fly-to — used for region zoom (looks dramatic) */
export function flyToMeshes(meshList, padFactor = 1.5) {
    if (!meshList.length) return;
    const box = new THREE.Box3();
    meshList.forEach(m => box.expandByObject(m));
    const center = new THREE.Vector3(); box.getCenter(center);
    const size   = new THREE.Vector3(); box.getSize(size);
    const fov    = camera.fov * Math.PI / 180;
    const halfH  = Math.max(size.x / (W() / H()), size.y) / 2;
    let dist = (halfH / Math.tan(fov / 2)) * padFactor;
    dist = Math.max(dist, 2.5);
    const endPos  = new THREE.Vector3(center.x, center.y + dist * 0.35, center.z + dist * 0.93);
    const endLook = new THREE.Vector3(center.x, center.y, 0);
    flyTo(endPos, endLook);
}

/* Top-down fly-to — used when zooming into a state or district.
 *
 * The map geometry lies in the XY plane; extrusions go along +Z toward
 * the viewer. A "top-down" view means the camera should be high on the Z
 * axis with only a tiny Y offset for a slight north tilt (so the state
 * still reads left=west, right=east, top=north). */
export function flyToMeshesTopDown(meshList, padFactor = 1.25) {
    if (!meshList.length) return;
    const box = new THREE.Box3();
    meshList.forEach(m => box.expandByObject(m));
    const center = new THREE.Vector3(); box.getCenter(center);
    const size   = new THREE.Vector3(); box.getSize(size);
    const fov    = camera.fov * Math.PI / 180;
    // W() already returns the panel-adjusted width on desktop
    const effectiveAspect = W() / H();
    const halfH = Math.max(size.x / effectiveAspect, size.y) / 2;
    let dist = (halfH / Math.tan(fov / 2)) * padFactor;
    dist = Math.max(dist, 1.5);
    // Camera almost directly above: high Z, slight Y tilt
    const endPos  = new THREE.Vector3(center.x, center.y + dist * 0.18, dist * 0.98);
    const endLook = new THREE.Vector3(center.x, center.y, 0);
    flyTo(endPos, endLook, 1000);

}

export { flyTo };