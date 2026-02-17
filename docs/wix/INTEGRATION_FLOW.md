# Wix Widget Integration — Complete Flow

## Overview

This document explains how the U9itus political message feed widget integrates with Wix sites using the session/campaign approach.

## Architecture

```
┌─────────────────┐
│   Wix Site      │
│   (Browser)     │
│                 │
│  ┌──────────┐   │     postMessage       ┌─────────────────────┐
│  │  Velo    │◄──┼──────events───────────┤  Hosted Widget      │
│  │  Widget  │   │                       │  (iframe from       │
│  │  Script  │   │ ◄─────embedded────────┤   your Railway app) │
│  └──────────┘   │                       └─────────────────────┘
└─────────────────┘                                │
                                                   │ REST API
                                                   ▼
                                        ┌──────────────────────┐
                                        │  U9itus API           │
                                        │  (Laravel Backend)   │
                                        │                      │
                                        │  - Create Voter      │
                                        │  - Fetch Campaigns   │
                                        │  - Create Session    │
                                        │  - Track Progress    │
                                        │  - Complete View     │
                                        └──────────────────────┘
                                                   │
                                                   ▼
                                        ┌──────────────────────┐
                                        │  MySQL Database      │
                                        │                      │
                                        │  - political_campaigns│
                                        │  - view_sessions     │
                                        │  - voters            │
                                        └──────────────────────┘
```

## Integration Flow

### 1. Politician Creates Campaign

**Location:** Wix Dashboard (Politician Panel)  
**API Endpoint:** `POST /api/v1/politicians/{politicianUuid}/campaigns`

1. Politician uploads video and sets campaign details
2. Campaign gets a UUID (e.g., `abc-123-def`)
3. Campaign is activated and ready for viewer sessions

### 2. Widget Installation on Wix Site

**Location:** Wix Editor  
**File:** `docs/wix/velo-widget.md`

Site owner:

1. Creates an HTML element with id `videoPlayerContainer`
2. Pastes Velo code from `docs/wix/velo-widget.md` into Page Code
3. Configures `WIDGET_CONFIG` with campaign UUID:
    ```js
    const WIDGET_CONFIG = {
        campaign: "abc-123-def", // From politician's dashboard
        voterEmail: "", // Optional - for logged-in members
    };
    ```

### 3. Widget Loads on Site Visitor's Browser

**Wix Velo Script** (`docs/wix/velo-widget.md`):

- Creates iframe pointing to `https://u9itus-production.up.railway.app/wix/widget?campaign=abc-123-def`
- Sets up postMessage listeners for events from iframe
- Forwards `view:heartbeat` and `view:complete` events to API

**Hosted Widget** (`resources/views/wix/widget/voter-feed.blade.php`):

1. **Initialize:**
    - Gets/creates anonymous voter UUID (stored in localStorage)
    - API: `POST /api/v1/voters` → returns `{voter: {uuid}}`
2. **Fetch Campaign:**
    - Uses campaign UUID from query param
    - API: `GET /api/v1/voters/{voterUuid}/campaigns` → returns available campaigns
3. **Create Session:**
    - API: `POST /api/v1/voters/{voterUuid}/campaigns/{campaignUuid}/watch`
    - Returns: `{session_id, media_url, payout, duration}`
    - **Session UUID ties Campaign → Voter → ViewSession**

4. **Load Video:**
    - Sets video src to `media_url`
    - Displays payout amount and duration

### 4. Voter Watches Video

**Widget sends heartbeats every 5 seconds:**

- Via postMessage: `view:heartbeat` → Wix Velo → API
- API: `POST /api/v1/sessions/{sessionUuid}/progress`
- Body: `{seconds_watched: 25}`

**Database Update:**

```sql
UPDATE view_sessions
SET watch_time_seconds = 25,
    completion_percentage = (25 / duration) * 100
WHERE uuid = 'session-uuid';
```

### 5. Voter Completes Video

**Widget triggers completion:**

- Via postMessage: `view:complete` → Wix Velo → API
- API: `POST /api/v1/sessions/{sessionUuid}/complete`
- Body: `{total_seconds_watched: 60}`

**Backend Logic** (`app/Services/PoliticalViewService.php`):

1. Validates watch time meets minimum threshold (80% default)
2. Calculates payout (e.g., $0.25)
3. Updates voter earnings: `voters.pending_earnings += 0.25`
4. Updates session status: `status = 'completed'`, `payment_status = 'pending'`
5. Returns: `{payout_earned: 0.25, status: 'completed'}`

### 6. Widget Shows Success

Widget displays:

```
✅ You earned $0.25! Thank you for watching.
```

## Key Database Relationships

