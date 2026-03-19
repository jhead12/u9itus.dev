# U9itus – Political Loyalty Ads (Standalone)

**Version:** 3.0.0  
**Framework:** Laravel 12 (Standalone Architecture)  
**Database:** MySQL (Railway Production)  
**Deployment:** Railway.app with Metal Build  
**Production URL:** https://u9itus-production.up.railway.app

## Overview

U9itus is a **standalone political advertising platform** that connects **politicians and local governance officials** directly with **potential voters** through paid video messages and live feeds. Politicians pay $0.60 per view; voters earn $0.25 for watching the full message. The platform includes **secure token-based ad delivery**, referral commissions, advanced fraud prevention, and automated batch payouts.

### Security-First Architecture

Unlike traditional ad platforms where users can click repeatedly, U9itus uses **push notification-based delivery** with one-time use tokens to prevent fraud and abuse.

> _"Regardless of how much artificial intelligence is used, without the human element the production that AI affords is all for naught. Human beings will still be required to purchase this production. I am offering a solution."_ — Head Enterprises

## Key Features

### Core Business Model — Per-View Economics

| Component                                          | Amount                          |
| -------------------------------------------------- | ------------------------------- |
| Politician pays per view                           | **$0.60**                       |
| Voter earns per view                               | **$0.25**                       |
| Voter-referral commission (10% of voter payout)    | $0.025 per view _(recurring)_   |
| Politician-procurement commission (10% of 1st buy) | ~$0.06+ _(one-time)_            |
| Payment processing (Stripe 2.5% gross-up)          | 2.5% of credit top-up amount    |
| Ops & infrastructure                               | ~$0.03–$0.12                    |
| **Platform net profit**                            | **$0.18–$0.30 (30–50% margin)** |

> **Stripe fee note:** When a politician adds credits, the charge is grossed-up so the platform always receives the full credit value requested. For example, a $100.00 credit purchase generates a $102.56 Stripe charge; after Stripe deducts $2.56 (2.5%), the platform nets exactly $100.00 and the politician's balance is credited $100.00.

### Referral Incentive Structure

Voters earn two distinct types of commission by referring others to the platform:

| Referral Type           | Who You Refer    | Commission                                   | Frequency           |
| ----------------------- | ---------------- | -------------------------------------------- | ------------------- |
| **Voter Referral**      | A new voter      | **10% of their payout** ($0.025) per view    | Recurring           |
| **Politician Referral** | A new politician | **10% residual income for Founding Members** | Ongoing commissions |

- Voter referral links route to `/register/voter?ref=<code>`
- Politician recruitment links route to `/register/politician?ref=<code>`
- Procurement commission fires automatically when the recruited politician makes their first Stripe credit purchase — guarded to trigger only once per politician.

### User Roles

1. **Politician** — Creates video messages or live feeds, pays to distribute them to voters
2. **Voter** — Watches political messages, earns money, refers friends
3. **Admin** — Approves campaigns, manages fraud, processes payouts

### Political Features

- Governance levels: Federal, State, County, City, School Board, Special District
- Political offices: Mayor, City Council, Governor, US Senator, etc.
- Target by state, city, congressional district
- Video messages + live feeds
- 100% watch requirement (must watch the full message to earn)

### Campaign Video Media

**v1 — YouTube (current foundation)**

All campaign `media_url` values are expected to be YouTube links in any of the supported formats:

| Format    | Example                                    |
| --------- | ------------------------------------------ |
| Short URL | `https://youtu.be/VIDEO_ID`                |
| Watch URL | `https://www.youtube.com/watch?v=VIDEO_ID` |
| Embed URL | `https://www.youtube.com/embed/VIDEO_ID`   |

The watch view (`resources/views/standalone/voter/watch.blade.php`) auto-detects a YouTube URL and renders the video via the **YouTube IFrame API** (`YT.Player`). This enables:

- Controlled playback triggered only after the session starts
- Server-side heartbeat progress tracking every 5 seconds
- Anti-skip enforcement (viewer cannot seek forward)
- `ENDED` event hooked to the completion/payout flow

**Planned — Future media type support**

The following additional source types are planned for future versions. The player section uses a `$isYouTube` branch, making it straightforward to extend:

| Source Type                        | Notes                                                      |
| ---------------------------------- | ---------------------------------------------------------- |
| Direct video file (MP4, WebM, OGG) | Native `<video>` element — already implemented as fallback |
| Vimeo                              | Vimeo Player SDK (`player.vimeo.com/video/VIDEO_ID`)       |
| Wistia                             | Wistia Embed API for privacy-friendly hosting              |
| Cloudflare Stream                  | HLS/DASH stream via `stream.cloudflare.com`                |
| AWS S3 / CloudFront                | Signed URL + native `<video>` element                      |
| HLS live streams                   | `hls.js` for live feed capability                          |

> **Implementation note:** Add a new `elseif` branch in the `@php` block at the top of the player section and a matching `if` block in the JavaScript section for each new source type.

