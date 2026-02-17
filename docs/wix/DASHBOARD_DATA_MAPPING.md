# Voter Dashboard - UI to Backend Mapping

This document shows the exact mapping between Wix UI elements and backend API data.

## Dashboard Elements

### Element: `#dashboardHeading`

**Component ID:** `comp-mlizfi2w`  
**Type:** Text  
**Data Source:** `dashboard.voter.full_name`  
**API Endpoint:** `GET /api/v1/voters/{uuid}`  
**Example Value:** `"Welcome, John Smith"`

```javascript
const dashboard = await getVoterDashboard();
$w("#dashboardHeading").text = `Welcome, ${dashboard.voter.full_name}`;
```

---

### Element: `#balanceAmount`

**Component ID:** `comp-mlizfk1o`  
**Type:** Text  
**Data Source:** `earnings.total_earned`  
**API Endpoint:** `GET /api/v1/voters/{uuid}/earnings`  
**Example Value:** `"$247.83"`

```javascript
const dashboard = await getVoterDashboard();
$w("#balanceAmount").text = formatCurrency(dashboard.balance);
// backend maps: earnings.total_earned → dashboard.balance
```

**Backend Response:**

```json
{
    "total_earned": 247.83,
    "pending_payout": 52.4,
    "total_paid": 195.43
}
```

---

### Element: `#pendingAmount`

**Component ID:** `comp-mlizflhi`  
**Type:** Text  
**Data Source:** `earnings.pending_payout`  
**API Endpoint:** `GET /api/v1/voters/{uuid}/earnings`  
**Example Value:** `"$52.40"`

```javascript
const dashboard = await getVoterDashboard();
$w("#pendingAmount").text = formatCurrency(dashboard.pending);
// backend maps: earnings.pending_payout → dashboard.pending
```

---

### Element: `#notificationCount`

**Component ID:** `comp-mlizfmnk`  
**Type:** Text  
**Data Source:** `notifications.unreadCount`  
**API Endpoint:** _TODO: Not yet implemented_  
**Example Value:** `"3"`

```javascript
const notifications = await getNotifications();
$w("#notificationCount").text = notifications.unreadCount.toString();
```

**Note:** This endpoint needs to be implemented in Laravel backend. Placeholder returns mock data.

---

### Element: `#tokenCount`

**Component ID:** `comp-mlizfnxf`  
**Type:** Text  
**Data Source:** `notifications.availableTokens`  
**API Endpoint:** _TODO: Should query ad_view_tokens table_  
**Example Value:** `"12"`

```javascript
const notifications = await getNotifications();
$w("#tokenCount").text = notifications.availableTokens.toString();
```

**Note:** This should query the `ad_view_tokens` table for unused tokens belonging to the voter.

---

### Element: `#viewHistoryItems`

**Component ID:** `comp-mlizfrdv`  
**Type:** Repeater  
**Data Source:** Array of `view_sessions`  
**API Endpoint:** `GET /api/v1/voters/{uuid}/history`  
**Example Data:** See below

```javascript
const history = await getViewHistory(1);
setupViewHistoryRepeater(history.data);
```

**Repeater Item Mapping:**

| Repeater Element  | Backend Field                   | Example Value           |
| ----------------- | ------------------------------- | ----------------------- |
| `#campaignName`   | `campaign.title`                | "Vote for Education"    |
| `#politicianName` | `campaign.politician.full_name` | "Sarah Johnson"         |
| `#viewTime`       | `completed_at` or `started_at`  | "Feb 14, 2026, 3:30 PM" |
| `#earnings`       | `voter_payout_amount`           | "$0.25"                 |
| `#status`         | `status` + `payment_status`     | "Paid" (green)          |
| `#watchTime`      | `watch_time_seconds`            | "2:00"                  |
| `#completion`     | `completion_percentage`         | "100%"                  |

**Backend Response:**

```json
{
    "data": [
        {
            "uuid": "session-789",
            "campaign": {
                "title": "Vote for Education Reform",
                "politician": {
                    "full_name": "Sarah Johnson"
                }
            },
            "status": "completed",
            "payment_status": "paid",
            "voter_payout_amount": 0.25,
            "watch_time_seconds": 120,
            "completion_percentage": 100.0,
            "started_at": "2026-02-14T15:28:00Z",
            "completed_at": "2026-02-14T15:30:00Z"
        }
    ],
    "current_page": 1,
    "last_page": 3,
    "total": 45
}
```

---

## Status Color Coding

The `#status` element color is determined by `getStatusDisplay()` function:

| Status                  | Color Code | Display Text      |
| ----------------------- | ---------- | ----------------- |
| `completed` + `paid`    | `#60BC57`  | "Paid"            |
| `completed` + `pending` | `#FAC249`  | "Pending Payment" |
| `in_progress`           | `#FAC249`  | "In Progress"     |
| `assigned`              | `#3899EC`  | "Assigned"        |
| `flagged`               | `#EE5951`  | "Flagged"         |
| `expired`               | `#8B949E`  | "Expired"         |
| `on_hold`               | `#EE5951`  | "On Hold"         |
| `failed`                | `#EE5951`  | "Payment Failed"  |

```javascript
function getStatusDisplay(sessionStatus, paymentStatus) {
    if (sessionStatus === "completed" && paymentStatus === "paid") {
        return { text: "Paid", color: "#60BC57" };
    }
    // ... see voter-dashboard.js for complete implementation
}
```

---

## Optional Elements

### Element: `#referralCode`

