/**
 * Three.js scene, camera, renderer, lights, controls, and resize handler.
 */
import * as THREE from 'three';
import { OrbitControls } from 'three/addons/controls/OrbitControls.js';

export const scene = new THREE.Scene();
export const camera = new THREE.PerspectiveCamera(40, window.innerWidth / window.innerHeight, 0.1, 100);
camera.position.set(0, 5.4, 10.2);
camera.up.set(0, 1, 0);

export const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: true });
renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
renderer.setSize(window.innerWidth, window.innerHeight);
renderer.setClearColor(0x06091a, 1);
renderer.setAnimationLoop(() => {}); // will be replaced by render loop

document.getElementById('map-container').appendChild(renderer.domElement);

export const controls = new OrbitControls(camera, renderer.domElement);
controls.enableDamping  = true;
controls.dampingFactor   = 0.06;
controls.minDistance      = 2;
controls.maxDistance      = 25;
controls.minPolarAngle   = Math.PI * 0.08;  // max tilt: ~85° from vertical
controls.maxPolarAngle   = Math.PI * 0.46;  // min tilt: ~26° from vertical
controls.enablePan       = true;
controls.panSpeed         = 0.6;
controls.enableRotate    = true;
controls.rotateSpeed     = 0.35;
controls.target.set(0, 0, 0);

/* Lights */
const hemiLight = new THREE.HemisphereLight(0xc8d8f0, 0x1a2040, 0.7);
scene.add(hemiLight);

const dirLight = new THREE.DirectionalLight(0xffffff, 0.55);
dirLight.position.set(5, 10, 8);
scene.add(dirLight);

const fillLight = new THREE.DirectionalLight(0x818cf8, 0.25);
fillLight.position.set(-5, 6, -4);
scene.add(fillLight);

/* Starfield */
const starGeo = new THREE.BufferGeometry();
const starPos = new Float32Array(2400);
for (let i = 0; i < 2400; i++) starPos[i] = (Math.random() - 0.5) * 50;
starGeo.setAttribute('position', new THREE.BufferAttribute(starPos, 3));
const starMat = new THREE.PointsMaterial({ color: 0x667799, size: 0.04 });
scene.add(new THREE.Points(starGeo, starMat));

/* Background plane */
const bgGeo = new THREE.PlaneGeometry(30, 20);
const bgMat = new THREE.MeshBasicMaterial({ color: 0x06091a });
const bgPlane = new THREE.Mesh(bgGeo, bgMat);
bgPlane.position.z = -2;
scene.add(bgPlane);

/* Main group for all map meshes */
export const mapGroup = new THREE.Group();
scene.add(mapGroup);

/* Resize */
export function resizeRenderer() {
    const container = document.getElementById('map-container');
    if (!container) return;
    const w = container.clientWidth;
    const h = container.clientHeight;
    renderer.setSize(w, h);
    camera.aspect = w / h;
    camera.updateProjectionMatrix();
}

resizeRenderer();