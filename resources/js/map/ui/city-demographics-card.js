/**
 * Shared city demographics card — renders a single city's Census ACS stats
 * (poverty rate, educational attainment, median household income). Used by
 * both the region panel (all cities in a region) and the state panel (top
 * cities within one state).
 */

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

export function fmtMoney(n) {
    if (n === null || n === undefined) return '—';
    return '$' + Number(n).toLocaleString('en-US');
}

export function fmtPct(n) {
    if (n === null || n === undefined) return '—';
    return Number(n).toFixed(1) + '%';
}

/** Fetches all tracked cities for a single state, via the region-demographics endpoint. */
export async function fetchCitiesForState(regionName, stateAbbr) {
    let data = null;
    try {
        const res = await fetch(`/api/v1/map/region-demographics?region=${encodeURIComponent(regionName)}`);
        if (res.ok) data = await res.json();
    } catch (err) {
        console.warn('[map] region-demographics fetch failed:', err);
    }
    return data?.states?.find(s => s.state === stateAbbr)?.cities ?? [];
}

export function renderCityCard(city, state, color) {
    const hasDistrict = !!city.district_number;
    const cursor = hasDistrict ? 'cursor:pointer;' : '';
    return `<div class="region-city-card" data-state="${escapeHtml(state)}" data-city="${escapeHtml(city.city)}" data-district="${escapeHtml(city.district_number ?? '')}"
            style="margin-bottom:10px;padding:10px 12px;border-radius:8px;border:1px solid rgba(148,163,184,0.2);background:rgba(15,23,42,0.5);${cursor}"
            ${hasDistrict ? `title="View ${escapeHtml(city.district_code)}"` : ''}>
        <div style="display:flex;justify-content:space-between;align-items:baseline;gap:8px;">
            <span style="font-size:13px;font-weight:700;color:#e2e8f0;">${escapeHtml(city.city)}${hasDistrict ? ` <span style="opacity:.5;font-weight:400;">→ ${escapeHtml(city.district_code)}</span>` : ''}</span>
            <span style="font-size:10px;color:#64748b;white-space:nowrap;">${city.population ? Number(city.population).toLocaleString('en-US') : '—'} people</span>
        </div>
        <div style="display:flex;gap:6px;margin-top:8px;flex-wrap:wrap;">
            <span style="font-size:10px;padding:3px 8px;border-radius:999px;background:${color}18;border:1px solid ${color}44;color:${color};">Poverty ${fmtPct(city.poverty_rate)}</span>
            <span style="font-size:10px;padding:3px 8px;border-radius:999px;background:${color}18;border:1px solid ${color}44;color:${color};">Bachelor's+ ${fmtPct(city.pct_bachelors_or_higher)}</span>
            <span style="font-size:10px;padding:3px 8px;border-radius:999px;background:${color}18;border:1px solid ${color}44;color:${color};">Median income ${fmtMoney(city.median_household_income)}</span>
        </div>
    </div>`;
}

const toastEl = document.getElementById('map-toast');

function showToast(message, type = 'info') {
    if (!toastEl) return;
    toastEl.textContent = message;
    toastEl.className = 'map-toast visible ' + type;
    setTimeout(() => toastEl.classList.remove('visible'), 3500);
}

/** Navigate to a city's congressional district — precomputed at sync time (geo:sync-census-demographics), no live geocode call needed. */
export function goToCityDistrict(state, districtNumber, cityName) {
    if (!districtNumber || typeof window.__mapGoTo !== 'function') return;
    showToast(`Opening ${cityName}'s congressional district…`, 'info');
    window.__mapGoTo(state, districtNumber, null);
}

/** Wire click-to-navigate on any .region-city-card[data-district] elements within a container. */
export function wireCityCardClicks(container) {
    container.querySelectorAll('.region-city-card[data-district]').forEach(card => {
        if (!card.dataset.district) return;
        card.addEventListener('click', () => goToCityDistrict(card.dataset.state, card.dataset.district, card.dataset.city));
    });
}
