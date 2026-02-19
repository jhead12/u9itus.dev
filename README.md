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

| Component                                 | Amount                          |
| ----------------------------------------- | ------------------------------- |
| Politician pays per view                  | **$0.60**                       |
| Voter earns per view                      | **$0.25**                       |
| Referral commission (10% of voter payout) | $0.025                          |
| Payment processing (estimated)            | ~$0.02                          |
| Ops & infrastructure                      | ~$0.03–$0.12                    |
| **Platform net profit**                   | **$0.18–$0.30 (30–50% margin)** |

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

| Table                          | Purpose                                                        |
| ------------------------------ | -------------------------------------------------------------- |
| **politicians**                | Politician profiles, governance level, office, district, party |
| **voters**                     | Voter profiles, wallet balance, referral codes, trust score    |
| **political_campaigns**        | Video/live-feed campaigns with per-view pricing and targeting  |
| **view_sessions**              | Individual view tracking — watch time, fraud score, payouts    |
| **referral_earnings**          | Referral commission records per view session                   |
| **ad_view_tokens**             | One-time secure tokens for ad delivery via notifications       |
| **campaign_transactions**      | Stripe payment records per politician                          |
| **politician_credits**         | Credit balance ledger for per-view billing                     |
| **politician_payment_methods** | Stored Stripe payment methods per politician                   |

### Services

| Service                         | Purpose                                                            |
| ------------------------------- | ------------------------------------------------------------------ |
| **PoliticalViewService**        | View lifecycle: assign → start → track → complete                  |
| **PoliticalPaymentService**     | Campaign billing, batch payouts, per-view profit calculation       |
| **FraudPreventionService**      | Device fingerprinting, rate limits, IP anomalies, trust scoring    |
| **CampaignBillingService**      | Stripe PaymentIntent creation, credit top-up, credit deduction     |
| **StripePaymentService**        | Low-level Stripe SDK wrapper (customers, payment methods, intents) |
| **StandardNotificationService** | Email/SMS notification delivery                                    |
| **StandardAuthService**         | Laravel session-based authentication                               |

### Controllers

| Controller                          | Purpose                                                                            |
| ----------------------------------- | ---------------------------------------------------------------------------------- |
| **Standalone\AuthController**       | Separate politician/voter registration, shared login, admin portal, password reset |
| **Standalone\DashboardController**  | Role-based dashboard routing                                                       |
| **Standalone\PoliticianController** | Full campaign CRUD, video upload, analytics, billing, profile                      |
| **Standalone\VoterController**      | Ad watching, earnings, referrals                                                   |
| **Standalone\AdminController**      | User management, fraud, payouts, campaign approval                                 |
| **SecureAdViewController**          | Token-based ad viewing, notification distribution                                  |
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

Requires `auth`, `verified`, and `role:admin` middleware. Access via `/admin/login`.

| Method | URL                             | Purpose                     |
| ------ | ------------------------------- | --------------------------- |
| `GET`  | `/admin/dashboard`              | Admin overview              |
| `GET`  | `/admin/campaigns/pending`      | Campaigns awaiting approval |
| `POST` | `/admin/campaigns/{id}/approve` | Approve campaign            |
| `POST` | `/admin/campaigns/{id}/reject`  | Reject campaign             |
| `GET`  | `/admin/users`                  | User list                   |
| `GET`  | `/admin/fraud`                  | Fraud dashboard             |
| `GET`  | `/admin/payouts`                | Payout overview             |
| `POST` | `/admin/payouts/batch-process`  | Run batch payout processing |
| `GET`  | `/admin/analytics`              | Platform analytics          |
| `GET`  | `/admin/settings`               | System settings             |

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
'revenue_per_view'         => 0.60,
'voter_payout_per_view'    => 0.25,
'referral_commission_pct'  => 10,
'min_watch_percent'        => 100,
'video_duration_min'       => 10,
'video_duration_max'       => 20,
'batch_payout_min'         => 10.00,
'fraud_daily_view_limit'   => 50,
'fraud_payout_hold_hours'  => 48,
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
php artisan test
```

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

| Phase    | Description                                                                        | Status      |
| -------- | ---------------------------------------------------------------------------------- | ----------- |
| Phase 1  | Auth & Foundation (auth views, dashboard layout, middleware, email verification)   | ✅ Complete |
| Phase 2  | Campaign Management (full CRUD, video upload, analytics, billing, profile views)   | ✅ Complete |
| Phase 3  | Analytics & Tracking (ViewSession lifecycle API, fraud detection, payout dispatch) | ✅ Complete |
| Phase 4  | Billing scaffold (Stripe service, webhook, credit ledger, billing views)           | ✅ Complete |
| Phase 5  | Voter watch experience (token-based video delivery, JS heartbeat)                  | ✅ Complete |
| Phase 6  | Admin features (campaign approval queue, KYC management, fraud review)             | ⬜ Pending  |
| Phase 7  | Notifications (email on approval/rejection/completion)                             | ⬜ Pending  |
| Phase 8  | Security & Fraud (advanced scoring, VPN detection, device fingerprinting)          | ⬜ Pending  |
| Phase 9  | Testing                                                                            | ✅ Ongoing  |
| Phase 10 | Deployment (Railway production config, env hardening)                              | ⬜ Pending  |

## Future Enhancements

- Live feed streaming via WebRTC
- Real-time notifications via Laravel Reverb/WebSockets
- Advanced fraud detection with ML scoring
- Mobile app (React Native)
- Multi-language support
- Advanced analytics dashboard
- Automated Stripe Connect for politician billing
- PayPal Mass Pay API for batch voter payouts
- Twilio SMS integration
- Firebase Cloud Messaging for push notifications

## Support

- **[Development Documentation](DEVELOPMENT.md)** — Development workflow
- **[Migration Notes](doc/MIGRATION_NOTES.md)** — Upgrade and migration history
- **[Changelog](doc/CHANGELOG.md)** — Version history
- GitHub Issues: https://github.com/jhead12/u9itus.dev/issues

## License

MIT License — See LICENSE file for details

## Credits

Developed by Head Enterprises  
Version 3.0.0 — Standalone Laravel 12 Architecture
