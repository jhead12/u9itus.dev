# U9itus Platform - Complete Modernization & Implementation Plan

## Loyalty Viewers Program - From Legacy to Modern Architecture

**Project:** U9itus 2.0  
**Version:** MVP → Full Platform  
**Date:** January 2026  
**Company:** Head Enterprises  
**License:** Licensed & Bonded in California

---

## 📋 Executive Summary

### Vision

Transform U9itus from a 2015 Laravel 4. 2 application into a modern, scalable platform connecting advertisers who want verified views with loyalty viewers who watch promotional content for compensation. Head Enterprises acts as the trusted intermediary ensuring authenticity, compliance, and fair payment processing.

### Core Innovation

**Admin-Controlled Assignment System**: Unlike typical ad platforms where users self-select content, U9itus uses a curated assignment model where Head Enterprises administrators manually or automatically assign ONE advertisement to ONE viewer at a time, ensuring quality control and fraud prevention.

### Platform Stakeholders

1. **Advertisers** - Businesses, politicians, organizations seeking verified human views
2. **Loyalty Viewers** - Individuals earning money by watching promotional content
3. **Head Enterprises** - Platform operator, verifier, and compliance authority

---

## 🎯 Business Model

### Revenue Streams

| **Source**                    | **Method**                           | **Rate**           |
| ----------------------------- | ------------------------------------ | ------------------ |
| **Service Fee**               | Percentage of campaign budget        | 15% per campaign   |
| **Premium Features**          | Live video calls, extended analytics | $50-200/month      |
| **Verification Certificates** | Blockchain-backed proof of views     | $5 per certificate |
| **Enterprise Plans**          | High-volume advertisers              | Custom pricing     |

### Unit Economics (Example Campaign)

```
Advertiser pays: $1,000 for 1,000 views
├── Viewer earnings: $850 (1,000 views × $0.85)
├── Head Enterprises fee: $150 (15%)
└── Platform costs: ~$50 (hosting, payment processing)
    Net profit per campaign: $100
```

### Scalability Targets

| **Metric**         | **Month 1** | **Month 6** | **Year 1** |
| ------------------ | ----------- | ----------- | ---------- |
| Active Advertisers | 10          | 100         | 500        |
| Active Viewers     | 100         | 1,000       | 10,000     |
| Daily Ad Views     | 500         | 5,000       | 50,000     |
| Monthly Revenue    | $2,500      | $25,000     | $250,000   |

---

## 📐 System Architecture

### MVP Architecture (Phase 1 - Weeks 1-3)

```
┌─────────────────────────────────────────────┐
│          CLIENTS (Web Browser)              │
│  Advertisers  │  Viewers  │  Admin          │
└───────────────┴───────────┴─────────────────┘
                     │
                     ▼
┌─────────────────────────────────────────────┐
│           Laravel 11 Application            │
│  ┌──────────────────────────────────────┐   │
│  │  Blade Templates (Server-Side UI)    │   │
│  └──────────────────────────────────────┘   │
│  ┌──────────────────────────────────────┐   │
│  │  Controllers & Business Logic        │   │
│  └──────────────────────────────────────┘   │
│  ┌──────────────────────────────────────┐   │
│  │  Eloquent ORM (Models)               │   │
│  └──────────────────────────────────────┘   │
└─────────────────────────────────────────────┘
                     │
        ┌────────────┴────────────┐
        │                         │
        ▼                         ▼
┌──────────────┐          ┌──────────────┐
│   SQLite     │          │  Local Files │
│   Database   │          │  (Videos)    │
└──────────────┘          └──────────────┘
        │
        ▼
┌──────────────────────────────────────┐
│      External Services (API)         │
│  ┌──────────┐  ┌──────────────────┐  │
│  │  Stripe  │  │  SendGrid Email  │  │
│  └──────────┘  └──────────────────┘  │
└──────────────────────────────────────┘
```

### Full Production Architecture (Phase 2+)

```
┌────────────────────────────────────────────────��───────────┐
│                    CLOUDFLARE CDN                          │
│  (DDoS Protection, SSL, Caching, Rate Limiting)            │
└───────────────────────┬────────────────────────────────────┘
                        │
┌───────────────────────▼────────────────────────────────────┐
│                  LOAD BALANCER                             │
│  (Route traffic to app servers)                            │
└───────────────────────┬────────────────────────────────────┘
                        │
        ┌───────────────┴───────────────┐
        │                               │
        ▼                               ▼
┌──────────────────┐          ┌──────────────────┐
│  Next.js         │          │  Laravel 11 API  │
│  Frontend App    │◄────────►│  Backend         │
│  (React/TS)      │   REST   │  (PHP 8.2)       │
└──────────────────┘          └────────┬─────────┘
                                       │
                              ┌────────┴─────────┐
                              │                  │
                              ▼                  ▼
                      ┌──────────────┐   ┌──────────────┐
                      │ PostgreSQL   │   │   Redis      │
                      │ (Primary DB) │   │ (Cache/Queue)│
                      └──────────────┘   └──────────────┘
                              │
                              ▼
                      ┌──────────────────────────────────┐
                      │    External Services              │
                      │  ┌──────────┐  ┌──────────────┐  │
                      │  │  Stripe  │  │  AWS S3/R2   │  │
                      │  │ Payments │  │ Video Store  │  │
                      │  └──────────┘  └──────────────┘  │
                      │  ┌──────────┐  ┌──────────────┐  │
                      │  │  Agora   │  │  Blockchain  │  │
                      │  │  WebRTC  │  │  Ethereum    │  │
                      │  └──────────┘  └──────────────┘  │
                      └──────────────────────────────────┘
```

---

## 💾 Complete Database Schema

### Core Tables

#### 1. Users (Extended Laravel Default)

