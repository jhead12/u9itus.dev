/**
 * Breadcrumb — shows Overview › Region › State navigation.
 */
import { mapMode, activeRegion, activeState } from '../state/map-state.js';
import { REGIONS } from '../config/constants.js';
import { enterOverviewMode, handleBack, enterRegionMode } from '../navigation/mode-transitions.js';

/**
 * Update breadcrumb DOM to reflect current map mode.
 */
export function updateBreadcrumb() {
    const el = document.getElementById('breadcrumb');
    if (!el) return;
    if (mapMode === 'overview') {
        el.innerHTML = '<span class="bc-item bc-active">Overview</span>';
    } else if (mapMode === 'region') {
        el.innerHTML = `<a class="bc-item bc-link" onclick="window.__mapReset()">Overview</a>
            <span class="bc-sep">›</span>
            <span class="bc-item bc-active" style="color:${REGIONS[activeRegion]?.hex}">${activeRegion}</span>`;
    } else {
        el.innerHTML = `<a class="bc-item bc-link" onclick="window.__mapReset()">Overview</a>
            <span class="bc-sep">›</span>
            <a class="bc-item bc-link" style="color:${REGIONS[activeRegion]?.hex}" onclick="window.__mapRegion('${activeRegion}')">${activeRegion}</a>
            <span class="bc-sep">›</span>
            <span class="bc-item bc-active">${activeState}</span>
            <span class="bc-sep">›</span>
            <span class="bc-item" style="color:#a7b4c7">Districts (119th)</span>`;
    }
}

/**
 * Wire up global breadcrumb handlers and back button.
 */
export function initBreadcrumb() {
    window.__mapReset = enterOverviewMode;
    window.__mapBack = handleBack;
    window.__mapRegion = name => enterRegionMode(name, REGIONS[name]);
    // btn-back is wired in app.js (orchestrator) — don't add a second listener here.
}