# Architecture

## Overview

U9itus is a **standalone Laravel 12 monolith** deployed on Railway. It combines a server-rendered Blade frontend with a REST API layer and a real-time WebSocket layer.

```
Browser (Blade + Tailwind + Alpine.js)
         │
         │ HTTPS
         ▼
   Laravel 12 App (Railway)
         │
         ├── Web routes   → Blade views (Standalone controllers)
         ├── API routes   → JSON (Api controllers + Sanctum)
         └── WebSocket    → Laravel Reverb (port 8080)
                                    │
                          Browser Echo + pusher-js
```

## Technology Stack

| Layer | Technology |
|-------|------------|
| Backend framework | Laravel 12 (PHP 8.2+) |
| Frontend templating | Blade + Tailwind CSS dark theme |
| JavaScript interactivity | Alpine.js |
| Asset bundler | Vite |
| Database (dev) | SQLite |
| Database (production) | MySQL (Railway) |
| Authentication | Laravel Sanctum + session auth |
| Permissions | Spatie Laravel Permission |
| Payments | Stripe SDK |
| Real-time / WebSockets | Laravel Reverb |
| Testing | Pest (via `php artisan test`) |
| Code style | Laravel Pint |

## Directory Structure

```
u9itus.dev/
├── app/
│   ├── Console/         Commands (e.g. admin:create, admin:reset-password)
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Api/         REST API controllers
│   │   │   └── Standalone/  Web (Blade) controllers
│   │   └── Middleware/
│   ├── Models/          Eloquent models
│   └── Services/        Business logic services
├── config/
│   └── u9itus.php       Platform constants (rates, limits, etc.)
├── database/
│   ├── migrations/
│   └── seeders/
├── doc/                 Extended documentation (plans, changelogs)
├── resources/
│   ├── css/
│   ├── js/
│   │   └── echo.js      Frontend WebSocket listeners
│   └── views/
│       └── standalone/  Blade views per role (voter, politician, admin)
├── routes/
│   ├── web.php
│   └── api.php
├── tests/
│   ├── Feature/
│   └── Unit/
└── wiki/                This wiki
```

## Request Flow

### Web (Blade)

```
Browser → web.php route → Middleware (auth, role) → Standalone Controller → Blade View
```

### REST API

```
Client → api.php route → auth:sanctum → Api Controller → JSON Response
```

### Real-time (WebSockets)

```
PHP Event → broadcast() → Reverb server → Echo (browser) → UI update
```

## WebSocket Channels (Phase 11)

| Channel | Subscribers | Events |
|---------|-------------|--------|
| `private-politician.{userId}` | Politician (own) | `campaign.approved`, `campaign.rejected`, `campaign.stopped` |
| `private-voter.{userId}` | Voter (own) | `ad.token.delivered`, `session.completed`, `payout.processed` |
| `private-admin.monitor` | Admin | `fraud.flag.raised`, `session.completed` |
| `presence-campaign.live.{uuid}` | All roles | `campaign.live.started`, Phase 12 WebRTC SDP/ICE |

## Key Configuration

Business logic constants are in `config/u9itus.php`:

```php
'revenue_per_view'               => 0.60,   // charged to politician per view
'viewer_payout_per_view'         => 0.25,   // paid to voter per completed view
'referral_commission_percent'    => 10,     // % of voter payout → voter referrer
'procurement_commission_percent' => 10,     // % of politician's 1st purchase → referrer
'min_watch_percent'              => 100,
'video_duration_min'             => 10,
'video_duration_max'             => 20,
'batch_payout_min'               => 5.00,
'fraud_daily_view_limit'         => 50,
'fraud_payout_hold_hours'        => 48,
```

Dynamic runtime overrides can be managed via `PlatformSettingsService` (admin settings UI at `/admin/settings`).

---

← [Getting Started](Getting-Started.md) | [Business Model →](Business-Model.md)