```sql
CREATE TABLE users (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID UNIQUE DEFAULT gen_random_uuid(),

    -- Authentication
    email VARCHAR(255) UNIQUE NOT NULL,
    email_verified_at TIMESTAMP,
    phone VARCHAR(20),
    phone_verified_at TIMESTAMP,
    password VARCHAR(255) NOT NULL,
    remember_token VARCHAR(100),

    -- User Type & Role
    user_type VARCHAR(20) NOT NULL CHECK (user_type IN ('advertiser', 'viewer', 'admin')),

    -- Profile
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    date_of_birth DATE,
    gender VARCHAR(20),

    -- Location
    street_address VARCHAR(255),
    city VARCHAR(100),
    state VARCHAR(50),
    zip_code VARCHAR(20),
    country VARCHAR(2) DEFAULT 'US',
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),

    -- KYC/Verification
    kyc_status VARCHAR(20) DEFAULT 'pending' CHECK (kyc_status IN ('pending', 'approved', 'rejected')),
    kyc_documents JSONB,
    kyc_approved_at TIMESTAMP,
    kyc_rejected_reason TEXT,

    -- For Viewers:  Assignment Management
    current_assignment_id BIGINT,
    is_available_for_assignment BOOLEAN DEFAULT true,
    last_assignment_at TIMESTAMP,

    -- Security
    two_factor_secret VARCHAR(255),
    two_factor_confirmed_at TIMESTAMP,
    last_login_at TIMESTAMP,
    last_login_ip INET,
    device_fingerprint VARCHAR(255),

    -- Status
    is_active BOOLEAN DEFAULT true,
    is_verified BOOLEAN DEFAULT false,
    is_banned BOOLEAN DEFAULT false,
    ban_reason TEXT,
    banned_at TIMESTAMP,
    banned_by BIGINT REFERENCES users(id),

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP
);

CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_type ON users(user_type);
CREATE INDEX idx_users_kyc_status ON users(kyc_status);
CREATE INDEX idx_users_available ON users(is_available_for_assignment) WHERE user_type = 'viewer';
```

#### 2. Advertisers

```sql
CREATE TABLE advertisers (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT UNIQUE REFERENCES users(id) ON DELETE CASCADE,

    -- Business Information
    company_name VARCHAR(255) NOT NULL,
    business_type VARCHAR(100),
    industry VARCHAR(100),
    website_url VARCHAR(255),
    tax_id VARCHAR(50), -- EIN for US businesses

    -- Business Location
    business_street VARCHAR(255),
    business_city VARCHAR(100),
    business_state VARCHAR(50),
    business_zip VARCHAR(20),
    business_country VARCHAR(2) DEFAULT 'US',

    -- Contact Person
    contact_name VARCHAR(255),
    contact_title VARCHAR(100),
    contact_phone VARCHAR(20),
    contact_email VARCHAR(255),

    -- License & Bonding (California Compliance)
    business_license_number VARCHAR(100),
    license_state VARCHAR(2),
    license_expiry DATE,
    bond_certificate_url VARCHAR(500),
    bond_amount DECIMAL(15,2),
    bonding_company VARCHAR(255),

    -- Payment Methods
    stripe_customer_id VARCHAR(255) UNIQUE,
    stripe_payment_method_id VARCHAR(255),
    paypal_merchant_id VARCHAR(255),
    crypto_wallet_address VARCHAR(255),
    preferred_payment_method VARCHAR(50) DEFAULT 'stripe',

    -- Spending & Budget Controls
    total_spent DECIMAL(15,2) DEFAULT 0,
    monthly_budget DECIMAL(15,2),
    daily_budget DECIMAL(15,2),
    auto_refill BOOLEAN DEFAULT false,
    low_balance_threshold DECIMAL(10,2),

    -- Reputation
    rating DECIMAL(3,2) DEFAULT 5.0,
    total_reviews INT DEFAULT 0,
    total_campaigns INT DEFAULT 0,
    total_views_purchased INT DEFAULT 0,

    -- Status
    is_verified BOOLEAN DEFAULT false,
    verified_at TIMESTAMP,
    account_status VARCHAR(50) DEFAULT 'active' CHECK (account_status IN ('pending', 'active', 'suspended', 'closed')),

    -- Metadata
    referral_source VARCHAR(100),
    notes TEXT, -- Admin notes

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP
);

CREATE INDEX idx_advertisers_user_id ON advertisers(user_id);
CREATE INDEX idx_advertisers_company ON advertisers(company_name);
CREATE INDEX idx_advertisers_status ON advertisers(account_status);
CREATE INDEX idx_advertisers_stripe ON advertisers(stripe_customer_id);
```

#### 3. Loyalty Viewers

