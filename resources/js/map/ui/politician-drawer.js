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
const polTabBtns = polDrawer?.querySelectorAll('.pol-tab') ?? [];
let _polTab = 'overview';
let _polCtx = null;
let _overviewReqSeq = 0;
let _economyReqSeq = 0;

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function safeUrl(url) {
    if (!url) return '';
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

const VISITED_ARTICLES_KEY = 'u9itus_visited_articles';
const VISITED_ARTICLES_MAX = 500;

function getVisitedArticles() {
    try {
        const raw = localStorage.getItem(VISITED_ARTICLES_KEY);
        return raw ? new Set(JSON.parse(raw)) : new Set();
    } catch {
        return new Set();
    }
}

function markArticleVisited(url) {
    if (!url) return;
    try {
        const visited = getVisitedArticles();
        if (visited.has(url)) return;
        visited.add(url);
        // Cap storage growth — evict oldest entries once past the limit.
        const arr = Array.from(visited).slice(-VISITED_ARTICLES_MAX);
        localStorage.setItem(VISITED_ARTICLES_KEY, JSON.stringify(arr));
    } catch {}
}

function isArticleVisited(url) {
    try {
        return getVisitedArticles().has(url);
    } catch {
        return false;
    }
}

/** Renders a "Recent News"-style card list — shared by news, press releases, and events. */
function renderItemListSection(label, items) {
    if (!items?.length) return '';
    return `
        <p class="pol-section-label" style="margin-top:16px;">${escapeHtml(label)}</p>
        <div style="display:grid;gap:8px;">
            ${items.map(item => {
                const href = safeUrl(item.source_url || '');
                if (!href) return '';
                const visited = isArticleVisited(href);
                return `<a href="${escapeHtml(href)}" target="_blank" rel="noopener" data-article-url="${escapeHtml(href)}" class="pol-article-card${visited ? ' visited' : ''}">
                    <div class="pol-article-headline">${visited ? '<span class="pol-article-check">✓</span> ' : ''}${escapeHtml(item.headline || 'Article')}</div>
                    <div class="pol-article-meta">${escapeHtml(item.source_name || item.provider || 'News')} · ${escapeHtml(formatPubDate(item.published_at))}</div>
                </a>`;
            }).join('')}
        </div>`;
}

const OVERVIEW_FETCH_TIMEOUT_MS = 8_000;

async function loadOverviewEnrichment(cand) {
    const reqSeq = ++_overviewReqSeq;
    const params = new URLSearchParams();
    params.set('full_name', cand?.full_name || '');
    if (cand?.slug) params.set('slug', cand.slug);
    if (activeState) params.set('state', activeState);
    if (cand?.office) params.set('office', cand.office);
    if (cand?.scrape_source) params.set('scrape_source', cand.scrape_source);
    if (cand?.external_candidate_id) params.set('external_candidate_id', cand.external_candidate_id);

    if (_polCtx) {
        _polCtx.extra = { ..._polCtx.extra, enrichmentLoading: true, enrichmentError: false };
        if (_polTab === 'overview') _renderPolBody();
    }

    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), OVERVIEW_FETCH_TIMEOUT_MS);

    try {
        const res = await fetch(`/api/v1/map/candidate-overview?${params.toString()}`, {
            signal: controller.signal,
        });
        clearTimeout(timeoutId);
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        if (!_polCtx || reqSeq !== _overviewReqSeq) return;
        _polCtx.extra = { ..._polCtx.extra, enrichment: data, enrichmentLoading: false, enrichmentError: false };
        if (_polTab === 'overview') _renderPolBody();
    } catch (err) {
        clearTimeout(timeoutId);
        if (err?.name === 'AbortError') {
            console.warn('[map] candidate-overview fetch timed out; showing base candidate data only');
        } else {
            console.warn('[map] candidate-overview fetch failed:', err);
        }
        if (_polCtx && reqSeq === _overviewReqSeq) {
            _polCtx.extra = { ..._polCtx.extra, enrichmentLoading: false, enrichmentError: true };
            if (_polTab === 'overview') _renderPolBody();
        }
    }
}

const ECONOMY_FETCH_TIMEOUT_MS = 8_000;

