/**
 * Deep-link navigation — URL param boot and window.__mapGoTo export.
 */
import * as THREE from 'three';
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

                // Mirror the on-canvas district click handler (mode-transitions.js)
                // so a programmatic navigation looks/behaves identically to a real
                // click — highlight the selected district, dim the rest, and open
                // its candidate panel. Previously this only flew the camera; the
                // panel never opened because it dispatched a 'select' DOM event
                // that nothing in the app was listening for.
                for (const d of districtMeshes) {
                    d.material.color.setHex(d.userData.originalColor);
                    d.material.opacity = 0.72;
                    d.position.z = 0.255;
                }
                const bright = new THREE.Color(dm.userData.partyHex || dm.userData.regionHex || '#6366f1')
                    .lerp(new THREE.Color(0xffffff), 0.55);
                dm.material.color.setHex(bright.getHex());
                dm.material.opacity = 1.0;
                dm.position.z = 0.31;

                flyToDistrictTopDown(dm);
                openDistrictPanel(dm.userData.districtNum, dm.userData.districtLabel, dm.userData.stateName, dm.userData.regionHex, dm.userData.party);
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