```sql
CREATE TABLE loyalty_viewers (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT UNIQUE REFERENCES users(id) ON DELETE CASCADE,

    -- Demographics
    age_range VARCHAR(20), -- '18-24', '25-34', etc.
    occupation VARCHAR(100),
    education_level VARCHAR(50),
    interests JSONB, -- ['technology', 'sports', 'cooking']

    -- Location Preferences for Ad Matching
    preferred_districts TEXT[],
    preferred_cities TEXT[],
    preferred_states TEXT[],
    preferred_countries TEXT[],
    willing_to_travel BOOLEAN DEFAULT false,

    -- Payment Information (Receive Money)
    paypal_email VARCHAR(255),
    cashapp_tag VARCHAR(100),
    venmo_username VARCHAR(100),
    crypto_wallet_address VARCHAR(255),
    bank_account_last4 VARCHAR(4),
    bank_routing_number VARCHAR(9),
    preferred_payout_method VARCHAR(50) DEFAULT 'paypal',

    -- Earnings Tracking
    total_earned DECIMAL(15,2) DEFAULT 0,
    pending_earnings DECIMAL(15,2) DEFAULT 0,
    paid_out DECIMAL(15,2) DEFAULT 0,
    lifetime_earnings DECIMAL(15,2) DEFAULT 0,
    current_balance DECIMAL(15,2) DEFAULT 0,

    -- Viewing Statistics
    total_views INT DEFAULT 0,
    total_watch_time INT DEFAULT 0, -- in seconds
    average_watch_time DECIMAL(10,2),
    average_completion_rate DECIMAL(5,2) DEFAULT 0,
    total_assignments INT DEFAULT 0,
    completed_assignments INT DEFAULT 0,
    expired_assignments INT DEFAULT 0,

    -- Trust & Quality Scores
    trust_score DECIMAL(5,2) DEFAULT 100.0, -- 0-100
    quality_score DECIMAL(5,2) DEFAULT 5.0, -- 0-5 stars
    response_time_avg INT, -- average seconds to start watching after assignment
    violations_count INT DEFAULT 0,
    warnings_count INT DEFAULT 0,

    -- Availability Settings
    available_days JSONB, -- ['monday', 'tuesday', ...]
    available_hours_start TIME,
    available_hours_end TIME,
    timezone VARCHAR(50),
    max_daily_views INT DEFAULT 10,

    -- Preferences
    ad_categories_blocked JSONB, -- ['alcohol', 'gambling']
    notification_preferences JSONB,

    -- Status
    is_active BOOLEAN DEFAULT true,
    is_verified BOOLEAN DEFAULT false,
    verification_date TIMESTAMP,
    verification_method VARCHAR(100), -- 'id_check', 'video_selfie', etc.
    account_status VARCHAR(50) DEFAULT 'active' CHECK (account_status IN ('pending', 'active', 'suspended', 'closed')),

    -- Referrals
    referred_by BIGINT REFERENCES loyalty_viewers(id),
    referral_code VARCHAR(20) UNIQUE,
    total_referrals INT DEFAULT 0,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP
);

CREATE INDEX idx_viewers_user_id ON loyalty_viewers(user_id);
CREATE INDEX idx_viewers_trust_score ON loyalty_viewers(trust_score);
CREATE INDEX idx_viewers_available ON loyalty_viewers(is_active, is_verified);
CREATE INDEX idx_viewers_location_prefs ON loyalty_viewers USING GIN (preferred_states);
CREATE INDEX idx_viewers_referral_code ON loyalty_viewers(referral_code);
```

#### 4. Campaigns

```sql
CREATE TABLE campaigns (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID UNIQUE DEFAULT gen_random_uuid(),
    advertiser_id BIGINT REFERENCES advertisers(id) ON DELETE CASCADE,

    -- Campaign Details
    title VARCHAR(255) NOT NULL,
    description TEXT,
    campaign_type VARCHAR(50) DEFAULT 'video' CHECK (campaign_type IN ('video', 'audio', 'text', 'live')),
    category VARCHAR(100), -- 'real_estate', 'retail', 'services', etc.

    -- Media Files
    media_file_url VARCHAR(500),
    media_file_path VARCHAR(500), -- local or S3 path
    media_duration INT, -- in seconds
    media_file_size BIGINT, -- in bytes
    media_format VARCHAR(20), -- 'mp4', 'mp3', etc.
    thumbnail_url VARCHAR(500),

    -- Budget & Pricing
    total_budget DECIMAL(15,2) NOT NULL,
    payment_per_view DECIMAL(10,2) NOT NULL,
    head_enterprises_fee_percent DECIMAL(5,2) DEFAULT 15.0,
    head_enterprises_fee_amount DECIMAL(10,2), -- calculated
    total_views_requested INT NOT NULL,
    views_completed INT DEFAULT 0,
    views_in_progress INT DEFAULT 0,
    views_assigned INT DEFAULT 0,

    -- Targeting Criteria
    target_districts TEXT[],
    target_cities TEXT[],
    target_counties TEXT[],
    target_states TEXT[],
    target_zip_codes TEXT[],
    target_countries TEXT[] DEFAULT ARRAY['US'],

    target_age_ranges TEXT[],
    target_genders TEXT[],
    target_interests TEXT[],
    target_income_levels TEXT[],

    -- Scheduling
    viewing_days TEXT[], -- ['monday', 'tuesday', ...]
    viewing_hours_start TIME,
    viewing_hours_end TIME,
    start_date DATE,
    end_date DATE,
    daily_view_limit INT,
    hourly_view_limit INT,

    -- Frequency Rules
    max_views_per_viewer INT DEFAULT 1,
    min_watch_time_percent INT DEFAULT 80, -- Must watch 80% to get paid
    allow_repeats BOOLEAN DEFAULT false,
    repeat_cooldown_days INT DEFAULT 30,

    -- Campaign Status
    status VARCHAR(50) DEFAULT 'draft' CHECK (status IN (
        'draft',
        'pending_approval',
        'active',
        'paused',
        'completed',
        'cancelled',
        'expired'
    )),

    approval_status VARCHAR(50) DEFAULT 'pending' CHECK (approval_status IN (
        'pending',
        'approved',
        'rejected',
        'requires_changes'
    )),

    approved_by BIGINT REFERENCES users(id),
    approved_at TIMESTAMP,
    rejection_reason TEXT,

    -- Payment Status
    payment_status VARCHAR(50) DEFAULT 'pending' CHECK (payment_status IN (
        'pending',
        'authorized',
        'captured',
        'partially_refunded',
        'refunded',
        'failed'
    )),

    stripe_payment_intent_id VARCHAR(255),
    stripe_charge_id VARCHAR(255),
    payment_captured_at TIMESTAMP,

    -- Analytics (calculated/cached)
    total_impressions INT DEFAULT 0,
    unique_viewers INT DEFAULT 0,
    average_watch_time DECIMAL(5,2),
    average_completion_rate DECIMAL(5,2),
    click_through_rate DECIMAL(5,2),
    engagement_score DECIMAL(5,2),

    -- Live Features (for live campaigns)
    is_live BOOLEAN DEFAULT false,
    live_session_url VARCHAR(500),
    live_start_time TIMESTAMP,
    live_end_time TIMESTAMP,

    -- Metadata
    external_campaign_id VARCHAR(100), -- For integrations
    notes TEXT, -- Admin notes
    tags TEXT[],

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP
);

CREATE INDEX idx_campaigns_advertiser ON campaigns(advertiser_id);
CREATE INDEX idx_campaigns_status ON campaigns(status);
CREATE INDEX idx_campaigns_approval ON campaigns(approval_status);
CREATE INDEX idx_campaigns_dates ON campaigns(start_date, end_date);
CREATE INDEX idx_campaigns_targeting_states ON campaigns USING GIN (target_states);
CREATE INDEX idx_campaigns_active ON campaigns(status, start_date, end_date) WHERE status = 'active';
```

