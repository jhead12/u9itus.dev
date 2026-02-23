import axios from "axios";
window.axios = axios;

window.axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";

/*
|--------------------------------------------------------------------------
| Laravel Echo — Reverb WebSocket client (Phase 11)
|--------------------------------------------------------------------------
|
| Echo is configured only when VITE_REVERB_APP_KEY is present in the
| build environment, so the bundle works fine in CI / environments that
| have no Reverb server.
|
| Required .env / Railway vars (also prefix with VITE_ for client exposure):
|   VITE_REVERB_APP_KEY
|   VITE_REVERB_HOST        (default: localhost)
|   VITE_REVERB_PORT        (default: 8080)
|   VITE_REVERB_SCHEME      (default: http)
|
*/
import Echo from "laravel-echo";
import Pusher from "pusher-js";

window.Pusher = Pusher;

if (import.meta.env.VITE_REVERB_APP_KEY) {
    window.Echo = new Echo({
        broadcaster: "reverb",
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST ?? "localhost",
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? "http") === "https",
        enabledTransports: ["ws", "wss"],
        // Attach the Sanctum CSRF token so Reverb can authorise private channels
        authEndpoint: "/broadcasting/auth",
    });
}
