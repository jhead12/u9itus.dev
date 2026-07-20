/**
 * Render loop — animates the scene and updates HTML overlay positions each frame.
 */
import { renderer, scene, camera, controls } from './scene/setup.js';
import { districtLabels } from './ui/labels-overlay.js';
import { citySprites, govSprites } from './ui/markers.js';
import { updateCandidateMarkers } from './ui/candidate-markers.js';
import { mapGroup } from './scene/setup.js';
import * as THREE from 'three';

const _lblVec = new THREE.Vector3();

function updateDistrictLabels() {
    if (!districtLabels.length) return;
    const W = renderer.domElement.clientWidth;
    const H = renderer.domElement.clientHeight;
    for (const lbl of districtLabels) {
        _lblVec.copy(lbl.worldPos);
        _lblVec.applyMatrix4(lbl.mesh.matrixWorld);
        _lblVec.project(camera);
        const sx = (_lblVec.x * 0.5 + 0.5) * W;
        const sy = (-_lblVec.y * 0.5 + 0.5) * H;
        const behind = _lblVec.z > 1;
        const outside = sx < -60 || sx > W + 60 || sy < 40 || sy > H + 60;
        if (behind || outside) {
            lbl.el.style.display = 'none';
        } else {
            lbl.el.style.display = 'flex';
            lbl.el.style.left = sx + 'px';
            lbl.el.style.top = sy + 'px';
        }
    }
}

function updateCityDots() {
    if (!citySprites.length && !govSprites.length) return;
    const W = renderer.domElement.clientWidth;
    const H = renderer.domElement.clientHeight;
    for (const dot of [...citySprites, ...govSprites]) {
        _lblVec.copy(dot.worldPos);
        _lblVec.applyMatrix4(mapGroup.matrixWorld);
        _lblVec.project(camera);
        const sx = (_lblVec.x * 0.5 + 0.5) * W;
        const sy = (-_lblVec.y * 0.5 + 0.5) * H;
        const behind = _lblVec.z > 1;
        const outside = sx < -40 || sx > W + 40 || sy < 20 || sy > H + 40;
        if (behind || outside) { dot.el.style.display = 'none'; }
        else { dot.el.style.display = 'flex'; dot.el.style.left = sx + 'px'; dot.el.style.top = sy + 'px'; }
    }
}

export function animate() {
    requestAnimationFrame(animate);
    controls.update();
    renderer.render(scene, camera);
    updateDistrictLabels();
    updateCityDots();
    updateCandidateMarkers();
}

export { updateDistrictLabels, updateCityDots };