#### 5. Ad Assignments (CORE TABLE - New System)

```sql
CREATE TABLE ad_assignments (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID UNIQUE DEFAULT gen_random_uuid(),

    -- Assignment Relationships
    campaign_id BIGINT REFERENCES campaigns(id) ON DELETE CASCADE,
    viewer_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
    assigned_by BIGINT REFERENCES users(id), -- Admin who made the assignment

    -- Status Tracking
    status VARCHAR(50) DEFAULT 'assigned' CHECK (status IN (
        'assigned',      -- Admin assigned, viewer hasn't started
        'in_progress',   -- Viewer is currently watching
        'completed',     -- Viewer finished (met requirements)
        'expired',       -- Deadline passed without completion
        'cancelled'      -- Admin manually cancelled
    )),

    -- Timestamps
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notified_at TIMESTAMP, -- When viewer was notified
    started_at TIMESTAMP, -- When viewer clicked "Watch"
    completed_at TIMESTAMP, -- When viewer finished
    expires_at TIMESTAMP DEFAULT (CURRENT_TIMESTAMP + INTERVAL '24 hours'),

    -- Watch Tracking
    watch_time INT DEFAULT 0, -- actual seconds watched
    required_watch_time INT, -- calculated from campaign
    completion_percentage DECIMAL(5,2) DEFAULT 0,
    watch_sessions INT DEFAULT 0, -- number of times viewer started/paused

    -- Location Verification
    viewer_ip INET,
    viewer_latitude DECIMAL(10,8),
    viewer_longitude DECIMAL(11,8),
    viewer_city VARCHAR(100),
    viewer_state VARCHAR(50),
    viewer_country VARCHAR(2),
    location_verified BOOLEAN DEFAULT false,

    -- Device Information (Fraud Detection)
    device_fingerprint VARCHAR(255),
    user_agent TEXT,
    browser VARCHAR(100),
    browser_version VARCHAR(50),
    os VARCHAR(100),
    os_version VARCHAR(50),
    device_type VARCHAR(50), -- 'desktop', 'mobile', 'tablet'
    screen_resolution VARCHAR(20),

    -- Engagement Tracking
    interacted BOOLEAN DEFAULT false, -- Clicked link, chatted, etc.
    interaction_type VARCHAR(100),
    interaction_data JSONB,
    mouse_movements INT DEFAULT 0,
    keyboard_events INT DEFAULT 0,
    tab_switches INT DEFAULT 0, -- How many times viewer switched tabs

    -- Verification & Fraud Detection
    is_verified BOOLEAN DEFAULT false,
    verification_method VARCHAR(100), -- 'auto', 'manual', 'blockchain'
    verification_hash VARCHAR(255), -- Blockchain transaction hash (if applicable)
    verification_timestamp TIMESTAMP,
    fraud_score DECIMAL(5,2) DEFAULT 0, -- 0-100, higher = more suspicious
    fraud_flags JSONB, -- ['vpn_detected', 'bot_behavior', etc.]
    requires_manual_review BOOLEAN DEFAULT false,
    reviewed_by BIGINT REFERENCES users(id),
    review_decision VARCHAR(50),
    review_notes TEXT,
    reviewed_at TIMESTAMP,

    -- Payment
    payment_amount DECIMAL(10,2),
    payment_status VARCHAR(50) DEFAULT 'pending' CHECK (payment_status IN (
        'pending',
        'approved',
        'paid',
        'rejected',
        'on_hold'
    )),
    payment_approved_at TIMESTAMP,
    paid_at TIMESTAMP,
    payment_method VARCHAR(50),
    payment_transaction_id VARCHAR(255),
    payment_rejection_reason TEXT,

    -- Feedback
    viewer_rating INT CHECK (viewer_rating BETWEEN 1 AND 5), -- Viewer rates the ad
    viewer_feedback TEXT,
    advertiser_rating INT CHECK (advertiser_rating BETWEEN 1 AND 5), -- Advertiser rates viewer
    advertiser_feedback TEXT,

    -- Metadata
    assignment_method VARCHAR(50), -- 'manual', 'auto'
    priority INT DEFAULT 0, -- Higher priority assignments shown first
    notes TEXT, -- Admin notes

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Unique constraint:  One viewer can only be assigned to same campaign once
    UNIQUE(campaign_id, viewer_id)
);

CREATE INDEX idx_assignments_campaign ON ad_assignments(campaign_id);
CREATE INDEX idx_assignments_viewer ON ad_assignments(viewer_id);
CREATE INDEX idx_assignments_status ON ad_assignments(status);
CREATE INDEX idx_assignments_payment_status ON ad_assignments(payment_status);
CREATE INDEX idx_assignments_dates ON ad_assignments(assigned_at, expires_at);
CREATE INDEX idx_assignments_verification ON ad_assignments(is_verified, requires_manual_review);
CREATE INDEX idx_assignments_active ON ad_assignments(viewer_id, status) WHERE status IN ('assigned', 'in_progress');
```

#### 6. Payments

