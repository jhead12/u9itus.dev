/**
 * Provenance strip + "Report a data problem" form for a candidate card.
 *
 * Shows where the row came from and how fresh it is ("Source: Ballotpedia ·
 * updated 3 days ago"), and lets a visitor flag what looks wrong. Reports go to
 * POST /api/v1/data-reports and are reviewed by an admin — nothing is changed
 * automatically. Built with DOM APIs (textContent) because names come from
 * scraped data.
 */
import { formatCalendarDate, parseCalendarDate } from '../utils/dates.js';

// Keep in sync with App\Models\DataReport::PROBLEMS.
const PROBLEMS = [
    ['wrong_name', 'Name is wrong or misspelled'],
    ['not_a_person', 'Not a real candidate'],
    ['duplicate', 'Listed more than once'],
    ['wrong_party', 'Wrong party'],
    ['wrong_office', 'Wrong office or district'],
    ['wrong_dates', 'Wrong election or term dates'],
    ['outdated', 'Out of date (no longer running / in office)'],
    ['other', 'Something else'],
];

function el(tag, attrs = {}, text) {
    const node = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs)) {
        if (v === false || v == null) continue;
        if (k === 'class') node.className = v;
        else node.setAttribute(k, v === true ? '' : v);
    }
    if (text != null) node.textContent = text;
    return node;
}

/** "today", "3 days ago", "2 months ago", or '' when the timestamp is unusable. */
export function relativeAge(iso, now = new Date()) {
    const d = parseCalendarDate(iso);
    if (!d) return '';
    const days = Math.floor((now - d) / 86400000);
    if (days < 1) return 'today';
    if (days === 1) return 'yesterday';
    if (days < 30) return `${days} days ago`;
    if (days < 365) {
        const m = Math.floor(days / 30);
        return `${m} month${m === 1 ? '' : 's'} ago`;
    }
    return formatCalendarDate(iso);
}

function subjectFor(cand, ctx) {
    const isPolitician = cand.source === 'platform' && cand.id;
    return {
        subject_type: isPolitician ? 'politician' : 'election_candidate_record',
        subject_id: isPolitician ? cand.id : null,
        subject_name: cand.full_name || null,
        subject_office: ctx.office || cand.office || cand.political_office || null,
        state: ctx.stateAbbr || null,
        source_label: cand.source_label || null,
    };
}

async function submit(payload) {
    const res = await fetch('/api/v1/data-reports', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(payload),
        signal: AbortSignal.timeout(10000),
    });
    if (res.status === 429) throw new Error('You have sent several reports just now — please try again in a minute.');
    if (res.status === 422) {
        const body = await res.json().catch(() => ({}));
        throw new Error(body.message || 'Please check the form and try again.');
    }
    if (!res.ok) throw new Error('Could not send the report. Please try again.');
}

function buildForm(cand, ctx, onDone) {
    const form = el('form', { class: 'dr-form', novalidate: true });
    const id = `dr-${Math.random().toString(36).slice(2, 8)}`;

    form.append(el('label', { class: 'dr-label', for: `${id}-problem` }, 'What looks wrong?'));
    const select = el('select', { id: `${id}-problem`, name: 'problem', class: 'dr-input', required: true });
    for (const [value, label] of PROBLEMS) select.append(el('option', { value }, label));
    form.append(select);

    form.append(el('label', { class: 'dr-label', for: `${id}-msg` }, 'Details (optional)'));
    form.append(el('textarea', { id: `${id}-msg`, name: 'message', class: 'dr-input', rows: '3', maxlength: '1000' }));

    // Honeypot: hidden from people, tempting to bots.
    const trap = el('div', { class: 'dr-trap', 'aria-hidden': 'true' });
    trap.append(el('input', { type: 'text', name: 'website', tabindex: '-1', autocomplete: 'off' }));
    form.append(trap);

    const error = el('p', { class: 'dr-error', role: 'alert' });
    error.hidden = true;
    const actions = el('div', { class: 'dr-actions' });
    const send = el('button', { type: 'submit', class: 'dr-send' }, 'Send report');
    const cancel = el('button', { type: 'button', class: 'dr-cancel' }, 'Cancel');
    actions.append(send, cancel);
    form.append(error, actions);

    cancel.addEventListener('click', () => onDone(false));
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        error.hidden = true;
        send.disabled = true;
        send.textContent = 'Sending…';
        try {
            await submit({
                ...subjectFor(cand, ctx),
                problem: select.value,
                message: form.elements.message.value.trim() || null,
                website: form.elements.website.value,
                page_url: location.href,
            });
            onDone(true);
        } catch (err) {
            error.textContent = err.message;
            error.hidden = false;
            send.disabled = false;
            send.textContent = 'Send report';
        }
    });
    return form;
}

/**
 * Fill `container` with the provenance line and report control for `cand`.
 * @param {HTMLElement} container
 * @param {Object} cand   candidate card from the state-candidates payload
 * @param {{stateAbbr?: string, office?: string}} [ctx]
 */
export function renderProvenance(container, cand, ctx = {}) {
    container.replaceChildren();
    if (!cand?.full_name) { container.hidden = true; return; }
    container.hidden = false;

    const line = el('p', { class: 'dr-line' });
    const label = cand.source_label || 'Public records';
    const age = relativeAge(cand.updated_at);
    line.append(el('span', { class: 'dr-source' }, `Source: ${label}`));
    if (age) line.append(el('span', { class: 'dr-age' }, `Updated ${age} · `));

    const toggle = el('button', { type: 'button', class: 'dr-toggle', 'aria-expanded': 'false' }, 'Report a data problem');
    line.append(toggle);
    container.append(line);

    const slot = el('div', { class: 'dr-slot' });
    container.append(slot);

    const close = (sent) => {
        slot.replaceChildren();
        toggle.setAttribute('aria-expanded', 'false');
        if (sent) {
            const thanks = el('p', { class: 'dr-thanks', role: 'status' }, 'Thanks — we’ll review it.');
            slot.append(thanks);
            toggle.hidden = true;
        } else {
            toggle.focus();
        }
    };
    toggle.addEventListener('click', () => {
        if (slot.firstChild) { close(false); return; }
        const form = buildForm(cand, ctx, close);
        slot.append(form);
        toggle.setAttribute('aria-expanded', 'true');
        form.querySelector('select').focus();
    });
}
