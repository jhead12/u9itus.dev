/**
 * Shared viewport-based content fetcher for map pin layers (business pins,
 * post/event pins, and any future geo-tagged content layer): debounced
 * refetch on pan/zoom, bounds-key memoization (skips redundant fetches for
 * tiny camera wiggles), and a stale-request guard so a slow or out-of-order
 * response never clobbers a newer one.
 */
import { getViewportBounds } from './content.js';

/**
 * @param {Object} opts
 * @param {import('three').Camera} opts.camera
 * @param {import('three').Group} opts.mapGroup
 * @param {import('three').EventDispatcher} opts.controls - fires 'change' on pan/zoom
 * @param {(bounds: object) => Promise<any>} opts.fetchItems
 * @param {(data: any) => void} opts.onItems - called with the resolved fetch payload
 * @param {() => void} opts.onEmpty - called when the viewport has no valid bounds (e.g. clear the layer)
 * @param {() => string[]} [opts.extraKeyParts] - extra bounds-key segments (e.g. active filter) so a filter change forces a refetch
 * @param {() => boolean} opts.hasItems - whether the layer currently has anything rendered (debounced refetch only runs while true)
 * @param {string} opts.logTag - prefix for the console.warn on fetch failure
 * @param {number} [opts.debounceMs=300]
 * @returns {{ refresh: (force?: boolean) => Promise<void>, forceRefetch: () => void }}
 */
export function createViewportFetcher({
    camera, mapGroup, controls, fetchItems, onItems, onEmpty,
    extraKeyParts, hasItems, logTag, debounceMs = 300,
}) {
    let currentRequestId = 0;
    let lastBoundsKey = '';
    let pendingFetch = null;

    async function refresh(force = false) {
        const bounds = getViewportBounds(camera, mapGroup);
        if (!bounds) {
            onEmpty();
            return;
        }

        const boundsKey = [
            bounds.south.toFixed(2),
            bounds.west.toFixed(2),
            bounds.north.toFixed(2),
            bounds.east.toFixed(2),
            ...(extraKeyParts ? extraKeyParts() : []),
        ].join(',');
        if (!force && boundsKey === lastBoundsKey) return;
        lastBoundsKey = boundsKey;

        currentRequestId++;
        const requestId = currentRequestId;

        try {
            pendingFetch = fetchItems(bounds);
            const data = await pendingFetch;
            if (requestId !== currentRequestId) return; // stale
            onItems(data);
        } catch (e) {
            if (requestId === currentRequestId) {
                console.warn(`[${logTag}] fetch failed:`, e.message);
            }
        } finally {
            pendingFetch = null;
        }
    }

    /** Invalidate the bounds-key memo so the next refresh() always re-fetches (e.g. on filter change). */
    function forceRefetch() {
        lastBoundsKey = '';
    }

    let refreshTimer = null;
    controls.addEventListener('change', () => {
        if (!hasItems()) return;
        clearTimeout(refreshTimer);
        refreshTimer = setTimeout(() => refresh(), debounceMs);
    });

    return { refresh, forceRefetch };
}
