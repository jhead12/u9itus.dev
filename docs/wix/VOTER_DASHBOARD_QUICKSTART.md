# Quick Start: Voter Dashboard Integration

This guide will help you quickly integrate the voter dashboard with backend data.

## Prerequisites

- Wix Editor with Velo enabled
- Access to backend code panel
- Laravel backend deployed and running

## Step 1: Add Backend Modules

1. Open your Wix site in the Editor
2. Click **Code Files** panel (left sidebar)
3. Hover over **Backend** section, click **+**
4. Create these files:

### File: `backend/api.jsw`

Copy contents from: `/src/backend/api.jsw`

### File: `backend/campaigns.jsw`

Copy contents from: `/src/backend/campaigns.jsw`

### File: `backend/members.jsw`

Copy contents from: `/src/backend/members.jsw`

## Step 2: Create Voter Dashboard Page

1. In Wix Editor, create a new page: **Voter Dashboard**
2. Add these elements (use exact nicknames):

### Required Elements:

| Element Type | Nickname             | Purpose              |
| ------------ | -------------------- | -------------------- |
| Text         | `#dashboardHeading`  | Welcome message      |
| Text         | `#balanceAmount`     | Total earned balance |
| Text         | `#pendingAmount`     | Pending payout       |
| Text         | `#notificationCount` | Unread notifications |
| Text         | `#tokenCount`        | Available ad tokens  |
| Repeater     | `#viewHistoryItems`  | View history list    |

### Repeater Items (inside `#viewHistoryItems`):

| Element Type | Nickname          | Purpose          |
| ------------ | ----------------- | ---------------- |
| Text         | `#campaignName`   | Campaign title   |
| Text         | `#politicianName` | Politician name  |
| Text         | `#viewTime`       | View date/time   |
| Text         | `#earnings`       | Amount earned    |
| Text         | `#status`         | Payment status   |
| Text         | `#watchTime`      | Duration watched |
| Text         | `#completion`     | Completion %     |

### Optional Elements:

| Element Type | Nickname              | Purpose               |
| ------------ | --------------------- | --------------------- |
| Text         | `#referralCode`       | Referral code display |
| Button       | `#refreshButton`      | Refresh dashboard     |
| Button       | `#copyReferralButton` | Copy referral code    |
| Repeater     | `#availableCampaigns` | Available campaigns   |
| Text         | `#errorMessage`       | Error display         |
| Text         | `#noHistoryMessage`   | No data message       |

## Step 3: Add Page Code

1. Click on the page in Wix Editor
2. Open the **Code Panel** (bottom of screen)
3. Replace the default code with contents from: `/src/pages/voter-dashboard.js`

Or copy this minimal version:

```javascript
import { getVoterDashboard, getViewHistory } from "backend/campaigns.jsw";

$w.onReady(async function () {
    await initializeDashboard();
});

async function initializeDashboard() {
    try {
        // Fetch dashboard data
        const [dashboard, viewHistory] = await Promise.all([
            getVoterDashboard(),
            getViewHistory(1),
        ]);

        // Update dashboard stats
        $w("#dashboardHeading").text = `Welcome, ${dashboard.voter.full_name}`;
        $w("#balanceAmount").text = `$${dashboard.balance.toFixed(2)}`;
        $w("#pendingAmount").text = `$${dashboard.pending.toFixed(2)}`;
        $w("#notificationCount").text = "3"; // TODO: Implement
        $w("#tokenCount").text = "12"; // TODO: Implement

        // Setup view history
        setupViewHistory(viewHistory.data);
    } catch (error) {
        console.error("Failed to load dashboard:", error);
        $w("#dashboardHeading").text = "Error Loading Dashboard";
    }
}

function setupViewHistory(data) {
    const repeater = $w("#viewHistoryItems");

    repeater.onItemReady(($item, itemData) => {
        $item("#campaignName").text = itemData.campaign?.title || "N/A";
        $item("#viewTime").text = new Date(
            itemData.completed_at,
        ).toLocaleDateString();
        $item("#earnings").text = `$${itemData.voter_payout_amount.toFixed(2)}`;
        $item("#status").text = itemData.payment_status || "Pending";
    });

    repeater.data = data.map((item) => ({
        ...item,
        _id: item.uuid,
    }));
}
```

## Step 4: Configure Backend URL

In `backend/api.jsw`, update the API_BASE URL:

```javascript
// For Production
const API_BASE = "https://u9itus-production.up.railway.app/api/v1";

// For Development
// const API_BASE = 'http://localhost:8000/api/v1';
```

## Step 5: Setup Voter Authentication

### Option A: Using Wix Members (Recommended)

In `backend/campaigns.jsw`, the code already integrates with Wix Members:

```javascript
// Uses authenticated Wix member to get/create voter
const voter = await getOrCreateVoterForMember();
```

