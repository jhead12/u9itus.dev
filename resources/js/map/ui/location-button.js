/**
 * "Find My District" geolocation button for the 3D map.
 *
 * Uses the browser Geolocation API, reverse-geocodes the coordinates
 * through the backend Census API, then flies the map to the user's
 * congressional district and opens the representative panel.
 */
import { trackEvent } from '../api/interaction.js';

const toastEl = document.getElementById('map-toast');
let isLocating = false;

function showToast(message, type = 'info') {
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
        case 2: return 'Your location could not be determined. Try a search instead.';
        case 3: return 'Location lookup timed out. Try a search instead.';
        default: return 'Could not find your district. Try searching by state or district.';
    }
}

async function resolveDistrict(lat, lng) {
    const res = await fetch(`/api/v1/map/geocode?lat=${encodeURIComponent(lat)}&lng=${encodeURIComponent(lng)}`, {
        headers: { 'Accept': 'application/json' },
    });
    if (!res.ok) {
        const data = await res.json().catch(() => ({}));
        throw new Error(data.error || 'We could not determine your congressional district.');
    }
    return res.json();
}

export async function findMyDistrict() {
    if (isLocating) return;

    if (!navigator.geolocation) {
        showToast('Your browser does not support geolocation. Use the search bar instead.', 'error');
        return;
    }

    if (window.isSecureContext === false) {
        showToast('Location requires a secure (HTTPS) connection. Use the search bar instead.', 'error');
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

        // Fly the map to the district. If __mapGoTo isn't available yet, or
        // the 3D state meshes haven't finished loading (it returns false),
        // fall back to a full reload with deep-link params so bootDeepLink can
        // retry once the map is ready — previously this was a silent no-op.
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
 * Wire up the top-bar button and any other triggers.
 */
export function initLocationButton() {
    document.getElementById('btn-find-district')?.addEventListener('click', (e) => {
        e.stopPropagation();
        findMyDistrict();
    });

    // cm-btn-find-district is wired in controls-menu.js, whose handler also
    // closes the dropdown. Avoid a duplicate listener here.
}