async function loadEconomyEnrichment(cand) {
    const reqSeq = ++_economyReqSeq;
    const params = new URLSearchParams();
    params.set('full_name', cand?.full_name || '');
    if (cand?.slug) params.set('slug', cand.slug);
    if (activeState) params.set('state', activeState);
    if (cand?.office) params.set('office', cand.office);

    if (_polCtx) {
        _polCtx.extra = { ..._polCtx.extra, economyLoading: true, economyError: false };
        if (_polTab === 'economy') _renderPolBody();
    }

    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), ECONOMY_FETCH_TIMEOUT_MS);

    try {
        const res = await fetch(`/api/v1/map/candidate-economy?${params.toString()}`, {
            signal: controller.signal,
        });
        clearTimeout(timeoutId);
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        if (!_polCtx || reqSeq !== _economyReqSeq) return;
        _polCtx.extra = { ..._polCtx.extra, economy: data, economyLoading: false, economyError: false };
        if (_polTab === 'economy') _renderPolBody();
    } catch (err) {
        clearTimeout(timeoutId);
        if (err?.name === 'AbortError') {
            console.warn('[map] candidate-economy fetch timed out; economy tab will show an error state');
        } else {
            console.warn('[map] candidate-economy fetch failed:', err);
        }
        if (_polCtx && reqSeq === _economyReqSeq) {
            _polCtx.extra = { ..._polCtx.extra, economyLoading: false, economyError: true };
            if (_polTab === 'economy') _renderPolBody();
        }
    }
}

/** Endorsement / PAC-affiliation badges — logo (if any) + label, linking out to the org's site. */
function renderPacBadges(pacAffiliations) {
    if (!pacAffiliations?.length) return '';
    return `
        <p class="pol-section-label" style="margin-top:16px;">Endorsements &amp; PAC Affiliations</p>
        <div class="pol-badges" style="margin-bottom:2px;">
            ${pacAffiliations.map(b => {
                const href = safeUrl(b.website_url || '');
                const logo = safeUrl(b.logo_url || '');
                const inner = `${logo ? `<img src="${escapeHtml(logo)}" alt="" onerror="this.remove()">` : ''}<span>${escapeHtml(b.label)}</span>`;
                return href
                    ? `<a class="pol-badge-logo" href="${escapeHtml(href)}" target="_blank" rel="noopener">${inner}</a>`
                    : `<span class="pol-badge-logo">${inner}</span>`;
            }).join('')}
        </div>
        <p style="font-size:9px;color:#475569;margin:4px 0 0;">Inferred from campaign-finance contributor matching, not a confirmed public endorsement.</p>`;
}

function renderIndustryBars(industries) {
    if (!industries?.length) return '';
    return `
        <p class="pol-section-label" style="margin-top:16px;">Top Industry Support</p>
        ${industries.map(ind => `
            <div class="pol-industry-row">
                <div class="pol-industry-label">
                    <span>${escapeHtml(ind.name)}</span>
                    <span style="color:#64748b;">${escapeHtml(ind.total_display || '')}</span>
                </div>
                <div class="pol-industry-track">
                    <div class="pol-industry-fill" style="width:${Math.max(0, Math.min(100, ind.pct || 0))}%"></div>
                </div>
            </div>`).join('')}`;
}

function renderContributors(contributors) {
    if (!contributors?.length) return '';
    return `
        <p class="pol-section-label" style="margin-top:16px;">Top Contributors</p>
        <div style="display:grid;gap:6px;">
            ${contributors.map(c => `
                <div style="display:flex;justify-content:space-between;gap:8px;padding:6px 10px;border-radius:8px;background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);">
                    <span style="font-size:11px;color:#cbd5e1;">${escapeHtml(c.name)}</span>
                    <span style="font-size:11px;color:#64748b;font-weight:600;">${escapeHtml(c.total_display || '')}</span>
                </div>`).join('')}
        </div>`;
}

