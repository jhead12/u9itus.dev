/**
 * One lightweight first-visit hint instead of an auto-launching tour.
 * Dismissed by the close button, by selecting a state, or after a short time,
 * and never shown again once dismissed. The full tour stays available from
 * Help (see tour.js).
 */
const HINT_KEY = 'u9_map_first_hint_v1';
const AUTO_HIDE_MS = 12000;

function seen() {
    try { return localStorage.getItem(HINT_KEY) === '1'; } catch { return false; }
}

function remember() {
    try { localStorage.setItem(HINT_KEY, '1'); } catch { /* storage unavailable */ }
}

export function initFirstHint() {
    const el = document.getElementById('map-first-hint');
    if (!el || seen()) return;

    let timer = null;
    const dismiss = () => {
        clearTimeout(timer);
        el.classList.remove('visible');
        remember();
        document.removeEventListener('u9:state-selected', dismiss);
    };

    // Wait for the loading screen to clear so the hint isn't hidden behind it.
    setTimeout(() => {
        el.classList.add('visible');
        timer = setTimeout(dismiss, AUTO_HIDE_MS);
    }, 1200);

    el.querySelector('[data-hint-close]')?.addEventListener('click', dismiss);
    document.addEventListener('u9:state-selected', dismiss);
}
