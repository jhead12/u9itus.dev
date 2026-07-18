/**
 * Politician profile drawer — slide-in panel with overview/economy/contact tabs.
 */
import { STATE_ABBR_MAP, PARTY_HEX, PARTY_LABEL } from '../config/constants.js';
import { activeState } from '../state/map-state.js';
import { fmtPop } from '../config/city-data.js';
import { partyClass } from './panel-state.js';
import { trackEvent } from '../api/interaction.js';

const polDrawer = document.getElementById('pol-drawer');
const polDrawerClose = document.getElementById('pol-drawer-close');
const polHeroEl = document.getElementById('pol-hero');
const polBodyEl = document.getElementById('pol-body');
const polTabBtns = polDrawer.querySelectorAll('.pol-tab');
let _polTab = 'overview';
let _polCtx = null;
let _overviewReqSeq = 0;

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function safeUrl(url) {
    try {
        const parsed = new URL(url, window.location.origin);
        if (parsed.protocol === 'http:' || parsed.protocol === 'https:') return parsed.toString();
    } catch {}
    return '';
}

function toEmbedUrl(url) {
    const safe = safeUrl(url);
    if (!safe) return '';

    const ytMatch = safe.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([A-Za-z0-9_-]{11})/);
    if (ytMatch?.[1]) return `https://www.youtube.com/embed/${ytMatch[1]}`;

    const vimeoMatch = safe.match(/vimeo\.com\/(\d+)/);
    if (vimeoMatch?.[1]) return `https://player.vimeo.com/video/${vimeoMatch[1]}`;

    return safe;
}

function formatPubDate(iso) {
    if (!iso) return 'Recent';
    try {
        return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    } catch {
        return 'Recent';
    }
}

async function loadOverviewEnrichment(cand) {
    const reqSeq = ++_overviewReqSeq;
    const params = new URLSearchParams();
    params.set('full_name', cand?.full_name || '');
    if (cand?.slug) params.set('slug', cand.slug);
    if (activeState) params.set('state', activeState);
    if (cand?.office) params.set('office', cand.office);
    if (cand?.scrape_source) params.set('scrape_source', cand.scrape_source);
    if (cand?.external_candidate_id) params.set('external_candidate_id', cand.external_candidate_id);

    try {
        const res = await fetch(`/api/v1/map/candidate-overview?${params.toString()}`);
        if (!res.ok) return;
        const data = await res.json();
        if (!_polCtx || reqSeq !== _overviewReqSeq) return;
        _polCtx.extra = { ..._polCtx.extra, enrichment: data };
        if (_polTab === 'overview') _renderPolBody();
    } catch {
        // Silent fallback: overview still renders base candidate data.
    }
}

const INDUSTRY_MOCK = [
    { name: 'Finance & Banking', pct: 64 },
    { name: 'Technology', pct: 51 },
    { name: 'Healthcare', pct: 43 },
    { name: 'Real Estate', pct: 35 },
    { name: 'Energy & Environment', pct: 27 },
    { name: 'Defense', pct: 18 },
];

