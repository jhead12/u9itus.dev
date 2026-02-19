# Politician Campaign System - Standalone Laravel Implementation

**Version:** 1.0.0  
**Platform:** Laravel 12 Standalone  
**Target:** Standalone Laravel-based Campaign System  
**Date:** February 17, 2026

## Overview

This document outlines the complete implementation plan for building a standalone campaign management system for the Politician partner. The system will allow politicians to create, manage, and track video promotion campaigns with comprehensive analytics, payment integration, and viewer tracking.

## System Features

### Core Capabilities

- ✅ Campaign creation and management (video promotions)
- ✅ Stripe payment integration for campaign budgets
- ✅ Real-time viewer statistics and analytics
- ✅ Geolocation tracking of viewers
- ✅ Credit/budget management system
- ✅ Cost tracking per campaign
- ✅ Video upload and hosting
- ✅ Comprehensive dashboard with visualizations

---

## Phase 1: Foundation & Authentication

### 1.1 Setup Stripe Integration

**Files to Create/Modify:**

- `config/services.php` - Add Stripe configuration
- `composer.json` - Add Stripe PHP SDK
- `.env` - Add Stripe keys

**Tasks:**

- [ ] Install Stripe PHP SDK: `composer require stripe/stripe-php`
- [ ] Add Stripe configuration to `config/services.php`:

```php
'stripe' => [
    'secret' => env('STRIPE_SECRET_KEY'),
    'public' => env('STRIPE_PUBLIC_KEY'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
],
```

- [ ] Add Stripe keys to `.env`:

```env
STRIPE_SECRET_KEY=sk_test_...
STRIPE_PUBLIC_KEY=pk_test_...
STRIPE_WEBHOOK_SECRET=whsec_...
```

- [ ] Create `app/Services/StripePaymentService.php` for payment processing
- [ ] Create webhook endpoint for Stripe events: `POST /api/stripe/webhooks`

**Documentation Reference:**

- Review legacy Stripe implementation in `legacy/app/controllers/AccountController.php` (lines 120-650)
- Existing payment service: `app/Services/PoliticalPaymentService.php`

---

### 1.2 Create Standalone Authentication System

**Files to Create:**

- `routes/standalone.php` - Standalone-specific routes
- `app/Http/Controllers/Standalone/AuthController.php`

- `resources/views/standalone/auth/` - Login/Register views

**Tasks:**

- [ ] Create `routes/standalone.php` for standalone routes
- [ ] Implement politician registration with email verification
- [ ] Implement login/logout with remember me functionality
- [ ] Add two-factor authentication (optional for security)
- [ ] Create password reset flow
- [ ] Add session management
- [ ] Create `AuthController` with methods:
    - `showRegister()`, `register()`
    - `showLogin()`, `login()`
    - `logout()`
    - `showForgotPassword()`, `sendResetLink()`
    - `showResetPassword()`, `resetPassword()`

**Database Tables:**

- Use existing `users` table with `user_type` = 'politician'
- Use existing `politicians` table for profile data

---

### 1.3 Build Politician Dashboard Layout

**Files to Create:**

- `resources/views/standalone/layouts/dashboard.blade.php`
- `resources/views/standalone/dashboard/index.blade.php`
- `public/css/standalone/dashboard.css`
- `resources/js/standalone/dashboard.js`

**Tasks:**

- [ ] Create responsive dashboard layout with sidebar navigation
- [ ] Add navigation items: Dashboard, Campaigns, Analytics, Billing, Profile, Settings
- [ ] Create header with user profile dropdown
- [ ] Add notification bell for system alerts
- [ ] Implement dark/light mode toggle
- [ ] Create responsive mobile menu

**UI Components:**

- Sidebar navigation
- Top bar with search and notifications
- Main content area
- Footer with links and version

---

## Phase 2: Campaign Management System

### 2.1 Campaign CRUD Operations

**Files to Create:**

- `app/Http/Controllers/Standalone/CampaignController.php`
- `app/Http/Requests/CreateStandaloneCampaignRequest.php`
- `app/Http/Requests/UpdateStandaloneCampaignRequest.php`
- `resources/views/standalone/campaigns/` - CRUD views

**Tasks:**

- [ ] Create campaign listing page with filters (active, paused, completed, draft)
- [ ] Build campaign creation form with validation:
    - Title, description
    - Campaign type (video message, live feed)
    - Governance level (Federal, State, County, City, School Board)
    - Target audience (states, cities, districts)
    - Budget allocation ($0.60 per view minimum)
    - Start/end dates
    - Video upload
