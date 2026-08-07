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
export let colorMode = 'region';     // 'region' | 'party' | 'poverty'
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

/** Governor party by state abbreviation — populated by ensureStateOverlays() */
export let govPartyByAbbr = {};

/** Poverty rate (%) by state abbreviation — populated by ensureStateOverlays() */
export let povertyRateByAbbr = {};

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

/**
 * Saved boundaries (congressional districts + top cities) for the logged-in
 * voter. Hydrated at boot from /voter/boundaries when U9.session.isVoter().
 * Keyed by a stable composite key; values are normalized records:
 *   { id, type, stateAbbr, districtNum, cityName, label, lat, lng }
 */
export const favoriteBoundaries = new Map();

export function favoriteKey({ type, stateAbbr, districtNum, cityName }) {
    return [type, (stateAbbr || '').toUpperCase(), districtNum || '', cityName || ''].join('|');
}

export function setFavorites(list) {
    favoriteBoundaries.clear();
    for (const b of list) addFavorite(b);
}

export function addFavorite(b) {
    favoriteBoundaries.set(favoriteKey(b), b);
}

export function removeFavoriteById(id) {
    for (const [k, b] of favoriteBoundaries) {
        if (b.id === id) { favoriteBoundaries.delete(k); return; }
    }
}

export function isFavorite(parts) {
    return favoriteBoundaries.has(favoriteKey(parts));
}

/**
 * Followed politicians (candidate-follow, distinct from favoriteBoundaries
 * above) for the logged-in voter. Hydrated at boot from /voter/favorites/ids
 * when U9.session.isVoter(). Keyed by plain numeric politician id — no
 * composite key needed since a politician has a single stable id, unlike a
 * boundary.
 */
export const followedPoliticians = new Set();

export function setFollowed(ids) {
    followedPoliticians.clear();
    for (const id of ids) followedPoliticians.add(id);
}

export function addFollowed(id) {
    followedPoliticians.add(id);
}

export function removeFollowed(id) {
    followedPoliticians.delete(id);
}

export function isFollowingPolitician(id) {
    return followedPoliticians.has(id);
}