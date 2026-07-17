/**
 * Floating district candidate labels — HTML overlays projected via camera each frame.
 */
import * as THREE from 'three';
import { STATE_ABBR_MAP, PARTY_HEX, PARTY_LABEL } from '../config/constants.js';
import { districtMeshes } from '../scene/district-overlay.js';
import { stateData } from '../state/map-state.js';
import { camera, renderer } from '../scene/setup.js';
import { openPolDrawer } from './politician-drawer.js';

export const mapLabelsLayer = document.getElementById('map-labels-layer');
export let districtLabels = [];
const _lblVec = new THREE.Vector3();

export function buildDistrictLabels(stateName) {
    clearDistrictLabels();
    const abbr = STATE_ABBR_MAP[stateName];
    if (!abbr || !districtMeshes.length) return;

    const seen = new Set();
    for (const mesh of districtMeshes) {
        const distNum = mesh.userData.districtNum;
        const apiKey = distNum === 'AL' ? `${abbr}-AL` : `${abbr}-${distNum}`;
        if (seen.has(apiKey)) continue;
        seen.add(apiKey);

        mesh.geometry.computeBoundingSphere();
        const worldPos = mesh.geometry.boundingSphere.center.clone();
        worldPos.z += 0.18;

        const cands = stateData?.house_candidates?.[apiKey] ?? [];
        const seated = cands.find(c => c.status === 'seated') ?? cands[0] ?? null;
        const name = seated?.full_name ?? '';
        const party = mesh.userData.party || 'U';
        const dotClr = PARTY_HEX[party] || '#64748b';

        const el = document.createElement('button');
        el.className = 'map-label';
        el.setAttribute('aria-label', `${apiKey}${name ? ' — ' + name : ''}`);
        el.innerHTML =
            `<span class="ml-dot" style="background:${dotClr}"></span>` +
            `<span class="ml-name">${name || apiKey}</span>` +
            `<span class="ml-dist">${apiKey}</span>`;

        el.addEventListener('click', () => {
            if (!seated) return;
            const pop = stateData?.district_populations?.[apiKey] ?? null;
            openPolDrawer(
                { ...seated, office: `U.S. Representative — ${apiKey}` },
                dotClr,
                { population: pop }
            );
        });

        mapLabelsLayer.appendChild(el);
        districtLabels.push({ el, worldPos, mesh, key: apiKey });
        requestAnimationFrame(() => el.classList.add('visible'));
    }
}

export function clearDistrictLabels() {
    for (const lbl of districtLabels) lbl.el.remove();
    districtLabels = [];
}

export function updateDistrictLabels() {
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