```sql
CREATE TABLE payments (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID UNIQUE DEFAULT gen_random_uuid(),

    -- Transaction Parties
    payer_id BIGINT REFERENCES users(id), -- Who is paying
    payee_id BIGINT REFERENCES users(id), -- Who is receiving

    -- Payment Details
    amount DECIMAL(15,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    payment_type VARCHAR(50) NOT NULL CHECK (payment_type IN (
        'campaign_funding',    -- Advertiser funds campaign
        'viewer_payout',       -- Viewer receives earnings
        'refund',              -- Money returned to advertiser
        'fee',                 -- Head Enterprises commission
        'bonus',               -- Referral bonus, etc.
        'adjustment'           -- Manual adjustment by admin
    )),

    -- Payment Method
    payment_method VARCHAR(50) NOT NULL, -- 'stripe', 'paypal', 'crypto', 'bank_transfer'
    payment_provider VARCHAR(50), -- 'stripe', 'paypal', 'coinbase'

    -- External Payment References
    stripe_payment_intent_id VARCHAR(255),
    stripe_charge_id VARCHAR(255),
    stripe_refund_id VARCHAR(255),
    paypal_order_id VARCHAR(255),
    paypal_payout_batch_id VARCHAR(255),
    crypto_transaction_hash VARCHAR(255),
    crypto_wallet_from VARCHAR(255),
    crypto_wallet_to VARCHAR(255),
    crypto_network VARCHAR(50), -- 'ethereum', 'polygon', 'bitcoin'

    -- Payment Status
    status VARCHAR(50) DEFAULT 'pending' CHECK (status IN (
        'pending',
        'processing',
        'completed',
        'failed',
        'cancelled',
        'refunded',
        'partially_refunded'
    )),

    -- Related Entities
    campaign_id BIGINT REFERENCES campaigns(id),
    ad_assignment_id BIGINT REFERENCES ad_assignments(id),

    -- Fee Breakdown (for transparency)
    subtotal DECIMAL(15,2),
    platform_fee DECIMAL(15,2),
    processing_fee DECIMAL(15,2), -- Stripe/PayPal fees
    tax_amount DECIMAL(15,2),

    -- Failure Handling
    error_code VARCHAR(100),
    error_message TEXT,
    retry_count INT DEFAULT 0,
    next_retry_at TIMESTAMP,

    -- Audit Trail
    initiated_by BIGINT REFERENCES users(id),
    approved_by BIGINT REFERENCES users(id),

    -- Metadata
    description TEXT,
    metadata JSONB, -- Additional payment details
    receipt_url VARCHAR(500),
    invoice_url VARCHAR(500),

    -- Timestamps
    initiated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP,
    completed_at TIMESTAMP,
    failed_at TIMESTAMP,
    refunded_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_payments_payer ON payments(payer_id);
CREATE INDEX idx_payments_payee ON payments(payee_id);
CREATE INDEX idx_payments_status ON payments(status);
CREATE INDEX idx_payments_type ON payments(payment_type);
CREATE INDEX idx_payments_campaign ON payments(campaign_id);
CREATE INDEX idx_payments_assignment ON payments(ad_assignment_id);
CREATE INDEX idx_payments_stripe ON payments(stripe_payment_intent_id);
CREATE INDEX idx_payments_paypal ON payments(paypal_order_id);
CREATE INDEX idx_payments_dates ON payments(initiated_at, completed_at);
```

#### 7. Payout Batches (Weekly Payouts to Viewers)

```sql
CREATE TABLE payout_batches (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID UNIQUE DEFAULT gen_random_uuid(),

    -- Batch Details
    batch_name VARCHAR(255), -- 'Weekly Payout - Jan 20, 2026'
    payout_date DATE NOT NULL,
    total_amount DECIMAL(15,2) NOT NULL,
    total_recipients INT NOT NULL,

    -- Status
    status VARCHAR(50) DEFAULT 'pending' CHECK (status IN (
        'pending',
        'processing',
        'completed',
        'failed',
        'partially_completed'
    )),

    -- Payment Method
    payment_method VARCHAR(50), -- 'paypal_mass_pay', 'stripe_transfers', 'manual'
    external_batch_id VARCHAR(255), -- PayPal batch ID, etc.

    -- Results
    successful_payouts INT DEFAULT 0,
    failed_payouts INT DEFAULT 0,

    -- Processing
    initiated_by BIGINT REFERENCES users(id),
    processed_at TIMESTAMP,
    completed_at TIMESTAMP,

    -- Metadata
    notes TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE payout_batch_items (
    id BIGSERIAL PRIMARY KEY,
    payout_batch_id BIGINT REFERENCES payout_batches(id) ON DELETE CASCADE,
    viewer_id BIGINT REFERENCES users(id),

    -- Payment Details
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50),
    payment_email VARCHAR(255), -- PayPal email, etc.

    -- Status
    status VARCHAR(50) DEFAULT 'pending' CHECK (status IN (
        'pending',
        'sent',
        'completed',
        'failed',
        'returned'
    )),

    -- External Reference
    transaction_id VARCHAR(255),

    -- Failure Info
    error_code VARCHAR(100),
    error_message TEXT,

    processed_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_payout_batches_date ON payout_batches(payout_date);
CREATE INDEX idx_payout_batches_status ON payout_batches(status);
CREATE INDEX idx_payout_batch_items_batch ON payout_batch_items(payout_batch_id);
CREATE INDEX idx_payout_batch_items_viewer ON payout_batch_items(viewer_id);
```

#### 8. View Verifications (Fraud Detection)

