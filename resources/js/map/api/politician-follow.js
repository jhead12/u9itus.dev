/**
 * Candidate-follow API — follow/unfollow an individual politician.
 *
 * Distinct from favorites.js (which saves a district/city boundary): this
 * hits the voter-only /voter/favorites/{politicianId} endpoints
 * (FavoriteController). There is no guest-cookie path for this feature, so
 * callers must check window.U9.session.isVoter() before using it.
 */
import { setFollowed, addFollowed, removeFollowed } from '../state/map-state.js';

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
const jsonHeaders = () => ({
    'Accept': 'application/json',
    'Content-Type': 'application/json',
    'X-CSRF-TOKEN': csrf(),
});

/** Hydrate followedPoliticians from the server. Voters only. */
export async function fetchFollowedPoliticianIds() {
    try {
        const res = await fetch('/voter/favorites/ids', { headers: jsonHeaders() });
        if (!res.ok) return;
        const data = await res.json();
        setFollowed(data.ids || []);
    } catch {}
}

/** Follow a politician. Returns true on success. */
export async function followPolitician(id) {
    if (!id) return false;
    try {
        const res = await fetch(`/voter/favorites/${id}`, {
            method: 'POST',
            headers: jsonHeaders(),
        });
        if (!res.ok) return false;
        addFollowed(id);
        return true;
    } catch {
        return false;
    }
}

/** Unfollow a politician. Returns true on success. */
export async function unfollowPolitician(id) {
    if (!id) return false;
    try {
        const res = await fetch(`/voter/favorites/${id}`, {
            method: 'DELETE',
            headers: jsonHeaders(),
        });
        if (!res.ok) return false;
        removeFollowed(id);
        return true;
    } catch {
        return false;
    }
}
