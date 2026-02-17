# Wix Backend Integration Guide

This document explains how the Wix frontend connects to the U9itus Laravel backend API.

## Architecture Overview

```
┌─────────────────────────────────────────────────────┐
│           Wix Site (Frontend)                       │
│  ┌──────────────────────────────────────────────┐   │
│  │  Voter Dashboard Page (voter-dashboard.js)   │   │
│  │  - Displays earnings, campaigns, history      │   │
│  └────────────┬─────────────────────────────────┘   │
│               │ Import                               │
│  ┌────────────▼─────────────────────────────────┐   │
│  │  Backend Module (campaigns.jsw)              │   │
│  │  - Server-side Wix code                      │   │
│  │  - Makes HTTP requests to Laravel API        │   │
│  └────────────┬─────────────────────────────────┘   │
│               │ API Calls                            │
└───────────────┼──────────────────────────────────────┘
                │
                │ HTTPS
                ▼
┌─────────────────────────────────────────────────────┐
│  Laravel Backend API (Railway)                      │
│  https://u9itus-production.up.railway.app            │
│                                                     │
│  Endpoints:                                         │
│  - GET  /api/v1/voters/{uuid}                      │
│  - GET  /api/v1/voters/{uuid}/earnings             │
│  - GET  /api/v1/voters/{uuid}/campaigns            │
│  - GET  /api/v1/voters/{uuid}/history              │
│  - POST /api/v1/voters/{uuid}/campaigns/{id}/watch │
│  - POST /api/v1/sessions/{uuid}/progress           │
│  - POST /api/v1/sessions/{uuid}/complete           │
└─────────────────────────────────────────────────────┘
```

## File Structure

```
src/
├── backend/                    # Server-side Wix modules (.jsw files)
│   ├── api.jsw                # HTTP request utilities
│   ├── campaigns.jsw          # Campaign and voter data functions
│   └── members.jsw            # Wix member authentication integration
│
├── pages/                     # Frontend page code
│   └── voter-dashboard.js     # Voter dashboard implementation
│
└── wix/                       # Wix dashboard extensions
    ├── dashboard-page.js      # Wix Dashboard SDK integration
    └── widget.js              # Video player widget
```

## Backend Modules (.jsw)

### api.jsw

Core HTTP request utilities for communicating with Laravel API.

**Functions:**

- `apiGet(endpoint, options)` - Make GET request
- `apiPost(endpoint, data, options)` - Make POST request
- `apiPut(endpoint, data, options)` - Make PUT request

**Example:**

```javascript
import { apiGet } from "backend/api.jsw";

const earnings = await apiGet("/voters/abc-123/earnings");
```

### campaigns.jsw

Business logic for voter dashboard data retrieval.

**Functions:**

- `getVoterDashboard()` - Get voter balance, pending, stats
- `getCampaigns()` - Get available campaigns for voter
- `getViewHistory(page)` - Get paginated view history
- `getReferrals()` - Get referral information
- `startWatchingCampaign(campaignUuid)` - Start a view session
- `registerVoter(voterData)` - Create new voter account
- `getNotifications()` - Get notification/token counts

**Example:**

```javascript
import { getVoterDashboard, getCampaigns } from "backend/campaigns.jsw";

const dashboard = await getVoterDashboard();
console.log(dashboard.balance); // $247.83

const campaigns = await getCampaigns();
console.log(campaigns.length); // 5 available campaigns
```

### members.jsw

Wix Member authentication and voter account synchronization.

**Functions:**

- `getCurrentMember()` - Get logged-in Wix member
- `getOrCreateVoterForMember()` - Sync Wix member with backend voter
- `isAuthenticated()` - Check if user is logged in
- `getMemberProfile()` - Get member display information

**Example:**

```javascript
import { getOrCreateVoterForMember } from "backend/members.jsw";

const voter = await getOrCreateVoterForMember();
console.log(voter.uuid); // Backend voter UUID
```

## Frontend Pages

### voter-dashboard.js

Complete voter dashboard implementation with real backend data.

**Features:**

- Real-time balance and earnings display
- Available campaigns list with watch buttons
- View history with pagination
- Referral code and sharing
- Notification and token counts

**Key Functions:**

- `initializeDashboard()` - Load all dashboard data
- `setupViewHistoryRepeater(data)` - Populate view history
- `setupCampaignsRepeater(campaigns)` - Populate campaigns
- `launchVideoPlayer(campaignUuid)` - Start watching video

**Example Usage:**

```javascript
// The page automatically loads on $w.onReady
// All data is fetched from backend and displayed

$w.onReady(async function () {
    await initializeDashboard();
});
```

## Data Flow

### Dashboard Load Sequence

```
1. User visits voter dashboard page
   ↓
2. $w.onReady() fires
   ↓
3. initializeDashboard() called
   ↓
4. Parallel API calls:
   - getVoterDashboard()    → /api/v1/voters/{uuid}
   - getCampaigns()         → /api/v1/voters/{uuid}/campaigns
   - getViewHistory()       → /api/v1/voters/{uuid}/history
   - getReferrals()         → /api/v1/voters/{uuid}/referrals
   - getNotifications()     → (placeholder)
   ↓
5. Update UI elements:
   - #dashboardHeading      → "Welcome, John Smith"
   - #balanceAmount         → "$247.83"
   - #pendingAmount         → "$52.40"
   - #notificationCount     → "3"
   - #tokenCount            → "12"
   - #viewHistoryItems      → [repeater with view history]
```

### Watch Campaign Sequence