```sql
CREATE TABLE view_verifications (
    id BIGSERIAL PRIMARY KEY,
    ad_assignment_id BIGINT UNIQUE REFERENCES ad_assignments(id) ON DELETE CASCADE,

    -- Verification Checks (Boolean Flags)
    device_fingerprint_check BOOLEAN DEFAULT false,
    ip_geolocation_check BOOLEAN DEFAULT false,
    watch_time_check BOOLEAN DEFAULT false,
    completion_rate_check BOOLEAN DEFAULT false,
    mouse_movement_check BOOLEAN DEFAULT false,
    keyboard_activity_check BOOLEAN DEFAULT false,
    tab_focus_check BOOLEAN DEFAULT false,
    screen_capture_check BOOLEAN DEFAULT false,

    -- AI/ML Fraud Detection
    bot_probability DECIMAL(5,2), -- 0-100%
    bot_detection_model VARCHAR(100),
    human_probability DECIMAL(5,2),
    behavior_patterns JSONB,

    -- Network Analysis
    vpn_detected BOOLEAN DEFAULT false,
    vpn_provider VARCHAR(100),
    proxy_detected BOOLEAN DEFAULT false,
    tor_detected BOOLEAN DEFAULT false,
    datacenter_ip BOOLEAN DEFAULT false,
    ip_reputation_score DECIMAL(5,2),

    -- Device Analysis
    device_reuse_count INT DEFAULT 0, -- How many other accounts use this device
    device_reputation_score DECIMAL(5,2),
    emulator_detected BOOLEAN DEFAULT false,
    headless_browser_detected BOOLEAN DEFAULT false,

    -- Behavioral Analysis
    suspicious_patterns JSONB, -- ['rapid_clicks', 'no_mouse_movement', etc.]
    watch_pattern_score DECIMAL(5,2), -- How natural the watching behavior was
    engagement_authenticity_score DECIMAL(5,2),

    -- Blockchain Verification (Optional)
    blockchain_hash VARCHAR(255),
    blockchain_network VARCHAR(50), -- 'ethereum', 'polygon'
    blockchain_timestamp TIMESTAMP,
    blockchain_verified BOOLEAN DEFAULT false,

    -- Manual Review
    requires_manual_review BOOLEAN DEFAULT false,
    manual_review_reason VARCHAR(255),
    reviewed_by BIGINT REFERENCES users(id),
    review_decision VARCHAR(50) CHECK (review_decision IN ('approve', 'reject', 'flag')),
    review_notes TEXT,
    reviewed_at TIMESTAMP,

    -- Final Verification Decision
    is_legitimate BOOLEAN,
    confidence_score DECIMAL(5,2), -- 0-100% confidence in legitimacy
    verification_method VARCHAR(100), -- 'auto', 'ai', 'manual', 'blockchain'

    -- Metadata
    verification_data JSONB, -- Raw verification details

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_verifications_assignment ON view_verifications(ad_assignment_id);
CREATE INDEX idx_verifications_legitimate ON view_verifications(is_legitimate);
CREATE INDEX idx_verifications_review ON view_verifications(requires_manual_review);
CREATE INDEX idx_verifications_confidence ON view_verifications(confidence_score);
```

---

## 🔐 Security & Compliance

### California Bonding Requirements

Head Enterprises must maintain:

- **Surety Bond:** $50,000 minimum
- **Business License:** Active California business license
- **Insurance:** General liability insurance ($1M minimum)

**Implementation:**

```php
// app/Services/ComplianceService.php
public function verifyBondingStatus(): bool
{
    $bond = HeadEnterprises::getCurrentBond();

    return $bond->amount >= 50000
        && $bond->expiry_date > now()
        && $bond->status === 'active';
}
```

### Data Protection (CCPA Compliance)

**Required Features:**

- Right to access personal data
- Right to delete personal data
- Right to opt-out of data selling
- Privacy policy with clear data usage

**Implementation:**

```php
// routes/web.php
Route::get('/privacy/export', [PrivacyController:: class, 'exportData'])
    ->middleware('auth');
Route::delete('/privacy/delete-account', [PrivacyController:: class, 'deleteAccount'])
    ->middleware('auth');
```

### Fraud Prevention

**Multi-Layer Verification:**

1. **Device Fingerprinting** - Track unique devices
2. **IP Geolocation** - Verify location claims
3. **Behavioral Analysis** - Detect bot-like behavior
4. **Manual Review** - Admin reviews suspicious activity
5. **Blockchain Logging** - Immutable audit trail

---

## 📱 User Interfaces

### Admin Dashboard Wireframe

```
┌──────────────────────────────���─────────────────────────────────┐
│ U9ITUS ADMIN DASHBOARD                        👤 Admin | Logout│
├────────────────────────────────────────────────────────────────┤
│                                                                │
│  📊 OVERVIEW                                                   │
│  ┌──────────────┬──────────────┬──────────────┬─────────────┐│
│  │ Active Camps │ Available    │ Today's      │ Total       ││
│  │ 24          │ Viewers      │ Assignments  │ Revenue     ││
│  │              │ 156          │ 89           │ $12,450     ││
│  └──────────────┴──────────────┴──────────────┴─────────────┘│
│                                                                │
│  🎯 AD ASSIGNMENT INTERFACE                                    │
│  ┌─────────────────────────────┬────────────────────────────┐│
│  │ AVAILABLE VIEWERS (156)     │ ACTIVE CAMPAIGNS (24)      ││
│  │                             │                            ││
│  │ ┌─────────────────────────┐ │ ┌────────────────────────┐││
│  │ │ 👤 John Smith           │ │ │ Joe's Pizza            │││
│  │ │ 📍 San Francisco, CA    │ │ │ Budget: $2,000         │││
│  │ │ ⭐ Trust:  98/100        │ │ │ Views: 1,247/2,000     │││
│  │ │ 💰 Earned: $234. 50      │ │ │ Pay: $1. 00/view        │││
│  │ │ [Assign Ad]             │ │ │ ▓▓▓▓▓▓▓▓░░░ 62%        │││
│  │ └─────────────────────────┘ │ └────────────────────────┘││
│  │                             │                            ││
│  │ ┌─────────────────────────┐ │ ┌────────────────────────┐││
│  │ │ 👤 Sarah Johnson        │ │ │ Smith Law Firm         │││
│  │ │ 📍 Los Angeles, CA      │ │ │ Budget: $5,000         │││
│  │ │ ⭐ Trust: 95/100        │ │ │ Views: 89/2,500        │││
│  │ │ 💰 Earned: $567.00      │ │ │ Pay: $2.00/view        │││
│  │ │ [Assign Ad]             │ │ │ ▓░░░░░░░░░░ 3%         │││
│  │ └─────────────────────────┘ │ └────────────────────────┘││
│  │                             │                            ││
│  │ [Load More...]              │ [Load More...]             ││
│  └─────────────────────────────┴────────────────────────────┘│
│                                                                │
│  [🤖 Auto-Assign 50 Ads]  [📊 View All Assignments]           │
│                                                                │
│  📋 RECENT ACTIVITY                                            │
│  ┌──────────────────────────────────────────────────────────┐│
│  │ ✅ Assignment completed: John Smith watched "Joe's Pizza"││
│  │ 🔔 New advertiser signed up: ABC Corporation            ││
│  │ ⚠️  Verification needed: Assignment #4523 flagged       ││
│  │ 💰 Payout processed: Sarah Johnson - $567.00            ││
│  └──────────────────────────────────────────────────────────┘│
└────────────────────────────────────────────────────────────────┘
```

