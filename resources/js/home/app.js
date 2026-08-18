import '../../css/specular-button.css';
import { mountSpecularButton } from '../components/specular-button.js';

const earnCta = document.getElementById('btn-earn-cta');
if (earnCta) {
    mountSpecularButton(earnCta, {
        radius: 999,
        lineColor: '#34d399',
        baseColor: '#065f46',
        intensity: 1.3,
        shineSize: 12,
        shineFade: 35,
        thickness: 1.2,
        speed: 0.3,
        followMouse: true,
        proximity: 200,
        autoAnimate: false,
    });
}