function renderFecSummary(fec) {
    if (!fec || !(fec.receipts || fec.disbursements || fec.cash_on_hand || fec.debt)) return '';
    return `
        <p class="pol-section-label" style="margin-top:16px;">FEC Financial Summary</p>
        <div class="pol-stat-grid">
            <div class="pol-stat"><span class="pol-stat-val">${escapeHtml(fec.receipts || '—')}</span><span class="pol-stat-lbl">Total Raised</span></div>
            <div class="pol-stat"><span class="pol-stat-val">${escapeHtml(fec.disbursements || '—')}</span><span class="pol-stat-lbl">Total Spent</span></div>
            <div class="pol-stat"><span class="pol-stat-val">${escapeHtml(fec.cash_on_hand || '—')}</span><span class="pol-stat-lbl">Cash on Hand</span></div>
            <div class="pol-stat"><span class="pol-stat-val">${escapeHtml(fec.debt || '—')}</span><span class="pol-stat-lbl">Debt</span></div>
        </div>`;
}

function renderEconomySources(sources, enrichedAt) {
    const links = [];
    const os = safeUrl(sources?.opensecrets_url || '');
    const fecUrl = safeUrl(sources?.fec_url || '');
    if (os) links.push(`<a href="${escapeHtml(os)}" target="_blank" rel="noopener" class="pol-link pol-link-alt">OpenSecrets →</a>`);
    if (fecUrl) links.push(`<a href="${escapeHtml(fecUrl)}" target="_blank" rel="noopener" class="pol-link pol-link-alt">FEC.gov →</a>`);
    const updated = enrichedAt ? formatPubDate(enrichedAt) : null;
    return `
        ${updated ? `<p style="font-size:10px;color:#475569;margin:16px 0 0;">Data updated ${escapeHtml(updated)}</p>` : ''}
        ${links.length ? `<div class="pol-link-row" style="margin-top:6px;">${links.join('')}</div>` : ''}`;
}

export function openPolDrawer(cand, accentColor, extra = {}) {
    if (!polDrawer || !polHeroEl || !polBodyEl) {
        console.error('[map] openPolDrawer: drawer DOM elements are missing');
        return;
    }
    if (!cand || typeof cand !== 'object') {
        console.error('[map] openPolDrawer: invalid candidate', cand);
        return;
    }

    try {
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
        // Focus after the slide-in transition so the drawer is visually ready.
        setTimeout(() => {
            try { polDrawerClose?.focus({ preventScroll: true }); } catch {}
        }, 360);
    } catch (err) {
        // Log the error but do NOT close the drawer — a partially-open drawer is
        // more useful than a silently failing one, and the error will show in
        // the console for further diagnosis.
        console.error('[map] openPolDrawer encountered an error but is keeping the drawer open:', err);
        try { polDrawer.removeAttribute('hidden'); polDrawer.classList.add('open'); } catch {}
    }
}

