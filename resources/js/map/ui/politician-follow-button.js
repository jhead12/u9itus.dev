/**
 * Star toggle button for following an individual politician (candidate),
 * shown in the map's candidate drawer. Companion to boundary-favorite.js's
 * createFavoriteButton(), which saves a district/city boundary instead — the
 * two are separate features (voter_favorite_politicians vs
 * voter_favorite_boundaries) and this one has no guest-cookie fallback, so
 * guests are sent to the sign-in nudge instead of hitting the API.
 */
import { followPolitician, unfollowPolitician } from '../api/politician-follow.js';
import { isFollowingPolitician } from '../state/map-state.js';
import { trackEvent } from '../api/interaction.js';

const isVoter = () => !!window.U9?.session?.isVoter?.();

export function createFollowButton(politicianId) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'boundary-fav-btn';

    const sync = () => {
        const followed = isVoter() && isFollowingPolitician(politicianId);
        btn.classList.toggle('saved', followed);
        btn.setAttribute('aria-pressed', String(followed));
        btn.title = followed ? 'Following — click to unfollow' : 'Follow this candidate';
        btn.innerHTML = followed ? '★' : '☆';
    };
    sync();

    btn.addEventListener('click', async (e) => {
        e.stopPropagation();

        if (!isVoter()) {
            document.getElementById('btn-signin-cta')?.click();
            trackEvent('politician_follow_blocked', { meta: { politician_id: politicianId, reason: 'guest' } });
            return;
        }

        if (isFollowingPolitician(politicianId)) {
            const ok = await unfollowPolitician(politicianId);
            if (ok) {
                sync();
                trackEvent('politician_unfollow', { meta: { politician_id: politicianId } });
            }
        } else {
            const ok = await followPolitician(politicianId);
            if (ok) {
                sync();
                trackEvent('politician_follow', { meta: { politician_id: politicianId } });
            }
        }
    });

    return btn;
}
