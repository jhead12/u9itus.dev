/**
 * Local business location pins on the 3-D U.S. map.
 * Sibling to post-pins.js — same viewport-fetch pattern, separate layer
 * since businesses are opt-in (Citizen.show_on_map) and filterable by
 * business_category rather than topic.
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

export let businessPins = [];

/** Active category filter, e.g. "food". Null shows every category. */
export let businessCategoryFilter = null;

const PIN_CLASS = 'business-pin';

const CATEGORY_ICON = {
    food: '🍴',
    retail: '🛍️',
    service: '🔧',
    nonprofit: '🤝',
    other: '📍',
};

const businessFetcher = createViewportFetcher({
    camera, mapGroup, controls,
    fetchItems: (bounds) => fetchMapContent({ ...bounds, category: businessCategoryFilter, limit: 50 }),
    onItems: (data) => renderBusinessPins(data.businesses ?? []),
    onEmpty: () => clearBusinessPins(),
    extraKeyParts: () => [businessCategoryFilter || ''],
    hasItems: () => businessPins.length > 0,
    logTag: 'business-pins',
});

export function setBusinessCategoryFilter(category) {
    businessCategoryFilter = category || null;
    businessFetcher.forceRefetch();
    refreshBusinessPins(true);
}

/**
 * Build or refresh business pins for the current viewport.
 * @param {boolean} force - fetch even if bounds haven't changed
 */
export async function refreshBusinessPins(force = false) {
    return businessFetcher.refresh(force);
}

/**
 * Render pins for a list of businesses.
 * @param {Array} items
 */
export function renderBusinessPins(items) {
    clearBusinessPins();
    for (const item of items) {
        const xy = project([item.lng, item.lat]);
        if (!xy) continue;
        const worldPos = new THREE.Vector3(xy[0], xy[1], 0.42);

        const icon = CATEGORY_ICON[item.category] || CATEGORY_ICON.other;
        const categoryClass = item.category ? ` ${item.category}` : '';

        const el = document.createElement('button');
        el.className = PIN_CLASS + categoryClass;
        el.setAttribute('aria-label', `Business: ${item.name}`);
        el.setAttribute('type', 'button');
        el.innerHTML =
            `<span class="business-pin-ring">${icon}</span>` +
            `<span class="business-pin-tag">` +
            `<strong>${escapeHtml(item.name)}</strong>` +
            (item.address ? `<span class="business-pin-address">${escapeHtml(item.address)}</span>` : '') +
            `</span>`;

        el.addEventListener('click', (e) => {
            e.stopPropagation();
            trackEvent('map_business_pin_click', {
                state: activeState || null,
                state_abbr: activeState ? (STATE_ABBR_MAP[activeState] ?? null) : null,
                meta: {
                    business_uuid: item.id,
                    business_name: item.name,
                    category: item.category,
                },
            });
            el.classList.toggle('expanded');
        });

        addOverlayItem(businessPins, mapLabelsLayer, el, worldPos, item);
    }
}

/**
 * Remove all business pins from the DOM and clear state.
 */
export function clearBusinessPins() {
    removeOverlayItems(businessPins);
    businessPins = [];
}

/**
 * Update pin screen positions each animation frame. No collision avoidance —
 * business pins don't steer clear of (or block) any other layer.
 */
export function updateBusinessPins() {
    updateOverlayPositions(businessPins, { collision: 'none' });
}
