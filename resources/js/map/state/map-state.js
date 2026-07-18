/**
 * Mutable map state shared across modules.
 * Variables are exported as let; setter functions are provided
 * for reassignment from other modules (ES module live bindings
 * allow cross-module reads but not reassignment of let bindings).
 */

export let mapMode = 'overview';     // 'overview' | 'region' | 'state'
export let activeRegion = null;
export let activeState = null;
export let selectedState = null;
export let statePanelRequestId = 0;
export let stateData = null;
export let colorMode = 'region';     // 'region' | 'party'
export let showSmallCities = false;

/** hoveredMesh lives in navigation/mode-transitions.js (local variable exported from there). */
/** hoveredDistrict lives in scene/district-overlay.js. */

/** Setters for mutable state (needed because ES module let exports cannot be reassigned from importers). */
export function setMapMode(v)       { mapMode = v; }
export function setActiveRegion(v)  { activeRegion = v; }
export function setActiveState(v)   { activeState = v; }
export function setSelectedState(v) { selectedState = v; }
export function nextRequestId()     { return ++statePanelRequestId; }
export function setStateData(v)     { stateData = v; }
export function setColorMode(v)     { colorMode = v; }
export function setShowSmallCities(v){ showSmallCities = v; }

/** Active data overlay layers (persisted in localStorage) */
export const ACTIVE_LAYERS = new Set();

/** Governor party by state abbreviation — populated by ensureGovernorParties() */
export let govPartyByAbbr = {};

/** District config — populated by initDistrictConfig().
 *  Fallback values mirror the 119th Congress (safe until the first daily sync). */
export const DISTRICT_CONFIG = {
    congress_number: 119,
    tigerweb_layer: 0,
    cd_field: 'CD119',
    congress_label: '119th Congress (2025–2027)',
    party_map: null,   // null = use the static DISTRICT_PARTY_MAP fallback
};

/** Per-state district cache for TIGERweb GeoJSON */
export const districtCache = {};

/** Per-state city boundary GeoJSON cache */
export const cityBoundaryCache = {};