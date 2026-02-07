# Dial4Dough System Architecture - Architech Canvas Workflow

## Overview

This document describes the complete system architecture for Dial4Dough that can be built in Architech Canvas at https://www.architech-dev.tech/canvas

---

## Components (Nodes)

### 1. **User Layer**

#### Politician User

- **Type**: User
- **Description**: Political candidate/office holder who creates campaigns
- **Actions**: Register, Create Campaign, Add Payment Method, Monitor Campaign

#### Voter User

- **Type**: User
- **Description**: Registered voter who watches political ads and earns money
- **Actions**: Register, Watch Video, Generate Referral Code, Request Payout

#### Admin User

- **Type**: User
- **Description**: Platform administrator
- **Actions**: Approve/Reject Campaigns, Review Fraud, Process Payouts

---

### 2. **Frontend Layer**

#### Wix Dashboard (Politician)

- **Type**: UI
- **Technology**: Wix Dashboard Extension (React)
- **Port**: N/A (Wix-hosted)
- **Endpoints Used**:
    - POST /api/politicians
    - POST /api/campaigns
    - GET /api/campaigns/{id}
    - POST /api/campaigns/{id}/payment

#### Wix Dashboard (Voter)

- **Type**: UI
- **Technology**: Wix Dashboard Extension (React)
- **Port**: N/A (Wix-hosted)
- **Endpoints Used**:
    - POST /api/voters
    - GET /api/voters/{id}/campaigns/available
    - POST /api/view-sessions
    - GET /api/voters/{id}/earnings

#### Wix Dashboard (Admin)

- **Type**: UI
- **Technology**: Wix Dashboard Extension (React)
- **Port**: N/A (Wix-hosted)
- **Endpoints Used**:
    - GET /api/admin/campaigns/pending
    - POST /api/admin/campaigns/{id}/approve
    - POST /api/admin/campaigns/{id}/reject
    - GET /api/admin/fraud-reviews
    - POST /api/admin/payouts/process

#### Laravel Welcome Page

- **Type**: UI
- **Technology**: Laravel Blade Template
- **URL**: https://dial4doughdev-production.up.railway.app
- **Shows**: Revenue model, profit margins, platform economics

---

### 3. **API Gateway / Backend**

#### Laravel API Server

- **Type**: Server
- **Technology**: Laravel 12 / PHP 8.2
- **Port**: 8080
- **URL**: https://dial4doughdev-production.up.railway.app
- **Hosting**: Railway.app
- **Responsibilities**:
    - RESTful API endpoints
    - Business logic orchestration
    - Authentication & authorization
    - Request validation
    - Response formatting

---

### 4. **Service Layer**

#### Auth Service (Wix OAuth)

- **Type**: Service A
- **Technology**: Laravel + Wix SDK
- **Responsibilities**:
    - Wix OAuth flow (`/wix/oauth/callback`)
    - Instance token validation
    - Site installation tracking
    - Member authentication