### Security & Fraud Prevention

**Token-Based Ad Delivery:**

- Secure one-time use tokens (SHA-256)
- Email/SMS/Push notification delivery
- 24-hour token expiration
- No panel-based ad access (prevents clicking abuse)
- Complete audit trail of all notifications

**Fraud Detection:**

- Device fingerprinting
- Rate limiting (max 10 ads per 24 hours)
- IP anomaly detection
- Rapid-fire view detection
- Payout hold periods (48-hour verification window)
- Voter trust scoring
- Token replay attack prevention

### Technical Stack

- **Backend**: Laravel 12 (PHP 8.2+)
- **Frontend**: Blade templates (Tailwind CSS dark theme)
- **Database**: SQLite (development) / MySQL (Railway production)
- **Authentication**: Laravel Sanctum + session-based auth
- **Permissions**: Spatie Laravel Permission (roles: `admin`, `politician`, `voter`)
- **Payments**: Stripe (politician billing) + PayPal/CashApp (voter payouts — placeholder)
- **Testing**: Pest

## Quick Start

### Requirements

- PHP 8.1 or higher
- Composer
- SQLite3
- Node.js 18+ & NPM

### Installation

1. **Clone the repository**

```bash
git clone https://github.com/jhead12/u9itus.dev.git
cd u9itus.dev
```

2. **Install dependencies**

```bash
composer install
npm install
```

3. **Environment setup**

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` and configure:

```env
APP_URL=https://yourdomain.com
FRONTEND_URL=https://app.yourdomain.com