export function closePolDrawer() {
    _overviewReqSeq++;
    if (!polDrawer) return;
    polDrawer.classList.remove('open');
    setTimeout(() => { if (polDrawer && !polDrawer.classList.contains('open')) polDrawer.setAttribute('hidden', ''); }, 340);
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
        const isLoadingEnrichment = !!extra?.enrichmentLoading && !enrichment;
        const hasEnrichmentError = !!extra?.enrichmentError && !enrichment;
        const news = Array.isArray(enrichment?.news) ? enrichment.news.slice(0, 3) : [];
        const pressReleases = Array.isArray(enrichment?.press_releases) ? enrichment.press_releases.slice(0, 3) : [];
        const events = Array.isArray(enrichment?.events) ? enrichment.events.slice(0, 3) : [];
        const activeVideo = enrichment?.active_video || null;
        const activeVideoUrl = safeUrl(activeVideo?.url || '');
        const activeVideoEmbed = activeVideoUrl ? toEmbedUrl(activeVideoUrl) : '';

        let videoHtml = '';
        if (activeVideoUrl && activeVideoEmbed) {
            const videoTitle = escapeHtml(activeVideo.title || 'Campaign Video');
            const videoSource = escapeHtml(activeVideo.source || 'campaign');
            const embedSrc = escapeHtml(activeVideoEmbed);
            // Lazy-load the iframe: show a placeholder until the user clicks.
            videoHtml = `
                <p class="pol-section-label" style="margin-top:16px;">Active Campaign Feed</p>
                <div class="pol-video-wrap" data-video-src="${embedSrc}" data-video-title="${videoTitle}" style="border:1px solid rgba(148,163,184,0.24);border-radius:10px;overflow:hidden;background:rgba(15,23,42,0.6);">
                    <div style="padding:8px 10px;border-bottom:1px solid rgba(148,163,184,0.18);font-size:11px;color:#cbd5e1;font-weight:600;">${videoTitle}</div>
                    <div class="pol-video-placeholder" style="position:relative;padding-top:56.25%;background:#020617;cursor:pointer;"
                         onclick="window.__loadPolVideo(this)"
                         role="button" tabindex="0" aria-label="Load campaign video">
                        <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:8px;">
                            <div style="width:48px;height:48px;border-radius:50%;background:rgba(99,102,241,0.2);border:1px solid rgba(99,102,241,0.5);display:flex;align-items:center;justify-content:center;font-size:20px;">▶</div>
                            <span style="font-size:11px;color:#94a3b8;">Click to load video</span>
                        </div>
                    </div>
                    <iframe
                        class="pol-video-iframe"
                        title="Campaign video feed"
                        style="display:none;position:absolute;inset:0;width:100%;height:100%;border:0;"
                        loading="lazy"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                        referrerpolicy="strict-origin-when-cross-origin"
                        allowfullscreen></iframe>
                    <div style="padding:8px 10px;font-size:11px;color:#64748b;">Source: ${videoSource}</div>
                </div>`;
        }

        const newsHtml = renderItemListSection('Recent News', news);
        const pressReleaseHtml = renderItemListSection('Press Releases', pressReleases);
        const eventsHtml = renderItemListSection('Upcoming Events', events);

        let enrichmentStatusHtml = '';
        if (isLoadingEnrichment) {
            enrichmentStatusHtml = `
                <p class="pol-section-label" style="margin-top:16px;">Recent News</p>
                <div style="display:flex;align-items:center;gap:8px;padding:10px 12px;border-radius:8px;border:1px solid rgba(99,102,241,0.15);background:rgba(99,102,241,0.06);">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" style="animation:spin 1s linear infinite;color:#6366f1;">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="31.4" stroke-dashoffset="10" stroke-linecap="round"/>
                    </svg>
                    <span style="font-size:11px;color:#94a3b8;">Loading latest headlines…</span>
                </div>`;
        } else if (hasEnrichmentError) {
            enrichmentStatusHtml = `
                <p class="pol-section-label" style="margin-top:16px;">Recent News</p>
                <div style="padding:10px 12px;border-radius:8px;border:1px solid rgba(239,68,68,0.2);background:rgba(239,68,68,0.06);">
                    <span style="font-size:11px;color:#f87171;">⚠ Headlines unavailable right now. Base candidate info is shown.</span>
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
            ${newsHtml}
            ${pressReleaseHtml}
            ${eventsHtml}
            ${enrichmentStatusHtml}`;

    } else if (_polTab === 'economy') {
        if (extra?.isCityView) {
            polBodyEl.innerHTML = `
                <p class="pol-section-label">Economy</p>
                <p class="pol-empty">Campaign finance data is shown for individual candidates — select a specific representative to view it.</p>`;
            return;
        }

        const economy = extra?.economy || null;
        const isLoadingEconomy = !!extra?.economyLoading && !economy;
        const hasEconomyError = !!extra?.economyError && !economy;

        if (isLoadingEconomy) {
            polBodyEl.innerHTML = `
                <p class="pol-section-label">Economy</p>
                <div style="display:flex;align-items:center;gap:8px;padding:10px 12px;border-radius:8px;border:1px solid rgba(99,102,241,0.15);background:rgba(99,102,241,0.06);">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" style="animation:spin 1s linear infinite;color:#6366f1;">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" stroke-dasharray="31.4" stroke-dashoffset="10" stroke-linecap="round"/>
                    </svg>
                    <span style="font-size:11px;color:#94a3b8;">Loading campaign finance data…</span>
                </div>`;
            return;
        }

        if (hasEconomyError) {
            polBodyEl.innerHTML = `
                <p class="pol-section-label">Economy</p>
                <div style="padding:10px 12px;border-radius:8px;border:1px solid rgba(239,68,68,0.2);background:rgba(239,68,68,0.06);">
                    <span style="font-size:11px;color:#f87171;">⚠ Campaign finance data unavailable right now.</span>
                </div>`;
            return;
        }

        if (!economy || !economy.has_data) {
            const message = economy?.reason === 'not_on_platform'
                ? 'This candidate has not claimed a U9itus profile yet, so campaign finance data is not available.'
                : 'Campaign finance data has not been enriched for this candidate yet — check back after the next nightly update.';
            polBodyEl.innerHTML = `
                <p class="pol-section-label">Economy</p>
                <p class="pol-empty">${escapeHtml(message)}</p>`;
            return;
        }

        polBodyEl.innerHTML = `
            ${renderPacBadges(economy.pac_affiliations)}
            ${renderIndustryBars(economy.top_industries)}
            ${renderContributors(economy.top_contributors)}
            ${renderFecSummary(economy.fec_summary)}
            ${renderEconomySources(economy.sources, economy.enriched_at)}`;

    } else {
        const links = [];
        if (c.profile_url) links.push(`<a href="${c.profile_url}" target="_blank" rel="noopener" class="pol-link pol-link-primary">👤 U9itus Profile</a>`);
        if (c.website) links.push(`<a href="${c.website}" target="_blank" rel="noopener" class="pol-link pol-link-alt">Official Website →</a>`);
        if (c.ballotpedia_url) links.push(`<a href="${c.ballotpedia_url}" target="_blank" rel="noopener" class="pol-link pol-link-alt">Ballotpedia →</a>`);

        const stateAbbr = activeState ? STATE_ABBR_MAP[activeState] : null;
        if (stateAbbr) {
            const districtNumber = extra?.districtNumber;
            const browseUrl = districtNumber
                ? `/politicians?state=${encodeURIComponent(stateAbbr)}&district=${encodeURIComponent(districtNumber)}&status=running`
                : `/politicians?state=${encodeURIComponent(stateAbbr)}&status=running`;
            const browseLabel = districtNumber
                ? `See all running candidates in ${stateAbbr}-${districtNumber} →`
                : `See all running candidates in ${stateAbbr} →`;
            links.push(`<a href="${browseUrl}" class="pol-link pol-link-alt">${browseLabel}</a>`);
        }

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
    if (!polDrawer || !polDrawerClose || !polBodyEl) {
        console.warn('[map] initPolDrawer: drawer DOM elements are missing, skipping drawer setup');
        return;
    }

    // Global helper for the lazy-loaded campaign video placeholder.
    window.__loadPolVideo = function (placeholderEl) {
        const wrap = placeholderEl?.closest('.pol-video-wrap');
        if (!wrap) return;
        const iframe = wrap.querySelector('.pol-video-iframe');
        const src = wrap.dataset.videoSrc;
        if (!iframe || !src) return;
        placeholderEl.style.display = 'none';
        iframe.src = src;
        iframe.style.display = 'block';
    };

    // Delegated on polDrawer (not polBodyEl) since its innerHTML is replaced
    // wholesale on every _renderPolBody() call — a direct listener would be
    // discarded along with the old content.
    polDrawer.addEventListener('click', e => {
        const link = e.target.closest('a[data-article-url]');
        if (!link || link.classList.contains('visited')) return;
        markArticleVisited(link.dataset.articleUrl);
        link.classList.add('visited');
        const headline = link.querySelector('.pol-article-headline');
        if (headline) headline.insertAdjacentHTML('afterbegin', '<span class="pol-article-check">✓</span> ');
    });

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
            // Economy data is fetched lazily — only once, the first time the
            // tab is actually opened — to avoid doubling requests per drawer
            // open for data most users never view.
            if (_polTab === 'economy' && _polCtx && !_polCtx.extra?.isCityView
                && !_polCtx.extra?.economy && !_polCtx.extra?.economyLoading) {
                loadEconomyEnrichment(_polCtx.cand);
            }
            _renderPolBody();
        });
    });
}