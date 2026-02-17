# Wix Velo Widget — Video Player Embed (client)

This file contains a ready-to-paste Wix Velo (site) snippet that site owners can use
in the Widget Code panel (or in Site Code) to embed the hosted U9itus video player
iframe and forward watch events to your U9itus app endpoints.

Key points

- Configure `APP_ORIGIN` to point at your deployed app (the server that serves `/wix/widget`).
- The snippet listens for postMessage events from the iframe and forwards progress/complete
  events to your app's API endpoints under `/api/v1/sessions/...`.
- Your server must enable CORS for the Wix site origin and accept the POST requests.

Usage

1. Create a container element in Wix Editor with `id="videoPlayerContainer"` (an HTML element or box).
2. Paste the snippet into the Page code (or the widget code panel) for the page where the widget will render.
3. Set `APP_ORIGIN` and optional `WIDGET_CONFIG` values below.

Code (paste into Wix page code or widget JS panel)

--- begin snippet ---
// Wix Velo page script: Video player embed and event forwarder
import {local} from 'wix-storage';
import wixWindow from 'wix-window';

$w.onReady(function() {
// Replace with your app URL (where /wix/widget and API endpoints are hosted)
const APP_ORIGIN = 'https://u9itus-production.up.railway.app';

// Optional configuration — read from Wix settings panel
const WIDGET_CONFIG = {
campaign: '', // Campaign UUID to show (required - from politician's dashboard)
politician: '', // Or Politician UUID to show their active campaign (alternative)
voterEmail: '', // Voter email if logged in to Wix (optional - enables tracking)
requireMember: false, // Require Wix member to qualify (optional)
};

const container = $w('#videoPlayerContainer');
if (!container) {
console.error('videoPlayerContainer element not found');
return;
}

// Build iframe src with query params the server widget expects
const url = new URL('/wix/widget', APP_ORIGIN);

// Campaign or Politician ID (server will fetch active campaign and create session)
if (WIDGET_CONFIG.campaign) url.searchParams.set('campaign', WIDGET_CONFIG.campaign);
else if (WIDGET_CONFIG.politician) url.searchParams.set('politician', WIDGET_CONFIG.politician);

// Voter identification (optional - enables earning tracking)
if (WIDGET_CONFIG.voterEmail) url.searchParams.set('voter_email', WIDGET_CONFIG.voterEmail);

// Optionally pass Wix member ID if available
// const member = wixUsers.currentUser;
// if (member.loggedIn) url.searchParams.set('wix_member_id', member.id);
const iframe = document.createElement('iframe');
iframe.src = url.toString();
iframe.style.width = '100%';
iframe.style.height = '600px';
iframe.style.border = '0';
iframe.setAttribute('aria-label', 'U9itus Political Message Feed');

// Render into Wix container by setting HTML content (use an HTML component or embed)
// If container is a Box/Container element, append via DOM. Wix $w elements expose .$el for HTML elements only.
// The most reliable approach is to use an HTML component in Wix Editor and give it id `videoPlayerHtml`.
// If you have an HTML component, set its srcdoc to a wrapper that includes the iframe.

// Try to append to the container's DOM node (works when the element is an HTML component)
try {
// If the Wix element is an HTML component, it has $w('#id').html property.
    if (typeof container.html !== 'undefined') {
      // Render raw HTML into the HTML component
      container.html = `<div style="width:100%">${iframe.outerHTML}</div>`;
} else {
// Fallback: inject into page DOM directly
// Create a wrapper node and append
const wrapper = document.createElement('div');
wrapper.style.width = '100%';
wrapper.style.maxWidth = '900px';
wrapper.style.margin = '0 auto';
wrapper.appendChild(iframe);
// Append to body; this places it on the page but not inside the Wix-managed element tree
document.body.appendChild(wrapper);
}
} catch (e) {
// If running inside Wix code sandbox, fallback to appending to body
const wrapper = document.createElement('div');
wrapper.style.width = '100%';
wrapper.style.maxWidth = '900px';
wrapper.style.margin = '0 auto';
wrapper.appendChild(iframe);
document.body.appendChild(wrapper);
}

// Helper to post messages to iframe
function postToIframe(type, payload) {
try {
iframe.contentWindow.postMessage({ type, payload }, APP_ORIGIN);
} catch (err) {
console.warn('postToIframe failed', err);
}
}

// Listen for messages from iframe
window.addEventListener('message', async (event) => {
if (!event || event.origin !== APP_ORIGIN) return;
const msg = event.data || {};
// Example messages the iframe may send: 'view:heartbeat', 'view:complete', 'widget:resize', 'widget:openLink'

    if (msg.type === 'view:heartbeat') {
      const { session, secondsWatched } = msg.payload || {};
      if (!session) return;
      // forward to app API: POST /api/v1/sessions/{session}/progress
      try {
        await fetch(`${APP_ORIGIN}/api/v1/sessions/${session}/progress`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ seconds_watched: secondsWatched }),
          credentials: 'include'
        });
      } catch (e) {
        console.warn('heartbeat forward failed', e);
      }
    }

    if (msg.type === 'view:complete') {
      const { session, totalSecondsWatched } = msg.payload || {};
      if (!session) return;
      try {
        const res = await fetch(`${APP_ORIGIN}/api/v1/sessions/${session}/complete`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ total_seconds_watched: totalSecondsWatched }),
          credentials: 'include'
        });
        const json = await res.json();
        // Optionally show a toast or update the UI
        wixWindow.openLightbox('ViewCompleteLightbox', json);
      } catch (e) {
        console.warn('complete forward failed', e);
      }
    }

    if (msg.type === 'widget:resize') {
      const h = parseInt(msg.payload && msg.payload.height, 10) || 600;
      iframe.style.height = `${h}px`;
    }

    if (msg.type === 'widget:openLink') {
      const url = msg.payload && msg.payload.url;
      if (url) window.open(url, '_blank', 'noopener');
    }

});

// Optionally perform an initial handshake to request size
setTimeout(() => postToIframe('host:requestSize', {}), 400);
});

--- end snippet ---

Notes & security

- The server at `APP_ORIGIN` must enable CORS for your Wix site origin, and validate incoming requests.
- For higher security, include an HMAC or JWT token in messages and validate it server-side.
- If you prefer not to call your app API from the client, implement a Wix backend HTTP function that proxies requests securely (recommended for private tokens).

Next steps I can take

- Add the matching postMessage handlers to the server-rendered widget view so it sends `view:heartbeat` and `view:complete` messages to the host (I can patch `resources/views/wix/widget/voter-feed.blade.php`).
- Provide a Wix Backend Web Module example that proxies session progress/complete requests to your API with server-side credentials.