```sql
-- A ViewSession connects:
view_sessions.id (primary key, auto-increment)
view_sessions.uuid (for API, unguessable)
view_sessions.political_campaign_id → political_campaigns.id
view_sessions.voter_id → voters.id

-- Campaign links to Politician:
political_campaigns.politician_id → politicians.id
```

## Session Creation Flow (Detail)

When widget calls `POST /api/v1/voters/{voterUuid}/campaigns/{campaignUuid}/watch`:

**Controller** (`app/Http/Controllers/Api/VoterController.php`):

```php
public function startView(Request $request, Voter $voter, PoliticalCampaign $campaign)
{
    // Creates ViewSession record:
    $session = $this->viewService->assignView($campaign, $voter, $request);
    $session = $this->viewService->startView($session);

    return response()->json([
        'session_id' => $session->uuid,  // ← This is what widget uses for tracking
        'media_url'  => $campaign->media_url,
        'payout'     => $campaign->voter_payout_per_view,
        'duration'   => $campaign->media_duration,
    ]);
}
```

**Database Insert:**

```sql
INSERT INTO view_sessions (
    uuid,
    political_campaign_id,  -- ← Links to politician's campaign
    voter_id,               -- ← Links to viewer
    status,
    started_at,
    expires_at,
    ip_address,
    user_agent
) VALUES (
    'generated-uuid-here',
    123,  -- campaign.id
    456,  -- voter.id
    'in_progress',
    NOW(),
    DATE_ADD(NOW(), INTERVAL 24 HOUR),
    '192.168.1.1',
    'Mozilla/5.0...'
);
```

## Configuration Requirements

### Environment Variables (.env)

```bash
WIX_APP_URL=https://u9itus-production.up.railway.app
DB_CONNECTION=mysql  # or sqlite for dev
```

### Wix Settings

- **Campaign UUID:** Politician provides this from their dashboard
- **App Origin:** `https://u9itus-production.up.railway.app`

## API Endpoints Summary

| Method | Endpoint                                               | Purpose                  | Returns                                     |
| ------ | ------------------------------------------------------ | ------------------------ | ------------------------------------------- |
| POST   | `/api/v1/voters`                                       | Create anonymous voter   | `{voter: {uuid}}`                           |
| GET    | `/api/v1/voters/{uuid}/campaigns`                      | List available campaigns | `{campaigns: [...]}`                        |
| POST   | `/api/v1/voters/{uuid}/campaigns/{campaignUuid}/watch` | Start session            | `{session_id, media_url, payout, duration}` |
| POST   | `/api/v1/sessions/{sessionUuid}/progress`              | Track heartbeat          | `{status: 'ok'}`                            |
| POST   | `/api/v1/sessions/{sessionUuid}/complete`              | Complete view            | `{payout_earned, status, payment_status}`   |

## Security Considerations

1. **Session UUID:** Unguessable UUID prevents enumeration attacks
2. **CORS:** Server must allow Wix site origins
3. **Rate Limiting:** 60 requests/minute on voter endpoints
4. **Fraud Detection:** Tracks IP, device fingerprint, watch patterns
5. **Payment Validation:** Requires 80% watch time minimum by default

## Next Steps

1. **Test Flow:**

    ```bash
    # Create a test campaign via API or admin panel
    # Copy the campaign UUID
    # Update velo-widget.md WIDGET_CONFIG.campaign
    # Paste into Wix page code
    # Preview Wix site and watch video
    ```

2. **Monitor:**
    - Check `view_sessions` table for new records
    - Verify `voters.pending_earnings` increments
    - Review `political_campaigns.views_completed` counter

3. **Production:**
    - Enable CORS for Wix site domain
    - Set up webhook for app installed/removed events
    - Configure Stripe for payouts (when voters claim earnings)

## Troubleshooting

**Widget doesn't load:**

- Check browser console for CORS errors
- Verify `APP_ORIGIN` matches your deployed app URL
- Ensure `/wix/widget` route is accessible

**Session not created:**

- Check campaign is `active` and has `approval_status = 'approved'`
- Verify voter creation succeeded (check localStorage for `u9itus_voter_uuid`)
- Review Laravel logs: `storage/logs/laravel.log`

**Payout not credited:**

- Verify watch time met minimum threshold (80% by default)
- Check `view_sessions.completion_percentage` in database
- Review `view_sessions.fraud_flags` for any blocks

## Files Modified

1. `src/site/widgets/Video Chat Player/Video Chat Player.x5kmw.js` - Widget glue script
2. `docs/wix/velo-widget.md` - Wix Velo integration documentation
3. `resources/views/wix/widget/voter-feed.blade.php` - Hosted widget view with session creation
4. `routes/wix.php` - Already had `/wix/widget` route
5. `routes/api.php` - Already had all session/campaign endpoints

---

**Summary:** The session UUID is created when the voter starts watching and links back to the campaign (which links to the politician). This provides full traceability: Politician → Campaign → ViewSession → Voter.
