/**
 * Guided tour — step-by-step onboarding overlay.
 */
import { TOUR_STEPS, TOUR_KEY } from '../config/tour-steps.js';

let _tourStep = 0;

/**
 * Start the guided tour. Skips if already completed (unless forced).
 */
export function startTutorial(force = false) {
    if (!force && localStorage.getItem(TOUR_KEY)) return;
    _tourStep = 0;
    document.getElementById('tutorial-overlay').classList.add('active');
    _renderTourStep(0);
}

function _tourNext() {
    _tourStep = Math.min(_tourStep + 1, TOUR_STEPS.length - 1);
    _renderTourStep(_tourStep);
}

function _tourBack() {
    _tourStep = Math.max(_tourStep - 1, 0);
    _renderTourStep(_tourStep);
}

function _tourEnd() {
    document.getElementById('tutorial-overlay').classList.remove('active');
    document.getElementById('tutorial-highlight').classList.add('hidden');
    try { localStorage.setItem(TOUR_KEY, '1'); } catch {}
}

/**
 * Build optional video/audio instructions block for a tour step.
 */
function _renderTourMedia(media) {
    if (!media) return '';
    const escAttr = (s) => String(s).replace(/"/g, '&quot;');
    const hasVideo = Array.isArray(media.video) && media.video.length;
    const hasAudio = Array.isArray(media.audio) && media.audio.length;
    if (!hasVideo && !hasAudio && !media.transcript) return '';

    const tracks = (media.captions || []).map(c =>
        `<track kind="captions" src="${escAttr(c.src)}" srclang="${escAttr(c.srclang || 'en')}" label="${escAttr(c.label || 'Captions')}"${c.default ? ' default' : ''}>`
    ).join('');

    let player = '';
    if (hasVideo) {
        const sources = media.video.map(s =>
            `<source src="${escAttr(s.src)}" type="${escAttr(s.type)}">`
        ).join('');
        player = `<video controls preload="metadata" playsinline
                    ${media.poster ? `poster="${escAttr(media.poster)}"` : ''}
                    aria-label="Tour instructions video">
                    ${sources}${tracks}
                    Your browser does not support embedded video.
                  </video>`;
    } else if (hasAudio) {
        const sources = media.audio.map(s =>
            `<source src="${escAttr(s.src)}" type="${escAttr(s.type)}">`
        ).join('');
        player = `<audio controls preload="metadata" aria-label="Tour instructions audio">
                    ${sources}
                    Your browser does not support embedded audio.
                  </audio>`;
    }

    const transcriptId = `t-transcript-${Math.random().toString(36).slice(2, 8)}`;
    const transcriptBlock = media.transcript
        ? `<button type="button" class="t-media-toggle"
                aria-expanded="false" aria-controls="${transcriptId}"
                onclick="(function(b){const p=document.getElementById('${transcriptId}');const o=p.hidden;p.hidden=!o;b.setAttribute('aria-expanded',String(o));b.firstElementChild.textContent=o?'Hide transcript':'Show transcript';})(this)">
              <span>Show transcript</span>
           </button>
           <div id="${transcriptId}" class="t-media-fallback" hidden>
              <strong>Transcript:</strong> ${media.transcript}
           </div>`
        : '';

    return `<div class="t-media-wrap" data-media>
                ${player}
            </div>
            ${transcriptBlock}`;
}

function _renderTourStep(idx) {
    const step   = TOUR_STEPS[idx];
    const total  = TOUR_STEPS.length;
    const card   = document.getElementById('tutorial-card');
    const hl     = document.getElementById('tutorial-highlight');
    const isLast = !!step.isLast;

    const dots = TOUR_STEPS.map((_, i) =>
        `<span class="t-dot${i === idx ? ' active' : ''}"></span>`
    ).join('');

    const mediaHtml = _renderTourMedia(step.media);
    card.classList.toggle('has-media', !!mediaHtml);

    card.innerHTML = `
        <p class="t-label">Step ${idx + 1} of ${total}</p>
        <p class="t-title">${step.title}</p>
        ${mediaHtml}
        <div class="t-body">${step.body}</div>
        <div class="t-actions">
            <div class="t-dots">${dots}</div>
            <div class="t-btns">
                ${idx > 0 ? `<button class="t-btn-back" onclick="window._tourBack()">← Back</button>` : ''}
                ${isLast
                    ? `<button class="t-btn-next" onclick="window._tourEnd()">Finish 🎉</button>`
                    : `<button class="t-btn-skip" onclick="window._tourEnd()">Skip</button>
                       <button class="t-btn-next" onclick="window._tourNext()">Next →</button>`}
            </div>
        </div>`;

    // Handle media source errors
    const mediaEl = card.querySelector('video, audio');
    if (mediaEl) {
        let failed = 0;
        const sources = mediaEl.querySelectorAll('source');
        sources.forEach(s => s.addEventListener('error', () => {
            if (++failed >= sources.length) {
                const wrap = card.querySelector('[data-media]');
                if (wrap) wrap.style.display = 'none';
            }
        }));
    }

    // Position highlight and card
    const target = step.target ? document.querySelector(step.target) : null;
    if (target) {
        const r = target.getBoundingClientRect(), pad = 6;
        hl.style.top    = `${r.top    - pad}px`;
        hl.style.left   = `${r.left   - pad}px`;
        hl.style.width  = `${r.width  + pad * 2}px`;
        hl.style.height = `${r.height + pad * 2}px`;
        hl.classList.remove('hidden');
        card.style.cssText = 'top:-9999px;left:-9999px;transform:none';
        requestAnimationFrame(() => _posCard(card, r, step.pos));
    } else {
        hl.classList.add('hidden');
        if (window.matchMedia('(max-width: 640px)').matches) {
            card.style.cssText = '';
        } else {
            card.style.cssText = 'top:50%;left:50%;transform:translate(-50%,-50%)';
        }
    }
}

/**
 * Position the tour card next to its target rect, clamping to the viewport.
 */
function _posCard(card, rect, side) {
    if (window.matchMedia('(max-width: 640px)').matches) {
        card.style.cssText = '';
        return;
    }
    const GAP = 16, MARGIN = 12;
    const vw = window.innerWidth, vh = window.innerHeight;
    const cr = card.getBoundingClientRect();
    const CW = cr.width  || 320;
    const CH = cr.height || 220;

    const fits = (s) => {
        switch (s) {
            case 'bottom': return rect.bottom + GAP + CH <= vh - MARGIN;
            case 'top':    return rect.top    - GAP - CH >= MARGIN;
            case 'left':   return rect.left   - GAP - CW >= MARGIN;
            case 'right':  return rect.right  + GAP + CW <= vw - MARGIN;
        }
        return true;
    };
    const opposite = { bottom: 'top', top: 'bottom', left: 'right', right: 'left' };
    if (side && opposite[side] && !fits(side) && fits(opposite[side])) {
        side = opposite[side];
    }

    let top, left;
    switch (side) {
        case 'bottom': top = rect.bottom + GAP;             left = rect.left + rect.width / 2 - CW / 2; break;
        case 'top':    top = rect.top - CH - GAP;           left = rect.left + rect.width / 2 - CW / 2; break;
        case 'left':   top = rect.top + rect.height / 2 - CH / 2; left = rect.left - CW - GAP;          break;
        default:       top = rect.top + rect.height / 2 - CH / 2; left = rect.right + GAP;              break;
    }
    top  = Math.max(MARGIN, Math.min(vh - CH - MARGIN, top));
    left = Math.max(MARGIN, Math.min(vw - CW - MARGIN, left));
    card.style.cssText = `top:${top}px;left:${left}px;transform:none`;
}

/**
 * Set up tour event listeners.
 */
export function initTour() {
    window.startTutorial = startTutorial;
    window._tourNext = _tourNext;
    window._tourBack = _tourBack;
    window._tourEnd = _tourEnd;

    // Dismiss on Escape
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && document.getElementById('tutorial-overlay').classList.contains('active')) {
            _tourEnd();
        }
    });

    // Auto-launch on first visit after map finishes loading
    setTimeout(() => startTutorial(), 2000);
}