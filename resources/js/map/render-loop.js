/**
 * Render loop — animates the scene and updates HTML overlay positions each frame.
 */
import { renderer, scene, camera, controls } from './scene/setup.js';
import { updateOverlays } from './scene/overlay-stack.js';
import { updateStateLabels } from './ui/state-labels.js';
import { updateSelectedDistrict } from './scene/selected-district.js';

export function animate() {
    requestAnimationFrame(animate);
    controls.update();
    renderer.render(scene, camera);
    updateOverlays();
    updateStateLabels();
    updateSelectedDistrict();
}
