# Plan: "Find My District" geolocation button on the 3D map

## Goal
Let users click a button on the map to discover which congressional district they are currently in, then fly the map to that state + district and open the representative's profile.

## Existing pieces we can reuse
- `DistrictLookupService::lookup($address)` resolves an address to state + district via the Census Geocoder (with Google Civic fallback).
- `window.__mapGoTo(state, district, slug)` already navigates the map from URL params / external calls.
- Map search palette already supports state/district search.
- Browser Geolocation API is available on most devices.

## Proposed implementation

### Backend: new public API endpoint
Create `App\Http\Controllers\Api\MapGeocodeController` with a single method:

`GET /api/v1/map/geocode?lat={lat}&lng={lng}`

1. Validate lat/lng are numeric and within valid ranges.
2. Reverse-geocode using the Census Geocoder reverse endpoint:
   `https://geocoding.geo.census.gov/geocoder/geographies/coordinates?x={lng}&y={lat}&benchmark=Public_AR_Current&vintage=Census2020_Current&format=json`
3. Parse the response with the same helpers already in `DistrictLookupService` to extract state and congressional district.
4. Return JSON:
   ```json
   {
     "ok": true,
     "state": "CA",
     "district_number": "33",
     "district_code": "CA-33",
     "district_label": "California 33rd Congressional District",
     "matched": false
   }
   ```
   or `{ "ok": false, "error": "..." }`.
5. Rate-limit to the existing map throttle bucket (`throttle:120,1`).

### Frontend: location button + geocode flow
1. Add a button in the map top bar (next to Search) with a target/location icon and label "Find My District".
2. On click:
   - Check for `navigator.geolocation`.
   - Call `navigator.geolocation.getCurrentPosition(...)` with timeout and high accuracy.
   - Show a temporary loading indicator / toast.
   - Fetch `/api/v1/map/geocode?lat=...&lng=...`.
   - On success, call `window.__mapGoTo(state, districtNumber, null)` to fly to the district.
   - On error, show a friendly inline message (permission denied, location unavailable, outside U.S., geocoder failed).
3. Add keyboard shortcut `L` to trigger the same flow.
4. Add the shortcut to the keyboard help overlay and the controls menu.

### Files that will change
- New: `app/Http/Controllers/Api/MapGeocodeController.php`
- `routes/api.php` — add `/map/geocode` route
- `resources/views/standalone/public/us-map.blade.php` — add top-bar button, keyboard help row
- `resources/js/map/ui/location-button.js` — new module for geolocation logic
- `resources/js/map/ui/keyboard.js` — add `L` shortcut
- `resources/js/map/ui/controls-menu.js` — add controls menu item
- `resources/js/map/app.js` — import/init location button
- `resources/css/map.css` — button + toast styles
- Tests: add a feature test for the geocode endpoint

## Trade-offs considered
- **Client-side reverse geocode vs. proxy through backend**
  - Client-side to Census would avoid a backend hop, but the Census API does not support CORS for direct browser calls in all environments. Routing through our backend lets us cache, rate-limit, normalize parsing, and reuse existing service helpers.
- **Census vs. Google Civic reverse**
  - Census coordinates endpoint is free, authoritative, and requires no API key. Use it first; add a Google Civic fallback later if needed.
- **High accuracy vs. fast lookup**
  - Use `enableHighAccuracy: true` with a 10-second timeout so mobile users get precise district boundaries; fallback to coarse location if high accuracy fails.

## Acceptance criteria
1. Clicking "Find My District" requests browser location, calls the API, and flies the map to the user's congressional district.
2. The district panel opens with the representative and candidates.
3. Errors (permission denied, unavailable, outside U.S., geocoder failure) show friendly messages without breaking the map.
4. Keyboard shortcut `L` works.
5. The endpoint returns proper error codes and is rate-limited.
6. Build passes and tests pass.
