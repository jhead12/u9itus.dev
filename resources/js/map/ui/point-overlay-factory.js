/**
 * Shared per-frame logic for HTML-pin map overlays (district labels, city/gov
 * markers, candidate dots, business/post pins): project a stored Three.js
 * world position to screen space, run greedy collision suppression against
 * higher-priority layers' already-placed rects, and write position/visibility
 * to the DOM. This used to be copy-pasted, slightly differently, in five files.
 *
 * These are plain functions operating on a caller-owned items array (matching
 * this codebase's existing per-layer `export let fooSprites = []` convention)
 * rather than a stateful object, so each layer keeps reassigning/exporting its
 * own array exactly as it does today.
 */
import * as THREE from 'three';
import { mapGroup, renderer, camera, leftInset } from '../scene/setup.js';
import { rectsOverlap } from '../scene/overlay-collision.js';

const _vec = new THREE.Vector3();

/**
 * Append a new pin's DOM element + stored world position to an overlay's
 * items array, and kick off its enter transition. `el` should already be
 * fully configured (className, innerHTML, click handler) — this only
 * handles insertion, not creation.
 */
export function addOverlayItem(items, container, el, worldPos, item) {
    container.appendChild(el);
    requestAnimationFrame(() => el.classList.add('visible'));
    items.push({ el, worldPos, item });
}

/**
 * Remove every item's DOM element. Does not reassign `items` — callers keep
 * reassigning their own exported `let` array to `[]`, same as before.
 */
export function removeOverlayItems(items) {
    for (const entry of items) entry.el.remove();
}

/**
 * @param {Array<{el, worldPos, item}>} items
 * @param {Object} config
 * @param {(item) => THREE.Object3D} [config.getTransformNode] - defaults to
 *   the shared mapGroup; district labels override this to project via their
 *   own district mesh's transform instead.
 * @param {'left'|'center'} [config.anchor='center'] - left: rect starts at sx
 *   (matches .city-marker/.gov-marker's left-edge anchor, so the dot sits at
 *   the true geo point). center: rect is centered on sx/sy.
 * @param {{w:number,h:number} | ((canvasWidth:number) => {w:number,h:number})} [config.boxSize] -
 *   approximate on-screen footprint for collision purposes; unused when collision:'none'.
 * @param {{x:number, top:number, bottom:number}} [config.margin] - out-of-viewport cull margin in px.
 * @param {'participate'|'none'} [config.collision='participate'] - 'none' skips
 *   collision entirely (self-clips to viewport only, doesn't consume or
 *   produce rects) — matches business/post pins' current behavior.
 * @param {(visible: Array<{entry, sx, sy}>) => Array<{entry, sx, sy}>} [config.sortItems] -
 *   optional pre-collision priority sort (e.g. gov markers win over city markers).
 * @param {Array<{left,right,top,bottom}>} [occupiedRects] - rects already
 *   placed by higher-priority layers this frame.
 * @returns {Array<{left,right,top,bottom}>} rects this layer placed, for
 *   lower-priority layers to avoid.
 */
export function updateOverlayPositions(items, config, occupiedRects = []) {
    if (!items.length) return [];
    const {
        getTransformNode = () => mapGroup,
        anchor = 'center',
        boxSize,
        margin = { x: 60, top: 20, bottom: 60 },
        collision = 'participate',
        sortItems,
    } = config;

    const W = renderer.domElement.clientWidth;
    const H = renderer.domElement.clientHeight;
    const box = typeof boxSize === 'function' ? boxSize(W) : boxSize;

    const visible = [];
    for (const entry of items) {
        _vec.copy(entry.worldPos);
        _vec.applyMatrix4(getTransformNode(entry.item).matrixWorld);
        _vec.project(camera);
        const sx = (_vec.x * 0.5 + 0.5) * W;
        const sy = (-_vec.y * 0.5 + 0.5) * H;
        const behind = _vec.z > 1;
        const outside = sx < -margin.x || sx > W + margin.x || sy < margin.top || sy > H + margin.bottom;
        if (behind || outside) {
            entry.el.style.display = 'none';
            continue;
        }
        visible.push({ entry, sx, sy });
    }

    if (collision === 'none') {
        for (const { entry, sx, sy } of visible) {
            entry.el.style.display = 'flex';
            entry.el.style.left = (sx + leftInset()) + 'px';
            entry.el.style.top = sy + 'px';
        }
        return [];
    }

    const ordered = sortItems ? sortItems(visible) : visible;
    const placed = [...occupiedRects];
    const result = [];
    for (const { entry, sx, sy } of ordered) {
        const rect = anchor === 'left'
            ? { left: sx, right: sx + box.w, top: sy - box.h / 2, bottom: sy + box.h / 2 }
            : { left: sx - box.w / 2, right: sx + box.w / 2, top: sy - box.h / 2, bottom: sy + box.h / 2 };
        if (placed.some(p => rectsOverlap(rect, p))) {
            entry.el.style.display = 'none';
            continue;
        }
        placed.push(rect);
        result.push(rect);
        entry.el.style.display = 'flex';
        entry.el.style.left = (sx + leftInset()) + 'px';
        entry.el.style.top = sy + 'px';
    }
    return result;
}
