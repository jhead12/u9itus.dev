# U9itus Development Guide

## Project Architecture

U9itus is a **standalone Laravel 12 application** deployed on Railway. The stack includes:

- **Backend**: Laravel 12 (PHP 8.2+)
- **Frontend**: Blade templates with Tailwind CSS dark theme + Alpine.js
- **Database**: SQLite (development) / MySQL (Railway production)
- **Authentication**: Laravel Sanctum + session-based auth
- **Permissions**: Spatie Laravel Permission (`admin`, `politician`, `voter`)
- **Payments**: Stripe (politician billing)
- **WebSockets**: Laravel Reverb (Phase 11) — real-time notifications + Phase 12 WebRTC signaling
- **Build**: Vite (JS/CSS compilation)

## Development Workflow

### 1. Start Backend (Laravel)

```bash
php artisan serve
# Runs on http://localhost:8000
```

### 2. Start Frontend Build (Vite)

```bash
npm run dev
# Watches and compiles assets (JS, CSS)
```

### 3. Run Both Simultaneously

```bash
npm run dev:all
# Uses concurrently to run Laravel + Vite together
```

## Configuration

### `.env`

Key environment variables:

```env
APP_URL=https://yourdomain.com
FRONTEND_URL=https://app.yourdomain.com

# Database
DB_CONNECTION=sqlite
# DB_CONNECTION=mysql (for production)

# Stripe
STRIPE_KEY=your-stripe-publishable-key
STRIPE_SECRET=your-stripe-secret-key
STRIPE_WEBHOOK_SECRET=your-webhook-secret
```

### Build Process

Vite compiles JS and CSS files from `resources/` and outputs to `public/build/`. Blade templates reference them:

```blade
@vite(['resources/css/app.css', 'resources/js/app.js'])
```

## Typical Development Flow

1. **Make backend changes** → Edit Laravel controllers, models, routes
2. **Make frontend changes** → Edit Blade views, JS/CSS files in `resources/`
3. **Test locally** → `npm run dev:all` + visit http://localhost:8000
4. **Run tests** → `php artisan test`
5. **Deploy** → Push to Railway

## Phase 11 — Real-time Notifications (Laravel Reverb / WebSockets)

Phase 11 adds a self-hosted WebSocket server (Laravel Reverb) so the platform can push notifications to browsers without polling. It also lays the **signaling-layer foundation** for the Phase 12 WebRTC live feeds.

### Architecture

```
Browser (Echo + pusher-js)  ← ws://  →  Reverb server (port 8080)
                                                ↑
                                   PHP events via broadcast()
                                   (dispatched from Services/Controllers)
```

**Private channels**
| Channel | Who subscribes | Events |
|---|---|---|
| `private-politician.{userId}` | Politician (own) | `campaign.approved`, `campaign.rejected`, `campaign.stopped` |
| `private-voter.{userId}` | Voter (own) | `ad.token.delivered`, `session.completed`, `payout.processed` |
| `private-admin.monitor` | Admin only | `fraud.flag.raised`, `session.completed` (throughput) |

**Presence channel** (Phase 12 WebRTC foundation)
| Channel | Who joins | Events |
|---|---|---|
| `presence-campaign.live.{uuid}` | Politicians + Voters + Admins | `campaign.live.started` + Phase 12 WebRTC SDP/ICE |

### Quick Start (local dev)

```bash
# 1. Generate Reverb credentials (append to .env automatically)
php artisan reverb:setup

# 2. Install the Reverb server package
composer require laravel/reverb

# 3. Install frontend Echo client
npm install

# 4. Start everything together
php artisan reverb:start &   # WebSocket server on :8080
npm run dev:all               # Laravel :8000 + Vite
```

### Required .env variables

```env
BROADCAST_DRIVER=reverb
REVERB_APP_ID=<random>
REVERB_APP_KEY=<random-32-char>
REVERB_APP_SECRET=<random-32-char>
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http

# Vite exposes these to the browser
VITE_REVERB_APP_KEY=${REVERB_APP_KEY}
VITE_REVERB_HOST=${REVERB_HOST}
VITE_REVERB_PORT=${REVERB_PORT}
VITE_REVERB_SCHEME=${REVERB_SCHEME}
```

### Dispatching events from PHP

Inject or resolve `ReverbBroadcastService` from the container:

```php
use App\Services\ReverbBroadcastService;

// In AdminController::approve()
$broadcast->campaignApproved($campaign);

// In PoliticalPaymentService::processBatchPayouts()
$broadcast->payoutProcessed($voter, $amount, 'PayPal', $ref);

// In FraudPreventionService::flagVoter()
$broadcast->fraudFlagRaised($voter, $score, $reason);
```

### Frontend Echo listeners

```js
import { listenAsVoter, toast } from "./echo";

// Mount after page load, userId from Blade meta tag
listenAsVoter(window.AUTH_USER_ID, {
    onAdReady(data) {
        toast(data.message, "info");
        document.getElementById("watch-btn").href = data.watch_url;
    },
    onSessionCompleted(data) {
        toast(data.message, "success");
        document.getElementById("balance").textContent =
            "$" + data.new_balance.toFixed(2);
    },
    onPayoutProcessed(data) {
        toast(data.message, "success");
    },
});
```

### Railway Production Setup

Add the following to `railway.json` or the Railway dashboard:

- New service: `php artisan reverb:start --host=0.0.0.0 --port=8080`
- Expose port `8080` (TCP)
- Set `REVERB_HOST` to the Railway-assigned domain for the Reverb service
- Set `REVERB_SCHEME=https` and `REVERB_PORT=443` if behind TLS

### Phase 12 Preview — WebRTC Live Feeds

Phase 12 will attach WebRTC signaling messages (SDP offer/answer, ICE candidates) to the **existing** `presence-campaign.live.{uuid}` presence channel introduced in Phase 11. No channel-architecture changes will be required — the presence channel already:

- Tracks who is currently in the live feed room (viewer count)
- Authorises subscribers by role (politician broadcast, voter watch, admin monitor)
- Dispatches `campaign.live.started` to notify subscribers

---

| Task                    | Command                        |
| ----------------------- | ------------------------------ |
| Start Laravel           | `php artisan serve`            |
| Watch frontend          | `npm run dev`                  |
| Start both              | `npm run dev:all`              |
| **Start Reverb server** | **`php artisan reverb:start`** |
| **Setup Reverb keys**   | **`php artisan reverb:setup`** |
| Build for prod          | `npm run build`                |
| Run migrations          | `php artisan migrate`          |
| Run tests               | `php artisan test`             |
| Code style              | `./vendor/bin/pint`            |

## Common Issues

### Frontend changes not showing

→ Make sure `npm run dev` is running (Vite watch mode)  
→ Clear browser cache  
→ Check `public/build/manifest.json` exists

### Database migration errors

→ Run `php artisan migrate:status` to check pending migrations  
→ Use `php artisan migrate:fresh` for a clean slate (development only)

### Test failures after changes

→ Run `php artisan test --filter=TestName` to isolate failures  
→ Check `phpunit.xml` for test database configuration

## Need Help?

- Laravel Docs: https://laravel.com/docs
- GitHub Issues: https://github.com/jhead12/u9itus.dev/issues
