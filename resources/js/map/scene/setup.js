/**
 * Three.js scene, camera, renderer, lights, controls, and resize handler.
 */
import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';

const container = document.getElementById('map-container');

/**
 * Effective canvas width: on desktop (>768px) subtract the panel width
 * when the panel is open so the map renders centered in the visible area,
 * not behind the side panel.
 */
const PANEL_WIDTH = 324; // panel width (300px) + right margin (12px) + border
export function W() {
    const panel = document.getElementById('info-panel');
    if (window.innerWidth > 768 && panel && panel.classList.contains('open')) {
        return Math.max(container.clientWidth - PANEL_WIDTH, 200);
    }
    return container.clientWidth;
}
export function H() { return container.clientHeight; }

export const scene = new THREE.Scene();
scene.background = new THREE.Color(0x06091a);
scene.fog = new THREE.FogExp2(0x060914, 0.004);

// Default overview framing — tuned so the lower-48 + Alaska/Hawaii inset
// fills ~70% of the viewport width (matches design reference).
export const camera = new THREE.PerspectiveCamera(42, W() / H(), 0.1, 300);
camera.position.set(0, 5.4, 10.2);

export const renderer = new THREE.WebGLRenderer({ antialias: true });
renderer.setSize(W(), H());
renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
container.appendChild(renderer.domElement);

/* Flat-map lighting: strong even ambient, minimal directional shadow */
scene.add(new THREE.AmbientLight(0xffffff, 1.7));
const sun = new THREE.DirectionalLight(0xffffff, 0.18);
sun.position.set(0, 20, 10);
scene.add(sun);

export const controls = new OrbitControls(camera, renderer.domElement);
controls.enableDamping = true;
controls.dampingFactor = 0.07;
controls.minDistance = 2;
controls.maxDistance = 45;
controls.minPolarAngle = 15 * Math.PI / 180;   // 15° — near top-down
controls.maxPolarAngle = 130 * Math.PI / 180;  // 130° — past horizontal (opposite tilt)
controls.target.set(0, 0, 0);

/* Lock rotation: the map should only tilt, zoom, and pan. */
controls.enableRotate = false;
controls.minAzimuthAngle = 0;
controls.maxAzimuthAngle = 0;

/* Left-drag and right-drag both pan; scroll wheel zooms; touch pans/zooms. */
controls.mouseButtons = {
    LEFT: THREE.MOUSE.PAN,
    MIDDLE: THREE.MOUSE.DOLLY,
    RIGHT: THREE.MOUSE.PAN,
};
controls.touches = {
    ONE: THREE.TOUCH.PAN,
    TWO: THREE.TOUCH.DOLLY_PAN,
};
controls.screenSpacePanning = true;

/* Stars — spherical shell so they read as a distant skybox */
const sBuf = new Float32Array(2000 * 3);
for (let i = 0; i < 2000; i++) {
    const r = 80 + Math.random() * 100, th = Math.random() * Math.PI * 2, ph = Math.acos(2 * Math.random() - 1);
    sBuf[i * 3]     = r * Math.sin(ph) * Math.cos(th);
    sBuf[i * 3 + 1] = r * Math.sin(ph) * Math.sin(th);
    sBuf[i * 3 + 2] = r * Math.cos(ph);
}
const sGeo = new THREE.BufferGeometry();
sGeo.setAttribute('position', new THREE.BufferAttribute(sBuf, 3));
scene.add(new THREE.Points(sGeo, new THREE.PointsMaterial({ color: 0x8899cc, size: 0.18, sizeAttenuation: true })));

/* Background plane */
scene.add(new THREE.Mesh(new THREE.PlaneGeometry(22, 14), new THREE.MeshLambertMaterial({ color: 0x0b1429 })));

/* Main group for all map meshes */
export const mapGroup = new THREE.Group();
scene.add(mapGroup);

/* Resize */
export function resizeRenderer() {
    camera.aspect = W() / H();
    camera.updateProjectionMatrix();
    renderer.setSize(W(), H(), false); // false = don't update canvas CSS size
    // Offset the canvas so it fills only the map area (not under the panel)
    renderer.domElement.style.width = W() + 'px';
    renderer.domElement.style.height = H() + 'px';
}

resizeRenderer();