/**
 * Geo-tagged civic content pins on the 3-D U.S. map.
 * Renders blog posts and civic events together.
 */
import * as THREE from 'three';
import { project } from '../scene/projection.js';
import { mapGroup, camera, controls } from '../scene/setup.js';
import { mapLabelsLayer } from './labels-overlay.js';
import { trackEvent } from '../api/interaction.js';
import { fetchMapContent } from '../api/content.js';
import { createViewportFetcher } from '../api/viewport-fetch.js';
import { addOverlayItem, removeOverlayItems, updateOverlayPositions } from './point-overlay-factory.js';
import { activeState } from '../state/map-state.js';
import { STATE_ABBR_MAP } from '../config/constants.js';
import { escapeHtml } from '../utils/html.js';

export let postPins = [];

const PIN_CLASS = 'post-pin';

const postFetcher = createViewportFetcher({
    camera, mapGroup, controls,
    fetchItems: (bounds) => fetchMapContent({ ...bounds, limit: 50 }),
    onItems: (data) => {
        const posts = (data.posts ?? []).map((p) => ({ ...p, type: 'post' }));
        const events = (data.events ?? []).map((e) => ({ ...e, type: 'event' }));
        renderPostPins([...posts, ...events]);
    },
    onEmpty: () => clearPostPins(),
    hasItems: () => postPins.length > 0,
    logTag: 'post-pins',
});

/**
 * Build or refresh content pins for the current viewport.
 * @param {boolean} force - fetch even if bounds haven't changed
 */
export async function refreshPostPins(force = false) {
    return postFetcher.refresh(force);
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

        addOverlayItem(postPins, mapLabelsLayer, el, worldPos, item);
    }
}

/**
 * Remove all content pins from the DOM and clear state.
 */
export function clearPostPins() {
    removeOverlayItems(postPins);
    postPins = [];
}

/**
 * Update pin screen positions each animation frame. No collision avoidance —
 * content pins don't steer clear of (or block) any other layer.
 */
export function updatePostPins() {
    updateOverlayPositions(postPins, { collision: 'none' });
}