### Viewer Dashboard Wireframe

```
┌────────────────────────────────────────────────────────────────┐
│ MY DASHBOARD                        👤 John Smith | Settings  │
├────────────────────────────────────────────────────────────────┤
│                                                                │
│  🎬 CURRENT ASSIGNMENT                                         │
│  ┌──────────────────────────────────────────────────────────┐│
│  │  📺 NEW AD ASSIGNED TO YOU!                               ││
│  │                                                          ││
│  │  Campaign: Joe's Pizza - Grand Opening Special          ││
│  │  Duration: 15 seconds                                    ││
│  │  You will earn: $1.50                                    ││
│  │  Deadline: 18 hours remaining                            ││
│  │                                                          ││
│  │  [▶️ WATCH AD NOW]                                       ││
│  └──────────────────────────────────────────────────────────┘│
│                                                                │
│  💰 YOUR EARNINGS                                              │
│  ┌──────────────┬──────────────┬──────────────┬────────────┐│
│  │ Today        │ This Week    │ Total Earned │ Available  ││
│  │ $12. 50       │ $87.50       │ $1,234.00    │ $47.50     ││
│  └──────────────┴──────────────┴──────────────┴────────────┘│
│                                                                │
│  [💸 Request Payout] (Minimum:  $25. 00)                        │
│  Next payout: Friday, Jan 20                                  │
│                                                                │
│  📊 YOUR STATS                                                 │
│  Ads Watched: 1,234 | Completion Rate: 96% | Trust Score: 98  │
│                                                                │
│  📋 RECENT ACTIVITY                                            │
│  ┌──────────────────────────────────────────────────────────┐│
│  │ Date       │ Campaign           │ Status    │ Earned    ││
│  ├────────────┼────────────────────┼───────────┼───────────┤│
│  │ Jan 17     │ Joe's Pizza        │ Completed │ $1. 50     ││
│  │ Jan 16     │ Smith Law Firm     │ Completed │ $2.00     ││
│  │ Jan 15     │ Tech Gadgets       │ Completed │ $1.25     ││
│  └──────────────────────────────────────────────────────────┘│
└────────────────────────────────────────────────────────────────┘
```

### Ad Watching Portal

```
┌────────────────────────────────────���───────────────────────────┐
│ WATCHING:  Joe's Pizza - Grand Opening Special          ⏱️ 12/15│
├────────────────────────────────────────────────────────────────┤
│                                                                │
│  ┌───────────────────────────────────┬──────────────────────┐│
│  │                                   │  💬 LIVE CHAT        ││
│  │         VIDEO PLAYER              │                      ││
│  │                                   │  [Advertiser]:        ││
│  │    🎬 Joe's Pizza Ad Playing      │  "Hi!  Any questions  ││
│  │                                   │   about our pizzas?" ││
│  │  ▓▓▓▓▓▓▓▓▓▓▓▓▓▓░░░░░░░░░░ 80%    │                      ││
│  │  [🔊] Mute  [⏸️] Pause            │  You:                 ││
│  │                                   │  [Type message...]   ││
│  │  ⏱️ Time: 12 / 15 seconds         │  [Send]              ││
│  │                                   │                      ││
│  │  ✅ Requirements:                  │  [📞 Request Video   ││
│  │  • Watch 80% (12 sec) ✓           │      Call]           ││
│  │  • Keep tab focused ✓             │                      ││
│  │  • No VPN ✓                       └──────────────────────┘│
│  │                                                          ││
│  │  💰 You will earn: $1.50                                 ││
│  │  ⏳ Must complete by: 6: 30 PM today                      ││
│  └──────────────────────────────────────────────────────────┘│
│                                                                │
│  ⚠️  Important: Do not switch tabs or pause for too long      │
│  🔒 Your view is being verified in real-time                   │
└────────────────────────────────────────────────────────────────┘
```

---

## 🚀 Implementation Timeline

### MVP (Weeks 1-3) - CURRENT FOCUS

**Week 1: Backend Foundation**

- ✅ Laravel 11 project setup
- ✅ Database migrations (all tables)
- ✅ User authentication with roles
- ✅ Basic models and relationships

**Week 2: Core Features**

- ✅ Campaign creation (advertiser)
- ✅ Video upload and validation
- ✅ Admin assignment interface
- ✅ Viewer dashboard

**Week 3: Payment & Testing**

- ✅ Stripe integration for advertisers
- ✅ Payout tracking for viewers
- ✅ Watch time validation
- ✅ End-to-end testing

### Phase 2 (Weeks 4-8) - Enhanced Features

**Week 4-5: Advanced Fraud Detection**

- Device fingerprinting integration
- AI-based bot detection
- IP reputation checking
- Suspicious pattern recognition

**Week 6: Analytics Dashboard**

- Advertiser campaign analytics
- Viewer performance metrics
- Admin platform statistics
- Export reports (PDF/CSV)

**Week 7: Notification System**

