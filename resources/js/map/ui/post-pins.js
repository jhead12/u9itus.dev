/**
 * Geo-tagged civic content pins on the 3-D U.S. map.
 * Renders blog posts and civic events together.
 */
import * as THREE from 'three';
import { project } from '../scene/projection.js';
import { mapGroup, renderer, camera, controls, leftInset } from '../scene/setup.js';
import { mapLabelsLayer } from './labels-overlay.js';
import { trackEvent } from '../api/interaction.js';
import { fetchMapContent, getViewportBounds } from '../api/content.js';
import { activeState } from '../state/map-state.js';
import { STATE_ABBR_MAP } from '../config/constants.js';

export let postPins = [];

let currentRequestId = 0;
let pendingFetch = null;
let lastBoundsKey = '';

const PIN_CLASS = 'post-pin';

/**
 * Build or refresh content pins for the current viewport.
 * @param {boolean} force - fetch even if bounds haven't changed
 */
export async function refreshPostPins(force = false) {
    const bounds = getViewportBounds(camera, mapGroup);
    if (!bounds) {
        clearPostPins();
        return;
    }

    // Round bounds to avoid refetching on tiny camera wiggles.
    const boundsKey = [
        bounds.south.toFixed(2),
        bounds.west.toFixed(2),
        bounds.north.toFixed(2),
        bounds.east.toFixed(2),
    ].join(',');
    if (!force && boundsKey === lastBoundsKey) return;
    lastBoundsKey = boundsKey;

    // Cancel any in-flight debounced fetch.
    currentRequestId++;
    const requestId = currentRequestId;

    try {
        pendingFetch = fetchMapContent({ ...bounds, limit: 50 });
        const data = await pendingFetch;
        if (requestId !== currentRequestId) return; // stale
        const posts = (data.posts ?? []).map((p) => ({ ...p, type: 'post' }));
        const events = (data.events ?? []).map((e) => ({ ...e, type: 'event' }));
        renderPostPins([...posts, ...events]);
    } catch (e) {
        if (requestId === currentRequestId) {
            console.warn('[post-pins] fetch failed:', e.message);
        }
    } finally {
        pendingFetch = null;
    }
}

/**
 * Render pins for a list of map content items.
 * @param {Array} items
 */
export function renderPostPins(items) {
    clearPostPins();
    for (const item of items) {
        const xy = project([item.lng, item.lat]);
        if (!xy) continue;
        const worldPos = new THREE.Vector3(xy[0], xy[1], 0.42);

        const isEvent = item.type === 'event';
        const promoted = !isEvent && item.is_promoted;
        const title = isEvent
            ? `${item.title} (${item.event_type})`
            : item.title;

        const el = document.createElement('button');
        el.className = PIN_CLASS + (isEvent ? ' event' : '');
        el.setAttribute('aria-label', `${isEvent ? 'Event' : 'Blog post'}: ${item.title}`);
        el.setAttribute('type', 'button');
        el.innerHTML =
            `<span class="post-pin-ring">` +
            `<span class="post-pin-core${promoted ? ' promoted' : ''}${isEvent ? ' event' : ''}"></span></span>` +
            `<span class="post-pin-tag">${escapeHtml(title)}</span>`;

        el.addEventListener('click', (e) => {
            e.stopPropagation();
            trackEvent('map_content_pin_click', {
                state: activeState || null,
                state_abbr: activeState ? (STATE_ABBR_MAP[activeState] ?? null) : null,
                meta: {
                    content_type: item.type,
                    content_uuid: item.id,
                    content_title: item.title,
                    promoted: promoted,
                },
            });
            window.open(item.url, '_blank', 'noopener,noreferrer');
        });

        mapLabelsLayer.appendChild(el);
        requestAnimationFrame(() => el.classList.add('visible'));
        postPins.push({ el, worldPos, item, pinPos: worldPos });
    }
}

/**
 * Remove all content pins from the DOM and clear state.
 */
export function clearPostPins() {
    for (const pin of postPins) pin.el.remove();
    postPins = [];
}

/**
 * Update pin screen positions each animation frame.
 */
export function updatePostPins() {
    if (!postPins.length) return;
    const W = renderer.domElement.clientWidth;
    const H = renderer.domElement.clientHeight;
    const _lblVec = new THREE.Vector3();
    for (const pin of postPins) {
        _lblVec.copy(pin.worldPos);
        _lblVec.applyMatrix4(mapGroup.matrixWorld);
        _lblVec.project(camera);
        const sx = (_lblVec.x * 0.5 + 0.5) * W;
        const sy = (-_lblVec.y * 0.5 + 0.5) * H;
        const behind = _lblVec.z > 1;
        const outside = sx < -60 || sx > W + 60 || sy < 20 || sy > H + 60;
        if (behind || outside) {
            pin.el.style.display = 'none';
        } else {
            pin.el.style.display = 'flex';
            pin.el.style.left = (sx + leftInset()) + 'px';
            pin.el.style.top = sy + 'px';
        }
    }
}

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

/* Debounced refetch when the map is panned/zoomed and content pins are active. */
let refreshTimer = null;
controls.addEventListener('change', () => {
    if (postPins.length === 0) return;
    clearTimeout(refreshTimer);
    refreshTimer = setTimeout(() => refreshPostPins(), 300);
});
