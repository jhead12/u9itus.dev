/**
 * Render loop — animates the scene and updates HTML overlay positions each frame.
 */
import { renderer, scene, camera, controls } from './scene/setup.js';
import { updateOverlays } from './scene/overlay-stack.js';

export function animate() {
    requestAnimationFrame(animate);
    controls.update();
    renderer.render(scene, camera);
    updateOverlays();
}