**Type:** Text  
**Data Source:** `dashboard.referralCode`  
**API Endpoint:** `GET /api/v1/voters/{uuid}`  
**Example Value:** `"JOHN2024"`

```javascript
if (dashboard.referralCode && $w("#referralCode")) {
    $w("#referralCode").text = dashboard.referralCode;
}
```

---

### Element: `#availableCampaigns`

**Type:** Repeater  
**Data Source:** Array of available campaigns  
**API Endpoint:** `GET /api/v1/voters/{uuid}/campaigns`

**Repeater Item Mapping:**

| Element            | Backend Field      | Example Value            |
| ------------------ | ------------------ | ------------------------ |
| `#campaignTitle`   | `title`            | "Healthcare Initiative"  |
| `#campaignSummary` | `message_summary`  | "Learn about my plan..." |
| `#payoutAmount`    | `payout`           | "$0.25"                  |
| `#duration`        | `media_duration`   | "2:00"                   |
| `#politicianName`  | `politician`       | "Michael Brown"          |
| `#governanceLevel` | `governance_level` | "City"                   |
| `#watchButton`     | Action button      | Opens video player       |

---

## Helper Functions

### `formatCurrency(amount)`

Converts number to currency string

```javascript
formatCurrency(247.83); // "$247.83"
formatCurrency(0.25); // "$0.25"
```

### `formatDate(dateString)`

Converts ISO date to readable format

```javascript
formatDate("2026-02-14T15:30:00Z"); // "Feb 14, 2026, 3:30 PM"
```

### `formatDuration(seconds)`

Converts seconds to MM:SS format

```javascript
formatDuration(120); // "2:00"
formatDuration(45); // "0:45"
```

---

## Backend API Endpoints

### Primary Endpoints Used by Dashboard

| Endpoint                          | Method | Purpose                      | Response Time |
| --------------------------------- | ------ | ---------------------------- | ------------- |
| `/api/v1/voters/{uuid}`           | GET    | Get voter profile            | ~100ms        |
| `/api/v1/voters/{uuid}/earnings`  | GET    | Get earnings summary         | ~150ms        |
| `/api/v1/voters/{uuid}/campaigns` | GET    | Get available campaigns      | ~200ms        |
| `/api/v1/voters/{uuid}/history`   | GET    | Get view history (paginated) | ~250ms        |
| `/api/v1/voters/{uuid}/referrals` | GET    | Get referral info            | ~150ms        |

### Session Endpoints (Used by Video Player)

| Endpoint                                           | Method | Purpose                            |
| -------------------------------------------------- | ------ | ---------------------------------- |
| `/api/v1/voters/{uuid}/campaigns/{campaign}/watch` | POST   | Start view session                 |
| `/api/v1/sessions/{session}/progress`              | POST   | Track viewing progress (heartbeat) |
| `/api/v1/sessions/{session}/complete`              | POST   | Mark view as completed             |

---

## Data Loading Strategy

The dashboard uses **parallel loading** for optimal performance:

```javascript
// Load all data simultaneously
const [dashboard, campaigns, viewHistory, referrals, notifications] =
    await Promise.all([
        getVoterDashboard(), // ~100-150ms
        getCampaigns(), // ~200ms
        getViewHistory(1), // ~250ms
        getReferrals(), // ~150ms
        getNotifications(), // instant (mock)
    ]);
// Total time: ~250ms (limited by slowest call)
// vs. ~850ms if loaded sequentially
```

---

## Error States

### Loading State

```javascript
$w("#dashboardHeading").text = "Loading Dashboard...";
$w("#balanceAmount").text = "...";
```

### Error State

```javascript
$w("#dashboardHeading").text = "Error Loading Dashboard";
$w("#errorMessage").text = "Unable to load data. Please try again.";
```

### No Data State

```javascript
$w("#noHistoryMessage").show();
$w("#viewHistoryItems").hide();
```

---

## Testing the Integration

### 1. Test Data Fetch

Open browser console on voter dashboard page:

```javascript
import { getVoterDashboard } from "backend/campaigns.jsw";

const data = await getVoterDashboard();
console.log("Balance:", data.balance);
console.log("Pending:", data.pending);
console.log("Views Completed:", data.viewsCompleted);
```

### 2. Test Repeater Data

```javascript
import { getViewHistory } from "backend/campaigns.jsw";

const history = await getViewHistory(1);
console.log("History items:", history.data.length);
console.log("First item:", history.data[0]);
```

### 3. Verify API Connection

```javascript
import { apiGet } from "backend/api.jsw";

const health = await apiGet("/health");
console.log("API Status:", health.status); // Should be 'ok'
```

---

## Implementation Checklist

- [x] Create backend modules (`api.jsw`, `campaigns.jsw`, `members.jsw`)
- [x] Create voter dashboard frontend (`voter-dashboard.js`)
- [x] Map all UI elements to backend data
- [x] Implement view history repeater
- [x] Add campaign repeater (optional)
- [x] Implement helper functions (currency, date, duration formatting)
- [x] Add error handling and loading states
- [ ] **TODO:** Implement real Wix Member authentication
- [ ] **TODO:** Create notification/token API endpoint in Laravel
- [ ] **TODO:** Add caching for dashboard data
- [ ] **TODO:** Implement real-time updates

---

## Support & References

- Backend Integration Guide: [BACKEND_INTEGRATION.md](./BACKEND_INTEGRATION.md)
- Laravel API Routes: `/routes/api.php`
- Controller Logic: `/app/Http/Controllers/Api/VoterController.php`
- View Session Model: `/app/Models/ViewSession.php`
