/**
 * Local business location pins on the 3-D U.S. map.
 * Sibling to post-pins.js — same viewport-fetch pattern, separate layer
 * since businesses are opt-in (Citizen.show_on_map) and filterable by
 * business_category rather than topic.
 */
import * as THREE from 'three';
import { project } from '../scene/projection.js';
import { mapGroup, renderer, camera, controls, leftInset } from '../scene/setup.js';
import { mapLabelsLayer } from './labels-overlay.js';
import { trackEvent } from '../api/interaction.js';
import { fetchMapContent, getViewportBounds } from '../api/content.js';
import { activeState } from '../state/map-state.js';
import { STATE_ABBR_MAP } from '../config/constants.js';

export let businessPins = [];

/** Active category filter, e.g. "food". Null shows every category. */
export let businessCategoryFilter = null;

let currentRequestId = 0;
let pendingFetch = null;
let lastBoundsKey = '';

const PIN_CLASS = 'business-pin';

const CATEGORY_ICON = {
    food: '🍴',
    retail: '🛍️',
    service: '🔧',
    nonprofit: '🤝',
    other: '📍',
};

export function setBusinessCategoryFilter(category) {
    businessCategoryFilter = category || null;
    lastBoundsKey = ''; // force a refetch with the new filter
    refreshBusinessPins(true);
}

/**
 * Build or refresh business pins for the current viewport.
 * @param {boolean} force - fetch even if bounds haven't changed
 */
export async function refreshBusinessPins(force = false) {
    const bounds = getViewportBounds(camera, mapGroup);
    if (!bounds) {
        clearBusinessPins();
        return;
    }

    const boundsKey = [
        bounds.south.toFixed(2),
        bounds.west.toFixed(2),
        bounds.north.toFixed(2),
        bounds.east.toFixed(2),
        businessCategoryFilter || '',
    ].join(',');
    if (!force && boundsKey === lastBoundsKey) return;
    lastBoundsKey = boundsKey;

    currentRequestId++;
    const requestId = currentRequestId;

    try {
        pendingFetch = fetchMapContent({ ...bounds, category: businessCategoryFilter, limit: 50 });
        const data = await pendingFetch;
        if (requestId !== currentRequestId) return; // stale
        renderBusinessPins(data.businesses ?? []);
    } catch (e) {
        if (requestId === currentRequestId) {
            console.warn('[business-pins] fetch failed:', e.message);
        }
    } finally {
        pendingFetch = null;
    }
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

        mapLabelsLayer.appendChild(el);
        requestAnimationFrame(() => el.classList.add('visible'));
        businessPins.push({ el, worldPos, item, pinPos: worldPos });
    }
}

/**
 * Remove all business pins from the DOM and clear state.
 */
export function clearBusinessPins() {
    for (const pin of businessPins) pin.el.remove();
    businessPins = [];
}

/**
 * Update pin screen positions each animation frame.
 */
export function updateBusinessPins() {
    if (!businessPins.length) return;
    const W = renderer.domElement.clientWidth;
    const H = renderer.domElement.clientHeight;
    const _lblVec = new THREE.Vector3();
    for (const pin of businessPins) {
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

/* Debounced refetch when the map is panned/zoomed and business pins are active. */
let refreshTimer = null;
controls.addEventListener('change', () => {
    if (businessPins.length === 0) return;
    clearTimeout(refreshTimer);
    refreshTimer = setTimeout(() => refreshBusinessPins(), 300);
});
