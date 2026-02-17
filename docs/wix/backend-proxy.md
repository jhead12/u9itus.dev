# Wix Backend Proxy (recommended)

This document shows a recommended Wix Backend Web Module (`.jsw`) that proxies session heartbeat and completion requests to your U9itus API using a server-side secret. Using a backend proxy keeps API keys off the visitor's browser and prevents misuse of your API endpoints.

Why use a backend proxy

- Hides API credentials / tokens in Wix Secrets Manager
- Lets Wix enforce rate limits and logging for your site
- Avoids exposing your production API origin to JS in the browser

How it works

1. The hosted widget (iframe) posts `view:heartbeat` and `view:complete` to the Wix Velo client.
2. The Velo client calls the Wix backend module (backend/proxy.jsw).
3. The backend module reads a secret from Wix Secrets and forwards the request to your API (`/api/v1/sessions/{session}/progress` or `/complete`).

Security note: create a dedicated API secret for Wix and validate it server-side (check header `X-Wix-Api-Key` or similar).

Example backend module (paste into Wix backend as `backend/proxy.jsw`)

```js
// backend/proxy.jsw
import { fetch } from "wix-fetch";
import { getSecret } from "wix-secrets-backend";

const API_BASE = "https://u9itus-production.up.railway.app/api/v1";

async function getApiKey() {
    // Set `D4D_API_KEY` in Wix Secrets (Dev Center → Secrets)
    return await getSecret("D4D_API_KEY");
}

export async function forwardHeartbeat(sessionUuid, secondsWatched) {
    const apiKey = await getApiKey();
    const url = `${API_BASE}/sessions/${sessionUuid}/progress`;
    const res = await fetch(url, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-Wix-Api-Key": apiKey,
        },
        body: JSON.stringify({ seconds_watched: secondsWatched }),
    });

    if (!res.ok) throw new Error("Heartbeat forward failed");
    return res.json();
}

export async function forwardComplete(sessionUuid, totalSecondsWatched) {
    const apiKey = await getApiKey();
    const url = `${API_BASE}/sessions/${sessionUuid}/complete`;
    const res = await fetch(url, {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
            "X-Wix-Api-Key": apiKey,
        },
        body: JSON.stringify({ total_seconds_watched: totalSecondsWatched }),
    });

    if (!res.ok) throw new Error("Complete forward failed");
    return res.json();
}
```

Example Velo client usage (call backend module functions instead of calling the API directly):

```js
import * as backend from "backend/proxy";

// When receiving a postMessage from the iframe (example):
if (msg.type === "view:heartbeat") {
    backend
        .forwardHeartbeat(msg.payload.session, msg.payload.secondsWatched)
        .catch((err) => console.warn("Heartbeat proxy error", err));
}

if (msg.type === "view:complete") {
    backend
        .forwardComplete(msg.payload.session, msg.payload.totalSecondsWatched)
        .then((json) => {
            // show completion UI
        })
        .catch((err) => console.warn("Complete proxy error", err));
}
```

Wix secrets setup

1. In Wix Dev Center, open Secrets Manager for your app.
2. Create a secret named `D4D_API_KEY` with a strong value.
3. Do not commit secrets to source control.

Server-side validation

- On the Laravel side, accept requests with `X-Wix-Api-Key` and verify it matches a value you configure in your production environment. This ensures only your Wix site can call the proxied endpoints.
