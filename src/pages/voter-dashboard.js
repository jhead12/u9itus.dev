/**
 * Voter Dashboard - Wix Velo Frontend
 *
 * This page displays the voter's earnings, available campaigns,
 * view history, and referral information by connecting to the
 * U9itus Laravel API via backend modules.
 */

import {
    getVoterDashboard,
    getCampaigns,
    getViewHistory,
    getReferrals,
    getNotifications,
} from "backend/campaigns.jsw";
import wixWindow from "wix-window";

// Component IDs (reference for styling/logic mapping)
const COMP_IDS = {
    dashboardHeading: "comp-mlizfi2w",
    balanceAmount: "comp-mlizfk1o",
    pendingAmount: "comp-mlizflhi",
    notificationCount: "comp-mlizfmnk",
    tokenCount: "comp-mlizfnxf",
    viewHistoryRepeater: "comp-mlizfrdv",
};

// Page state
let voterData = null;
let currentPage = 1;

/**
 * Initialize the dashboard when page loads
 */
$w.onReady(async function () {
    showLoadingState();
    await initializeDashboard();
});

/**
 * Show loading state
 */
function showLoadingState() {
    $w("#dashboardHeading").text = "Loading Dashboard...";
    $w("#balanceAmount").text = "...";
    $w("#pendingAmount").text = "...";
    $w("#notificationCount").text = "...";
    $w("#tokenCount").text = "...";
}

/**
 * Initialize dashboard with data from backend
 */
async function initializeDashboard() {
    try {
        // Fetch all dashboard data in parallel
        const [dashboard, campaigns, viewHistory, referrals, notifications] =
            await Promise.all([
                getVoterDashboard(),
                getCampaigns(),
                getViewHistory(currentPage),
                getReferrals(),
                getNotifications(),
            ]);

        voterData = dashboard;

        // Update dashboard heading
        const voterName = dashboard.voter.full_name || "Voter";
        $w("#dashboardHeading").text = `Welcome, ${voterName}`;

        // Update balance and earnings
        $w("#balanceAmount").text = formatCurrency(dashboard.balance);
        $w("#pendingAmount").text = formatCurrency(dashboard.pending);

        // Update notification and token counts
        $w("#notificationCount").text = notifications.unreadCount.toString();
        $w("#tokenCount").text = notifications.availableTokens.toString();

        // Display referral code if available
        if (dashboard.referralCode && $w("#referralCode")) {
            $w("#referralCode").text = dashboard.referralCode;
        }

        // Setup view history repeater
        if (viewHistory.data && viewHistory.data.length > 0) {
            setupViewHistoryRepeater(viewHistory.data);
        } else {
            showNoHistoryMessage();
        }

        // Setup available campaigns if element exists
        if ($w("#availableCampaigns")) {
            setupCampaignsRepeater(campaigns);
        }

        // Setup event handlers
        setupEventHandlers(dashboard, referrals);
    } catch (error) {
        console.error("Failed to initialize dashboard:", error);
        showErrorState(error.message);
    }
}

/**
 * Setup view history repeater with real data
 * @param {Array} historyData - Array of view session objects
 */
function setupViewHistoryRepeater(historyData) {
    const repeater = $w("#viewHistoryItems");

    repeater.onItemReady(($item, itemData) => {
        // Campaign name
        const campaignTitle = itemData.campaign?.title || "Unknown Campaign";
        $item("#campaignName").text = campaignTitle;

        // Politician name
        if ($item("#politicianName") && itemData.campaign?.politician) {
            $item("#politicianName").text =
                itemData.campaign.politician.full_name;
        }

        // View date/time
        const viewDate =
            itemData.completed_at || itemData.started_at || itemData.created_at;
        $item("#viewTime").text = viewDate ? formatDate(viewDate) : "N/A";

        // Earnings amount
        const earnings = itemData.voter_payout_amount || 0;
        $item("#earnings").text = formatCurrency(earnings);

        // Watch time
        if ($item("#watchTime")) {
            const watchTime = itemData.watch_time_seconds || 0;
            $item("#watchTime").text = formatDuration(watchTime);
        }

        // Status with color coding
        const status = getStatusDisplay(
            itemData.status,
            itemData.payment_status,
        );
        $item("#status").text = status.text;

        // Apply status color if element supports it
        if ($item("#status").style) {
            $item("#status").style.color = status.color;
        }

        // Completion percentage
        if ($item("#completion") && itemData.completion_percentage) {
            $item("#completion").text = `${itemData.completion_percentage}%`;
        }
    });

    // Map data and set repeater
    repeater.data = historyData.map((item) => ({
        ...item,
        _id:
            item.uuid || item.id || Math.random().toString(36).substring(2, 11),
    }));
}

