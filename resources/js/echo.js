/**
 * echo.js — U9itus Phase 11 WebSocket listener helpers
 *
 * Import this module in role-specific dashboard JS bundles:
 *   import { listenAsPolitician, listenAsVoter, listenAsAdmin } from './echo';
 *
 * All listeners are no-ops when Echo is not initialised (no REVERB key in env),
 * so the app degrades gracefully in environments without a Reverb server.
 */

/*
|--------------------------------------------------------------------------
| Guard — returns false when Echo is not configured
|--------------------------------------------------------------------------
*/
function echoReady() {
    return typeof window.Echo !== "undefined";
}

/*
|--------------------------------------------------------------------------
| Politician listeners
| Channel: private-politician.{userId}
|--------------------------------------------------------------------------
*/
export function listenAsPolitician(
    userId,
    { onApproved = () => {}, onRejected = () => {}, onStopped = () => {} } = {},
) {
    if (!echoReady()) return;

    const channel = window.Echo.private(`politician.${userId}`);

    channel.listen(".campaign.approved", (data) => {
        console.debug("[Echo] campaign.approved", data);
        onApproved(data);
    });

    channel.listen(".campaign.rejected", (data) => {
        console.debug("[Echo] campaign.rejected", data);
        onRejected(data);
    });

    channel.listen(".campaign.stopped", (data) => {
        console.debug("[Echo] campaign.stopped", data);
        onStopped(data);
    });

    return channel;
}

/*
|--------------------------------------------------------------------------
| Voter listeners
| Channel: private-voter.{userId}
|--------------------------------------------------------------------------
*/
export function listenAsVoter(
    userId,
    {
        onAdReady = () => {},
        onSessionCompleted = () => {},
        onPayoutProcessed = () => {},
    } = {},
) {
    if (!echoReady()) return;

    const channel = window.Echo.private(`voter.${userId}`);

    channel.listen(".ad.token.delivered", (data) => {
        console.debug("[Echo] ad.token.delivered", data);
        onAdReady(data);
    });

    channel.listen(".session.completed", (data) => {
        console.debug("[Echo] session.completed", data);
        onSessionCompleted(data);
    });

    channel.listen(".payout.processed", (data) => {
        console.debug("[Echo] payout.processed", data);
        onPayoutProcessed(data);
    });

    return channel;
}

/*
|--------------------------------------------------------------------------
| Admin monitor listener
| Channel: private-admin.monitor
|--------------------------------------------------------------------------
*/
export function listenAsAdmin({
    onFraudFlag = () => {},
    onSessionCompleted = () => {},
} = {}) {
    if (!echoReady()) return;

    const channel = window.Echo.private("admin.monitor");

    channel.listen(".fraud.flag.raised", (data) => {
        console.debug("[Echo] fraud.flag.raised", data);
        onFraudFlag(data);
    });

    channel.listen(".session.completed", (data) => {
        console.debug("[Echo] session.completed (admin)", data);
        onSessionCompleted(data);
    });

    return channel;
}

/*
|--------------------------------------------------------------------------
| Live campaign presence channel  (Phase 12 WebRTC signaling foundation)
| Channel: presence-campaign.live.{campaignUuid}
|--------------------------------------------------------------------------
*/
export function joinCampaignLive(
    campaignUuid,
    {
        onHere = () => {}, // initial member list
        onJoining = () => {}, // someone joins
        onLeaving = () => {}, // someone leaves
        onLiveStarted = () => {}, // live feed started event
    } = {},
) {
    if (!echoReady()) return;

    const channel = window.Echo.join(`campaign.live.${campaignUuid}`);

    channel
        .here(onHere)
        .joining(onJoining)
        .leaving(onLeaving)
        .listen(".campaign.live.started", (data) => {
            console.debug("[Echo] campaign.live.started", data);
            onLiveStarted(data);
        });

    return channel;
}

/*
|--------------------------------------------------------------------------
| Utility — show a toast notification (Alpine.js / Tailwind)
|
| Usage:
|   import { toast } from './echo';
|   toast('$0.25 has been added to your wallet.', 'success');
|
| The host template must define an Alpine component with id="toast-container"
| that exposes show(message, type). See layouts/app.blade.php.
|--------------------------------------------------------------------------
*/
export function toast(message, type = "info") {
    const el = document.getElementById("toast-container");
    if (el && el.__x) {
        el.__x.$data.show(message, type);
    } else {
        console.info(`[Toast] ${type}: ${message}`);
    }
}