- Email notifications (SendGrid)
- SMS notifications (Twilio)
- In-app notifications
- Push notifications (PWA)

**Week 8: Location Targeting**

- GPS-based ad matching
- Geofencing capabilities
- Location verification
- Regional campaign support

### Phase 3 (Weeks 9-12) - Real-Time Features

**Week 9-10: Live Chat System**

- Node.js + Socket.io server
- Real-time messaging
- Presence indicators
- Chat history

**Week 11-12: Live Video Calls**

- WebRTC integration (Agora. io)
- 1-on-1 video calls
- Call recording (optional)
- Call duration tracking

### Phase 4 (Weeks 13-16) - Web3 Integration

**Week 13: Smart Contracts**

- Solidity contract development
- View verification on blockchain
- Deploy to Polygon testnet

**Week 14: Crypto Payments**

- Wallet connection (MetaMask)
- USDC/ETH payment acceptance
- Crypto payout support

**Week 15-16: Advanced Features**

- NFT-based viewer rewards
- Decentralized view verification
- Blockchain-backed certificates

---

## 💰 Cost Breakdown

### MVP Development Costs

| **Category**      | **Item**                              | **Cost** |
| ----------------- | ------------------------------------- | -------- |
| **Hosting**       | Heroku/Laravel Forge (month 1)        | $15      |
| **Domain**        | u9itus.com (annual)                   | $12      |
| **Email**         | SendGrid (free tier)                  | $0       |
| **Payments**      | Stripe (2.9% + $0.30 per transaction) | Variable |
| **SSL**           | Let's Encrypt                         | $0       |
| **Total Month 1** |                                       | **~$30** |

### Production Costs (Monthly)

| **Category**        | **Service**              | **Est. Cost**   |
| ------------------- | ------------------------ | --------------- |
| **Backend Hosting** | AWS EC2 or DigitalOcean  | $50-100         |
| **Database**        | AWS RDS PostgreSQL       | $30-80          |
| **Storage**         | AWS S3 / Cloudflare R2   | $20-50          |
| **CDN**             | Cloudflare Pro           | $20             |
| **Email**           | SendGrid (10k emails/mo) | $20             |
| **SMS**             | Twilio                   | $10-30          |
| **Video**           | Agora.io (1000 min/mo)   | $50             |
| **Monitoring**      | Sentry                   | $26             |
| **Total**           |                          | **$226-376/mo** |

### Revenue Projections

**Scenario: 100 active viewers, 10 advertisers**

```
Monthly Ad Views: 3,000 (30 views/viewer/month)
Average Payment Per View: $1.00
Total Revenue: $3,000

Viewer Payouts: $2,550 (85%)
Platform Revenue: $450 (15%)
Operating Costs: $300
Net Profit: $150/month
```

**Scenario: 1,000 active viewers, 50 advertisers**

```
Monthly Ad Views: 30,000
Average Payment Per View: $1.00
Total Revenue: $30,000

Viewer Payouts: $25,500 (85%)
Platform Revenue: $4,500 (15%)
Operating Costs: $500
Net Profit: $4,000/month ($48k/year)
```

---

## 📚 Next Steps After MVP

Once the MVP is live and validated:

1. **Gather User Feedback**
    - Survey advertisers: What features do they need?
    - Survey viewers: What would make them watch more ads?
    - Track metrics: Completion rates, fraud rates, satisfaction

2. **Optimize Core Flow**
    - Reduce assignment friction
    - Improve watch experience
    - Speed up verification

3. **Scale Infrastructure**
    - Move to PostgreSQL
    - Implement caching (Redis)
    - Set up CDN for videos

4. **Add Revenue Features**
    - Premium advertiser tiers
    - Viewer referral bonuses
    - API access for enterprises

5. **Expand Geographically**
    - Launch in additional states
    - International expansion (Canada, UK)
    - Multi-language support

---

## 📞 Support & Contact

### For Technical Issues

- **Email:** support@u9itus.com
- **Phone:** (555) 123-4567
- **Support Hours:** Mon-Fri 9am-6pm PST

### For Compliance Questions

- **Email:** compliance@u9itus.com
- **California License:** #XXXXXX
- **Bond Information:** Available upon request

### For Business Inquiries

- **Email:** business@u9itus.com
- **Partnership Opportunities:** partners@u9itus.com

---

## ✅ Success Criteria

### MVP Launch Metrics

- [ ] 10 verified advertisers onboarded
- [ ] 100 verified viewers onboarded
- [ ] 500+ ad views completed successfully
- [ ] <5% fraud/invalid views
- [ ] 90%+ viewer satisfaction
- [ ] 85%+ advertiser satisfaction
- [ ] Payment processing works 100%
- [ ] All assignments tracked accurately

### 6-Month Goals

- [ ] 100+ active advertisers
- [ ] 1,000+ active viewers
- [ ] $25,000+ monthly revenue
- [ ] <2% fraud rate
- [ ] 95%+ uptime
- [ ] Positive unit economics

---

## 🎓 Lessons Learned & Best Practices

### Key Insights

1. **One Ad at a Time Works** - Viewers focus better, fraud decreases
2. **Admin Control Essential** - Quality control prevents gaming the system
3. **80% Watch Time Rule** - Sweet spot between advertiser value and viewer effort
4. **24-Hour Assignment Window** - Creates urgency without pressure
5. **Weekly Payouts** - Reduces transaction costs, still feels frequent

### Common Pitfalls to Avoid

- ❌ Allowing viewers to self-select ads (leads to cherry-picking)
- ❌ No watch time validation (advertiser gets poor quality views)
- ❌ Instant payouts (increases fraud risk)
- ❌ No location verification (defeats targeting purpose)
- ❌ Skipping admin approval (low-quality ads get through)

---

**This document serves as the complete blueprint for building, launching, and scaling the U9itus platform. ** 🚀

---

_Last Updated: January 17, 2026_  
_Document Version: 2.0_  
_Prepared by: Development Team_