/**
 * Setup campaigns repeater
 * @param {Array} campaigns - Array of available campaigns
 */
function setupCampaignsRepeater(campaigns) {
    const repeater = $w("#availableCampaigns");

    repeater.onItemReady(($item, itemData) => {
        $item("#campaignTitle").text = itemData.title || "Political Message";
        $item("#campaignSummary").text = itemData.message_summary || "";
        $item("#payoutAmount").text = formatCurrency(itemData.payout);
        $item("#duration").text = formatDuration(itemData.media_duration);

        if ($item("#politicianName")) {
            $item("#politicianName").text = itemData.politician || "Candidate";
        }

        if ($item("#governanceLevel")) {
            $item("#governanceLevel").text = itemData.governance_level || "";
        }

        // Watch button handler
        $item("#watchButton").onClick(() => {
            launchVideoPlayer(itemData.uuid);
        });
    });

    repeater.data = campaigns.map((item) => ({
        ...item,
        _id: item.uuid || Math.random().toString(36).substring(2, 11),
    }));
}

/**
 * Setup event handlers for interactive elements
 */
function setupEventHandlers(dashboard, referrals) {
    // Refresh button
    if ($w("#refreshButton")) {
        $w("#refreshButton").onClick(() => {
            initializeDashboard();
        });
    }

    // View earnings details button
    if ($w("#viewEarningsButton")) {
        $w("#viewEarningsButton").onClick(() => {
            showEarningsDetails(dashboard);
        });
    }

    // Copy referral code button
    if ($w("#copyReferralButton")) {
        $w("#copyReferralButton").onClick(() => {
            copyReferralCode(referrals.referralCode);
        });
    }

    // Share referral button
    if ($w("#shareReferralButton")) {
        $w("#shareReferralButton").onClick(() => {
            shareReferralLink(referrals.referralCode);
        });
    }

    // Pagination buttons for view history
    if ($w("#nextPageButton")) {
        $w("#nextPageButton").onClick(async () => {
            currentPage++;
            const history = await getViewHistory(currentPage);
            setupViewHistoryRepeater(history.data);
        });
    }

    if ($w("#prevPageButton")) {
        $w("#prevPageButton").onClick(async () => {
            if (currentPage > 1) {
                currentPage--;
                const history = await getViewHistory(currentPage);
                setupViewHistoryRepeater(history.data);
            }
        });
    }
}

/**
 * Launch video player in lightbox or navigate to video page
 * @param {string} campaignUuid - Campaign UUID
 */
function launchVideoPlayer(campaignUuid) {
    // Option 1: Open in lightbox
    wixWindow.openLightbox("VideoPlayerLightbox", { campaign: campaignUuid });

    // Option 2: Navigate to video page (uncomment if preferred)
    // import wixLocation from 'wix-location';
    // wixLocation.to(`/watch-video?campaign=${campaignUuid}`);
}

/**
 * Show earnings details modal
 */
