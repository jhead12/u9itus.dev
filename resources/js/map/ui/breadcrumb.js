/**
 * Breadcrumb — shows Overview › Region › State › District navigation.
 *
 * Earlier steps are real buttons (reachable by keyboard and screen readers);
 * the last step is the current place, marked with aria-current.
 */
import { mapMode, activeRegion, activeState } from '../state/map-state.js';
import { REGIONS } from '../config/constants.js';
import { enterOverviewMode, handleBack, enterRegionMode } from '../navigation/mode-transitions.js';
import { getOpenDistrict } from './panel-district.js';
import { escapeHtml } from '../utils/html.js';

const SEP = '<span class="bc-sep" aria-hidden="true">›</span>';

function link(label, target, color = '') {
    const style = color ? ` style="color:${color}"` : '';
    return `<li><button type="button" class="bc-item bc-link" data-bc="${target}"${style}>${escapeHtml(label)}</button>${SEP}</li>`;
}

function current(label, color = '') {
    const style = color ? ` style="color:${color}"` : '';
    return `<li><span class="bc-item bc-active" aria-current="location"${style}>${escapeHtml(label)}</span></li>`;
}

/**
 * Update breadcrumb DOM to reflect current map mode.
 */
export function updateBreadcrumb() {
    const el = document.getElementById('breadcrumb');
    if (!el) return;
    const regionHex = REGIONS[activeRegion]?.hex || '';
    let items;
    if (mapMode === 'overview') {
        items = current('Overview');
    } else if (mapMode === 'region') {
        items = link('Overview', 'overview') + current(activeRegion, regionHex);
    } else {
        const district = getOpenDistrict();
        items = link('Overview', 'overview') + link(activeRegion, 'region', regionHex);
        if (district && district.stateName === activeState) {
            items += link(activeState, 'state') + current(district.label);
        } else {
            items += current(activeState)
                + `<li class="bc-extra">${SEP}<span class="bc-item" style="color:#a7b4c7">Districts (119th)</span></li>`;
        }
    }
    el.innerHTML = `<ol class="bc-list">${items}</ol>`;
    fitBreadcrumb();
}

/** On narrow screens, drop the "Overview" step when the others would otherwise be cut short. */
function fitBreadcrumb() {
    const list = document.querySelector('#breadcrumb .bc-list');
    if (!list) return;
    list.classList.remove('bc-compact');
    const truncated = [...list.querySelectorAll('.bc-item')].some(i => i.scrollWidth > i.clientWidth + 1);
    if (truncated && list.children.length > 2) list.classList.add('bc-compact');
}

/**
 * Wire up global breadcrumb handlers and back button.
 */
export function initBreadcrumb() {
    window.__mapReset = enterOverviewMode;
    window.__mapBack = handleBack;
    window.__mapRegion = name => enterRegionMode(name, REGIONS[name]);
    // btn-back is wired in app.js (orchestrator) — don't add a second listener here.
    window.addEventListener('resize', fitBreadcrumb);

    document.getElementById('breadcrumb')?.addEventListener('click', (e) => {
        const target = e.target.closest('[data-bc]')?.dataset.bc;
        if (target === 'overview') enterOverviewMode();
        else if (target === 'region') enterRegionMode(activeRegion, REGIONS[activeRegion]);
        else if (target === 'state') window.__mapGoTo?.(activeState);
    });
}
