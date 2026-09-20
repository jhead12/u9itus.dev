/**
 * "Find My District" geolocation button for the 3D map.
 *
 * Uses the browser Geolocation API, reverse-geocodes the coordinates
 * through the backend Census API, then flies the map to the user's
 * congressional district and opens the representative panel. It is the
 * secondary path in the "Find your district" card (address-entry.js), which
 * also reuses goToDistrict() below for typed addresses and ZIP codes.
 */
import { trackEvent } from '../api/interaction.js';

const toastEl = document.getElementById('map-toast');
let isLocating = false;

export function showToast(message, type = 'info') {
    if (!toastEl) return;
    toastEl.textContent = message;
    toastEl.className = 'map-toast visible ' + type;
    setTimeout(() => toastEl.classList.remove('visible'), 3500);
}

function clearToast() {
    if (!toastEl) return;
    toastEl.classList.remove('visible');
}

function friendlyGeolocationError(code) {
    switch (code) {
        case 1: return 'Location access was denied. Check your browser permissions and try again.';
        case 2: return 'Your location could not be determined. Try entering your address instead.';
        case 3: return 'Location lookup timed out. Try entering your address instead.';
        default: return 'Could not find your district. Try searching by state or district.';
    }
}

async function resolveDistrict(lat, lng) {
    const res = await fetch('/api/v1/map/geocode', {
        method: 'POST',
        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ lat, lng }),
    });
    if (!res.ok) {
        const data = await res.json().catch(() => ({}));
        throw new Error(data.error || 'We could not determine your congressional district.');
    }
    return res.json();
}

/**
 * Fly the map to a resolved district ({ state, district_number }) and open its
 * panel. If __mapGoTo isn't available yet, or the 3D state meshes haven't
 * finished loading (it returns false), fall back to a full reload with
 * deep-link params so bootDeepLink can retry once the map is ready —
 * previously this was a silent no-op.
 */
export async function goToDistrict(data) {
    const deepLink = () => {
        const params = new URLSearchParams({
            state: data.state,
            district: data.district_number,
        });
        location.assign(`${location.pathname}?${params.toString()}`);
    };

    if (typeof window.__mapGoTo === 'function') {
        const handled = await window.__mapGoTo(data.state, data.district_number, null);
        if (!handled) deepLink();
    } else {
        deepLink();
    }
}

export async function findMyDistrict() {
    if (isLocating) return;

    if (!navigator.geolocation) {
        showToast('Your browser does not support geolocation. Enter your address instead.', 'error');
        return;
    }

    if (window.isSecureContext === false) {
        showToast('Location requires a secure (HTTPS) connection. Enter your address instead.', 'error');
        return;
    }

    isLocating = true;
    showToast('Finding your congressional district…', 'info');
    trackEvent('find_my_district_click', {});

    try {
        const position = await new Promise((resolve, reject) => {
            navigator.geolocation.getCurrentPosition(resolve, reject, {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 60000,
            });
        });

        const { latitude, longitude } = position.coords;
        const data = await resolveDistrict(latitude, longitude);

        if (!data.ok || !data.state || !data.district_number) {
            throw new Error(data.error || 'We could not determine your congressional district.');
        }

        clearToast();
        trackEvent('find_my_district_success', {
            state: data.state,
            district: data.district_code,
        });

        await goToDistrict(data);
    } catch (err) {
        const message = err?.code
            ? friendlyGeolocationError(err.code)
            : err.message || 'Could not find your district.';
        showToast(message, 'error');
        trackEvent('find_my_district_error', { meta: { message } });
    } finally {
        isLocating = false;
    }
}

/**
 * The top-bar "Find my district" button, the Controls menu item and the L key
 * all open the "Find your district" card now (address-entry.js), so there is
 * nothing to wire here; kept so app.js's boot sequence stays unchanged.
 */
export function initLocationButton() {}
