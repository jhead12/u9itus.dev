/**
 * Keyboard accessibility — tilt, orbit, zoom, and help overlay.
 */
import { controls, camera } from '../scene/setup.js';
import { enterOverviewMode } from '../navigation/mode-transitions.js';
import * as THREE from 'three';

const kbHelp = document.getElementById('kb-help');
const kbHelpClose = document.getElementById('kb-help-close');
const kbBadge = document.getElementById('kb-hint-badge');
const mapRegion = document.getElementById('map-canvas-region');

/**
 * Toggle the keyboard help overlay.
 * @param {boolean} [open]  Force open (true) or closed (false); omit to toggle.
 */
export function toggleKbHelp(open) {
    kbHelp.classList.toggle('open', open ?? !kbHelp.classList.contains('open'));
    if (kbHelp.classList.contains('open')) {
        kbHelpClose.focus();
    }
}

/**
 * Tilt the camera by delta radians.
 * zoomFactor < 1 = zoom in, > 1 = zoom out.
 */
export function tiltCamera(delta, zoomFactor = 1) {
    if (!controls || !camera) return;
    let dist = camera.position.length() * zoomFactor;
    dist = Math.max(controls.minDistance, Math.min(controls.maxDistance, dist));
    let phi = Math.atan2(Math.sqrt(camera.position.x ** 2 + camera.position.z ** 2), camera.position.y);
    const theta = Math.atan2(camera.position.x, camera.position.z);
    phi = Math.max(controls.minPolarAngle, Math.min(controls.maxPolarAngle, phi + delta));
    camera.position.set(
        dist * Math.sin(phi) * Math.sin(theta),
        dist * Math.cos(phi),
        dist * Math.sin(phi) * Math.cos(theta),
    );
    controls.update();
}

/**
 * Orbit the camera left/right by delta radians (azimuth).
 */
export function orbitCamera(delta) {
    if (!controls || !camera) return;
    const dist = camera.position.length();
    const phi = Math.atan2(Math.sqrt(camera.position.x ** 2 + camera.position.z ** 2), camera.position.y);
    const theta = Math.atan2(camera.position.x, camera.position.z) + delta;
    camera.position.set(
        dist * Math.sin(phi) * Math.sin(theta),
        dist * Math.cos(phi),
        dist * Math.sin(phi) * Math.cos(theta),
    );
    controls.update();
}

/**
 * Zoom in/out by a multiplicative factor on the camera distance.
 */
export function stepZoom(factor) {
    const dir = new THREE.Vector3().subVectors(camera.position, controls.target);
    const dist = dir.length();
    const newDist = Math.min(Math.max(dist * factor, controls.minDistance), controls.maxDistance);
    dir.normalize().multiplyScalar(newDist);
    camera.position.copy(controls.target).add(dir);
    controls.update();
}

/**
 * Set up all keyboard event listeners.
 */
export function initKeyboard() {
    kbHelpClose.addEventListener('click', () => toggleKbHelp(false));
    kbBadge.addEventListener('click', () => toggleKbHelp(true));

    // Close help on Escape
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && kbHelp.classList.contains('open')) {
            toggleKbHelp(false);
            mapRegion.focus();
        }
    });

    // Focus ring
    mapRegion.addEventListener('focus', () => mapRegion.classList.add('kb-active'));
    mapRegion.addEventListener('blur', () => mapRegion.classList.remove('kb-active'));

    // Enter/Space on canvas → open search
    mapRegion.addEventListener('keydown', e => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            document.getElementById('btn-search').click();
            return;
        }
        if (e.key === '?') { toggleKbHelp(true); return; }
        const ZOOM_STEP = 1.15;
        if (!controls) return;
        switch (e.key) {
            case '+': case '=':
                e.preventDefault();
                stepZoom(1 / ZOOM_STEP);
                break;
            case '-': case '_':
                e.preventDefault();
                stepZoom(ZOOM_STEP);
                break;
        }
    });

    // Global shortcuts
    document.addEventListener('keydown', e => {
        if (e.target.matches('input, textarea, [contenteditable]')) return;
        const TILT_STEP = 0.08;
        const ORBIT_STEP = 0.05;
        switch (e.key) {
            case '?': toggleKbHelp(); break;
            case 'r': case 'R': enterOverviewMode(); break;
            case 'o': case 'O': window.toggleOfficesSection?.(); break;
            case 'ArrowUp':
                e.preventDefault();
                tiltCamera(-TILT_STEP, 1.0);
                break;
            case 'ArrowDown':
                e.preventDefault();
                tiltCamera(+TILT_STEP, 1.0);
                break;
            case 'ArrowLeft':
                e.preventDefault();
                orbitCamera(-ORBIT_STEP);
                break;
            case 'ArrowRight':
                e.preventDefault();
                orbitCamera(+ORBIT_STEP);
                break;
        }
    });
}