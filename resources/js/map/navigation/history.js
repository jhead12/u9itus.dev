/**
 * Map history — one browser history entry per map level, so the phone's back
 * gesture (and the browser Back button) steps district → state → region →
 * overview instead of leaving the page.
 *
 * Each level records itself with recordLocation(); this module owns the
 * address bar (?region=, ?state=, ?district=) and replays entries on popstate.
 */
import { STATE_ABBR_MAP, REGIONS } from '../config/constants.js';

const PARAMS = ['region', 'state', 'district', 'slug'];
const ABBR_TO_NAME = Object.fromEntries(Object.entries(STATE_ABBR_MAP).map(([name, abbr]) => [abbr, name]));

/** The history entry being replayed: records on the way to it replace rather than push. */
let restoring = null;
let navigate = null;
/** Opening a shared link: its steps rewrite the one entry it arrived on, until it reaches this place. */
let booting = null;

/** @typedef {{ level: 'overview'|'region'|'state'|'district', region?: string, state?: string, district?: string }} MapLocation */

function sameLocation(a, b) {
    return !!a && !!b && a.level === b.level && (a.region || null) === (b.region || null)
        && (a.state || null) === (b.state || null) && (a.district || null) === (b.district || null);
}

/** True while replaying `restoring` and `loc` is it (or the state a district replay passes through). */
function isReplayStep(loc) {
    if (!restoring) return false;
    if (sameLocation(loc, restoring)) return true;
    return restoring.level === 'district' && loc.level === 'state' && loc.state === restoring.state;
}

function urlFor(loc) {
    const url = new URL(window.location.href);
    PARAMS.forEach(p => url.searchParams.delete(p));
    if (loc.level === 'region') url.searchParams.set('region', loc.region);
    if (loc.level === 'state' || loc.level === 'district') {
        url.searchParams.set('state', STATE_ABBR_MAP[loc.state] || loc.state);
        if (loc.level === 'district') url.searchParams.set('district', String(loc.district));
    }
    return url;
}

/** Where the address bar points on first load (overview when it names nothing we know). */
export function locationFromUrl(search = window.location.search) {
    const params = new URLSearchParams(search);
    const abbr = params.get('state');
    const state = abbr && (abbr.length === 2 ? ABBR_TO_NAME[abbr.toUpperCase()] : abbr);
    if (state) {
        const district = params.get('district');
        return district ? { level: 'district', state, district } : { level: 'state', state };
    }
    const region = params.get('region');
    if (region && REGIONS[region]) return { level: 'region', region };
    return { level: 'overview' };
}

/** Page title for a location, so the tab and screen readers name the current place. */
function titleFor(loc) {
    const base = 'U.S. Regional Map – U9itus';
    if (loc.level === 'region') return `${loc.region} Region – ${base}`;
    if (loc.level === 'state') return `${loc.state} – ${base}`;
    if (loc.level === 'district') {
        const d = String(loc.district).toUpperCase() === 'AL' ? 'At-Large District' : `District ${loc.district}`;
        return `${loc.state} ${d} – ${base}`;
    }
    return base;
}

/**
 * Note that the map is now showing `loc`. A new place gets its own history
 * entry; re-rendering the same place, replaying an entry, or opening a shared
 * link only rewrite the current one.
 * @param {MapLocation} loc
 */
export function recordLocation(loc) {
    if (!window.history?.pushState) return;
    const current = window.history.state?.map;
    const entry = { ...window.history.state, map: loc };
    const url = urlFor(loc);
    // A shared link's server-rendered title already names its place; keep it until the user moves.
    if (!booting) document.title = titleFor(loc);
    if (booting || isReplayStep(loc) || sameLocation(current, loc)) {
        window.history.replaceState(entry, '', url);
    } else {
        window.history.pushState(entry, '', url);
    }
    // The URL names no region, so the shared link's target is matched without it.
    if (booting && sameLocation({ ...loc, region: null }, booting)) booting = null;
}

/**
 * @param {(loc: MapLocation) => Promise<void>|void} goTo  shows a location on the map
 */
export function initMapHistory(goTo) {
    navigate = goTo;
    const initial = locationFromUrl();
    booting = initial.level === 'overview' ? null : initial;
    window.history.replaceState({ ...window.history.state, map: initial }, '');

    window.addEventListener('popstate', async (e) => {
        const loc = e.state?.map;
        if (!loc || !navigate) return;
        restoring = loc;
        try {
            await navigate(loc);
        } finally {
            if (restoring === loc) restoring = null;
        }
    });
}

/** A shared link has finished opening (or given up): later moves get their own entries. */
export function finishBoot() {
    booting = null;
}
