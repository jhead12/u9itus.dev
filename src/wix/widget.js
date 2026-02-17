/**
 * U9itus – Wix Site Widget Extension
 *
 * This is the embeddable widget that renders on Wix site pages.
 * It displays available political campaigns and the video player
 * for voters to watch and earn.
 */

const API_BASE = window.location.origin + "/api/v1";

/**
 * Fetch available campaigns for a voter.
 */
export async function fetchCampaigns(voterId) {
    const res = await fetch(`${API_BASE}/voters/${voterId}/campaigns`);
    return res.json();
}

/**
 * Start a view session for a campaign.
 */
export async function startView(voterId, campaignId) {
    const res = await fetch(
        `${API_BASE}/voters/${voterId}/campaigns/${campaignId}/watch`,
        {
            method: "POST",
            headers: { "Content-Type": "application/json" },
        },
    );
    return res.json();
}

/**
 * Send a heartbeat to track viewing progress.
 */
export async function trackProgress(sessionUuid, secondsWatched) {
    const res = await fetch(`${API_BASE}/sessions/${sessionUuid}/progress`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ seconds_watched: secondsWatched }),
    });
    return res.json();
}

/**
 * Complete the view and trigger payout.
 */
export async function completeView(sessionUuid, totalSeconds) {
    const res = await fetch(`${API_BASE}/sessions/${sessionUuid}/complete`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({ total_seconds_watched: totalSeconds }),
    });
    return res.json();
}

/**
 * Register a new voter.
 */
export async function registerVoter(data) {
    const res = await fetch(`${API_BASE}/voters`, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(data),
    });
    return res.json();
}

/**
 * Get voter earnings summary.
 */
export async function getEarnings(voterId) {
    const res = await fetch(`${API_BASE}/voters/${voterId}/earnings`);
    return res.json();
}