export function openPolDrawer(cand, accentColor, extra = {}) {
    _polCtx = { cand, accentColor: accentColor || '#6366f1', extra };
    loadOverviewEnrichment(cand);
    _polTab = 'overview';
    polTabBtns.forEach(t => {
        t.classList.toggle('active', t.dataset.tab === 'overview');
        t.setAttribute('aria-selected', t.dataset.tab === 'overview');
    });
    polBodyEl.setAttribute('aria-labelledby', 'pol-tab-overview');
    polDrawer.style.setProperty('--pol-accent', _polCtx.accentColor);
    polDrawer.removeAttribute('hidden');
    trackEvent('pol_drawer_open', {
        candidate_name: cand?.full_name || null,
        candidate_slug: cand?.slug || null,
        party: cand?.party || null,
        state: activeState || null,
        state_abbr: activeState ? STATE_ABBR_MAP[activeState] : null,
        meta: extra?.cityName ? { cityName: extra.cityName } : null,
    });

    const c = cand;
    const ac = _polCtx.accentColor;

    if (extra?.isCityView) {
        const leanColor = extra.leaning === 'R' ? '#ef4444' : extra.leaning === 'D' ? '#3b82f6' : '#94a3b8';
        const leanLabel = extra.leaning === 'R' ? 'Republican leaning' : extra.leaning === 'D' ? 'Democratic leaning' : 'Mixed / Split';
        polHeroEl.innerHTML = `
            <div class="pol-avatar-ph" style="font-size:26px;background:rgba(245,158,11,0.1);border:2px solid rgba(245,158,11,0.25);">🏙</div>
            <div class="pol-hero-info">
                <h2 class="pol-name" id="pol-drawer-name">${c.full_name}</h2>
                <p class="pol-title">${c.office || '—'}</p>
                <div class="pol-badges">
                    <span style="background:rgba(245,158,11,0.12);border:1px solid rgba(245,158,11,0.3);color:#f59e0b;padding:2px 10px;border-radius:999px;font-size:11px;font-weight:700;">${fmtPop(extra.cityPop)} residents</span>
                    ${extra.district ? `<span style="background:rgba(99,102,241,0.12);border:1px solid rgba(99,102,241,0.25);color:#818cf8;padding:2px 8px;border-radius:999px;font-size:10px;font-weight:600;">${extra.district}</span>` : ''}
                    <span style="background:rgba(0,0,0,0.2);border:1px solid ${leanColor}44;color:${leanColor};padding:2px 8px;border-radius:999px;font-size:10px;font-weight:600;">${leanLabel}</span>
                </div>
            </div>`;
    } else {
        const ph = c.photo;
        const avH = ph
            ? `<img class="pol-avatar-lg" src="${ph}" alt="${c.full_name}" onerror="this.outerHTML='<div class=\\'pol-avatar-ph\\'>' + window.avatarInitials('${c.full_name}','${ac}',64) + '</div>'">`
            : `<div class="pol-avatar-ph">${window.avatarInitials(c.full_name, ac, 64)}</div>`;
        polHeroEl.innerHTML = `
            ${avH}
            <div class="pol-hero-info">
                <h2 class="pol-name" id="pol-drawer-name">${c.full_name}</h2>
                <p class="pol-title">${c.office || '—'}</p>
                <div class="pol-badges">
                    <span class="party-pill ${partyClass(c.party)}">${PARTY_LABEL[c.party] || c.party || '—'}</span>
                    ${c.status === 'seated' ? `<span style="background:rgba(99,102,241,0.12);border:1px solid rgba(99,102,241,0.25);color:#818cf8;padding:2px 8px;border-radius:999px;font-size:10px;font-weight:600;">In Office</span>` : ''}
                    ${c.is_running ? `<span style="background:rgba(52,211,153,0.1);border:1px solid rgba(52,211,153,0.25);color:#34d399;padding:2px 8px;border-radius:999px;font-size:10px;font-weight:600;">Running 2026</span>` : ''}
                    ${c.verified ? `<span title="Verified" style="color:#fbbf24;font-size:13px;line-height:1;">✓</span>` : ''}
                </div>
            </div>`;
    }

    _renderPolBody();
    requestAnimationFrame(() => polDrawer.classList.add('open'));
    polDrawerClose.focus();
}

export function closePolDrawer() {
    _overviewReqSeq++;
    polDrawer.classList.remove('open');
    setTimeout(() => { if (!polDrawer.classList.contains('open')) polDrawer.setAttribute('hidden', ''); }, 340);
    _polCtx = null;
}

