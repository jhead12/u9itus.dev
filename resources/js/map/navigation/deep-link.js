/**
 * Deep-link navigation — URL param boot and window.__mapGoTo export.
 */
import { STATE_ABBR_MAP } from '../config/constants.js';
import { stateMeshes } from '../scene/state-meshes.js';
import { districtMeshes, flyToDistrictTopDown } from '../scene/district-overlay.js';
import { enterStateMode } from './mode-transitions.js';
import { openDistrictPanel } from '../ui/panel-district.js';

/**
 * Navigate to a state/district from external code.
 * Called by URL params, iframes, and window.__mapGoTo().
 */
window.__mapGoTo = async function (state, district = null, slug = null) {
    const ABBR_TO_NAME = Object.fromEntries(
        Object.entries(STATE_ABBR_MAP).map(([name, abbr]) => [abbr, name])
    );
    const stateName = (state && state.length === 2)
        ? (ABBR_TO_NAME[state.toUpperCase()] || state)
        : state;

    const mesh = stateMeshes.find(m => m.userData.name === stateName);
    if (!mesh) return;

    await enterStateMode(stateName, mesh.userData.regionName, mesh.userData.region);

    if (district) {
        const target = String(district).padStart(2, '0');
        let waited = 0;
        const trySelect = setInterval(() => {
            waited += 100;
            const dm = districtMeshes.find(m => String(m.userData.districtNum).padStart(2, '0') === target);
            if (dm) {
                clearInterval(trySelect);
                dm.dispatchEvent(new CustomEvent('select'));
                flyToDistrictTopDown(dm);
                dm.material.color.setHex(dm.userData.hoverColor || 0xffffff);
            }
            if (waited >= 2000) clearInterval(trySelect);
        }, 100);
    }

    if (slug) {
        let waited = 0;
        const tryOpen = setInterval(() => {
            waited += 150;
            const link = document.querySelector(`[data-slug="${slug}"], a[href*="${slug}"]`);
            if (link) { clearInterval(tryOpen); link.click(); }
            if (waited >= 3000) clearInterval(tryOpen);
        }, 150);
    }
};

// __mapReset / __mapBack / __mapRegion are owned by ui/breadcrumb.js
// (initBreadcrumb), which imports enterOverviewMode/handleBack/enterRegionMode
// directly. Don't reassign them here — last-loaded-wins shadowing caused
// behavior divergence between the two modules.

/**
 * Boot: read URL params and deep-link on first load.
 */
export function bootDeepLink() {
    const params = new URLSearchParams(location.search);
    const pState = params.get('state');
    const pDistrict = params.get('district');
    const pSlug = params.get('slug');
    if (!pState) return;

    const tryBoot = setInterval(() => {
        if (stateMeshes.length > 0) {
            clearInterval(tryBoot);
            window.__mapGoTo(pState, pDistrict, pSlug);
        }
    }, 200);
    setTimeout(() => clearInterval(tryBoot), 8000);
}