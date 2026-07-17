/**
 * Map interaction analytics — fire-and-forget event tracking.
 * This module just re-exports the window.__mapTrack function that
 * is set up in the standalone script block in the Blade template.
 * The session ID generation and beacon logic lives in the Blade
 * template's inline script (lines 1615-1658) because it must run
 * before the module loads.
 */

export function trackEvent(eventType, payload = {}) {
    if (typeof window.__mapTrack === 'function') {
        window.__mapTrack(eventType, payload);
    }
}