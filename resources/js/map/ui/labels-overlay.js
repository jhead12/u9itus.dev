/**
 * Floating district candidate labels — HTML overlays projected via camera each frame.
 */
import * as THREE from 'three';
import { STATE_ABBR_MAP, PARTY_HEX, PARTY_LABEL } from '../config/constants.js';
import { districtMeshes } from '../scene/district-overlay.js';
import { stateData } from '../state/map-state.js';
import { camera, renderer } from '../scene/setup.js';
import { openPolDrawer } from './politician-drawer.js';
import { openDistrictPanel } from './panel-district.js';

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
            // Bare district-number pills (no on-record officeholder to open a
            // profile for) used to no-op here — clicking them did nothing.
            // Fall back to the same district panel the underlying map shape
            // opens, so every pill is clickable.
            if (!seated) {
                openDistrictPanel(mesh.userData.districtNum, mesh.userData.districtLabel, mesh.userData.stateName, mesh.userData.regionHex, mesh.userData.party);
                return;
            }
            const pop = stateData?.district_populations?.[apiKey] ?? null;
            openPolDrawer(
                { ...seated, office: `U.S. Representative — ${apiKey}` },
                dotClr,
                { population: pop }
            );
        });

        mapLabelsLayer.appendChild(el);
        districtLabels.push({ el, worldPos, mesh, key: apiKey, hasName: !!name });
        requestAnimationFrame(() => el.classList.add('visible'));
    }
}

export function clearDistrictLabels() {
    for (const lbl of districtLabels) lbl.el.remove();
    districtLabels = [];
}

// Approximate on-screen footprint of a .map-label pill, for collision
// purposes only — cheaper than measuring each element's real layout size
// every frame, and close enough since all labels share the same style.
const LABEL_W = 92;
const LABEL_H = 24;
const MIN_GAP_DESKTOP = 8;
const MIN_GAP_MOBILE = 14;
const _visible = [];

export function updateDistrictLabels() {
    if (!districtLabels.length) return;
    const W = renderer.domElement.clientWidth;
    const H = renderer.domElement.clientHeight;
    const gap = W < 500 ? MIN_GAP_MOBILE : MIN_GAP_DESKTOP;
    const minDx = LABEL_W + gap;
    const minDy = LABEL_H + gap;

    _visible.length = 0;
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
            _visible.push({ lbl, sx, sy });
        }
    }

    // Named labels (an actual candidate/representative) win screen space over
    // bare district-number placeholders when they'd overlap. Array#sort is
    // stable, so ties keep their original (district mesh) order.
    _visible.sort((a, b) => (b.lbl.hasName ? 1 : 0) - (a.lbl.hasName ? 1 : 0));

    // Greedy collision suppression: dense areas (e.g. LA, the Bay Area) used
    // to pile every district's label on top of each other since projection
    // alone doesn't account for on-screen crowding. Hide anything that would
    // overlap an already-placed, higher-priority label instead of stacking it.
    const placed = [];
    for (const { lbl, sx, sy } of _visible) {
        const collides = placed.some(p => Math.abs(p.sx - sx) < minDx && Math.abs(p.sy - sy) < minDy);
        if (collides) {
            lbl.el.style.display = 'none';
            continue;
        }
        placed.push({ sx, sy });
        lbl.el.style.display = 'flex';
        lbl.el.style.left = sx + 'px';
        lbl.el.style.top = sy + 'px';
    }
}