- [ ] Implement campaign edit functionality
- [ ] Add campaign pause/resume feature
- [ ] Create campaign duplication feature
- [ ] Add campaign archive/delete with confirmation
- [ ] Implement campaign status workflow:
    - Draft → Pending Approval → Active → Paused → Completed

**Routes:**

```php
Route::prefix('campaigns')->name('campaigns.')->group(function () {
    Route::get('/', 'CampaignController@index')->name('index');
    Route::get('/create', 'CampaignController@create')->name('create');
    Route::post('/', 'CampaignController@store')->name('store');
    Route::get('/{campaign}', 'CampaignController@show')->name('show');
    Route::get('/{campaign}/edit', 'CampaignController@edit')->name('edit');
    Route::put('/{campaign}', 'CampaignController@update')->name('update');
    Route::post('/{campaign}/pause', 'CampaignController@pause')->name('pause');
    Route::post('/{campaign}/resume', 'CampaignController@resume')->name('resume');
    Route::post('/{campaign}/duplicate', 'CampaignController@duplicate')->name('duplicate');
    Route::delete('/{campaign}', 'CampaignController@destroy')->name('destroy');
});
```

---

### 2.2 Video Upload & Management

**Files to Create:**

- `app/Services/VideoUploadService.php`
- `app/Http/Controllers/Standalone/MediaController.php`
- `config/media.php`

**Tasks:**

- [ ] Implement chunked video upload for large files
- [ ] Add video validation (format: MP4, max size: 500MB, max duration: 5 min)
- [ ] Create video processing queue job for:
    - Thumbnail generation
    - Duration extraction
    - Format conversion (if needed)
    - Compression/optimization
- [ ] Setup video storage (AWS S3, DO Spaces, or local storage)
- [ ] Generate streaming-friendly URLs
- [ ] Implement video preview player
- [ ] Add video replacement feature
- [ ] Create video usage tracking

**Storage Configuration:**

```php
// config/filesystems.php
'videos' => [
    'driver' => 's3',
    'key' => env('AWS_ACCESS_KEY_ID'),
    'secret' => env('AWS_SECRET_ACCESS_KEY'),
    'region' => env('AWS_DEFAULT_REGION'),
    'bucket' => env('AWS_VIDEOS_BUCKET'),
    'visibility' => 'public',
],
```

**Queue Job:**

```php
// app/Jobs/ProcessVideoUpload.php
- Extract video metadata
- Generate 3 thumbnail options
- Create HLS/DASH streaming manifest
- Store in CDN
```

---

### 2.3 Campaign Payment & Billing Flow

**Files to Create:**

- `app/Services/CampaignBillingService.php`
- `app/Models/CampaignTransaction.php`
- `database/migrations/xxxx_create_campaign_transactions_table.php`

**Tasks:**

- [ ] Create campaign budget calculation:
    - $0.60 per view
    - Minimum budget: $6.00 (10 views)
    - Maximum budget: Configurable
- [ ] Implement Stripe payment intent creation
- [ ] Add payment method management:
    - Save credit cards securely
    - Support multiple payment methods
    - Set default payment method
- [ ] Create pre-payment authorization before campaign activation
- [ ] Implement real-time budget tracking
- [ ] Add auto-refill feature (optional)
- [ ] Create billing history page
- [ ] Generate PDF invoices for transactions
- [ ] Implement refund handling for cancelled campaigns
- [ ] Add low balance alerts

**Payment Flow:**

1. Politician creates campaign
2. System calculates total cost (views × $0.60)
3. Stripe payment intent created
4. Politician confirms payment
5. Payment captured
6. Campaign activated
7. Views start counting
8. Budget decreases with each view

**Database Schema:**

