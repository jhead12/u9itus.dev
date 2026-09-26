/**
 * Deep-link navigation — URL param boot and window.__mapGoTo export.
 */
import { STATE_ABBR_MAP, REGIONS } from '../config/constants.js';
import { stateMeshes } from '../scene/state-meshes.js';
import { districtMeshes, flyToDistrictTopDown } from '../scene/district-overlay.js';
import { enterStateMode, enterRegionMode, enterOverviewMode } from './mode-transitions.js';
import { initMapHistory, finishBoot } from './history.js';
import { openDistrictPanel } from '../ui/panel-district.js';
import { activeState, mapMode } from '../state/map-state.js';
import { showToast } from '../ui/location-button.js';

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
    if (!mesh) return false;

    // Opening a district in the state already on screen needs no reload.
    const hasDistrict = district !== null && district !== '';
    if (!(hasDistrict && mapMode === 'state' && activeState === stateName)) {
        await enterStateMode(stateName, mesh.userData.regionName, mesh.userData.region);
    }

    // enterStateMode awaits the bounded boundary request; no timer races.
    if (mapMode !== 'state' || activeState !== stateName) return true;
    if (hasDistrict) {
        const normalize = value => ['AL', '00', '0'].includes(String(value).toUpperCase()) ? 'AL' : String(Number(value));
        const target = normalize(district);
        const dm = districtMeshes.find(m => m.userData.stateName === stateName && normalize(m.userData.districtNum) === target);
        if (dm) {
            flyToDistrictTopDown(dm);
            openDistrictPanel(dm.userData.districtNum, dm.userData.districtLabel, stateName, dm.userData.regionHex, dm.userData.party);
        } else {
            openDistrictPanel(target, target === 'AL' ? `${state} At-Large` : `${state}-${String(target).padStart(2, '0')}`, stateName, mesh.userData.region?.hex || '#6366f1', 'U');
            showToast('District information is open. Boundaries are unavailable; use Retry in the district list to load the map outline.', 'info');
        }
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

    return true;
};

// __mapReset / __mapBack / __mapRegion are owned by ui/breadcrumb.js
// (initBreadcrumb), which imports enterOverviewMode/handleBack/enterRegionMode
// directly. Don't reassign them here — last-loaded-wins shadowing caused
// behavior divergence between the two modules.

/** Show a location replayed from browser history (see history.js). */
async function showLocation(loc) {
    if (loc.level === 'overview') enterOverviewMode();
    else if (loc.level === 'region' && REGIONS[loc.region]) enterRegionMode(loc.region, REGIONS[loc.region]);
    else if (loc.level === 'state') await window.__mapGoTo(loc.state);
    else if (loc.level === 'district') await window.__mapGoTo(loc.state, loc.district);
}

/**
 * Boot: read URL params and deep-link on first load.
 */
export function bootDeepLink() {
    initMapHistory(showLocation);
    const params = new URLSearchParams(location.search);
    const pState = params.get('state');
    const pDistrict = params.get('district');
    const pSlug = params.get('slug');
    const pRegion = params.get('region');
    if (!pState && pRegion && REGIONS[pRegion]) {
        const tryRegion = setInterval(() => {
            if (stateMeshes.length > 0) {
                clearInterval(tryRegion);
                enterRegionMode(pRegion, REGIONS[pRegion]);
                finishBoot();
            }
        }, 200);
        setTimeout(() => { clearInterval(tryRegion); finishBoot(); }, 8000);
        return;
    }
    if (!pState) { finishBoot(); return; }

    const tryBoot = setInterval(() => {
        if (stateMeshes.length > 0) {
            clearInterval(tryBoot);
            window.__mapGoTo(pState, pDistrict, pSlug).finally(finishBoot);
        }
    }, 200);
    setTimeout(() => { clearInterval(tryBoot); if (!stateMeshes.length) finishBoot(); }, 8000);
}