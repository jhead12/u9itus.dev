/**
 * Candidate popup — quick-view card for a candidate.
 */
import { partyClass } from './panel-state.js';

const candPopup = document.getElementById('cand-popup');

/**
 * Open the candidate quick-view popup near the anchor element.
 * @param {Object} c           Candidate data object.
 * @param {string} color       Accent color (party hex).
 * @param {Element} anchorEl   The clicked DOM element to position near.
 */
export function openCandidatePopup(c, color, anchorEl) {
    color = color || '#6366f1';

    // Avatar
    const avWrap = document.getElementById('popup-avatar-wrap');
    if (c.photo) {
        const ph48 = window.avatarInitials(c.full_name, color, 48).replace(/'/g, '&apos;').replace(/"/g, '&quot;');
        avWrap.innerHTML = `<img class="popup-avatar" src="${c.photo}" alt="${c.full_name}"
            onerror="this.outerHTML='<div class=&quot;popup-avatar-ph&quot;>${ph48}</div>'">`;
    } else {
        avWrap.innerHTML = `<div class="popup-avatar-ph">${window.avatarInitials(c.full_name, color, 48)}</div>`;
    }

    document.getElementById('popup-name').textContent    = c.full_name;
    document.getElementById('popup-office').textContent  = c.office || c.party || '';
    document.getElementById('popup-bio').textContent     = c.bio || 'No biography available.';
    document.getElementById('popup-raised').textContent   = c.raised || '—';
    document.getElementById('popup-status').textContent   = c.status === 'seated' ? '● Seated' : '● Running';
    document.getElementById('popup-status').style.color    = c.status === 'seated' ? '#818cf8' : '#34d399';

    const partyShort = { Democratic:'Dem', Republican:'Rep', Libertarian:'Lib', Green:'Grn', Independent:'Ind' };
    document.getElementById('popup-party-badge').textContent = partyShort[c.party] || c.party?.slice(0,4) || '—';
    document.getElementById('popup-party-badge').className    = `popup-stat-val party-pill ${partyClass(c.party)}`;
    document.getElementById('popup-party-badge').style.cssText  = 'display:block;font-size:12px;font-weight:700;padding:2px 0;border:none;background:none;';

    const stanceEl = document.getElementById('popup-stance');
    if (c.stance_topic) {
        stanceEl.innerHTML = `<strong>${c.stance_topic}:</strong> ${c.stance_text}`;
        stanceEl.style.display = '';
    } else {
        stanceEl.style.display = 'none';
    }

    // CTAs
    const campLink = document.getElementById('popup-campaign-link');
    campLink.href = c.profile_url || '#';
    campLink.style.background = `linear-gradient(135deg, ${color}, ${color}cc)`;
    campLink.style.opacity = c.profile_url ? '1' : '0.45';
    campLink.style.pointerEvents = c.profile_url ? '' : 'none';

    const bpLink = document.getElementById('popup-bp-link');
    bpLink.href  = c.ballotpedia_url || '#';
    bpLink.style.color       = color;
    bpLink.style.borderColor = color + '55';
    bpLink.style.opacity     = c.ballotpedia_url ? '1' : '0.4';
    bpLink.style.pointerEvents = c.ballotpedia_url ? '' : 'none';

    // Position popup near the anchor element
    candPopup.style.display = 'block';
    candPopup.classList.add('visible');
    const panelRect  = document.getElementById('info-panel').getBoundingClientRect();
    const anchorRect = anchorEl.getBoundingClientRect();
    const popW = 320, popH = candPopup.offsetHeight || 360;
    let top  = anchorRect.top - 10;
    let left = panelRect.left - popW - 12;
    if (left < 8) left = panelRect.right + 12;
    if (top + popH > window.innerHeight - 8) top = window.innerHeight - popH - 8;
    if (top < 60) top = 60;
    candPopup.style.top  = top + 'px';
    candPopup.style.left = left + 'px';
}

/**
 * Close the candidate popup.
 */
export function closePopup() {
    candPopup.classList.remove('visible');
    candPopup.style.display = 'none';
}

/**
 * Set up popup event listeners (close button, Escape, outside click).
 */
export function initPopup() {
    document.getElementById('cand-popup-close')?.addEventListener('click', closePopup);
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closePopup(); });
    document.addEventListener('click', e => {
        if (candPopup.classList.contains('visible') && !candPopup.contains(e.target) && !e.target.closest('.candidate-name')) closePopup();
    });
}

// avatarInitials is defined globally in the Blade template
/* global avatarInitials */