```sql
CREATE TABLE campaign_transactions (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID UNIQUE DEFAULT gen_random_uuid(),
    campaign_id BIGINT REFERENCES political_campaigns(id),
    politician_id BIGINT REFERENCES politicians(id),

    transaction_type VARCHAR(50), -- 'charge', 'refund', 'adjustment'
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',

    stripe_payment_intent_id VARCHAR(255),
    stripe_charge_id VARCHAR(255),
    stripe_refund_id VARCHAR(255),

    status VARCHAR(50), -- 'pending', 'succeeded', 'failed', 'refunded'
    description TEXT,
    metadata JSONB,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## Phase 3: Viewer Tracking & Analytics

### 3.1 Video View Session Tracking

**Files to Modify:**

- `app/Services/PoliticalViewService.php` (enhance existing)
- `app/Http/Controllers/Api/VoterController.php` (update for standalone)

**Tasks:**

- [ ] Implement view session creation when voter watches video
- [ ] Track viewing progress with heartbeat mechanism (every 5 seconds)
- [ ] Calculate completion percentage
- [ ] Track watch time vs video duration
- [ ] Record viewer device information
- [ ] Capture viewer browser and OS
- [ ] Implement view validation (minimum watch time: 100%)
- [ ] Create view session state machine:
    - Assigned → Started → In Progress → Completed → Paid

**Session Tracking Flow:**

```javascript
// Frontend tracking script
1. Video starts → POST /api/sessions/{session}/start
2. Every 5s → POST /api/sessions/{session}/heartbeat {seconds_watched: 35}
3. Video completes → POST /api/sessions/{session}/complete
4. Backend validates 100% watch requirement
5. If valid → Mark as completed, credit voter earnings
6. Deduct from campaign budget
```

---

### 3.2 Geolocation Tracking

**Files to Create:**

- `app/Services/GeolocationService.php`
- `database/migrations/xxxx_add_geolocation_to_view_sessions.php`

**Tasks:**

- [ ] Integrate IP geolocation service (MaxMind GeoIP2, IP2Location, or ipapi)
- [ ] Capture viewer location on session start:
    - Country
    - State/Province
    - City
    - Postal code
    - Latitude/Longitude
    - Timezone
- [ ] Store location data in `view_sessions` table
- [ ] Create location-based reporting
- [ ] Add geographic filtering for campaign targeting
- [ ] Implement location heatmap visualization
- [ ] Create location-based analytics dashboard

**Database Migration:**

```php
Schema::table('view_sessions', function (Blueprint $table) {
    $table->string('viewer_country', 2)->nullable();
    $table->string('viewer_state', 100)->nullable();
    $table->string('viewer_city', 100)->nullable();
    $table->string('viewer_postal_code', 20)->nullable();
    $table->decimal('viewer_latitude', 10, 8)->nullable();
    $table->decimal('viewer_longitude', 11, 8)->nullable();
    $table->string('viewer_timezone', 50)->nullable();
});
```

**Service Implementation:**

```php
// app/Services/GeolocationService.php
public function getLocationFromIP(string $ip): array
{
    // Use MaxMind GeoIP2 or similar service
    return [
        'country' => 'US',
        'state' => 'California',
        'city' => 'Los Angeles',
        'postal_code' => '90001',
        'latitude' => 34.0522,
        'longitude' => -118.2437,
        'timezone' => 'America/Los_Angeles',
    ];
}
```

---

### 3.3 Real-Time Statistics & Analytics Dashboard

**Files to Create:**

- `app/Http/Controllers/Standalone/AnalyticsController.php`
- `app/Services/CampaignAnalyticsService.php`
- `resources/views/standalone/analytics/index.blade.php`
- `resources/js/components/charts/` - Chart components

**Tasks:**

- [ ] Create campaign overview statistics:
    - Total views (completed vs in-progress)
    - Total cost spent
    - Remaining budget/credits
    - Average view duration
    - Completion rate percentage
    - Cost per completed view
- [ ] Build time-series charts:
    - Views over time (daily, weekly, monthly)
    - Spending over time
    - Geographic distribution of views
- [ ] Implement real-time updates using:
    - Laravel Echo + Pusher/Soketi for WebSocket
    - Or polling every 30 seconds
- [ ] Create demographic breakdown:
    - Age ranges
    - Gender distribution
    - Geographic distribution (map view)
- [ ] Add performance metrics:
    - Best performing time slots
    - Peak viewing hours
    - Device breakdown (mobile vs desktop)
- [ ] Create exportable reports (CSV, PDF)

**Chart Types to Include:**

- Line chart: Views over time
- Bar chart: Views by location (top 10 cities/states)
- Pie chart: Device type distribution
- Heatmap: Geographic view distribution
- Gauge: Budget remaining vs spent
- Table: Detailed view sessions list

**Analytics API Endpoints:**

```php
GET /api/campaigns/{campaign}/analytics/overview
GET /api/campaigns/{campaign}/analytics/views-over-time
GET /api/campaigns/{campaign}/analytics/geographic-distribution
GET /api/campaigns/{campaign}/analytics/demographic-breakdown
GET /api/campaigns/{campaign}/analytics/device-breakdown
GET /api/campaigns/{campaign}/analytics/export
```

---

### 3.4 Credits & Budget Management System

**Files to Create:**

- `app/Models/PoliticianCredit.php`
- `app/Services/CreditManagementService.php`
- `database/migrations/xxxx_create_politician_credits_table.php`

**Tasks:**

- [ ] Create credit ledger system for tracking:
    - Credit purchases
    - Credit usage per view
    - Credit refunds
    - Credit adjustments (admin)
- [ ] Implement pre-purchase credit bundles:
    - 100 views = $60 ($0.60 each)
    - 500 views = $275 ($0.55 each, 8% discount)
    - 1000 views = $500 ($0.50 each, 17% discount)
- [ ] Add auto-reload when credits fall below threshold
- [ ] Create credit expiration system (optional, 1 year)
- [ ] Build credit transfer between campaigns (same politician)
- [ ] Implement credit gifting/sharing (optional)
- [ ] Create credit transaction history page
- [ ] Add credit balance widget to dashboard

**Database Schema:**

```sql
CREATE TABLE politician_credits (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID UNIQUE DEFAULT gen_random_uuid(),
    politician_id BIGINT REFERENCES politicians(id),

    transaction_type VARCHAR(50), -- 'purchase', 'usage', 'refund', 'adjustment', 'transfer'
    amount DECIMAL(10,2) NOT NULL, -- Positive for additions, negative for deductions
    balance_after DECIMAL(10,2) NOT NULL,

    campaign_id BIGINT REFERENCES political_campaigns(id), -- For usage transactions
    related_transaction_id BIGINT REFERENCES politician_credits(id), -- For refunds/adjustments

    description TEXT,
    metadata JSONB,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_politician_credits_politician ON politician_credits(politician_id);
CREATE INDEX idx_politician_credits_created ON politician_credits(created_at);
```

**Credit Management Flow:**

1. Politician purchases credits via Stripe
2. Credits added to balance
3. Campaign created and activated
4. Each view deducts $0.60 from balance
5. Real-time balance updates
6. Low balance notification at 20% remaining
7. Auto-reload if enabled

---

## Phase 4: Billing & Invoice System

### 4.1 Invoice Generation

**Files to Create:**

- `app/Models/Invoice.php`
- `app/Services/InvoiceService.php`
- `database/migrations/xxxx_create_invoices_table.php`
- `resources/views/standalone/invoices/` - Invoice templates

**Tasks:**

- [ ] Create invoice generation for each transaction
- [ ] Design professional PDF invoice template
- [ ] Include invoice details:
    - Invoice number (auto-generated)
    - Issue date
    - Due date
    - Politician details (name, address, tax ID)
    - Itemized charges (campaign name, views, cost per view)
    - Subtotal, tax (if applicable), total
    - Payment method used
    - Transaction ID
- [ ] Implement invoice storage (S3 or local)
- [ ] Create invoice download endpoint
- [ ] Add invoice email delivery
- [ ] Build invoice history page
- [ ] Create invoice search and filtering

**Invoice Template (PDF):**

```
┌─────────────────────────────────────────────┐
│ U9ITUS POLITICAL LOYALTY ADS                │
│ Invoice #INV-2026-00123                     │
├─────────────────────────────────────────────┤
│ Bill To:                                    │
│ John Smith for Congress                     │
│ 123 Main Street                             │
│ Washington, DC 20001                        │
│ Tax ID: XX-XXXXXXX                         │
├─────────────────────────────────────────────┤
│ Description          Qty    Rate    Amount  │
│ Campaign: "Vote Yes" 100    $0.60   $60.00  │
│                                             │
│                             Subtotal $60.00 │
│                             Tax (0%) $0.00  │
│                             ───────────────  │
│                             TOTAL    $60.00 │
└─────────────────────────────────────────────┘
```

---

### 4.2 Billing History & Reports

**Files to Create:**

- `app/Http/Controllers/Standalone/BillingController.php`
- `resources/views/standalone/billing/index.blade.php`

**Tasks:**

- [ ] Create billing dashboard with statistics:
    - Total spent (all time)
    - Spending this month
    - Average cost per campaign
    - Most expensive campaign
- [ ] Build transaction history table:
    - Date, description, amount, status, invoice
    - Pagination and filtering
- [ ] Add date range filtering
- [ ] Create spending trends chart (monthly)
- [ ] Implement CSV export for accounting
- [ ] Add year-end tax report generation
- [ ] Create spending by campaign breakdown

---

## Phase 5: Frontend UI Components

### 5.1 Dashboard Overview Page

**Files to Create:**

- `resources/views/standalone/dashboard/index.blade.php`
- `resources/js/components/DashboardWidgets.vue`

**Tasks:**

- [ ] Create welcome banner with politician name
- [ ] Build statistics cards:
    - Active campaigns count
    - Total views (all time)
    - Total spent
    - Available credits
- [ ] Add recent campaigns list (last 5)
- [ ] Create quick actions section:
    - Create new campaign
    - Add credits
    - View analytics
- [ ] Display notifications/alerts
- [ ] Add activity feed (recent views, payments)
- [ ] Create mini-chart: Views last 7 days

---

### 5.2 Campaign Management UI

**Files to Create:**

- `resources/views/standalone/campaigns/index.blade.php`
- `resources/views/standalone/campaigns/create.blade.php`
- `resources/views/standalone/campaigns/edit.blade.php`
- `resources/views/standalone/campaigns/show.blade.php`

**Tasks:**

- [ ] Build campaign listing page with:
    - Grid or list view toggle
    - Status badges (active, paused, draft, completed)
    - Thumbnail preview
    - Key metrics (views, cost, completion rate)
    - Action buttons (edit, pause/resume, analytics, duplicate, delete)
- [ ] Create campaign creation wizard (multi-step form):
    - Step 1: Basic info (title, description, type)
    - Step 2: Video upload
    - Step 3: Targeting (location, demographics)
    - Step 4: Budget & scheduling
    - Step 5: Review & submit
- [ ] Build campaign detail page with:
    - Video player with view count
    - Real-time statistics
    - View sessions list
    - Geographic distribution map
    - Timeline of activity
- [ ] Add drag-and-drop file upload
- [ ] Implement form autosave (draft)

---

### 5.3 Analytics Visualizations

**Files to Create:**

- `resources/js/components/charts/ViewsChart.vue`
- `resources/js/components/charts/GeographicMap.vue`
- `resources/js/components/charts/DeviceBreakdown.vue`
- `resources/js/components/charts/PerformanceGauge.vue`

**Tasks:**

- [ ] Integrate Chart.js or ApexCharts library
- [ ] Create responsive chart components
- [ ] Implement real-time data updates
- [ ] Add chart export options (PNG, SVG, PDF)
- [ ] Build interactive tooltips
- [ ] Create date range selector
- [ ] Add chart comparison (campaign vs campaign)

**Chart Library Setup:**

```bash
npm install chart.js vue-chartjs
# or
npm install apexcharts vue3-apexcharts
```

---

### 5.4 Responsive Design & Mobile Optimization

**Tasks:**

- [ ] Ensure all pages are mobile-responsive
- [ ] Create mobile-specific navigation (hamburger menu)
- [ ] Optimize forms for touch input
- [ ] Add swipe gestures for navigation
- [ ] Implement progressive web app (PWA) features:
    - Service worker for offline support
    - Add to home screen prompt
    - Push notifications
- [ ] Test on multiple devices and browsers

---

## Phase 6: Admin Features & Approval Workflow

### 6.1 Campaign Approval System

**Files to Create:**

- `app/Http/Controllers/Standalone/AdminController.php`
- `resources/views/standalone/admin/campaigns/index.blade.php`
- `app/Notifications/CampaignApprovedNotification.php`
- `app/Notifications/CampaignRejectedNotification.php`

**Tasks:**

- [ ] Create admin dashboard for campaign review
- [ ] Build approval queue with pending campaigns
- [ ] Implement campaign review interface:
    - View campaign details
    - Watch video preview
    - Check for policy violations
    - Approve or reject with reason
- [ ] Add bulk approval feature
- [ ] Create approval history log
- [ ] Send email notifications on approval/rejection
- [ ] Add rejection reason templates
- [ ] Implement appeal process

**Approval Workflow:**

```
Draft → Submit for Approval → Pending Review → Admin Reviews
                                    ↓
                        ┌───────────┴───────────┐
                        ↓                       ↓
                   Approved                 Rejected
                        ↓                       ↓
                   Active              Back to Draft
                                      (with feedback)
```

---

### 6.2 Admin Analytics & Monitoring

**Files to Create:**

- `resources/views/standalone/admin/dashboard.blade.php`
- `app/Services/PlatformAnalyticsService.php`

**Tasks:**

- [ ] Create platform-wide statistics:
    - Total politicians registered
    - Total campaigns created
    - Total views delivered
    - Platform revenue
    - Active campaigns
- [ ] Build campaign performance leaderboard
- [ ] Create politician spending report
- [ ] Add system health monitoring
- [ ] Implement fraud detection dashboard
- [ ] Create financial reconciliation tools

---

## Phase 7: Notifications & Communication

### 7.1 Email Notification System

**Files to Create:**

- `app/Notifications/` - Various notification classes
- `resources/views/emails/` - Email templates

**Tasks:**

- [ ] Setup email service (Mailgun, SendGrid, Amazon SES)
- [ ] Create transactional email templates:
    - Welcome email
    - Campaign created confirmation
    - Payment successful
    - Low balance warning
    - Campaign approved/rejected
    - Campaign completed
    - Monthly summary report
- [ ] Implement email preferences
- [ ] Add unsubscribe functionality
- [ ] Create email delivery tracking
- [ ] Build email queue system

---

### 7.2 In-App Notifications

**Files to Create:**

- `app/Models/Notification.php` (use Laravel's built-in)
- `resources/js/components/NotificationBell.vue`

**Tasks:**

- [ ] Create notification center in dashboard
- [ ] Implement notification types:
    - Campaign status changes
    - Payment confirmations
    - Budget alerts
    - System announcements
    - Feature updates
- [ ] Add notification preferences
- [ ] Implement real-time notifications (WebSocket)
- [ ] Create mark as read functionality
- [ ] Add notification archiving

---

## Phase 8: Security & Fraud Prevention

### 8.1 Enhanced Security Features

**Files to Modify:**

- `app/Services/FraudPreventionService.php` (enhance existing)

**Tasks:**

- [ ] Implement rate limiting on all endpoints
- [ ] Add CSRF protection on all forms
- [ ] Create API token authentication for external integrations
- [ ] Implement IP whitelisting for admin access
- [ ] Add login attempt limiting (max 5 attempts)
- [ ] Create account lockout mechanism
- [ ] Implement security audit logging
- [ ] Add two-factor authentication (2FA)
- [ ] Create password strength requirements
- [ ] Implement session timeout (30 min inactivity)

---

### 8.2 Fraud Detection & Prevention

**Tasks:**

- [ ] Implement view session fraud detection:
    - Rapid-fire viewing detection
    - Bot detection (CAPTCHA on suspicious activity)
    - Duplicate device fingerprinting
    - Abnormal viewing patterns
    - IP reputation checking
- [ ] Create fraud scoring system
- [ ] Build automated fraud flagging
- [ ] Add manual review queue for flagged sessions
- [ ] Implement view refund mechanism for fraud
- [ ] Create fraud analytics dashboard
- [ ] Add fraud reporting tools

**Fraud Detection Rules:**

```php
// Example fraud detection rules
1. Same IP address viewing > 10 campaigns/hour
2. Same device fingerprint across multiple voters
3. View completion in < 80% of video duration
4. Geographic impossibility (2 views from different continents within minutes)
5. Browser automation detection
6. VPN/Proxy detection
7. Unusual viewing hours (1am-5am spikes)
```

---

## Phase 9: Testing & Quality Assurance

### 9.1 Unit & Feature Testing

**Files to Create:**

- `tests/Feature/Campaign/` - Campaign feature tests
- `tests/Feature/Payment/` - Payment feature tests
- `tests/Unit/Services/` - Service unit tests

**Tasks:**

- [ ] Write unit tests for all services:
    - `StripePaymentService`
    - `CampaignAnalyticsService`
    - `CreditManagementService`
    - `GeolocationService`
    - `VideoUploadService`
- [ ] Write feature tests for:
    - Campaign CRUD operations
    - Payment flow
    - View session tracking
    - Analytics endpoints
    - Admin approval workflow
- [ ] Create test database seeder
- [ ] Implement continuous integration (GitHub Actions)
- [ ] Add code coverage reporting (aim for >80%)

**Test Examples:**

```php
// tests/Feature/Campaign/CreateCampaignTest.php
public function test_politician_can_create_campaign()
{
    $politician = Politician::factory()->create();

    $response = $this->actingAs($politician->user)
        ->post('/campaigns', [
            'title' => 'Vote Yes on Proposition 1',
            'description' => 'Support local schools',
            'campaign_type' => 'video',
            'total_budget' => 60.00,
            'total_views_requested' => 100,
        ]);

    $response->assertStatus(201);
    $this->assertDatabaseHas('political_campaigns', [
        'title' => 'Vote Yes on Proposition 1',
    ]);
}
```

---

### 9.2 Integration Testing

**Tasks:**

- [ ] Test Stripe payment integration (use test mode)
- [ ] Test video upload and processing
- [ ] Test email delivery
- [ ] Test geolocation API integration
- [ ] Test WebSocket real-time updates
- [ ] Test PDF invoice generation
- [ ] Create end-to-end user journey tests

---

### 9.3 Performance Testing

**Tasks:**

- [ ] Load test API endpoints (Apache JMeter, k6)
- [ ] Test database query performance
- [ ] Optimize N+1 query issues
- [ ] Test concurrent view session handling
- [ ] Monitor memory usage
- [ ] Test file upload performance
- [ ] Optimize asset loading (CSS/JS bundling)
- [ ] Implement database indexing strategy
- [ ] Add query caching where appropriate

**Performance Targets:**

- API response time: < 200ms (p95)
- Dashboard load time: < 2 seconds
- Video upload: Support files up to 500MB
- Concurrent users: Support 1000+ simultaneous viewers
- Database queries: < 100ms average

---

## Phase 10: Deployment & DevOps

### 10.1 Production Environment Setup

**Tasks:**

- [ ] Setup production server (Railway, AWS, DigitalOcean)
- [ ] Configure production database (MySQL/PostgreSQL)
- [ ] Setup Redis for caching and queues
- [ ] Configure production environment variables
- [ ] Setup SSL certificates (Let's Encrypt)
- [ ] Configure CDN for video delivery (CloudFront, Cloudflare)
- [ ] Setup backup strategy:
    - Database backups (daily)
    - File storage backups (weekly)
    - Backup retention policy (30 days)
- [ ] Configure monitoring (Sentry, New Relic, DataDog)
- [ ] Setup log aggregation (Papertrail, Loggly)

---

### 10.2 CI/CD Pipeline

**Tasks:**

- [ ] Create GitHub Actions workflow for:
    - Run tests on every push
    - Code quality checks (PHPStan, Psalm)
    - Deploy to staging on merge to `develop`
    - Deploy to production on merge to `main`
- [ ] Setup deployment scripts
- [ ] Configure zero-downtime deployment
- [ ] Implement database migration strategy
- [ ] Create rollback procedure
- [ ] Setup staging environment for testing

**GitHub Actions Workflow:**

```yaml
name: CI/CD Pipeline

on:
    push:
        branches: [main, develop]
    pull_request:
        branches: [main, develop]

jobs:
    test:
        runs-on: ubuntu-latest
        steps:
            - uses: actions/checkout@v3
            - name: Setup PHP
              uses: shivammathur/setup-php@v2
              with:
                  php-version: "8.2"
            - name: Install dependencies
              run: composer install
            - name: Run tests
              run: php artisan test

    deploy:
        needs: test
        if: github.ref == 'refs/heads/main'
        runs-on: ubuntu-latest
        steps:
            - name: Deploy to production
              run: ./deploy.sh
```

---

### 10.3 Monitoring & Maintenance

**Tasks:**

- [ ] Setup uptime monitoring (Pingdom, UptimeRobot)
- [ ] Configure error tracking (Sentry)
- [ ] Implement performance monitoring (New Relic APM)
- [ ] Setup database query monitoring
- [ ] Create health check endpoints
- [ ] Implement automated alerts:
    - Server down
    - High error rate
    - Slow API responses
    - Database connection issues
    - Disk space warnings
- [ ] Create maintenance mode page
- [ ] Document incident response procedure

---

## Technical Specifications

### Technology Stack

**Backend:**

- Laravel 12
- PHP 8.2+
- MySQL 8.0 / PostgreSQL 15
- Redis 7.0 (caching, queues, sessions)

**Frontend:**

- Vue.js 3 / React 18 (for interactive components)
- Tailwind CSS 3
- Alpine.js (for simple interactivity)
- Chart.js / ApexCharts
- Axios for API calls

**Third-Party Services:**

- Stripe (payments)
- AWS S3 / DigitalOcean Spaces (video storage)
- MaxMind GeoIP2 (geolocation)
- Pusher / Soketi (WebSocket)
- Mailgun / SendGrid (email)
- Twilio (SMS, optional)

**DevOps:**

- Docker (containerization)
- GitHub Actions (CI/CD)
- Railway / AWS / DigitalOcean (hosting)
- Cloudflare (CDN, DDoS protection)

---

## Database Schema Summary

### New Tables to Create

1. **campaign_transactions** - Payment and credit transactions
2. **politician_credits** - Credit ledger for politicians
3. **invoices** - Generated invoices for billing
4. **fraud_logs** - Fraud detection events
5. **notification_preferences** - User notification settings

### Tables to Modify

1. **view_sessions** - Add geolocation fields
2. **political_campaigns** - Add credit tracking fields
3. **politicians** - Add billing preferences fields

---

## API Endpoints Summary

### Public Routes

```
POST   /api/auth/register
POST   /api/auth/login
POST   /api/auth/logout
POST   /api/auth/forgot-password
POST   /api/auth/reset-password
```

### Protected Routes (Requires Authentication)

```
# Campaigns
GET    /api/campaigns
POST   /api/campaigns
GET    /api/campaigns/{id}
PUT    /api/campaigns/{id}
DELETE /api/campaigns/{id}
POST   /api/campaigns/{id}/pause
POST   /api/campaigns/{id}/resume
POST   /api/campaigns/{id}/duplicate

# Analytics
GET    /api/campaigns/{id}/analytics/overview
GET    /api/campaigns/{id}/analytics/views-over-time
GET    /api/campaigns/{id}/analytics/geographic
GET    /api/campaigns/{id}/analytics/device-breakdown
GET    /api/campaigns/{id}/analytics/export

# Billing
GET    /api/billing/transactions
GET    /api/billing/invoices
GET    /api/billing/invoices/{id}/download
POST   /api/billing/payment-methods
GET    /api/billing/credits/balance
POST   /api/billing/credits/purchase

# Media
POST   /api/media/video/upload
POST   /api/media/video/chunk-upload
GET    /api/media/video/{id}
DELETE /api/media/video/{id}

# Profile
GET    /api/profile
PUT    /api/profile
PUT    /api/profile/password
PUT    /api/profile/notification-preferences
```

### Admin Routes

```
GET    /api/admin/campaigns/pending-approval
POST   /api/admin/campaigns/{id}/approve
POST   /api/admin/campaigns/{id}/reject
GET    /api/admin/platform/statistics
GET    /api/admin/fraud/flagged-sessions
```

---

## Implementation Timeline

### Week 1-2: Foundation

- Setup authentication system
- Configure Stripe integration
- Create dashboard layout
- Database migrations

### Week 3-4: Campaign Management

- Campaign CRUD operations
- Video upload system
- Payment flow integration
- Basic analytics

### Week 5-6: Analytics & Tracking

- Geolocation tracking
- Real-time statistics
- Chart visualizations
- Credits management

### Week 7-8: UI/UX Polish

- Complete frontend components
- Mobile responsiveness
- Email notifications
- Admin approval workflow

### Week 9-10: Testing & Security

- Comprehensive testing
- Fraud prevention
- Performance optimization
- Security hardening

### Week 11-12: Deployment

- Production setup
- CI/CD pipeline
- Monitoring setup
- Documentation
- Launch! 🚀

---

## Success Metrics

### Key Performance Indicators (KPIs)

**Business Metrics:**

- Number of active politicians
- Total campaigns created
- Total views delivered
- Platform revenue
- Customer satisfaction score

**Technical Metrics:**

- API uptime (target: 99.9%)
- Average API response time (target: < 200ms)
- Page load time (target: < 2s)
- Error rate (target: < 0.1%)
- Test coverage (target: > 80%)

**User Experience Metrics:**

- Time to create first campaign (target: < 5 minutes)
- Campaign approval time (target: < 24 hours)
- Video upload success rate (target: > 99%)
- Payment success rate (target: > 98%)

---

## Documentation & Training

### Documentation to Create

1. **User Guide** - Politician onboarding and feature walkthrough
2. **API Documentation** - Complete API reference with examples
3. **Admin Guide** - Campaign approval and platform management
4. **Developer Guide** - Setup, architecture, and contribution guidelines
5. **Security Guide** - Security best practices and compliance
6. **Troubleshooting Guide** - Common issues and solutions

### Training Materials

- Video tutorials for campaign creation
- Interactive product tour
- FAQ section
- Support ticket system
- Knowledge base

---

## Conclusion

This implementation plan provides a comprehensive roadmap for building a standalone Laravel-based campaign management system for politicians. The system will offer robust features for campaign creation, payment processing, real-time analytics, and viewer tracking - all as part of the standalone Laravel platform.

### Next Steps

1. Review and approve this implementation plan
2. Set up development environment
3. Begin Phase 1: Foundation & Authentication
4. Follow the checklist systematically
5. Regular progress reviews and adjustments

### Support & Questions

For questions or clarifications during implementation, refer to:

- Existing codebase documentation in `/doc` folder
- Laravel documentation: https://laravel.com/docs
- Stripe API documentation: https://stripe.com/docs/api

---

**Document Version:** 1.0.0  
**Last Updated:** February 17, 2026  
**Author:** Development Team  
**Status:** Ready for Implementation
