/**
 * Star toggle button for saving a map boundary (district or top city).
 *
 * Reflects saved state from map-state and POSTs/DELETEs to /voter/boundaries
 * (voters) or /map/boundaries (guests, cookie-backed — see favorites.js) on
 * click. After a successful change it dispatches `u9:favorites-changed` so
 * the Layers panel can re-render chips without an import cycle back into
 * layers-panel.js.
 */
import { saveBoundary, removeBoundary } from '../api/favorites.js';
import { favoriteBoundaries, favoriteKey, isFavorite } from '../state/map-state.js';
import { trackEvent } from '../api/interaction.js';

function notifyChanged() {
    window.dispatchEvent(new CustomEvent('u9:favorites-changed'));
}

/**
 * `payload` uses the server's snake_case fields:
 *   { type, state_abbr, district_number?, city_name?, label, lat?, lng? }
 */
export function createFavoriteButton(payload) {
    const parts = {
        type:       payload.type,
        stateAbbr:  payload.state_abbr,
        districtNum: payload.district_number ?? null,
        cityName:   payload.city_name ?? null,
    };

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'boundary-fav-btn';

    const sync = () => {
        const saved = isFavorite(parts);
        btn.classList.toggle('saved', saved);
        btn.setAttribute('aria-pressed', String(saved));
        btn.title = saved ? 'Saved — click to remove' : 'Save this boundary';
        btn.innerHTML = saved ? '★' : '☆';
    };
    sync();

    btn.addEventListener('click', async (e) => {
        e.stopPropagation();
        const existing = favoriteBoundaries.get(favoriteKey(parts));
        if (existing) {
            const ok = await removeBoundary(existing.id);
            if (ok) {
                sync();
                notifyChanged();
                trackEvent('boundary_unsave', { meta: { type: payload.type } });
            }
        } else {
            const rec = await saveBoundary(payload);
            if (rec?.error === 'limit_reached') {
                // Guest cookie is full (25 items) — nudge sign-in instead of silently dropping the save.
                document.getElementById('btn-signin-cta')?.click();
                trackEvent('boundary_save_blocked', { meta: { type: payload.type, reason: 'limit_reached' } });
                return;
            }
            if (rec) {
                sync();
                notifyChanged();
                trackEvent('boundary_save', { meta: { type: payload.type, state: payload.state_abbr } });
            }
        }
    });

    return btn;
}