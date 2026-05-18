# Database Schema

## Tables

| Table | Purpose |
|-------|---------|
| **politicians** | Politician profiles — governance level, office, district, party, `slug`, `page_settings` (JSON), `page_published`, verification status, transparency opt-ins |
| **politician_pages** | _(Phase 13)_ Public page theme config — layout preset, colors, background style, hero banner, section toggles, custom CTA |
| **politician_initiatives** | _(Phase 13)_ Policy positions / platform planks — title, description, icon, sort order, published flag |
| **voters** | Voter profiles — wallet balance, referral codes, trust score |
| **political_campaigns** | Video/live-feed campaigns with per-view pricing and geo-targeting |
| **view_sessions** | Individual view tracking — watch time, fraud score, payouts |
| **referral_earnings** | Referral commission records — voter-view (recurring) and politician-procurement (one-time) |
| **ad_view_tokens** | One-time secure SHA-256 tokens for ad delivery via notifications |
| **campaign_transactions** | Stripe payment records per politician |
| **politician_credits** | Credit balance ledger for per-view billing |
| **politician_payment_methods** | Stored Stripe payment methods per politician |
| **campaign_audit_logs** | Immutable admin action log — field-level diffs for approve/reject/edit/stop/reactivate |
| **fraud_signals** | _(Phase 8)_ Per-event fraud signal log — signal type, score impact, IP/fingerprint context, admin resolution |
| **user_onboarding_progress** | _(Phase 17)_ Per-user onboarding state — current phase, completed phases (JSON), phase data, completion status |
| **notification_preferences** | _(Phase 18)_ Per-user notification channel preferences — email/in-app/push/SMS toggles, FCM token, phone |
| **notifications** | _(Phase 18)_ Laravel built-in notifications table — polymorphic notifiable, `read_at`, data (JSON) |

## Service Layer

| Service | Purpose |
|---------|---------|
| **PoliticalViewService** | View lifecycle: assign → start → track → complete |
| **PoliticalPaymentService** | Campaign billing, batch payouts, per-view profit calculation |
| **FraudPreventionService** | Multi-signal fraud scoring: rate limits, device fingerprinting, bot UA, IP anomalies, VPN/Tor/datacenter, auto-flag, `fraud_signals` audit |
| **CampaignBillingService** | Stripe PaymentIntent creation, credit top-up, credit deduction |
| **StripePaymentService** | Low-level Stripe SDK wrapper (customers, payment methods, intents) |
| **StandardNotificationService** | Email/SMS notification delivery |
| **StandardAuthService** | Laravel session-based authentication |
| **IpReputationService** | VPN / proxy / Tor / datacenter IP detection via CIDR blocklist + optional ipinfo.io enrichment |
| **DeviceFingerprintService** | Server-side composite fingerprint generation, bot UA analysis, fingerprint compare/store |
| **ReverbBroadcastService** | WebSocket event dispatch — ad delivery, payout alerts, campaign status, presence |
| **OnboardingService** | _(Phase 17)_ Multi-phase onboarding management — phase definitions per role, progress tracking, skip handling |
| **BallotpediaService** | _(Phase 16)_ Ballotpedia API integration — voting records, committee assignments, sponsored legislation; 24-hour cache |
| **OpenSecretsService** | _(Phase 16)_ OpenSecrets API integration — campaign finance data, top contributors, industry breakdown; 24-hour cache |
| **VoteSmartService** | _(Phase 16)_ Vote Smart API integration — interest group ratings, issue positions, key votes; 24-hour cache |
| **FECService** | _(Phase 16)_ FEC API integration — official filings, financial summaries, committee data; federal candidates only; 24-hour cache |
| **ProfileVerificationService** | _(Phase 16)_ Government email verification — `.gov`/`.mil` domain validation, token generation/validation |
| **FirebaseCloudMessagingService** | _(Phase 18)_ FCM push notification delivery — OAuth 2.0 auth, payload assembly, FCM token management |
| **TwilioSmsService** | _(Phase 18)_ Twilio SMS delivery — phone number validation, E.164 formatting, delivery tracking |

## Controllers

| Controller | Purpose |
|------------|---------|
| **Standalone\AuthController** | Separate politician/voter registration, shared login, admin portal, password reset |
| **Standalone\DashboardController** | Role-based dashboard routing |
| **Standalone\PoliticianController** | Campaign CRUD, video upload, analytics, billing, profile |
| **Standalone\VoterController** | Ad room, token-based ad watching, earnings, referrals |
| **Standalone\AdminController** | User management, fraud, payouts, campaign approval |
| **Api\PoliticianController** | Politician CRUD, campaign management (API) |
| **Api\VoterController** | Registration, view sessions, earnings (API) |
| **Api\AdminController** | Analytics, approvals, payouts, fraud (API) |
| **Api\StripeWebhookController** | `payment_intent.succeeded` / `payment_intent.payment_failed` |

---

← [Routes and API](Routes-and-API.md) | [Security and Fraud →](Security-and-Fraud.md)
