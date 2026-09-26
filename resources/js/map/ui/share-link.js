/**
 * District share links — building them, sharing them, and keeping the
 * address bar in step with the open district.
 *
 * Every URL here uses the same `?state=&district=&slug=` params that
 * bootDeepLink / window.__mapGoTo (navigation/deep-link.js) read on load, so
 * a shared or copied link lands the recipient back on the same district.
 */
import { trackEvent } from '../api/interaction.js';
import { showToast } from './location-button.js';
import { activeState } from '../state/map-state.js';

const DEEP_LINK_PARAMS = ['state', 'district', 'slug'];

/**
 * Absolute share URL for a district. The viewer's referral code
 * (window.U9.session, hydrated from the `u9-ref-code` meta tag) is attached
 * so a signup from the shared link gets attributed to them.
 */
export function districtShareUrl(stateAbbr, districtNum = null, slug = null) {
    const params = new URLSearchParams();
    params.set('state', stateAbbr);
    if (districtNum) params.set('district', String(districtNum));
    if (slug) params.set('slug', slug);
    const refCode = window.U9?.session?.referralCode;
    if (refCode) params.set('ref', refCode);
    return `${window.location.origin}/map?${params.toString()}`;
}

/** Native share sheet where available, clipboard copy otherwise. */
export async function shareLink(url, label = 'this district') {
    if (!url) return;

    trackEvent('district_share_click', { district_label: label, state: activeState || null });

    const shareData = { title: `${label} on U9itus`, text: `Check out ${label} on U9itus`, url };
    if (navigator.share) {
        try {
            await navigator.share(shareData);
            return;
        } catch (error) {
            if (!error || error.name === 'AbortError') return;
        }
    }

    try {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(url);
            showToast('Share link copied to clipboard', 'info');
        } else {
            window.prompt('Copy this link:', url);
        }
    } catch (error) {
        console.error('[map] shareLink: failed to copy link', error);
    }
}

/** 🔗 button for the district panel header; mirrors the save-boundary star. */
export function createShareButton(url, label) {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'boundary-fav-btn boundary-share-btn';
    btn.title = `Share ${label}`;
    btn.setAttribute('aria-label', `Share ${label}`);
    btn.textContent = '🔗';
    btn.addEventListener('click', e => {
        e.stopPropagation();
        shareLink(url, label);
    });
    return btn;
}

/**
 * Reflect the open district in the address bar (replaceState, so the back
 * button isn't flooded with one entry per district click). Pass no args to
 * strip the deep-link params when the panel closes; unrelated params such
 * as `ref` are left alone.
 */
export function syncDistrictUrl(stateAbbr = null, districtNum = null) {
    if (!window.history?.replaceState) return;
    const url = new URL(window.location.href);
    DEEP_LINK_PARAMS.forEach(p => url.searchParams.delete(p));
    if (stateAbbr) {
        url.searchParams.set('state', stateAbbr);
        if (districtNum) url.searchParams.set('district', String(districtNum));
    }
    if (url.href !== window.location.href) window.history.replaceState(window.history.state, '', url);
}
