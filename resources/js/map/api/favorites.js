/**
 * Saved-boundaries API — fetch / save / remove a voter's pinned map
 * boundaries (congressional districts + top cities).
 *
 * All calls no-op for non-voters (the /voter/boundaries routes are
 * role:voter-gated server-side). Reuses the csrf-token meta emitted by
 * us-map.blade.php when MAP_VOTER_FEATURES_ENABLED is on.
 */
import { addFavorite, removeFavoriteById, setFavorites } from '../state/map-state.js';

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
const jsonHeaders = () => ({
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    'X-CSRF-TOKEN': csrf(),
});

const canCall = () => !!window.U9?.session?.isVoter?.();

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
        const res = await fetch('/voter/boundaries', { headers: jsonHeaders() });
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
 * Returns the normalized record on success, else null.
 */
export async function saveBoundary(payload) {
    if (!canCall()) return null;
    try {
        const res = await fetch('/voter/boundaries', {
            method: 'POST',
            headers: jsonHeaders(),
            body: JSON.stringify(payload),
        });
        if (!res.ok) return null;
        const data = await res.json();
        if (!data.ok) return null;
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
        const res = await fetch(`/voter/boundaries/${id}`, {
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