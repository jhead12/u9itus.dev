/**
 * U9itus – Wix Dashboard Page Extension
 *
 * This module initializes the Wix Dashboard SDK and communicates
 * with our Laravel backend API to render the politician/voter dashboard
 * inside the Wix Dashboard iframe.
 */

import { dashboard } from "@wix/dashboard";

/**
 * Initialize the Wix Dashboard page.
 * Called automatically when the dashboard page loads inside Wix.
 */
export async function init() {
    try {
        // Show a toast notification on load
        dashboard.showToast({
            message: "U9itus – Political Loyalty Ads loaded",
            type: "success",
        });
    } catch (error) {
        console.error("Failed to initialize Wix Dashboard:", error);
    }
}

/**
 * Navigate to a dashboard page within Wix.
 */
export function navigateTo(path) {
    dashboard.navigate({ pageId: path });
}

/**
 * Show a confirmation modal using Wix SDK.
 */
export async function confirmAction(title, message) {
    try {
        const result = await dashboard.showConfirmationDialog({
            title,
            content: message,
            primaryAction: { label: "Confirm" },
            secondaryAction: { label: "Cancel" },
        });
        return result === "PRIMARY";
    } catch {
        return false;
    }
}

// Auto-init when loaded
if (typeof window !== "undefined") {
    window.addEventListener("DOMContentLoaded", init);
}