- **External Dependency**: Wix API (https://www.wixapis.com)

#### Campaign Management Service

- **Type**: Service B
- **Technology**: Laravel Service Class
- **Responsibilities**:
    - Campaign creation & validation
    - Budget management
    - Status transitions (pending → active → completed)
    - View count tracking
- **Methods**:
    - `createCampaign()`
    - `approveCampaign()`
    - `incrementViewCount()`
    - `calculateCompletionPercentage()`

#### View Tracking Service

- **Type**: Service C
- **Technology**: Laravel Service Class
- **Responsibilities**:
    - Track video watch time
    - Validate completion threshold (80%)
    - Update view session status
    - Calculate earnings
- **Methods**:
    - `startViewSession()`
    - `trackProgress()`
    - `completeView()`
    - `validateWatchTime()`

#### Payment Processing Service

- **Type**: Service D
- **Technology**: Laravel Service Class + Stripe SDK
- **Responsibilities**:
    - Charge politicians (Stripe)
    - Calculate payouts to voters
    - Process referral commissions
    - Handle payment holds
- **Methods**:
    - `chargeAdvertiser($amount)`
    - `calculateViewerEarnings($viewCount)`
    - `processReferralCommission($referrerId, $amount)`
    - `batchPayoutToVoters()`

#### Fraud Detection Service

- **Type**: Service E
- **Technology**: Laravel Service Class
- **Responsibilities**:
    - Detect suspicious patterns
    - Flag high-volume viewers
    - Rate limiting per voter
    - IP/device fingerprinting
- **Rules**:
    - Max 50 views per day per voter
    - Max 5 views per hour
    - Duplicate IP detection
    - Watch time pattern analysis
- **Methods**:
    - `checkVoterActivity($voterId)`
    - `flagSuspiciousViews($sessionId)`
    - `holdPayments($voterId)`

#### Referral System Service

- **Type**: Service F
- **Technology**: Laravel Service Class
- **Responsibilities**:
    - Generate unique referral codes
    - Track referral relationships
    - Calculate 10% commissions
    - Credit referral earnings
- **Methods**:
    - `generateReferralCode($voterId)`
    - `trackReferralSignup($code, $newVoterId)`
    - `creditReferralEarnings($referrerId, $amount)`

#### Webhook Handler Service

- **Type**: Service G
- **Technology**: Laravel Service Class
- **Responsibilities**:
    - Process Wix webhooks
    - Handle app install/uninstall
    - Member login events
    - Signature verification
- **Endpoints**:
    - POST `/api/wix/webhooks`
- **Events**:
    - App Installed
    - App Removed
    - Member Registered
    - Member Login

---

### 5. **Data Layer**

#### MySQL Database (Primary)

- **Type**: Database
- **Technology**: MySQL 9.4.0
- **Hosting**: Railway.app
- **Host**: shinkansen.proxy.rlwy.net:39648
- **Connection**: Public TCP Proxy
- **Tables**:
    - `users` - User accounts
    - `advertisers` - Politician metadata
    - `campaigns` - Ad campaigns
    - `ad_assignments` - Campaign-to-advertiser mapping
    - `loyalty_viewers` - Voter accounts
    - `view_sessions` - Individual video views
    - `referral_earnings` - Commission tracking
    - `wix_sites` - Installed Wix sites
    - `wix_site_members` - Wix member mappings
    - `political_campaigns` - Political campaign details
    - `political_ad_requests` - Ad submission requests
    - `voter_views` - View history
    - `voter_earnings` - Earning records
    - `notifications` - System notifications

#### Redis Cache (Future)

- **Type**: Database
- **Technology**: Redis
- **Status**: Not yet implemented
- **Planned Use**:
    - Session storage
    - Rate limiting counters
    - View count caching
    - Queue management

---

### 6. **External Services**

#### Stripe Payment Gateway

- **Type**: External Service
- **Provider**: Stripe
- **API**: https://api.stripe.com
- **Responsibilities**:
    - Process politician payments ($0.60 per view)
    - Handle voter payouts (batch $0.25 per view)
    - Manage payment methods
    - Process refunds
- **Webhooks**: Payment confirmation, failed payments

#### Wix Platform

- **Type**: External Service
- **Provider**: Wix.com
- **API**: https://www.wixapis.com
- **Responsibilities**:
    - OAuth authentication
    - Member management
    - Site information
    - Dashboard hosting
- **Webhooks**: App lifecycle events, member events

#### Video Hosting (YouTube/Vimeo)

- **Type**: External Service
- **Provider**: YouTube, Vimeo, or custom
- **Responsibilities**:
    - Host political ad videos
    - Provide embed URLs
    - Track video playback
    - Provide duration metadata

---

## Data Flows (Connections)

### Flow 1: Politician Campaign Creation

```
Politician User → Wix Dashboard (Politician) → Laravel API Server
  → Campaign Management Service → MySQL Database
  → Payment Processing Service → Stripe Payment Gateway
  → Laravel API Server → Wix Dashboard (Politician)
```

**Steps:**

1. Politician fills out campaign form (title, video URL, budget, target views)
2. Wix Dashboard sends POST `/api/campaigns`
3. Laravel validates request
4. Campaign Management Service creates campaign (status: pending)
5. Payment Processing Service creates Stripe payment intent
6. Record saved to `campaigns` table
7. Success response returned with campaign ID

---

### Flow 2: Admin Campaign Approval

```
Admin User → Wix Dashboard (Admin) → Laravel API Server
  → Campaign Management Service → MySQL Database
  → Payment Processing Service → Stripe Payment Gateway
  → Laravel API Server → Wix Dashboard (Admin)
```

**Steps:**

1. Admin views pending campaigns
2. Admin clicks "Approve" button
3. Wix Dashboard sends POST `/api/admin/campaigns/{id}/approve`
4. Laravel validates admin permissions
5. Campaign Management Service updates status to "active"
6. Payment Processing Service captures Stripe payment ($0.60 × target_views)
7. Campaign now visible to voters
8. Success response with updated campaign

---

### Flow 3: Voter Watches Video & Earns

```
Voter User → Wix Dashboard (Voter) → Laravel API Server
  → View Tracking Service → MySQL Database
  → Fraud Detection Service → MySQL Database
  → Payment Processing Service → MySQL Database
  → Laravel API Server → Wix Dashboard (Voter)
```

**Steps:**

1. Voter opens voter dashboard
2. Wix Dashboard fetches GET `/api/voters/{id}/campaigns/available`
3. Voter clicks "Watch Video"
4. Wix Dashboard sends POST `/api/view-sessions` with campaign_id
5. View Tracking Service creates view session (status: in_progress)
6. Fraud Detection Service checks voter activity limits
7. Timer tracks watch time in frontend
8. When 80% completed, send PUT `/api/view-sessions/{id}/complete`
9. View Tracking Service validates completion
10. Payment Processing Service credits $0.25 to voter earnings
11. Campaign Management Service increments views_completed
12. If voter has referrer, Referral System Service credits $0.025 commission
13. Success response with updated earnings

---

### Flow 4: Referral Commission

```
Voter User (Referrer) → Wix Dashboard (Voter) → Laravel API Server
  → Referral System Service → MySQL Database
  → Laravel API Server → Wix Dashboard (Voter)

Voter User (New) → Wix Dashboard (Voter) → Laravel API Server
  → Referral System Service → MySQL Database
  [Triggers when new voter completes first view]
  → Payment Processing Service → MySQL Database
```

**Steps:**

1. Existing voter generates referral code
2. Send GET `/api/voters/{id}/referral-code`
3. Referral System Service creates/retrieves unique code
4. Voter shares code with friend
5. New voter registers with referral code
6. Send POST `/api/voters` with `referral_code` parameter
7. Referral System Service links voters (referred_by field)
8. When new voter completes ANY view:
    - Payment Processing Service calculates $0.25 for viewer
    - Referral System Service calculates $0.025 (10%) for referrer
    - Both amounts credited to respective pending_earnings
    - Record created in `referral_earnings` table

---

### Flow 5: Fraud Detection & Review

```
Voter User → Wix Dashboard (Voter) → Laravel API Server
  → Fraud Detection Service → MySQL Database
  [If suspicious activity detected]
  → Laravel API Server → Admin User (notification)

Admin User → Wix Dashboard (Admin) → Laravel API Server
  → Fraud Detection Service → MySQL Database
  → Payment Processing Service → MySQL Database
```

**Steps:**

1. During view completion, Fraud Detection Service runs checks:
    - Views per day count
    - Views per hour count
    - IP address patterns
    - Watch time patterns
2. If threshold exceeded (50 views/day):
    - Set `flagged_for_fraud = true` on voter
    - Set `payment_status = 'held'` on view sessions
    - Create admin notification
3. Admin opens fraud review dashboard
4. Admin reviews voter activity history
5. Admin makes decision:
    - **Clear flag**: POST `/api/admin/fraud/{id}/clear`
        - Releases held payments
        - Unflag voter
    - **Confirm fraud**: POST `/api/admin/fraud/{id}/confirm`
        - Rejects held payments
        - Bans voter account

---

### Flow 6: Payout Processing (Batch)

```
Admin User → Wix Dashboard (Admin) → Laravel API Server
  → Payment Processing Service → MySQL Database
  → Payment Processing Service → Stripe Payment Gateway
  → MySQL Database
  → Laravel API Server → Admin User (notification)
```

**Steps:**

1. Admin clicks "Process Payouts" (weekly/threshold-based)
2. Send POST `/api/admin/payouts/process`
3. Payment Processing Service queries voters with `pending_earnings > $10`
4. For each eligible voter:
    - Create Stripe payout to voter's payment method
    - Move amount from `pending_earnings` to `total_earned`
    - Set `last_payout_date`
    - Create record in `payouts` table
5. Batch process completes
6. Return summary: total voters paid, total amount, any failures

---

### Flow 7: Wix App Installation

```
Wix Platform → Laravel API Server (Webhook)
  → Webhook Handler Service → Auth Service
  → MySQL Database → Laravel API Server
```

**Steps:**

1. User installs Dial4Dough app on Wix site
2. Wix sends webhook POST `/api/wix/webhooks` with event "app.installed"
3. Webhook Handler Service validates signature
4. Auth Service creates record in `wix_sites` table
5. Send OAuth redirect to user for permissions
6. User grants permissions
7. Wix redirects to `/wix/oauth/callback?code=xxx`
8. Auth Service exchanges code for access token
9. Store access_token and refresh_token in `wix_sites`
10. App now fully installed and authenticated

---

## Per-View Economics Breakdown

### Revenue Model ($0.60 per view)

```
Politician Pays: $0.60
  ↓
├─ Viewer Payout: $0.25 (42%)
├─ Referral Commission: $0.025 (4%) [only if referred viewer]
├─ Payment Processing: $0.02 (3%)
├─ Operations (CDN, servers, fraud, support): $0.03-$0.12 (5-20%)
└─ Platform Profit: $0.21-$0.30 (35-50%)
```

### Target Margins

- **Best case** (efficient ops): 50% margin ($0.30 profit)
- **Conservative**: 35% margin ($0.21 profit)
- **Planning target**: 25-40% net margin

---

## Architecture Patterns

### 1. Request/Response Pattern

- All API calls follow REST conventions
- JSON request/response format
- HTTP status codes for success/error
- Consistent error response structure

### 2. Service Layer Pattern

- Business logic isolated in service classes
- Controllers are thin (validation + service calls)
- Services are reusable across controllers
- Single Responsibility Principle

### 3. Event-Driven (Webhooks)

- Wix platform sends async events
- Laravel webhook handler validates signatures
- Events trigger background jobs
- Idempotent webhook processing

### 4. Database Transaction Pattern

- Multi-step operations wrapped in DB transactions
- Rollback on any failure
- Ensures data consistency
- Example: Campaign approval + payment capture

### 5. Fraud Detection Pattern

- Real-time checks during view completion
- Hold payments, don't reject immediately
- Admin review queue for borderline cases
- Machine learning patterns (future)

---

## Security Measures

### 1. Authentication

- Wix OAuth 2.0 for user authentication
- JWT tokens for API requests
- Instance token validation for dashboard requests
- API key authentication for external services

### 2. Authorization

- Role-based access control (politician/voter/admin)
- Route middleware for permission checks
- Resource ownership validation
- Rate limiting per user/IP

### 3. Input Validation

- Laravel Form Request validation
- Type casting and sanitization
- SQL injection prevention (Eloquent ORM)
- XSS prevention (Blade templating)

### 4. Payment Security

- PCI compliance via Stripe
- No credit card data stored
- Payment intent confirmation required
- Webhook signature verification

### 5. Fraud Prevention

- Device fingerprinting
- IP address tracking
- Rate limiting (50 views/day)
- Watch time pattern analysis
- Manual admin review queue

---

## Scaling Considerations

### Current Scale (MVP)

- Single Laravel API server
- MySQL database on Railway
- Synchronous request processing
- Direct payment processing

### Future Scale (10K+ users)

- **Load Balancing**: Multiple Laravel instances behind load balancer
- **Queue System**: Laravel Queues + Redis for async jobs
- **CDN**: CloudFlare for static assets and video delivery
- **Database**: Read replicas for analytics queries
- **Caching**: Redis for session storage and rate limiting
- **Microservices**: Split payment/fraud into separate services

---

## Monitoring & Observability

### Metrics to Track

1. **Business Metrics**:
    - Total views per day
    - Revenue per view (actual vs target $0.60)
    - Voter earnings payout rate
    - Campaign approval rate/time
    - Referral conversion rate

2. **Technical Metrics**:
    - API response time (p50, p95, p99)
    - Database query time
    - Error rate by endpoint
    - Queue job processing time
    - Payment success rate

3. **Fraud Metrics**:
    - Flagged voters per day
    - False positive rate
    - Average views per voter
    - Payment hold volume
    - Admin review backlog

### Logging

- Laravel logs: `/storage/logs/laravel.log`
- Wix webhook events
- Payment processing logs
- Fraud detection alerts
- Admin actions audit log

---

## Deployment Pipeline

### Current Setup

```
GitHub (master branch)
  ↓ (git push)
Railway.app (auto-deploy)
  ↓ (build via nixpacks.toml)
Deploy to production
  ↓ (wait-for-db.sh)
Run database migrations
  ↓ (php artisan serve)
Start web server (port 8080)
```

### Environment Variables

- `APP_ENV=production`
- `DB_HOST=shinkansen.proxy.rlwy.net`
- `WIX_APP_ID`, `WIX_APP_SECRET`
- `STRIPE_KEY`, `STRIPE_SECRET`

---

## Canvas Instructions

To build this in Architech Canvas:

1. **Add User Nodes**: Drag 3 "User" components (Politician, Voter, Admin)

2. **Add UI Nodes**: Drag 3 "Frontend" components (Wix Politician Dashboard, Wix Voter Dashboard, Wix Admin Dashboard)

3. **Add Server Node**: Drag 1 "Server" component (Laravel API)

4. **Add Service Nodes**: Drag 7 "Service" components:
    - Auth Service (Wix OAuth)
    - Campaign Management
    - View Tracking
    - Payment Processing
    - Fraud Detection
    - Referral System
    - Webhook Handler

5. **Add Database Node**: Drag 1 "Database" component (MySQL)

6. **Add External Service Nodes**: Drag 3 external services:
    - Stripe
    - Wix Platform
    - Video Hosting

7. **Connect the Nodes**:
    - Users → Frontend dashboards (labeled "interacts")
    - Frontend dashboards → Laravel API (labeled "requests")
    - Laravel API → All services (labeled "calls")
    - Services → Database (labeled "queries")
    - Services → External services (labeled "calls")
    - Payment Processing → Stripe (labeled "charges/payouts")
    - Auth Service → Wix Platform (labeled "authenticates")
    - Webhook Handler ← Wix Platform (labeled "sends events")

8. **Label Each Node** with the names and descriptions from this document

9. **Add Annotations** for the 7 data flows described above

---

## Key Takeaways

✅ **Profitable**: 25-50% margins per view with $0.60 pricing model  
✅ **Scalable**: Service-oriented architecture ready for growth  
✅ **Secure**: Multi-layer fraud detection and payment security  
✅ **Integrated**: Seamless Wix platform integration via OAuth  
✅ **Transparent**: Clear revenue breakdown for all stakeholders

**Status**: ✅ Deployed and operational at https://dial4doughdev-production.up.railway.app
