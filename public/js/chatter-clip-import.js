import { publicSourceUrl } from './chatter-source-url.js';

const draftKey = 'u9itus.chatter.clip.v1';
const maxAge = 30 * 60 * 1000;

export function validatedClip(value) {
    if (!value || value.version !== 1 || !publicSourceUrl(value.source_url)) throw new Error('Invalid source link.');
    if (typeof value.headline !== 'string' || !value.headline.trim() || value.headline.length > 240) throw new Error('Invalid headline.');
    if (typeof value.source_excerpt !== 'string' || value.source_excerpt.length > 2000) throw new Error('Invalid excerpt.');
    // Never accept identity, candidate selection, consent, moderation or publication fields.
    return { version: 1, source_url: value.source_url.trim(), headline: value.headline.trim(), source_excerpt: value.source_excerpt };
}

function platformFor(source) {
    const host = new URL(source).hostname.toLowerCase();
    const platforms = { 'x.com': 'x', 'twitter.com': 'x', 'instagram.com': 'instagram', 'substack.com': 'substack', 'tiktok.com': 'tiktok', 'youtube.com': 'youtube', 'youtu.be': 'youtube', 'facebook.com': 'facebook' };
    return Object.entries(platforms).find(([domain]) => host === domain || host.endsWith('.' + domain))?.[1] ?? 'news';
}

const handoff = document.querySelector('[data-clip-handoff]');
if (handoff) {
    const fragment = window.location.hash;
    // Remove clipping data before navigating, without sending it to the server.
    window.history.replaceState(null, '', window.location.pathname);
    try {
        sessionStorage.removeItem(draftKey);
        if (!fragment.startsWith('#clip=') || fragment.length > 32000) throw new Error('Missing or oversized clipping.');
        const clip = validatedClip(JSON.parse(decodeURIComponent(fragment.slice(6))));
        sessionStorage.setItem(draftKey, JSON.stringify({ clip, createdAt: Date.now() }));
        // This fixed, first-party destination enforces authentication, 2FA and contributor access.
        window.location.replace('/contribute/chatter');
    } catch {
        handoff.textContent = 'We could not open this clipping. Please try the extension again, or use the submission form to paste the public source link. Browser session storage must be enabled.';
    }
}

const form = document.querySelector('[data-chatter-clip-form]');
if (form) {
    const notice = document.querySelector('[data-clip-notice]');
    const notify = text => { notice.textContent = text; notice.hidden = false; };
    try {
        const raw = sessionStorage.getItem(draftKey);
        sessionStorage.removeItem(draftKey);
        if (raw && form.dataset.restoreClip === 'true') {
            if (raw.length > 16000) throw new Error('Oversized draft.');
            const saved = JSON.parse(raw);
            if (!Number.isFinite(saved.createdAt) || Date.now() - saved.createdAt > maxAge || saved.createdAt > Date.now() + 60000) {
                notify('Your clipping expired. Open the extension again to capture a fresh copy.');
            } else {
                const clip = validatedClip(saved.clip);
                for (const key of ['source_url', 'headline', 'source_excerpt']) form.elements.namedItem(key).value = clip[key];
                form.elements.namedItem('platform').value = platformFor(clip.source_url);
                notify('Clipping imported. Choose a politician, check the headline and excerpt, and explain why this source is relevant. Nothing has been submitted yet.');
            }
        }
    } catch {
        notify('The clipping could not be restored. You can paste the source link below to continue.');
    }
}