```
1. User clicks "Watch" button on campaign
   ↓
2. launchVideoPlayer(campaignUuid) called
   ↓
3. startWatchingCampaign(campaignUuid) in backend
   ↓
4. POST /api/v1/voters/{uuid}/campaigns/{uuid}/watch
   ↓
5. Backend creates ViewSession and returns:
   - session_id
   - media_url
   - payout
   - duration
   ↓
6. Open video player lightbox/page with session data
   ↓
7. Video player sends heartbeats:
   POST /api/v1/sessions/{session}/progress
   ↓
8. On completion:
   POST /api/v1/sessions/{session}/complete
   ↓
9. Backend calculates payout and updates voter balance
```

## API Response Examples

### GET /api/v1/voters/{uuid}

```json
{
    "voter": {
        "uuid": "abc-123-def-456",
        "full_name": "John Smith",
        "email": "john@example.com",
        "referral_code": "JOHN2024",
        "created_at": "2026-01-15T10:30:00Z"
    },
    "earnings": {
        "total_earned": 247.83,
        "pending_payout": 52.4,
        "total_paid": 195.43
    }
}
```

### GET /api/v1/voters/{uuid}/campaigns

```json
{
    "campaigns": [
        {
            "uuid": "campaign-123",
            "title": "Vote for Education Reform",
            "message_summary": "Learn about my plan to improve local schools",
            "politician": "Sarah Johnson",
            "governance_level": "City",
            "payout": 0.25,
            "media_duration": 120,
            "thumbnail_url": "https://...",
            "is_live": false
        }
    ]
}
```

### GET /api/v1/voters/{uuid}/history

```json
{
    "data": [
        {
            "uuid": "session-789",
            "campaign": {
                "title": "Healthcare Initiative",
                "politician": {
                    "full_name": "Michael Brown"
                }
            },
            "status": "completed",
            "payment_status": "paid",
            "voter_payout_amount": 0.25,
            "watch_time_seconds": 120,
            "completion_percentage": 100,
            "completed_at": "2026-02-14T15:30:00Z"
        }
    ],
    "current_page": 1,
    "last_page": 3,
    "total": 45
}
```

## Authentication & Security

### Voter UUID Storage

Wix backend modules use **Wix Secrets** to securely store voter UUIDs:

```javascript
import wixSecretsBackend from "wix-secrets-backend";

// Store
await wixSecretsBackend.setSecret("voter_uuid", "abc-123-def-456");

// Retrieve
const uuid = await wixSecretsBackend.getSecret("voter_uuid");
```

### Member Synchronization

When a Wix member logs in:

1. Check if voter UUID exists for this member
2. If not, create new voter in backend
3. Store mapping: `voter_uuid_{memberId}` → `backend_voter_uuid`
4. Use backend voter UUID for all API calls

### Rate Limiting

Backend API has rate limiting:

- 60 requests per minute (general)
- 10 requests per minute (voter registration)

## Testing

### Manual Testing

1. **Test Dashboard Load:**

    ```javascript
    // In browser console on voter dashboard page
    import { getVoterDashboard } from "backend/campaigns.jsw";
    const data = await getVoterDashboard();
    console.log(data);
    ```

2. **Test Campaign Fetch:**

    ```javascript
    import { getCampaigns } from "backend/campaigns.jsw";
    const campaigns = await getCampaigns();
    console.log(campaigns);
    ```

3. **Test API Connection:**
    ```javascript
    import { apiGet } from "backend/api.jsw";
    const health = await apiGet("/health");
    console.log(health); // Should return { status: 'ok' }
    ```

### Error Handling

All backend functions include try-catch blocks and return graceful defaults:

```javascript
try {
    const data = await apiGet("/voters/xyz/earnings");
    return data;
} catch (error) {
    console.error("Failed to fetch earnings:", error);
    return { total_earned: 0, pending_payout: 0 }; // Safe default
}
```

## Configuration

### Change Backend URL

Edit `src/backend/api.jsw`:

```javascript
// Development
const API_BASE = "http://localhost:8000/api/v1";

// Production
const API_BASE = "https://u9itus-production.up.railway.app/api/v1";
```

### Enable CORS

Backend already configured in Laravel:

- `config/cors.php` allows Wix domain origins
- All API routes include CORS headers

## Troubleshooting

### Common Issues

**Issue: "No voter UUID found"**

- Solution: Implement member authentication or manually set test UUID
- Check: `getVoterUuid()` function in `campaigns.jsw`

**Issue: "API request failed: 404"**

- Solution: Verify voter UUID exists in backend database
- Check: Backend Laravel logs

**Issue: "CORS error"**

- Solution: Ensure Wix site domain is added to Laravel CORS config
- Check: `config/cors.php` in backend

**Issue: "Rate limit exceeded"**

- Solution: Reduce number of API calls or implement caching
- Check: Backend rate limiting in `routes/api.php`

## Next Steps

1. **Implement Real Authentication:**
    - Use `members.jsw` to sync Wix members with voters
    - Remove test UUID fallback

2. **Add Caching:**
    - Cache dashboard data for 30 seconds
    - Use Wix Storage API for client-side caching

3. **Implement Notifications:**
    - Create Laravel API endpoint for notifications
    - Update `getNotifications()` function

4. **Add Real-Time Updates:**
    - Use Wix Realtime for live balance updates
    - Implement WebSocket connection to backend

## Support

For API questions, see:

- Laravel API documentation: `/docs/api/`
- Backend route reference: `routes/api.php`
- Controller implementations: `app/Http/Controllers/Api/`