**Required:** Install Wix Members app on your site:

1. Add Wix Members app to your site
2. Enable member login on the dashboard page
3. The code automatically syncs member → voter

### Option B: Test Mode (Development Only)

For testing without authentication, use a static voter UUID:

```javascript
// In backend/campaigns.jsw, getVoterUuid() function:
return "your-test-voter-uuid-123";
```

**Get a test voter UUID:**

1. Create a voter via API:
    ```bash
    curl -X POST https://your-backend.com/api/v1/voters \
      -H "Content-Type: application/json" \
      -d '{
        "email": "test@example.com",
        "full_name": "Test Voter",
        "zip_code": "10001"
      }'
    ```
2. Copy the returned `uuid` from the response
3. Use it in your test code

## Step 6: Test the Integration

### Test 1: Preview the Page

1. Click **Preview** in Wix Editor
2. Navigate to Voter Dashboard page
3. Check browser console for any errors

### Test 2: Verify Data Loading

Open browser console and run:

```javascript
// Test backend connection
import { getVoterDashboard } from "backend/campaigns.jsw";
const data = await getVoterDashboard();
console.log(data);
```

Expected output:

```javascript
{
  balance: 247.83,
  pending: 52.40,
  voter: { full_name: "Test Voter", ... },
  viewsCompleted: 15,
  ...
}
```

### Test 3: Check API Connection

```javascript
import { apiGet } from "backend/api.jsw";
const health = await apiGet("/health");
console.log(health); // { status: 'ok', ... }
```

## Step 7: Publish Your Site

1. Click **Publish** in Wix Editor
2. Test on live site
3. Monitor for errors in browser console

## Common Issues & Fixes

### Issue: "Cannot find module 'backend/campaigns.jsw'"

**Fix:** Ensure backend files are in the **Backend** section of Code Files panel, not Public or Page Code.

---

### Issue: "CORS error" when calling API

**Fix:** Add your Wix site URL to Laravel CORS config:

```php
// config/cors.php
'allowed_origins' => [
    'https://your-wix-site.com',
    'https://your-wix-site.wixsite.com',
    'https://editor.wixsite.com'
],
```

---

### Issue: "No voter UUID found"

**Fix:** Either:

1. Log in with Wix Members (if using Option A)
2. Create a test voter and hardcode UUID (if using Option B)

```javascript
// In backend/campaigns.jsw
async function getVoterUuid() {
    return "abc-123-def-456"; // Your test voter UUID
}
```

---

### Issue: Data not updating

**Fix:** Clear browser cache and Wix preview cache:

- In Wix Editor: **Dev Mode** → **Clear Preview Cache**
- In browser: Hard refresh (Ctrl+Shift+R or Cmd+Shift+R)

---

### Issue: "Rate limit exceeded"

**Fix:** Backend has rate limits (60 req/min). Reduce API calls:

- Use `Promise.all()` for parallel requests
- Implement caching
- Don't call API in loops

---

## Next Steps

### Add Campaign Viewing

Create a video player lightbox:

1. Add a **Lightbox** named "VideoPlayerLightbox"
2. Add video player element
3. Connect to campaign watch endpoint

```javascript
// In dashboard code
function launchVideoPlayer(campaignUuid) {
    wixWindow.openLightbox("VideoPlayerLightbox", {
        campaign: campaignUuid,
    });
}
```

### Implement Real-Time Updates

Use Wix Realtime to push balance updates:

```javascript
import wixRealtime from "wix-realtime";

wixRealtime.subscribe("balance-update", (data) => {
    $w("#balanceAmount").text = `$${data.newBalance.toFixed(2)}`;
});
```

### Add Notifications

Create notification endpoint in Laravel backend:

```php
// routes/api.php
Route::get('/voters/{voter}/notifications', [VoterController::class, 'notifications']);
```

Then update `backend/campaigns.jsw`:

```javascript
export async function getNotifications() {
    const voterUuid = await getVoterUuid();
    return await apiGet(`/voters/${voterUuid}/notifications`);
}
```

## Resources

- **Full Code Reference:** `/src/pages/voter-dashboard.js`
- **Data Mapping Guide:** [DASHBOARD_DATA_MAPPING.md](./DASHBOARD_DATA_MAPPING.md)
- **Integration Guide:** [BACKEND_INTEGRATION.md](./BACKEND_INTEGRATION.md)
- **Wix Velo API:** https://www.wix.com/velo/reference/api-overview
- **Laravel API Routes:** `/routes/api.php`

## Support

If you encounter issues:

1. Check browser console for errors
2. Verify backend is running: `https://your-backend.com/api/health`
3. Review Laravel logs: `storage/logs/laravel.log`
4. Test API endpoints directly with Postman

---

**Ready to go!** 🚀 Your voter dashboard is now connected to real backend data.