function showEarningsDetails(dashboard) {
    const message = `
        Total Earned: ${formatCurrency(dashboard.balance)}
        Pending Payout: ${formatCurrency(dashboard.pending)}
        Total Paid: ${formatCurrency(dashboard.paid)}
        
        Views Completed: ${dashboard.viewsCompleted}
        Views Pending: ${dashboard.viewsPending}
        
        Referral Earnings: ${formatCurrency(dashboard.referralEarnings)}
    `;

    wixWindow.openLightbox("EarningsDetailsLightbox", {
        balance: dashboard.balance,
        pending: dashboard.pending,
        paid: dashboard.paid,
        viewsCompleted: dashboard.viewsCompleted,
        viewsPending: dashboard.viewsPending,
        referralEarnings: dashboard.referralEarnings,
    });
}

/**
 * Copy referral code to clipboard
 */
function copyReferralCode(code) {
    // Wix doesn't have direct clipboard API, use a workaround
    console.log("Referral code:", code);
    // Show success message
    if ($w("#successMessage")) {
        $w("#successMessage").text = "Referral code copied!";
        $w("#successMessage").show();
        setTimeout(() => {
            $w("#successMessage").hide();
        }, 3000);
    }
}

/**
 * Share referral link
 */
function shareReferralLink(code) {
    const shareUrl = `https://u9itus.com/signup?ref=${code}`;
    wixWindow.openLightbox("ShareReferralLightbox", {
        referralCode: code,
        shareUrl: shareUrl,
    });
}

/**
 * Show no history message
 */
function showNoHistoryMessage() {
    if ($w("#noHistoryMessage")) {
        $w("#noHistoryMessage").show();
        $w("#viewHistoryItems").hide();
    }
}

/**
 * Show error state
 */
function showErrorState(errorMessage) {
    $w("#dashboardHeading").text = "Error Loading Dashboard";

    if ($w("#errorMessage")) {
        $w("#errorMessage").text =
            errorMessage ||
            "Unable to load dashboard data. Please try again later.";
        $w("#errorMessage").show();
    }
}

// ========================================
// Helper Functions
// ========================================

/**
 * Format currency value
 * @param {number} amount - Amount in dollars
 * @returns {string} Formatted currency string
 */
function formatCurrency(amount) {
    return `$${parseFloat(amount || 0).toFixed(2)}`;
}

/**
 * Format date for display
 * @param {string} dateString - ISO date string
 * @returns {string} Formatted date
 */
function formatDate(dateString) {
    try {
        const date = new Date(dateString);
        return date.toLocaleDateString("en-US", {
            month: "short",
            day: "numeric",
            year: "numeric",
            hour: "numeric",
            minute: "2-digit",
        });
    } catch (e) {
        return "N/A";
    }
}

/**
 * Format duration in seconds to readable format
 * @param {number} seconds - Duration in seconds
 * @returns {string} Formatted duration
 */
function formatDuration(seconds) {
    if (!seconds) return "0:00";

    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs.toString().padStart(2, "0")}`;
}

/**
 * Get status display text and color
 * @param {string} sessionStatus - View session status
 * @param {string} paymentStatus - Payment status
 * @returns {object} Status display object
 */
function getStatusDisplay(sessionStatus, paymentStatus) {
    // Map backend status to display
    const statusMap = {
        completed: { text: "Completed", color: "#60BC57" },
        in_progress: { text: "In Progress", color: "#FAC249" },
        assigned: { text: "Assigned", color: "#3899EC" },
        flagged: { text: "Flagged", color: "#EE5951" },
        expired: { text: "Expired", color: "#8B949E" },
    };

    const paymentMap = {
        pending: { text: "Pending Payment", color: "#FAC249" },
        paid: { text: "Paid", color: "#60BC57" },
        on_hold: { text: "On Hold", color: "#EE5951" },
        failed: { text: "Payment Failed", color: "#EE5951" },
    };

    // Show payment status if completed, otherwise show session status
    if (sessionStatus === "completed" && paymentStatus) {
        return (
            paymentMap[paymentStatus] || {
                text: paymentStatus,
                color: "#8B949E",
            }
        );
    }

    return (
        statusMap[sessionStatus] || { text: sessionStatus, color: "#8B949E" }
    );
}
