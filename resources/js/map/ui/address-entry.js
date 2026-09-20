/**
 * "Find your district" card — the map's starting point.
 *
 * A visitor can type a street address or ZIP code, use their device location
 * as the secondary option, or just close the card and browse. A full address
 * (or the device location) pins one district; a ZIP can span several, so the
 * card asks the visitor to choose rather than guessing.
 *
 * The typed text goes to /api/v1/map/geocode and nowhere else: it is never
 * put in analytics events, storage or the URL.
 */
import { trackEvent } from '../api/interaction.js';
import { findMyDistrict, goToDistrict, showToast } from './location-button.js';

let card;
let input;
let statusEl;
let choicesEl;
let submitBtn;
let opener;
let busy = false;

const STATUS_CLASSES = ['fd-info', 'fd-error'];

function setStatus(message, kind = 'info') {
    statusEl.textContent = message;
    statusEl.classList.remove(...STATUS_CLASSES);
    if (message) statusEl.classList.add(kind === 'error' ? 'fd-error' : 'fd-info');
}

function clearChoices() {
    choicesEl.replaceChildren();
    choicesEl.hidden = true;
}

function showChoices(candidates) {
    choicesEl.replaceChildren(...candidates.map((c) => {
        const li = document.createElement('li');
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'fd-choice';
        btn.textContent = `${c.district_code}${c.district_label ? ` — ${c.district_label}` : ''}`;
        btn.addEventListener('click', () => pick(c, 'zip'));
        li.appendChild(btn);
        return li;
    }));
    choicesEl.hidden = false;
    choicesEl.querySelector('button')?.focus();
}

export function isFindDistrictOpen() {
    return !!card && !card.hidden;
}

export function closeFindDistrict({ restoreFocus = true } = {}) {
    if (!card || card.hidden) return;
    card.hidden = true;
    opener?.setAttribute('aria-expanded', 'false');
    if (restoreFocus) opener?.focus();
}

export function openFindDistrict() {
    if (!card) return;
    card.hidden = false;
    opener?.setAttribute('aria-expanded', 'true');
    setStatus('');
    clearChoices();
    input.focus();
    input.select();
    trackEvent('find_district_card_open', {});
}

function toggleFindDistrict() {
    if (isFindDistrictOpen()) closeFindDistrict();
    else openFindDistrict();
}

/** Send the visitor to a resolved district and say how precisely we found it. */
async function pick(district, precision, matchedAddress = null) {
    closeFindDistrict({ restoreFocus: false });
    const label = district.district_label || district.district_code || 'your district';
    const how = {
        address: matchedAddress ? `Showing ${label} for ${matchedAddress}.` : `Showing ${label} for that address.`,
        location: `Showing ${label} for your location.`,
        zip: `Showing ${label}. Add your street address to confirm it’s the right one.`,
    }[precision] ?? `Showing ${label}.`;
    showToast(how, 'info');
    trackEvent('find_district_success', { state: district.state, district: district.district_code, meta: { precision } });
    await goToDistrict(district);
}

async function submit(event) {
    event.preventDefault();
    if (busy) return;

    const value = input.value.trim();
    clearChoices();

    if (!value) {
        setStatus('Enter a street address or a 5-digit ZIP code.', 'error');
        input.focus();
        return;
    }

    busy = true;
    submitBtn.disabled = true;
    setStatus('Looking up your district…');

    try {
        const res = await fetch(`/api/v1/map/geocode?address=${encodeURIComponent(value)}`, {
            headers: { Accept: 'application/json' },
        });
        const data = await res.json().catch(() => ({}));

        if (res.status === 429) {
            setStatus('Too many lookups in a short time. Wait a minute and try again.', 'error');
        } else if (!res.ok || !data.ok) {
            setStatus(data.error || 'We couldn’t look that up right now. Try again in a moment.', 'error');
            trackEvent('find_district_error', { meta: { status: res.status, needs_address: !!data.needs_address } });
        } else if (data.ambiguous) {
            setStatus(data.message, 'info');
            showChoices(data.candidates || []);
            trackEvent('find_district_ambiguous', { meta: { choices: (data.candidates || []).length } });
        } else {
            await pick(data, data.precision, data.matched_address);
        }
    } catch {
        setStatus('We couldn’t reach the lookup service. Check your connection and try again.', 'error');
    } finally {
        busy = false;
        submitBtn.disabled = false;
    }
}

export function initAddressEntry() {
    card = document.getElementById('find-district-card');
    opener = document.getElementById('btn-find-district');
    if (!card || !opener) return;

    input = card.querySelector('#fd-input');
    statusEl = card.querySelector('#fd-status');
    choicesEl = card.querySelector('#fd-choices');
    submitBtn = card.querySelector('#fd-submit');

    opener.addEventListener('click', (e) => {
        e.stopPropagation();
        toggleFindDistrict();
    });
    document.getElementById('hint-find-district')?.addEventListener('click', openFindDistrict);
    card.querySelector('#fd-form').addEventListener('submit', submit);
    card.querySelector('#fd-close').addEventListener('click', () => closeFindDistrict());
    card.querySelector('#fd-browse').addEventListener('click', () => closeFindDistrict());
    card.querySelector('#fd-locate').addEventListener('click', () => {
        closeFindDistrict({ restoreFocus: false });
        findMyDistrict();
    });

    card.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            e.stopPropagation();
            closeFindDistrict();
        }
    });

    // Clicking the map (or anywhere outside the card) puts it away, so it
    // never sits on top of what the visitor is trying to explore.
    document.addEventListener('pointerdown', (e) => {
        if (!isFindDistrictOpen()) return;
        if (card.contains(e.target) || opener.contains(e.target)) return;
        closeFindDistrict({ restoreFocus: false });
    });
}
