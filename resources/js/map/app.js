/**
 * U.S. Map — Vite entry point.
 * Imports all modules, initializes the scene, and wires up event handlers.
 */
import '../../css/map.css';
import '../../css/specular-button.css';

/* ── Avatar initials helper (global, used by onerror attrs in HTML) ── */
window.avatarInitials = function (name, color, size) {
    const parts = (name || '').trim().split(/\s+/);
    const initials = (parts.length >= 2
        ? parts[0][0] + parts[parts.length - 1][0]
        : (parts[0]?.[0] || '?')).toUpperCase();
    const fontSize = size >= 44 ? 16 : 13;
    const bg     = color ? color + '30' : 'rgba(99,102,241,0.18)';
    const border = color ? color + '70' : 'rgba(99,102,241,0.4)';
    const h = size / 2;
    return `<svg width="${size}" height="${size}" viewBox="0 0 ${size} ${size}" xmlns="http://www.w3.org/2000/svg">`
        + `<circle cx="${h}" cy="${h}" r="${h}" fill="${bg}" stroke="${border}" stroke-width="1.5"/>`
        + `<text x="50%" y="50%" text-anchor="middle" dominant-baseline="central"`
        + ` font-family="system-ui,sans-serif" font-size="${fontSize}" font-weight="700"`
        + ` fill="${color || '#818cf8'}" opacity="0.9">${initials}</text></svg>`;
};

/* ── Config ── */
import { REGIONS, STATE_ABBR_MAP, STATE_FIPS, DISTRICT_COUNTS, PARTY_HEX, PARTY_LABEL, OFFICE_ROLES, CITY_OFFICE_ROLES, DISTRICT_PARTY_MAP, stateToRegion } from './config/constants.js';
import { TOP_CITIES, STATE_CAPITALS, fmtPop } from './config/city-data.js';
import { TOUR_STEPS, TOUR_KEY } from './config/tour-steps.js';

/* ── State ── */
import { mapMode, activeRegion, activeState, selectedState, statePanelRequestId, stateData, colorMode, showSmallCities, DISTRICT_CONFIG, govPartyByAbbr, districtCache, cityBoundaryCache } from './state/map-state.js';
import { restoreBootLayers } from './state/layer-directory.js';
import './state/session.js';

/* ── Scene ── */
import { scene, camera, renderer, controls, mapGroup, resizeRenderer } from './scene/setup.js';
import { loadMapData } from './scene/state-meshes.js';
import { stateMeshes } from './scene/state-meshes.js';
import { buildDistrictOverlay, clearDistricts, resetDistrictSelection, districtMeshes, hoveredDistrict } from './scene/district-overlay.js';
import { toggleNationalBoundaries, nationalDistVisible, _syncNatDistVisibility, loadNationalBoundaries } from './scene/national-boundaries.js';

/* ── API ── */
import { initDistrictConfig } from './api/district-config.js';
import { trackEvent } from './api/interaction.js';
import { fetchBoundaries } from './api/favorites.js';
import { fetchFollowedPoliticianIds } from './api/politician-follow.js';
import { initDigestOptInPrompt } from './ui/digest-optin.js';

/* ── UI ── */
import { initSearch, openSearch, closeSearch } from './ui/search.js';
import { buildLegend, showRegionLegend, showPartyLegend } from './ui/legend.js';
import { openStatePanel, partyClass, detectElectionPhase, renderCandidate, renderOfficeGroup, noDataNotice, initOfficesToggle, initCandidateCardClick } from './ui/panel-state.js';
import { openDistrictPanel } from './ui/panel-district.js';
import { initPopup, closePopup } from './ui/popup.js';
import { initPolDrawer, openPolDrawer, closePolDrawer } from './ui/politician-drawer.js';
import { initControlsMenu, updateDistrictsBtn } from './ui/controls-menu.js';
import { initLayersPanel, toggleLayer, applyPopulationDensity, renderFavoriteChips } from './ui/layers-panel.js';
import { buildCityMarkers, clearCityMarkers, buildGovMarkers, clearGovMarkers, loadCityBoundaries, clearCityLayer, citySprites, govSprites } from './ui/markers.js';
import { buildDistrictLabels, clearDistrictLabels, updateDistrictLabels, districtLabels, mapLabelsLayer } from './ui/labels-overlay.js';
import { initBreadcrumb, updateBreadcrumb } from './ui/breadcrumb.js';
import { initTour, startTutorial } from './ui/tour.js';
import { initKeyboard, toggleKbHelp, stepZoom } from './ui/keyboard.js';
import { initInfoPanel, openInfoPanel } from './ui/info-panel.js';
import { initMobileMenu } from './ui/mobile-menu.js';
import { initLocationButton } from './ui/location-button.js';
import { mountSpecularButton } from '../components/specular-button.js';

/* ── Navigation ── */
import { enterOverviewMode, enterRegionMode, enterStateMode, handleBack, initHoverClick, hoveredMesh } from './navigation/mode-transitions.js';
import { bootDeepLink } from './navigation/deep-link.js';

/* ── Render loop ── */
import { animate } from './render-loop.js';

/* ════════════════════════════════════════════════════════
   MAP LOAD
════════════════════════════════════════════════════════ */
loadMapData().then(() => {
    buildLegend();
    restoreBootLayers();
    initDistrictConfig();
}).catch(err => {
    console.error('Map load failed:', err);
});

/* ════════════════════════════════════════════════════════
   INIT ALL UI HANDLERS
════════════════════════════════════════════════════════ */
initHoverClick();
initSearch();
initPopup();
initPolDrawer();
initControlsMenu();
initLayersPanel();
initInfoPanel();
initMobileMenu();
initBreadcrumb();
initTour();
initKeyboard();
initOfficesToggle();
initCandidateCardClick();
initLocationButton();

const earnCta = document.getElementById('btn-signin-cta');
if (earnCta) {
    mountSpecularButton(earnCta, {
        radius: 6,
        lineColor: '#34d399',
        baseColor: '#065f46',
        intensity: 1.3,
        shineSize: 12,
        shineFade: 35,
        thickness: 1.2,
        speed: 0.3,
        followMouse: true,
        proximity: 200,
        autoAnimate: false,
    });
}
bootDeepLink();

/* Hydrate saved boundaries — voters from the DB, guests from their cookie. */
fetchBoundaries().then(renderFavoriteChips);
/* Hydrate followed politicians (voters only — no guest path for this feature). */
if (window.U9?.session?.isVoter?.()) fetchFollowedPoliticianIds();
initDigestOptInPrompt();

/* Wire back button */
document.getElementById('btn-back').addEventListener('click', handleBack);

/* Wire district boundaries button */
document.getElementById('btn-districts').addEventListener('click', toggleNationalBoundaries);

/* Make __mapBack_handler available for deep-link breadcrumb */
window.__mapBack_handler = handleBack;
window.__mapRegions = REGIONS;

/* Expose toggleKbHelp for the inline onclick on the kb-tour-btn (Replay tour) */
window.toggleKbHelp = toggleKbHelp;

/* Expose national-boundaries sync for mode-transitions */
window.__syncNatDistVisibility = _syncNatDistVisibility;

/* Start render loop */
animate();
window.addEventListener('resize', resizeRenderer);