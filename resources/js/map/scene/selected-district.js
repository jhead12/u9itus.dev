/**
 * The selected congressional district: a strong outline and a persistent label.
 *
 * A filled, lightened mesh alone is easy to lose among 50 other labelled
 * shapes, so the selection gets (a) a bright outline with a dark halo, drawn
 * with screen-space-width lines because WebGL lines are otherwise always 1px,
 * and (b) a callout label ("CA-38 · Linda T. Sánchez") that is never hidden by
 * the label collision pass — it sits above the district's top edge so it doesn't
 * cover a small district.
 *
 * State lives here rather than in district-overlay.js so that file can call in
 * without an import cycle.
 */
import * as THREE from 'three';
import { Line2 } from 'three/addons/lines/Line2.js';
import { LineGeometry } from 'three/addons/lines/LineGeometry.js';
import { LineMaterial } from 'three/addons/lines/LineMaterial.js';
import { mapGroup, renderer, camera, leftInset } from './setup.js';
import { project } from './projection.js';

const OUTLINE_Z = 0.325;
const CORE_COLOR = 0xffffff;
const HALO_COLOR = 0x05070f;

let outline = null;
let labelEl = null;
let labelAnchor = null;
let labelTransformNode = null;
const _vec = new THREE.Vector3();

function ringPositions(ring) {
    const out = [];
    for (const coord of ring) {
        const p = project(coord);
        if (p) out.push(p[0], p[1], OUTLINE_Z);
    }
    return out;
}

function makeLine(positions, color, width, opacity, renderOrder) {
    const geo = new LineGeometry();
    geo.setPositions(positions);
    const mat = new LineMaterial({ color, linewidth: width, transparent: true, opacity, depthTest: false });
    mat.resolution.set(renderer.domElement.clientWidth, renderer.domElement.clientHeight);
    const line = new Line2(geo, mat);
    line.computeLineDistances();
    line.renderOrder = renderOrder;
    return line;
}

function disposeOutline() {
    if (!outline) return;
    mapGroup.remove(outline);
    outline.traverse((o) => { o.geometry?.dispose(); o.material?.dispose(); });
    outline = null;
}

function ensureLabel() {
    if (labelEl) return labelEl;
    labelEl = document.createElement('div');
    labelEl.id = 'selected-district-label';
    labelEl.className = 'sel-district-label';
    labelEl.setAttribute('role', 'status');
    labelEl.style.display = 'none';
    document.getElementById('map-labels-layer').appendChild(labelEl);
    return labelEl;
}

/** Centre of the district's largest polygon — the part a label should sit on. */
function labelAnchorFor(meshes) {
    let best = null;
    for (const m of meshes) {
        m.geometry.computeBoundingSphere();
        const sphere = m.geometry.boundingSphere;
        if (!best || sphere.radius > best.sphere.radius) best = { mesh: m, sphere };
    }
    return best;
}

/**
 * @param {THREE.Mesh[]} meshes every polygon of the selected district
 * @param {{code: string, name?: string, partyHex?: string}} info
 */
export function showSelectedDistrict(meshes, info) {
    disposeOutline();
    if (!meshes.length) { hideLabel(); return; }

    outline = new THREE.Group();
    for (const mesh of meshes) {
        for (const ring of mesh.userData.rings ?? []) {
            const positions = ringPositions(ring);
            if (positions.length < 6) continue;
            outline.add(makeLine(positions, HALO_COLOR, 7, 0.7, 5));
            outline.add(makeLine(positions, CORE_COLOR, 3, 1, 6));
        }
    }
    mapGroup.add(outline);

    const best = labelAnchorFor(meshes);
    // Top edge of the district, not its centre: the callout must sit above the
    // shape (a small district would otherwise be hidden behind its own label).
    labelAnchor = best.sphere.center.clone();
    labelAnchor.y += best.sphere.radius;
    labelAnchor.z += 0.1;
    labelTransformNode = best.mesh;

    const el = ensureLabel();
    const accent = info.partyHex || '#a5b4fc';
    el.style.setProperty('--sel-accent', accent);
    el.innerHTML = `<span class="sel-code">${info.code}</span>${info.name ? `<span class="sel-name"></span>` : ''}`;
    if (info.name) el.querySelector('.sel-name').textContent = info.name;
    el.setAttribute('aria-label', `Selected district ${info.code}${info.name ? ', ' + info.name : ''}`);
}

function hideLabel() {
    labelAnchor = null;
    labelTransformNode = null;
    if (labelEl) labelEl.style.display = 'none';
}

export function clearSelectedDistrict() {
    disposeOutline();
    hideLabel();
}

export function hasSelectedDistrict() {
    return outline !== null;
}

/** Per-frame: keep the outline's pixel width right and pin the label to its district. */
export function updateSelectedDistrict() {
    if (!outline) return;
    const W = renderer.domElement.clientWidth;
    const H = renderer.domElement.clientHeight;
    outline.traverse((o) => { if (o.material?.resolution) o.material.resolution.set(W, H); });

    if (!labelEl || !labelAnchor) return;
    _vec.copy(labelAnchor).applyMatrix4(labelTransformNode.matrixWorld).project(camera);
    if (_vec.z > 1) { labelEl.style.display = 'none'; return; }

    const inset = leftInset();
    const sx = (_vec.x * 0.5 + 0.5) * W;
    const sy = (-_vec.y * 0.5 + 0.5) * H;
    // Keep the callout on screen even when the district itself is at an edge.
    const x = Math.min(Math.max(sx, 70), W - 70);
    const y = Math.min(Math.max(sy, 64), H - 24);
    labelEl.style.display = 'flex';
    labelEl.style.left = (x + inset) + 'px';
    labelEl.style.top = y + 'px';
}
