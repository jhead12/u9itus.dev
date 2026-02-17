/*
 * Video Chat Player (Wix widget glue)
 *
 * This script is intended for the Wix site widget code panel. It injects
 * an iframe pointing to the hosted U9itus widget view and provides a
 * lightweight postMessage handshake for sizing and basic actions.
 *
 * Usage notes:
 * - Set the hosting app origin via `data-app-origin` attribute on the
 *   widget container or update `DEFAULT_APP_ORIGIN` below.
 * - The hosted widget view is served from `/wix/widget` in the app and
 *   will receive query params from the iframe src. The server-side
 *   implementation already exists at `/wix/widget` (see routes).
 */

(function () {
    "use strict";

    // Default backend/app origin (replace with your deployed app URL)
    const DEFAULT_APP_ORIGIN = "https://app.example.com";

    // Timeout for handshake attempts
    const HANDSHAKE_TIMEOUT = 4000;

    // Create the iframe and attach to the Wix widget container
    function createWidgetIframe(container) {
        const appOrigin = container.dataset.appOrigin || DEFAULT_APP_ORIGIN;

        // Instance id can be provided via data-instance, or Wix will attach an id
        const instanceId = container.dataset.instanceId || "";

        // Build widget URL. Server renders a fully working player at /wix/widget
        const url = new URL("/wix/widget", appOrigin);
        if (instanceId) url.searchParams.set("instance", instanceId);

        const iframe = document.createElement("iframe");
        iframe.src = url.toString();
        iframe.width = "100%";
        iframe.height = "600";
        iframe.style.border = "0";
        iframe.setAttribute("aria-label", "U9itus Political Message Feed");
        iframe.referrerPolicy = "no-referrer-when-downgrade";

        // Expose a small API on the iframe element for resizing/commands
        iframe.postWidgetMessage = function (type, payload) {
            iframe.contentWindow &&
                iframe.contentWindow.postMessage({ type, payload }, appOrigin);
        };

        container.appendChild(iframe);

        // Basic handshake: ask iframe for preferred height and apply
        const handshakeStart = Date.now();
        function askForSize() {
            if (!iframe.contentWindow) return;
            iframe.postWidgetMessage("host:requestSize", {});
            if (Date.now() - handshakeStart < HANDSHAKE_TIMEOUT) {
                setTimeout(askForSize, 300);
            }
        }
        askForSize();

        // Listen for messages from the iframe
        window.addEventListener("message", (event) => {
            if (event.origin !== appOrigin) return; // only accept from configured origin
            const msg = event.data || {};
            if (
                msg.type === "widget:resize" &&
                msg.payload &&
                msg.payload.height
            ) {
                iframe.style.height = `${parseInt(msg.payload.height, 10)}px`;
            }
            if (
                msg.type === "widget:openLink" &&
                msg.payload &&
                msg.payload.url
            ) {
                // Open links in a new tab from the host page
                window.open(msg.payload.url, "_blank", "noopener");
            }
        });

        return iframe;
    }

    // Find Wix widget container(s). Wix adds an element with data-attributes
    // into the page when rendering the custom widget. Try common selectors.
    function findWidgetContainers() {
        // 1) Common pattern: element with class 'video-chat-player' or custom dataset
        const selectors = [
            '[data-widget="video-chat-player"]',
            ".video-chat-player",
            "#video-chat-player",
        ];

        const containers = [];
        selectors.forEach((sel) =>
            document.querySelectorAll(sel).forEach((el) => containers.push(el)),
        );

        // fallback: look for first empty wix-code panel container
        if (containers.length === 0) {
            const fallback =
                document.querySelector(
                    "[data-app-origin], [data-instance-id]",
                ) || document.body;
            containers.push(fallback);
        }

        return containers;
    }

    // Initialize all containers on DOM ready
    function init() {
        const containers = findWidgetContainers();
        containers.forEach((container) => {
            // Avoid double-init
            if (container.__d4d_initialized) return;
            container.__d4d_initialized = true;

            // If container is body, create a dedicated wrapper
            if (container === document.body) {
                const wrapper = document.createElement("div");
                wrapper.className = "d4d-widget-wrapper";
                wrapper.style.width = "100%";
                wrapper.style.maxWidth = "900px";
                wrapper.style.margin = "0 auto";
                document.body.appendChild(wrapper);
                createWidgetIframe(wrapper);
            } else {
                createWidgetIframe(container);
            }
        });
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }

    // Expose for debugging
    window.D4DWidget = { createWidgetIframe, DEFAULT_APP_ORIGIN };
})();
