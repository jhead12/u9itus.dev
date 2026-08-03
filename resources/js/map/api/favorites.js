/**
 * Saved-boundaries API — fetch / save / remove a pinned map boundary
 * (congressional district or top city), for both voters and guests.
 *
 * Voters hit the role:voter-gated /voter/boundaries routes; guests hit the
 * cookie-backed /map/boundaries routes (GuestBoundaryFavoriteController) —
 * same JSON shape either way, so callers don't need to care which. Reuses
 * the csrf-token meta emitted by us-map.blade.php when
 * MAP_VOTER_FEATURES_ENABLED is on (now rendered for guests too).
 */
import { addFavorite, removeFavoriteById, setFavorites } from '../state/map-state.js';

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
const jsonHeaders = () => ({
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    'X-CSRF-TOKEN': csrf(),
});

const boundariesEndpoint = () => (window.U9?.session?.isVoter?.() ? '/voter/boundaries' : '/map/boundaries');
const canCall = () => !!csrf();

/** Normalize a server record (snake_case) to the in-memory shape (camelCase). */
function normalize(b) {
    return {
        id:         b.id,
        type:       b.type,
        stateAbbr:  b.state_abbr,
        districtNum: b.district_number ?? null,
        cityName:   b.city_name ?? null,
        label:      b.label,
        lat:        b.lat ?? null,
        lng:        b.lng ?? null,
    };
}

/**
 * Load the voter's saved boundaries into map-state. Returns the list (empty
 * for guests / on failure so callers can render the empty state).
 */
export async function fetchBoundaries() {
    if (!canCall()) return [];
    try {
        const res = await fetch(boundariesEndpoint(), { headers: jsonHeaders() });
        if (!res.ok) return [];
        const data = await res.json();
        const list = (data.boundaries || []).map(normalize);
        setFavorites(list);
        return list;
    } catch {
        return [];
    }
}

/**
 * Save a boundary. `payload` uses the server's snake_case field names
 * ({ type, state_abbr, district_number?, city_name?, label, lat?, lng? }).
 * Returns the normalized record on success, `{ error }` on a known failure
 * (e.g. the guest 25-item cookie cap), or null on any other failure.
 */
export async function saveBoundary(payload) {
    if (!canCall()) return null;
    try {
        const res = await fetch(boundariesEndpoint(), {
            method: 'POST',
            headers: jsonHeaders(),
            body: JSON.stringify(payload),
        });
        const data = await res.json().catch(() => null);
        if (!res.ok || !data?.ok) return data?.error ? { error: data.error } : null;
        const rec = normalize({
            id:              data.id,
            type:            payload.type,
            state_abbr:      payload.state_abbr,
            district_number: payload.district_number ?? null,
            city_name:       payload.city_name ?? null,
            label:           payload.label,
            lat:             payload.lat ?? null,
            lng:             payload.lng ?? null,
        });
        addFavorite(rec);
        return rec;
    } catch {
        return null;
    }
}

/** Remove a saved boundary by id. Returns true on success. */
export async function removeBoundary(id) {
    if (!canCall() || !id) return false;
    try {
        const res = await fetch(`${boundariesEndpoint()}/${id}`, {
            method: 'DELETE',
            headers: jsonHeaders(),
        });
        if (!res.ok) return false;
        removeFavoriteById(id);
        return true;
    } catch {
        return false;
    }
}

/**
 * Opt into the weekly saved-places digest. Voters just flip their existing
 * notification preference (no email needed). Guests must supply an email —
 * a confirmation link is sent, no digest content goes out until it's clicked.
 * Returns the parsed response body ({ ok, status }) or null on failure.
 */
export async function optInToDigest(email) {
    try {
        const res = await fetch('/map/boundaries-digest-optin', {
            method: 'POST',
            headers: jsonHeaders(),
            body: JSON.stringify(email ? { email } : {}),
        });
        const data = await res.json().catch(() => null);
        return res.ok && data?.ok ? data : null;
    } catch {
        return null;
    }
}