function _renderPolBody() {
    if (!_polCtx) return;
    const { cand: c, accentColor: ac, extra } = _polCtx;
    const pop = extra?.population ?? null;

    if (_polTab === 'overview') {
        if (extra?.isCityView) {
            const { cityPop, district, rep, leaning } = extra;
            const leanColor = leaning === 'R' ? '#ef4444' : leaning === 'D' ? '#3b82f6' : '#94a3b8';
            const leanLabel = leaning === 'R' ? 'Republican' : leaning === 'D' ? 'Democratic' : 'Mixed / Split';
            const repName = rep?.full_name ?? '—';
            const repOffice = district ? `${district} · U.S. House` : '—';
            polBodyEl.innerHTML = `
                <div class="pol-stat-grid">
                    <div class="pol-stat">
                        <span class="pol-stat-val" style="color:#f59e0b;">${fmtPop(cityPop)}</span>
                        <span class="pol-stat-lbl">City Population</span>
                    </div>
                    <div class="pol-stat">
                        <span class="pol-stat-val" style="color:${leanColor};">${leanLabel}</span>
                        <span class="pol-stat-lbl">Political Leaning</span>
                    </div>
                    <div class="pol-stat">
                        <span class="pol-stat-val">${district ?? '—'}</span>
                        <span class="pol-stat-lbl">Congressional District</span>
                    </div>
                    <div class="pol-stat">
                        <span class="pol-stat-val" style="color:${PARTY_HEX[rep?.party] || '#94a3b8'}">${repName}</span>
                        <span class="pol-stat-lbl">District Rep</span>
                    </div>
                </div>
                ${rep ? `
                <p class="pol-section-label" style="margin-top:16px;">District Representative</p>
                <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:rgba(255,255,255,0.04);border-radius:10px;border:1px solid rgba(255,255,255,0.06);">
                    ${rep.photo
                        ? `<img src="${rep.photo}" style="width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid ${PARTY_HEX[rep.party] || '#334155'};flex-shrink:0;" onerror="this.style.display='none'">`
                        : `<div style="width:40px;height:40px;border-radius:50%;background:rgba(99,102,241,0.15);display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0;">${window.avatarInitials(rep.full_name, '#6366f1', 40)}</div>`}
                    <div>
                        <div style="font-size:13px;font-weight:600;color:#e2e8f0;">${rep.full_name}</div>
                        <div style="font-size:11px;color:#64748b;margin-top:2px;">${repOffice}</div>
                        <span class="party-pill ${partyClass(rep.party)}" style="margin-top:5px;display:inline-block;">${PARTY_LABEL[rep.party] || rep.party || '—'}</span>
                    </div>
                </div>` : ''}`;
            return;
        }

        const elDate = c.general_date || c.election_date || null;
        const elStr = elDate ? (() => { try { return new Date(elDate).toLocaleDateString('en-US', { month: 'short', year: 'numeric' }); } catch { return '—'; } })() : '—';
        const popVal = pop ? pop.formatted : '—';
        const popSub = pop ? `(${pop.census_year} Census)` : '';
        const bioHtml = c.bio
            ? `<p class="pol-section-label">About</p><p class="pol-bio">${c.bio}</p>`
            : '';
        const enrichment = extra?.enrichment || null;
        const news = Array.isArray(enrichment?.news) ? enrichment.news.slice(0, 3) : [];
        const activeVideo = enrichment?.active_video || null;
        const activeVideoUrl = safeUrl(activeVideo?.url || '');
        const activeVideoEmbed = activeVideoUrl ? toEmbedUrl(activeVideoUrl) : '';

        let videoHtml = '';
        if (activeVideoUrl) {
            videoHtml = `
                <p class="pol-section-label" style="margin-top:16px;">Active Campaign Feed</p>
                <div style="border:1px solid rgba(148,163,184,0.24);border-radius:10px;overflow:hidden;background:rgba(15,23,42,0.6);">
                    <div style="padding:8px 10px;border-bottom:1px solid rgba(148,163,184,0.18);font-size:11px;color:#cbd5e1;font-weight:600;">${escapeHtml(activeVideo.title || 'Campaign Video')}</div>
                    <div style="position:relative;padding-top:56.25%;background:#020617;">
                        <iframe
                            src="${escapeHtml(activeVideoEmbed)}"
                            title="Campaign video feed"
                            style="position:absolute;inset:0;width:100%;height:100%;border:0;"
                            loading="lazy"
                            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                            referrerpolicy="strict-origin-when-cross-origin"
                            allowfullscreen></iframe>
                    </div>
                    <div style="padding:8px 10px;font-size:11px;color:#64748b;">Source: ${escapeHtml(activeVideo.source || 'campaign')}</div>
                </div>`;
        }

        let newsHtml = '';
        if (news.length) {
            newsHtml = `
                <p class="pol-section-label" style="margin-top:16px;">Recent News</p>
                <div style="display:grid;gap:8px;">
                    ${news.map(item => {
                        const href = safeUrl(item.source_url || '');
                        if (!href) return '';
                        return `<a href="${escapeHtml(href)}" target="_blank" rel="noopener" style="display:block;text-decoration:none;padding:8px 10px;border-radius:8px;border:1px solid rgba(148,163,184,0.2);background:rgba(15,23,42,0.5);">
                            <div style="font-size:12px;color:#e2e8f0;font-weight:600;line-height:1.4;">${escapeHtml(item.headline || 'Article')}</div>
                            <div style="margin-top:4px;font-size:10px;color:#94a3b8;">${escapeHtml(item.source_name || item.provider || 'News')} · ${escapeHtml(formatPubDate(item.published_at))}</div>
                        </a>`;
                    }).join('')}
                </div>`;
        }

        polBodyEl.innerHTML = `
            <div class="pol-stat-grid">
                <div class="pol-stat">
                    <span class="pol-stat-val">${popVal}</span>
                    <span class="pol-stat-lbl">District Population ${popSub}</span>
                </div>
                <div class="pol-stat">
                    <span class="pol-stat-val" style="color:${PARTY_HEX[c.party] || ac}">${PARTY_LABEL[c.party] || c.party || '—'}</span>
                    <span class="pol-stat-lbl">Party</span>
                </div>
                <div class="pol-stat">
                    <span class="pol-stat-val">${c.status === 'seated' ? 'Seated' : (c.is_running ? 'Running' : '—')}</span>
                    <span class="pol-stat-lbl">Status</span>
                </div>
                <div class="pol-stat">
                    <span class="pol-stat-val">${elStr}</span>
                    <span class="pol-stat-lbl">Next Election</span>
                </div>
            </div>
            ${bioHtml}
            ${videoHtml}
            ${newsHtml}`;

    } else if (_polTab === 'economy') {
        polBodyEl.innerHTML = `
            <p class="pol-section-label">Top Industry Support</p>
            <p style="font-size:11px;color:#475569;line-height:1.55;margin:0 0 14px;">Estimated donor-industry breakdown. Full FEC / OpenSecrets integration is planned for a future sprint.</p>
            ${INDUSTRY_MOCK.map(ind => `
                <div class="pol-industry-row">
                    <div class="pol-industry-label">
                        <span>${ind.name}</span>
                        <span style="color:#64748b;">${ind.pct}%</span>
                    </div>
                    <div class="pol-industry-track">
                        <div class="pol-industry-fill" style="width:${ind.pct}%"></div>
                    </div>
                </div>`).join('')}
            <p style="font-size:10px;color:#1e293b;margin:16px 0 0;font-style:italic;">Placeholder data — wired to OpenSecrets API in Sprint 2.</p>`;

    } else {
        const links = [];
        if (c.profile_url) links.push(`<a href="${c.profile_url}" target="_blank" rel="noopener" class="pol-link pol-link-primary">👤 U9itus Profile</a>`);
        if (c.website) links.push(`<a href="${c.website}" target="_blank" rel="noopener" class="pol-link pol-link-alt">Official Website →</a>`);
        if (c.ballotpedia_url) links.push(`<a href="${c.ballotpedia_url}" target="_blank" rel="noopener" class="pol-link pol-link-alt">Ballotpedia →</a>`);
        polBodyEl.innerHTML = `
            <p class="pol-section-label">Links &amp; Resources</p>
            ${links.length
                ? `<div class="pol-link-row">${links.join('')}</div>`
                : `<p class="pol-empty">No contact links available for this candidate yet.</p>`}
            <p class="pol-section-label" style="margin-top:20px;">District Region</p>
            <p style="font-size:12px;color:#64748b;line-height:1.55;">
                Population data, demographic breakdowns, and local economic indicators are
                displayed in the <strong style="color:#94a3b8;">Overview</strong> and <strong style="color:#94a3b8;">Economy</strong> tabs.
                Cultural and civic data layers are accessible from the <strong style="color:#94a3b8;">Layers</strong> panel on the map.
            </p>`;
    }
}

export function initPolDrawer() {
    polDrawerClose.addEventListener('click', closePolDrawer);
    polDrawer.addEventListener('keydown', e => {
        if (e.key === 'Escape') closePolDrawer();
    });
    polTabBtns.forEach(tab => {
        tab.addEventListener('click', () => {
            _polTab = tab.dataset.tab;
            polTabBtns.forEach(t => {
                t.classList.toggle('active', t.dataset.tab === _polTab);
                t.setAttribute('aria-selected', t.dataset.tab === _polTab);
            });
            polBodyEl.setAttribute('aria-labelledby', `pol-tab-${_polTab}`);
            _renderPolBody();
        });
    });
}