# Database Configuration
DB_CONNECTION=sqlite
# DB_CONNECTION=mysql (for production)
```

4. **Database setup**

```bash
touch database/database.sqlite
php artisan migrate
```

5. **Build frontend assets**

```bash
npm run build
```

6. **Start development server**

```bash
php artisan serve
```

## Application Structure

### Database Schema

| Table                          | Purpose                                                                                                                                                                                                                                                                                                                                                                                                                       |
| ------------------------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **politicians**                | Politician profiles, governance level, office, district, party; includes `slug` (5-char UUID prefix + SEO-friendly name, e.g. `a3f9b-mayor-john-smith-chicago`), `page_settings` (JSON theme config), `page_published` flag; _(Phase 16)_ verification status (unverified/pending/verified), government email verification, transparency opt-in toggles (Ballotpedia, OpenSecrets, Vote Smart, FEC), external API IDs storage |
| **politician_pages**           | _(Phase 13)_ Public-facing campaign page theme config — layout preset, primary/accent colors, background style, hero banner, section toggles, custom CTA                                                                                                                                                                                                                                                                      |
| **politician_initiatives**     | _(Phase 13)_ Policy positions and platform planks displayed on the politician's public page — title, description, icon, sort order, published flag                                                                                                                                                                                                                                                                            |
| **voters**                     | Voter profiles, wallet balance, referral codes, trust score                                                                                                                                                                                                                                                                                                                                                                   |
| **political_campaigns**        | Video/live-feed campaigns with per-view pricing and targeting                                                                                                                                                                                                                                                                                                                                                                 |
| **view_sessions**              | Individual view tracking — watch time, fraud score, payouts                                                                                                                                                                                                                                                                                                                                                                   |
| **referral_earnings**          | Referral commission records — voter-view (recurring) and politician-procurement (one-time)                                                                                                                                                                                                                                                                                                                                    |
| **ad_view_tokens**             | One-time secure tokens for ad delivery via notifications                                                                                                                                                                                                                                                                                                                                                                      |
| **campaign_transactions**      | Stripe payment records per politician                                                                                                                                                                                                                                                                                                                                                                                         |
| **politician_credits**         | Credit balance ledger for per-view billing                                                                                                                                                                                                                                                                                                                                                                                    |
| **politician_payment_methods** | Stored Stripe payment methods per politician                                                                                                                                                                                                                                                                                                                                                                                  |
| **campaign_audit_logs**        | Immutable admin action log — field-level diffs for approve/reject/edit/stop/reactivate                                                                                                                                                                                                                                                                                                                                        |
| **fraud_signals**              | _(Phase 8)_ Per-event fraud signal log — signal type, score impact, IP/fingerprint context, admin resolution                                                                                                                                                                                                                                                                                                                  |
| **user_onboarding_progress**   | _(Phase 17)_ Per-user onboarding state tracking — current phase, completed phases (JSON), phase data storage, completion status, skip flag                                                                                                                                                                                                                                                                                    |
| **notification_preferences**   | _(Phase 18)_ Per-user notification channel preferences — email/in-app/push/SMS toggles per notification type, FCM token storage, phone number for SMS                                                                                                                                                                                                                                                                         |
| **notifications**              | _(Phase 18)_ Laravel's built-in notifications table — stores in-app notifications with polymorphic notifiable relationship, read_at timestamp, notification data (JSON)                                                                                                                                                                                                                                                       |

### Services

| Service                         | Purpose                                                                                                                                                            |
| ------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------ | --- | --------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **PoliticalViewService**        | View lifecycle: assign → start → track → complete                                                                                                                  |
| **PoliticalPaymentService**     | Campaign billing, batch payouts, per-view profit calculation                                                                                                       |
| **FraudPreventionService**      | Multi-signal fraud scoring: rate limits, device fingerprinting, bot UA detection, IP anomalies, VPN/Tor/datacenter detection, auto-flag, `fraud_signals` audit log |
| **CampaignBillingService**      | Stripe PaymentIntent creation, credit top-up, credit deduction                                                                                                     |
| **StripePaymentService**        | Low-level Stripe SDK wrapper (customers, payment methods, intents)                                                                                                 |
| **StandardNotificationService** | Email/SMS notification delivery                                                                                                                                    |
| **StandardAuthService**         | Laravel session-based authentication                                                                                                                               |
| **IpReputationService**         | VPN / proxy / Tor exit-node / datacenter IP detection via CIDR blocklist + optional ipinfo.io enrichment (Phase 8)                                                 |
| **DeviceFingerprintService**    | Server-side composite fingerprint generation, bot user-agent analysis, fingerprint compare/store (Phase 8)                                                         |
| **ReverbBroadcastService**      | WebSocket event dispatch — ad delivery, payout alerts, campaign status, presence                                                                                   |
| **OnboardingService**           | _(Phase 17)_ Multi-phase user onboarding management — phase definitions per role, progress tracking, completion logic, skip handling, onboarding stats             |     | **BallotpediaService**            | _(Phase 16)_ Ballotpedia API integration — fetch voting records, committee assignments, sponsored legislation; 24-hour cache, government domain validation |
| **OpenSecretsService**          | _(Phase 16)_ OpenSecrets API integration — campaign finance data, top contributors, industry breakdown, sector totals; 24-hour cache                               |
| **VoteSmartService**            | _(Phase 16)_ Vote Smart API integration — interest group ratings, issue positions, key votes, biographical data; 24-hour cache                                     |
| **FECService**                  | _(Phase 16)_ Federal Election Commission API integration — official FEC filings, financial summaries, committee data; federal candidates only; 24-hour cache       |
| **ProfileVerificationService**  | _(Phase 16)_ Government email verification — .gov/.mil domain validation, verification token generation/validation, transparency eligibility checks                |     | **FirebaseCloudMessagingService** | _(Phase 18)_ FCM push notification delivery — OAuth 2.0 authentication, notification payload assembly, FCM token management, delivery logging              |
| **TwilioSmsService**            | _(Phase 18)_ Twilio SMS delivery — API authentication, phone number validation, E.164 formatting, delivery tracking                                                |

### Controllers

| Controller                          | Purpose                                                                            |
| ----------------------------------- | ---------------------------------------------------------------------------------- |
| **Standalone\AuthController**       | Separate politician/voter registration, shared login, admin portal, password reset |
| **Standalone\DashboardController**  | Role-based dashboard routing                                                       |
| **Standalone\PoliticianController** | Full campaign CRUD, video upload, analytics, billing, profile                      |
| **Standalone\VoterController**      | Ad room, SHA-256 token-based ad watching, earnings, referrals                      |
| **Standalone\AdminController**      | User management, fraud, payouts, campaign approval                                 |
| **Api\PoliticianController**        | Politician CRUD, campaign management (API)                                         |
| **Api\VoterController**             | Registration, view sessions, earnings (API)                                        |
| **Api\AdminController**             | Analytics, approvals, payouts, fraud (API)                                         |
| **Api\StripeWebhookController**     | `payment_intent.succeeded` / `payment_intent.payment_failed`                       |

## API Endpoints

### Authentication Routes

| Method | URL                    | Purpose                                     |
| ------ | ---------------------- | ------------------------------------------- |
| `GET`  | `/login`               | Shared login page (Politician / Voter tabs) |
| `POST` | `/login`               | Authenticate and redirect by role           |
| `GET`  | `/admin/login`         | Dedicated admin portal                      |
| `POST` | `/admin/login`         | Admin-only authentication (role-enforced)   |
| `GET`  | `/register`            | Role-chooser landing page                   |
| `GET`  | `/register/politician` | Politician registration form                |
| `POST` | `/register/politician` | Create politician account + profile         |
| `GET`  | `/register/voter`      | Voter registration form                     |
| `POST` | `/register/voter`      | Create voter account + profile              |
| `POST` | `/logout`              | Sign out                                    |

### Politician Dashboard (`/politician/*`)

Requires `auth`, `verified`, and `role:politician` middleware.

| Method | URL                                        | Purpose                                     |
| ------ | ------------------------------------------ | ------------------------------------------- |
| `GET`  | `/politician/dashboard`                    | Overview stats                              |
| `GET`  | `/politician/campaigns`                    | Campaign list                               |
| `GET`  | `/politician/campaigns/create`             | New campaign form                           |
| `POST` | `/politician/campaigns`                    | Store new campaign                          |
| `GET`  | `/politician/campaigns/{id}`               | Campaign detail                             |
| `GET`  | `/politician/campaigns/{id}/edit`          | Edit campaign form                          |
| `PUT`  | `/politician/campaigns/{id}`               | Update campaign                             |
| `POST` | `/politician/campaigns/{id}/submit-review` | Submit draft for admin review               |
| `GET`  | `/politician/analytics`                    | Platform-wide analytics overview            |
| `GET`  | `/politician/billing`                      | Credit balance + Stripe transaction history |
| `POST` | `/politician/billing/add-funds`            | Create Stripe PaymentIntent to add credits  |
| `GET`  | `/politician/profile`                      | View/edit profile                           |
| `PUT`  | `/politician/profile`                      | Update political profile                    |

### Voter Dashboard (`/voter/*`)

Requires `auth`, `verified`, and `role:voter` middleware.

| Method | URL                              | Purpose                               |
| ------ | -------------------------------- | ------------------------------------- |
| `GET`  | `/voter/dashboard`               | Earnings overview                     |
| `GET`  | `/voter/watch/{token}`           | Load ad via secure token              |
| `POST` | `/voter/watch/{token}/complete`  | Mark session complete, trigger payout |
| `GET`  | `/voter/earnings`                | Earnings summary                      |
| `POST` | `/voter/earnings/request-payout` | Request cash payout                   |
| `GET`  | `/voter/referrals`               | Referral overview                     |
| `GET`  | `/voter/profile`                 | Profile page                          |

### Admin Dashboard (`/admin/*`)

Email: admin@U9itus.com
Password: _(set via `railway run php artisan admin:create`)_

Requires `auth`, `verified`, and `role:admin` middleware. Access via `/admin/login`.

**Admin Password Management:**

Create or update admin account:

```bash
php artisan admin:create --email=admin@u9itus.com --name="Admin User"
```

Reset admin password (CLI):

```bash
php artisan admin:reset-password --email=admin@u9itus.com
```

Reset password (Web): Admins can also use the standard password reset flow at `/forgot-password` — a "Forgot password?" link is available on the admin login page.

| Method | URL                                | Purpose                                    |
| ------ | ---------------------------------- | ------------------------------------------ |
| `GET`  | `/admin/dashboard`                 | Admin overview                             |
| `GET`  | `/admin/campaigns/pending`         | Campaigns awaiting approval                |
| `GET`  | `/admin/campaigns/{id}/edit`       | Edit any campaign (admin only)             |
| `PUT`  | `/admin/campaigns/{id}`            | Update campaign fields + write audit entry |
| `POST` | `/admin/campaigns/{id}/approve`    | Approve campaign                           |
| `POST` | `/admin/campaigns/{id}/reject`     | Reject campaign with reason                |
| `POST` | `/admin/campaigns/{id}/stop`       | Force-pause a live campaign with reason    |
| `POST` | `/admin/campaigns/{id}/reactivate` | Reactivate a stopped campaign              |
| `GET`  | `/admin/campaigns/{id}/audit`      | Paginated immutable audit log for campaign |
| `GET`  | `/admin/users`                     | User list                                  |
| `GET`  | `/admin/fraud`                     | Fraud dashboard                            |
| `GET`  | `/admin/payouts`                   | Payout overview (`admin.payouts.index`)    |
| `GET`  | `/admin/payouts/pending`           | Pending payout sessions                    |
| `POST` | `/admin/payouts/batch-process`     | Run batch payout processing                |
| `GET`  | `/admin/analytics`                 | Platform analytics                         |
| `GET`  | `/admin/settings`                  | System settings                            |

### REST API (`/api/v1/*`)

Protected by `auth:sanctum` middleware.

#### Politician API

| Method | URL                                         | Purpose                   |
| ------ | ------------------------------------------- | ------------------------- |
| `POST` | `/api/v1/politicians`                       | Create politician profile |
| `GET`  | `/api/v1/politicians/{id}`                  | Get politician            |
| `PUT`  | `/api/v1/politicians/{id}`                  | Update profile            |
| `POST` | `/api/v1/politicians/{id}/campaigns`        | Create campaign           |
| `GET`  | `/api/v1/politicians/{id}/campaigns`        | List campaigns            |
| `GET`  | `/api/v1/politicians/{id}/billing/balance`  | Credit balance            |
| `POST` | `/api/v1/politicians/{id}/billing/purchase` | Purchase credits          |

#### Voter API

| Method | URL                                         | Purpose                                      |
| ------ | ------------------------------------------- | -------------------------------------------- |
| `POST` | `/api/v1/voters`                            | Register voter (with optional referral code) |
| `GET`  | `/api/v1/voters/{id}`                       | Get voter profile                            |
| `GET`  | `/api/v1/voters/{id}/earnings`              | Earnings summary                             |
| `GET`  | `/api/v1/voters/{id}/referrals`             | Referral earnings                            |
| `GET`  | `/api/v1/voters/{id}/campaigns`             | Available campaigns                          |
| `POST` | `/api/v1/voters/{id}/campaigns/{cid}/watch` | Assign watch session                         |
| `POST` | `/api/v1/sessions/{session}/progress`       | Progress heartbeat                           |
| `POST` | `/api/v1/sessions/{session}/complete`       | Mark view completed                          |

#### Admin API

| Method | URL                                    | Purpose                     |
| ------ | -------------------------------------- | --------------------------- |
| `GET`  | `/api/v1/admin/analytics`              | Platform-wide analytics     |
| `GET`  | `/api/v1/admin/campaigns/pending`      | Pending approval queue      |
| `POST` | `/api/v1/admin/campaigns/{id}/approve` | Approve a campaign          |
| `POST` | `/api/v1/admin/campaigns/{id}/reject`  | Reject a campaign           |
| `POST` | `/api/v1/admin/payouts/process`        | Run batch payout processing |
| `GET`  | `/api/v1/admin/voters/flagged`         | List fraud-flagged voters   |
| `POST` | `/api/v1/admin/voters/{id}/clear-flag` | Clear fraud flag            |

#### Stripe Webhook

| Method | URL                    | Purpose                                                             |
| ------ | ---------------------- | ------------------------------------------------------------------- |
| `POST` | `/api/stripe/webhooks` | Handle `payment_intent.succeeded` / `payment_intent.payment_failed` |

## Configuration

### Business Logic

Key values in `config/u9itus.php`:

```php
'revenue_per_view'              => 0.60,   // charged to politician per view
'viewer_payout_per_view'        => 0.25,   // paid to voter per completed view
'referral_commission_percent'   => 10,     // % of voter payout → voter referrer (recurring)
'procurement_commission_percent'=> 10,     // % of politician's 1st purchase → voter referrer (one-time)
'min_watch_percent'             => 100,
'video_duration_min'            => 10,
'video_duration_max'            => 20,
'batch_payout_min'              => 5.00,
'fraud_daily_view_limit'        => 50,
'fraud_payout_hold_hours'       => 48,
```

## Security

- Role-based access control via Spatie Permission (`admin`, `politician`, `voter`)
- API authentication via Laravel Sanctum
- Admin portal (`/admin/login`) enforces `role:admin` check post-authentication
- Separate politician and voter registration flows prevent role confusion
- Fraud prevention with multi-signal scoring
- 48-hour payout hold for verification window
- Device fingerprinting to prevent multi-account abuse
- CSRF protection on all forms
- SQL injection prevention via Eloquent ORM
- Signed URLs for email verification links

## Development

### Running Tests

```bash
# Run full suite
php artisan test

# Unit tests only (services)
php artisan test --testsuite=Unit

# Feature tests only
php artisan test --testsuite=Feature

# Run with code coverage (requires Xdebug)
php artisan test --coverage
```

**Test Suite Overview (187 tests, 405 assertions)**

| Suite                                        | Tests | Coverage                                                                                                                                                 |
| -------------------------------------------- | ----- | -------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `Unit/Services/FraudPreventionServiceTest`   | 14    | Score calculation, all fraud flags (incl. VPN/bot UA), signal persistence, `flagVoter`, `holdPayouts`, `releasePayouts`, `updateTrustScore`, `clearFlag` |
| `Unit/Services/CampaignBillingServiceTest`   | 9     | `recordTransaction`, credit ledger, procurement commissions, `finalizePaymentIntent`                                                                     |
| `Unit/Services/PoliticalViewServiceTest`     | 11    | Full view lifecycle, idempotency, state-targeted campaigns, earnings summary                                                                             |
| `Unit/Services/StripePaymentServiceTest`     | 6     | No-key error path, `ensureCustomer` null-safe, `parseWebhook` fallback                                                                                   |
| `Unit/Services/IpReputationServiceTest`      | 9     | CIDR datacenter detection, Tor prefix match, score cap, cache, ipinfo.io mock                                                                            |
| `Unit/Services/DeviceFingerprintServiceTest` | 14    | `generate` stability, `compare` cases, `storeIfNew`, bot UA keyword/marker detection                                                                     |
| `Feature/Campaign/AdminApprovalTest`         | 10    | Admin access control, approve/reject/stop/reactivate campaign workflow                                                                                   |
| `Feature/Campaign/CampaignCrudTest`          | 20    | Campaign CRUD, validation, submit-for-review, analytics, billing views                                                                                   |
| `Feature/Api/ViewSessionLifecycleTest`       | 13    | View session assign → start → progress → complete, referral earnings                                                                                     |
| `Feature/Api/*`                              | 25    | Politician API, Voter API, Admin API, Health endpoint                                                                                                    |
| `Feature/Billing/*`                          | 7     | Credit purchase, Stripe webhook (success/failure/idempotency/sig-verify)                                                                                 |
| `Feature/Auth/*`                             | 19    | Registration, login, email verification, password reset/update                                                                                           |
| `Feature/Standalone/VoterWatchTest`          | 16    | Token delivery, watch session, heartbeat, payout, voter dashboard                                                                                        |

### Code Style

```bash
./vendor/bin/pint
```

### Database Management

```bash
php artisan migrate         # Run migrations
php artisan migrate:fresh   # Fresh migration
php artisan migrate:status  # Check migration status
```

### Development Server

```bash
npm run dev:all   # Start Laravel + Vite together
```

## Implementation Progress

| Phase    | Description                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  | Status      |
| -------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------- |
| Phase 1  | Auth & Foundation (auth views, dashboard layout, middleware, email verification)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             | ✅ Complete |
| Phase 2  | Campaign Management (full CRUD, video upload, analytics, billing, profile views) + public campaign discovery entry points and district-first browse pathways for guest users                                                                                                                                                                                                                                                                                                                                                                                                                 | ✅ Complete |
| Phase 3  | Analytics & Tracking (ViewSession lifecycle API, fraud detection, payout dispatch)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           | ✅ Complete |
| Phase 4  | Billing scaffold (Stripe service, webhook, credit ledger, billing views)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                     | ✅ Complete |
| Phase 5  | Voter watch experience (token-based video delivery, JS heartbeat)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            | ✅ Complete |
| Phase 6  | Admin features (campaign approval queue, edit/stop/reactivate campaigns, KYC management, fraud review, immutable audit log)                                                                                                                                                                                                                                                                                                                                                                                                                                                                  | ✅ Complete |
| Phase 7  | Notifications (email on approval/rejection/ - Admin signup notification email,User Signed up Email, Admin Email notification, managment system, completion)                                                                                                                                                                                                                                                                                                                                                                                                                                  | ✅ Complete |
| Phase 8  | Security & Fraud (advanced scoring, VPN detection, device fingerprinting, bot UA detection, Tor/datacenter IP blocklist, `fraud_signals` audit table, `IpReputationService`, `DeviceFingerprintService`, auto-flag + `FraudFlagRaised` broadcast, `releasePayouts`/`clearFlag`/`updateTrustScore` methods)                                                                                                                                                                                                                                                                                   | ✅ Complete |
| Phase 9  | Testing (unit tests for all services, feature tests for admin approval workflow, CI coverage reporting)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                      | ✅ Complete |
| Phase 10 | Deployment (Railway production config, env hardening)                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        | ⬜ Pending  |
| Phase 11 | Real-time Notifications — Laravel Reverb/WebSockets (private voter/politician channels, admin broadcast, ad-delivery push, payout alerts, live presence; WebRTC signaling foundation for Phase 12)                                                                                                                                                                                                                                                                                                                                                                                           | ✅ Complete |
| Phase 12 | **Mobile Application — React Native with Metro** (Android & iOS native apps; live feed streaming via WebRTC, politician → voter HLS/WebRTC live video built on Phase 11 Reverb server; Metro bundler for optimized app builds; native in-app notifications, token-based ad delivery, real-time earnings/payout updates; iOS deployment via Xcode, Android via Android Studio)              | ⬜ Pending  |
| Phase 13 | Politician Public Profile Pages — public `/p/{slug}` campaign pages with custom color themes (CSS variables, not raw CSS), layout presets, initiative/platform section, running + past campaign video/update sections, reusable public preview campaign cards with status/update badges, verified badge, Open Graph meta for social sharing; slug format: `{5-char-uuid-prefix}-{seo-readable-name}` (e.g. `a3f9b-mayor-john-smith-chicago`)                                                                                                                                                 | ✅ Complete |
| Phase 14 | Repeat Viewing + Campaign Scheduling — politician-controlled repeat-view toggle (cooldown hours, max views/voter cap, unique voter stats, repeat-view stats), campaign delivery window (`scheduled_start_at` / `scheduled_end_at`), `Scheduled` status, `campaigns:apply-schedule` Artisan command (every 5 min), scheduler-written audit log entries (`activated_by_schedule` / `paused_by_schedule`)                                                                                                                                                                                       | ✅ Complete |
| Phase 15 | Voter Benefits & Registration — expanded earnings callout (ad views + voter-referral recurring commission + politician-referral one-time bonus), voter registration status questionnaire on sign-up form with vote.gov link, registration status field stored on voter profile, dashboard registration prompt + voter registration status card in profile, and accessibility-focused voter dashboard updates (running campaigns prioritized first; clearer "Running Campaigns" navigation label)                                                                                             | ✅ Complete |
| Phase 16 | Public Records & Transparency — earned transparency model where only verified politicians can display integrated public data on their `/p/{slug}` profile page; government email verification system (.gov, .mil domains); opt-in toggles per data source (Ballotpedia voting record, OpenSecrets campaign finance, Vote Smart issue ratings, FEC filing data); API integration services with 24-hour caching; ProfileVerificationService with email verification flow; transparent data attribution (direct links to source platforms); display section on public profile below initiatives | ✅ Complete |
| Phase 17 | User Onboarding System — role-specific multi-phase onboarding flows with progress tracking (`user_onboarding_progress` table, `OnboardingService`, role-based middleware); Voter onboarding (5 phases: welcome, profile setup, first watch tutorial, payout setup, referral links); Politician onboarding (5 phases: welcome, political profile, payment method, first campaign, add credits); Admin onboarding (4 phases: welcome, campaign approval tutorial, fraud management, payout processing); skip option available                                                                  | ✅ Complete |
| Phase 18 | In-App Notification System — Laravel Notification classes for all event types, database-backed notification center, real-time notification bell UI component (Alpine.js), notification preferences per channel (email/in-app/push/SMS), mark-as-read/archive functionality, Firebase Cloud Messaging service for push notifications, Twilio SMS service integration, notification API endpoints (`/api/notifications/*`), per-user preferences management UI                                                                                                                                 | ✅ Complete |

## Roadmap Sprint Snapshot (Synced From Changelog)

This section mirrors the sprint-level implementation tracking in [doc/CHANGELOG.md](doc/CHANGELOG.md).

### 2026-03-15 — Roadmap Alignment Update

- Product-level U9itus changelog replaced framework placeholder release notes
- Sprint-by-sprint tracking format added
- Explicit status markers added (`Complete`, `In Progress`, `Planned`)

### Sprint 0 — Decision Sprint (Week of Mar 16, 2026)

Status: `Complete`

- Implemented: guest browsing recommendation (directory/profile view-only flow)
- Decision locked: existing campaigns retain historical stored rates (Option A)
- Decision locked: Q&A campaign type naming is `q_and_a` with V1 pricing parity to standard video campaigns
- Decision locked: fee transparency scope includes billing, invoices, and analytics summaries (Option B)
- Decision implemented: payout threshold canonical setting key unified to `min_payout_amount`

### Sprint 1 — Pilot-Ready Stabilization (Week of Mar 23, 2026)

Status: `Partially Complete`

- Implemented: Notification API coverage (list, unread count, mark one/all, auth guards)
- Implemented: notification bell hydration on dashboard UI
- Implemented: dynamic settings propagation for campaign pricing/payout and payout threshold usage in key voter and payout paths
- Implemented: fee transparency in politician billing, invoices, and analytics summaries
- Implemented: `q_and_a` campaign type enablement (enum, validation, forms, migration, and campaign CRUD test coverage)
- Remaining: logout `419` fix and 30-second hard max campaign duration enforcement

### Sprint 2 — Pilot Launch + District Foundation (Week of Mar 30, 2026)

Status: `Partially Complete`

- Implemented: district lookup by address
- Implemented: guest view-only politician directory browsing
- Implemented: guest public profile preview mode
- Implemented: homepage public browse entry points
- Implemented: candidate matching review/admin approval and import workflows
- Remaining: California profile seeding from API data; deeper profile auto-population from public sources

### Sprint 3 — Virtual Town Hall: Q&A Videos (Week of Apr 6, 2026)

Status: `Planned`

- Planned: topic-based voter browsing, intro + Q&A combined profile layout, hosted media expansion beyond YouTube, post-view engagement prompt
- Note: `q_and_a` campaign type groundwork is already implemented as Sprint 1 dependency work

### Sprint 4 — Transparency Layer (Week of Apr 13, 2026)

Status: `In Progress`

- Delivered/partially delivered: transparency controls on politician profiles, FEC integration in current configuration
- Planned: harden/expand Ballotpedia, OpenSecrets, Vote Smart integrations; add consolidated `Dig Deeper` research tab/section

### Sprint 5 — Engagement + Growth Loop (Week of Apr 20, 2026)

Status: `Planned`

- Planned: compensated micro-surveys, post-view verification prompt/quiz, automated approval criteria for scaling moderation, homepage/domain refinements, FAQ/support scaffolding

### Validation Snapshot (Current)

- Public politician directory guest browsing: passing
- Guest public profile preview mode: passing
- District lookup flow: passing
- Homepage public campaign browse entry path: passing
- Notification API flow: passing
- Notification bell UI hydration: passing
- Candidate matching admin review/import flow: passing
- Campaign CRUD Q&A campaign creation path: passing

## Future Enhancements

### Mobile Application Development (Phase 12)

**Framework:** React Native with Metro bundler (web & web-native code reuse)

**Target Platforms:**
- **Android:** Native app via Android Studio, Metro bundler optimization
- **iOS:** Native app via Xcode, Metro bundler optimization  
- **macOS:** Native desktop app support via Metro

**Mobile-First Features:**
- ~~Live feed streaming via WebRTC~~ → Phase 12 (politician → voter HLS/WebRTC streams built on Phase 11 Reverb)
- Native push notifications via Firebase Cloud Messaging (FCM) and Apple Push Notification (APN)
- Offline-first token-based ad delivery with background sync
- Native in-app notification center with badge counts
- Biometric authentication (Face ID, Touch ID, fingerprint)
- Photos app integration for profile pictures
- System camera integration for politician video uploads (mobile-optimized)
- Real-time wallet/earnings updates via WebSocket channels
- Payout request management with native payment sheet integration
- Metro bundle size optimization for mobile networks

**Shared Codebase:**
- API layer (voter registration, campaign browsing, view sessions, earnings)
- Business logic (referral commission calculation, fraud prevention scoring)
- Event handling (Reverb WebSocket subscriptions, Stripe webhooks)
- Authentication (Laravel Sanctum token management)
- Notification preferences (channel toggles, delivery methods)

### Web Platform Enhancements

- Advanced fraud detection with ML scoring
- Expand campaign video sources beyond YouTube v1 (Vimeo, Cloudflare Stream, HLS, S3 — see [Campaign Video Media](#campaign-video-media))
- ~~Real-time notifications via Laravel Reverb/WebSockets~~ → Phase 11 (✅ Complete)
- ~~Allow admin to stop campaigns, if there are errors, such as video not playing, incorrect locations~~ ✅ Implemented (stop/reactivate with required reason, full immutable audit log; real-time WebSocket push via Phase 11)
- Multi-language support
- Advanced analytics dashboard
- ~~Automated Stripe Connect for politician billing~~ ✅ Implemented (auto-customer creation, saved payment methods)
- ~~PayPal Mass Pay API for batch voter payouts~~ ✅ Implemented (`PayPalPayoutService` wired into `processBatchPayouts`)
- Twilio SMS integration — the **5-character UUID prefix** embedded in every politician's `slug` (e.g. `a3f9b` from `a3f9b-mayor-john-smith-chicago`) is intentionally designed as a stable short-ID that can serve as a lookup key for SMS verification, phone-based 2FA, and any future telephony service (Twilio Verify, short-code campaigns, etc.) without exposing the full UUID in a text message
- ~~Firebase Cloud Messaging for push notifications~~ → Phase 18 (✅ Complete on web; Phase 12 native integration for mobile)

### Public Politician Profile Next Steps

- Add voter-facing filters or tabs for `Intro`, `Issues`, and `Past Updates` inside the public campaign section on `/p/{slug}` pages
- Surface a "latest update" summary card above the running campaigns list so voters can quickly see what is new in the race
- Expand the transparency block into a simpler voter-friendly format that summarizes FEC, Vote Smart, OpenSecrets, and Ballotpedia data before linking to the full source detail

## Support

- **[Development Documentation](DEVELOPMENT.md)** — Development workflow
- **[Migration Notes](doc/MIGRATION_NOTES.md)** — Upgrade and migration history
- **[Changelog](doc/CHANGELOG.md)** — Version history
- GitHub Issues: https://github.com/jhead12/u9itus.dev/issues

## License

MIT License — See LICENSE file for details

## Credits

Developed by Head Enterprises  
**Version 3.0.0 — Standalone Laravel 12 Architecture (Web) + React Native with Metro (Mobile, Phase 12)**  
Last updated: March 18, 2026

**Development Timeline & Phase Completion:**

- **Phase 12 (Upcoming):** Mobile Application — React Native with Metro bundler for Android, iOS, and macOS; live feed streaming via WebRTC; native push notifications (FCM/APN); offline-first token delivery; biometric authentication
- **Phase 18 (Complete):** In-App Notification System — Laravel Notification classes, database-backed notification center, real-time UI, channel preferences (email/in-app/push/SMS), Firebase Cloud Messaging, Twilio SMS services
- **Phase 17 (Complete):** User Onboarding System with role-specific multi-phase flows (voter: 5 phases, politician: 5 phases, admin: 4 phases), `OnboardingService`, progress tracking, middleware integration
- **Phase 16 (Complete):** Public Records & Transparency — Government email verification system, API integrations (Ballotpedia, OpenSecrets, Vote Smart, FEC), opt-in transparency controls, public data display on politician profiles
- **Phase 11 (Complete):** Laravel Reverb/WebSockets real-time notification system with 9 broadcast events (`ReverbBroadcastService`, private channels for voter/politician/admin, presence channel foundation for Phase 12 WebRTC)
- **Phase 8 (Complete):** `IpReputationService`, `DeviceFingerprintService`, enhanced `FraudPreventionService`, `fraud_signals` table
- **Phases 1–7, 9–10, 13–15 (Complete):** Auth & Foundation, Campaign Management, Analytics, Billing, Voter Watch, Admin Features, Notifications, Testing, Deployment, Profile Pages, Repeat Viewing, Voter Benefits
- **Route Fix:** `admin.payouts` → `admin.payouts.index` in `payouts-pending.blade.php`
