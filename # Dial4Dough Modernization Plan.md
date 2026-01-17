# Dial4Dough Modernization Plan

## Loyalty Viewers Platform - Technical Architecture & Implementation Strategy

**Project:** Dial4Dough Platform Modernization  
**Version:** 2.0  
**Date:** January 2026  
**Prepared By:** Development Team  
**Company:** Head Enterprises

---

## 📋 Table of Contents

1. [Executive Summary](#executive-summary)
2. [Business Requirements](#business-requirements)
3. [Current System Analysis](#current-system-analysis)
4. [Proposed Architecture](#proposed-architecture)
5. [Technology Stack](#technology-stack)
6. [Database Schema Design](#database-schema-design)
7. [User Flows & Features](#user-flows--features)
8. [Implementation Roadmap](#implementation-roadmap)
9. [Security & Compliance](#security--compliance)
10. [Cost Estimates](#cost-estimates)
11. [Appendix](#appendix)

---

## 1. Executive Summary

### Project Overview

Dial4Dough is a revolutionary advertising platform connecting **Advertisers** who want their content viewed with **Loyalty Viewers** who watch promotional content for compensation. **Head Enterprises** acts as the trusted intermediary, ensuring authenticity, payment processing, and quality control.

### Core Value Proposition

- **For Advertisers:** Guaranteed, verified views from real people in targeted locations
- **For Loyalty Viewers:** Legitimate income opportunity by watching short advertisements
- **For Head Enterprises:** Scalable platform with revenue from service fees and verification

### Modernization Goals

1. Upgrade from Laravel 4.2.9 (2015) to Laravel 11 (2026)
2. Add real-time video/audio ad delivery with live chat
3. Implement multi-payment system (Stripe, PayPal, Crypto/Web3)
4. Build robust fraud detection and view verification
5. Create modern, responsive user interfaces
6. Ensure California state compliance and bonding requirements

---

## 2. Business Requirements

### 2.1 Advertiser Requirements

Advertisers represent any person, company, politician, or organization seeking verified views. They must provide:

| **Requirement**          | **Description**                             | **Technical Implementation**                        |
| ------------------------ | ------------------------------------------- | --------------------------------------------------- |
| **Budget Allocation**    | Total dollar amount for viewer compensation | Payment escrow system, budget tracking dashboard    |
| **Promotional Media**    | Video file (MP3/MP4), 10-20 seconds         | Media upload service, format validation, S3 storage |
| **Payment Methods**      | Stripe, PayPal, Crypto wallet               | Multi-payment gateway integration                   |
| **Business Information** | Name, address, email, phone, website        | KYC verification, business profile management       |
| **Viewing Schedule**     | Days of week (Monday-Sunday)                | Campaign scheduler with timezone support            |
| **Viewing Frequency**    | Max views per viewer                        | Rate limiting, viewer eligibility rules             |
| **Location Targeting**   | District, city, county, state, zip, country | Geolocation filtering, IP verification              |
| **Requested Location**   | Specific geographic targeting               | GPS validation, location-based ad serving           |

### 2.2 Loyalty Viewer Requirements

Loyalty Viewers watch advertisements for compensation. They must provide:

| **Requirement**          | **Description**                      | **Technical Implementation**                    |
| ------------------------ | ------------------------------------ | ----------------------------------------------- |
| **Personal Information** | Legal name, address, email, phone    | Identity verification (KYC), profile management |
| **Preferred Locations**  | Geographic preferences for ads       | Location preferences, GPS matching              |
| **Payment Resource**     | PayPal, Cash App, Crypto wallet      | Payout integration, payment method management   |
| **Viewing Behavior**     | Watch time, completion rate, ratings | Analytics tracking, engagement metrics          |
| **Device Information**   | Browser, OS, IP address              | Fraud detection, device fingerprinting          |

### 2.3 Head Enterprises Requirements

Head Enterprises manages the platform and ensures legitimacy:

| **Capability**          | **Description**                                           | **Technical Implementation**                            |
| ----------------------- | --------------------------------------------------------- | ------------------------------------------------------- |
| **Viewer Verification** | Confirm authentic, legal viewers                          | AI-powered fraud detection, biometric verification      |
| **Payment Processing**  | Handle advertiser payments, viewer payouts, fee deduction | Payment gateway integration, escrow system              |
| **View Certification**  | Prove views are genuine                                   | Blockchain-based view logging, cryptographic signatures |
| **License & Bonding**   | California state compliance                               | Legal document management, compliance reporting         |
| **Analytics Dashboard** | Real-time campaign metrics                                | Admin analytics portal, reporting tools                 |
| **Dispute Resolution**  | Handle advertiser/viewer disputes                         | Support ticket system, refund management                |

---

## 3. Current System Analysis

### 3.1 Legacy Technology Stack (2015)

```
Backend:
├── Laravel 4.2.9 (PHP 5.4+)
├── MySQL (Primary database)
├── MongoDB (Ad content storage)
├── Twilio SDK (Phone system)
├── Laravel Cashier 2.0 (Stripe payments)
├── Confide (Authentication)
└── Entrust (Role-based permissions)

Frontend:
├── AngularJS 1.3. 12
├── Bootstrap 3.3.1
├── jQuery 2.1.1
└── Gulp build system

Infrastructure:
├── Vagrant (Development environment)
└── ngrok (Tunneling)
```

### 3.2 Critical Issues

| **Issue**                   | **Impact**                               | **Risk Level** |
| --------------------------- | ---------------------------------------- | -------------- |
| **Unsupported PHP/Laravel** | Security vulnerabilities, no updates     | 🔴 Critical    |
| **No real-time features**   | Can't support live video/chat            | 🔴 Critical    |
| **Outdated frontend**       | Poor mobile experience, slow performance | 🟡 High        |
| **Limited payment options** | No crypto support                        | 🟡 High        |
| **No fraud detection**      | View authenticity concerns               | 🔴 Critical    |
| **No video delivery**       | Core feature missing                     | 🔴 Critical    |

### 3.3 Existing Database Models (To Migrate)

- **User** - Authentication, roles, profile
- **Marketer** - MongoDB collection for advertisers
- **Addial** - MongoDB collection for ad campaigns
- **Role/Permission** - RBAC system
- **Post/Comment** - Blog/content system

---

## 4. Proposed Architecture

### 4.1 System Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                    CLIENT LAYER (Users)                         │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐          │
│  │  Advertisers │  │    Viewers   │  │   Admin      │          │
│  │   Dashboard  │  │  Ad Portal   │  │   Panel      │          │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘          │
│         │                  │                  │                  │
└─────────┼──────────────────┼──────────────────┼──────────────────┘
          │                  │                  │
          └──────────────────┴──────────────────┘
                             │
          ┌──────────────────▼──────────────────┐
          │       LOAD BALANCER (Cloudflare)     │
          └──────────────────┬──────────────────┘
                             │
          ┌──────────────────▼──────────────────────────────────┐
          │          APPLICATION LAYER                          │
          │  ┌────────────────────┐  ┌────────────────────┐    │
          │  │  Next.js Frontend  │  │  Laravel 11 API    │    │
          │  │  - React UI        │  │  - RESTful API     │    │
          │  │  - TypeScript      │  │  - Authentication  │    │
          │  │  - Tailwind CSS    │  │  - Business Logic  │    │
          │  └─────────┬──────────┘  └─────────┬──────────┘    │
          └────────────┼─────────────────────────┼───────────────┘
                       │                         │
          ┌────────────▼─────────────────────────▼───────────────┐
          │         REAL-TIME LAYER (Node.js)                    │
          │  ┌──────────────┐  ┌──────────────┐  ┌────────────┐ │
          │  │  Socket.io   │  │  WebRTC      │  │  Video     │ │
          │  │  (Chat)      │  │  (Live call) │  │  Streaming │ │
          │  └──────────────┘  └──────────────┘  └────────────┘ │
          └──────────────────────────┬───────────────────────────┘
                                     │
          ┌──────────────────────────▼───────────────────────────┐
          │              DATA LAYER                              │
          │  ┌──────────────┐  ┌──────────────┐  ┌────────────┐ │
          │  │ PostgreSQL   │  │   MongoDB    │  │   Redis    │ │
          │  │ (Users, Ads) │  │ (Media meta) │  │  (Cache)   │ │
          │  └──────────────┘  └──────────────┘  └────────────┘ │
          └──────────────────────────────────────────────────────┘
                                     │
          ┌──────────────────────────▼───────────────────────────┐
          │           EXTERNAL SERVICES                          │
          │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌─────────┐ │
          │  │  Stripe  │ │  PayPal  │ │  Web3    │ │   AWS   │ │
          │  │ Payment  │ │ Payment  │ │  Crypto  │ │   S3    │ │
          │  └──────────┘ └──────────┘ └──────────┘ └─────────┘ │
          │  ┌──────────┐ ┌──────────┐ ┌──────────┐             │
          │  │  Twilio  │ │  Agora   │ │Blockchain│             │
          │  │   SMS    │ │  Video   │ │  Verify  │             │
          │  └──────────┘ └──────────┘ └──────────┘             │
          └──────────────────────────────────────────────────────┘
```

### 4.2 Technology Stack Evolution

#### Backend API (Laravel 11)

```php
// Modern Laravel 11 Structure
app/
├── Http/
│   ├── Controllers/
│   │   ├── API/
│   │   │   ├── AdvertiserController.php      // Advertiser management
│   │   │   ├── ViewerController.php          // Loyalty viewer management
│   │   │   ├── CampaignController.php        // Ad campaign CRUD
│   │   │   ├── PaymentController.php         // Payment processing
│   │   │   └── VerificationController.php    // View verification
│   │   └── Admin/
│   │       └── DashboardController.php       // Admin analytics
│   ├── Middleware/
│   │   ├── VerifyAdvertiser.php
│   │   ├── VerifyViewer.php
│   │   └── TrackViewTime.php
│   └── Requests/
│       ├── CreateCampaignRequest.php
│       └── ViewerRegistrationRequest.php
├── Models/
│   ├── User.php
│   ├── Advertiser.php
│   ├── Viewer.php
│   ├── Campaign.php
│   ├── AdView.php                            // View tracking
│   ├── Payment. php
│   └── ViewVerification.php                  // Fraud detection
├── Services/
│   ├── PaymentService.php                    // Multi-gateway payments
│   ├── ViewVerificationService.php           // Authenticity checks
│   ├── GeolocationService.php                // Location matching
│   ├── MediaService.php                      // Video processing
│   └── BlockchainService.php                 // Web3 integration
└── Jobs/
    ├── ProcessViewPayment.php                // Async payout
    ├── VerifyViewAuthenticity.php            // Fraud detection
    └── GenerateAnalyticsReport.php
```

#### Frontend (Next.js 14)

```typescript
// Modern React/TypeScript Structure
src/
├── app/                                       // App Router (Next.js 14)
│   ├── (advertiser)/
│   │   ├── dashboard/
│   │   ├── campaigns/
│   │   │   ├── create/
│   │   │   └── [id]/
│   │   └── analytics/
│   ├── (viewer)/
│   │   ├── dashboard/
│   │   ├── watch/                            // Ad viewing portal
│   │   └── earnings/
│   └── (admin)/
│       └── dashboard/
├── components/
│   ├── ui/                                    // Shadcn components
│   ├── advertiser/
│   │   ├── CampaignForm.tsx
│   │   ├── BudgetTracker.tsx
│   │   └── ViewAnalytics.tsx
│   ├── viewer/
│   │   ├── AdPlayer.tsx                      // Video player
│   │   ├── LiveChat.tsx                      // Real-time chat
│   │   └── EarningsWidget.tsx
│   └── shared/
│       ├── PaymentMethodSelector.tsx
│       └── LocationPicker.tsx
├── hooks/
│   ├── useWebSocket.ts                       // Socket.io integration
│   ├── useWebRTC.ts                          // Video call hooks
│   └── usePayment.ts                         // Payment processing
└── services/
    ├── api. ts                                // Laravel API client
    └── web3.ts                               // Crypto wallet integration
```

#### Real-time Server (Node.js)

```javascript
// Node.js WebSocket & WebRTC Server
server/
├── index.js                                   // Express + Socket.io
├── services/
│   ├── socketManager.js                      // Chat & presence
│   ├── webrtcServer.js                       // Video call signaling
│   ├── viewTracker.js                        // Real-time view tracking
│   └── fraudDetector.js                      // AI-based fraud detection
└── config/
    └── mediasoup.config.js                   // WebRTC configuration
```

---

## 5. Technology Stack

### 5.1 Recommended Technologies

| **Category**           | **Technology**              | **Version** | **Purpose**                         |
| ---------------------- | --------------------------- | ----------- | ----------------------------------- |
| **Backend Framework**  | Laravel                     | 11.x        | API, business logic, authentication |
| **Database (Primary)** | PostgreSQL                  | 16+         | User data, campaigns, transactions  |
| **Database (NoSQL)**   | MongoDB                     | 7+          | Ad metadata, analytics logs         |
| **Cache/Queue**        | Redis                       | 7+          | Session storage, job queues         |
| **Frontend Framework** | Next.js                     | 14+         | React-based UI, SSR, SEO            |
| **UI Library**         | Shadcn UI + Tailwind        | Latest      | Component library, styling          |
| **Real-time (Chat)**   | Socket.io                   | 4+          | WebSocket connections               |
| **Real-time (Video)**  | Agora. io or Daily. co      | Latest      | Managed WebRTC service              |
| **Payment (Fiat)**     | Stripe + PayPal             | Latest      | Credit cards, PayPal, ACH           |
| **Payment (Crypto)**   | Thirdweb / Moralis          | Latest      | Web3 wallet integration             |
| **Storage**            | AWS S3 / R2                 | Latest      | Video/audio file storage            |
| **CDN**                | Cloudflare                  | Latest      | Content delivery, DDoS protection   |
| **SMS/Phone**          | Twilio                      | Latest      | 2FA, notifications                  |
| **Email**              | SendGrid                    | Latest      | Transactional emails                |
| **Monitoring**         | Sentry + Laravel Pulse      | Latest      | Error tracking, performance         |
| **Analytics**          | Mixpanel / PostHog          | Latest      | User behavior tracking              |
| **Fraud Detection**    | Custom ML + Fingerprint. js | Latest      | View authenticity                   |
| **Blockchain**         | Ethereum (or Polygon)       | Latest      | View verification, payments         |

### 5.2 Development Tools

- **PHP:** 8.2+
- **Node.js:** 20 LTS
- **Package Manager:** Composer (PHP), pnpm (Node)
- **Version Control:** Git + GitHub
- **CI/CD:** GitHub Actions
- **Containerization:** Docker + Docker Compose
- **API Testing:** Postman, Pest (Laravel)
- **E2E Testing:** Playwright
- **Code Quality:** Laravel Pint, ESLint, Prettier

---

## 6. Database Schema Design

### 6.1 PostgreSQL Schema (Primary Database)

#### Users Table (Extended)

```sql
CREATE TABLE users (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID UNIQUE DEFAULT gen_random_uuid(),
    email VARCHAR(255) UNIQUE NOT NULL,
    email_verified_at TIMESTAMP,
    phone VARCHAR(20),
    phone_verified_at TIMESTAMP,
    password VARCHAR(255) NOT NULL,
    user_type VARCHAR(20) NOT NULL CHECK (user_type IN ('advertiser', 'viewer', 'admin')),

    -- KYC/Verification
    kyc_status VARCHAR(20) DEFAULT 'pending' CHECK (kyc_status IN ('pending', 'approved', 'rejected')),
    kyc_documents JSONB,

    -- Profile
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    date_of_birth DATE,

    -- Location
    street_address VARCHAR(255),
    city VARCHAR(100),
    state VARCHAR(50),
    zip_code VARCHAR(20),
    country VARCHAR(2),
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),

    -- Security
    two_factor_secret VARCHAR(255),
    two_factor_confirmed_at TIMESTAMP,
    remember_token VARCHAR(100),

    -- Metadata
    last_login_at TIMESTAMP,
    last_login_ip INET,
    device_fingerprint VARCHAR(255),

    -- Status
    is_active BOOLEAN DEFAULT true,
    is_banned BOOLEAN DEFAULT false,
    ban_reason TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP
);

CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_type ON users(user_type);
CREATE INDEX idx_users_location ON users(latitude, longitude);
```

#### Advertisers Table

```sql
CREATE TABLE advertisers (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES users(id) ON DELETE CASCADE,

    -- Business Information
    company_name VARCHAR(255) NOT NULL,
    business_type VARCHAR(100),
    website_url VARCHAR(255),
    tax_id VARCHAR(50),

    -- Business Location
    business_street VARCHAR(255),
    business_city VARCHAR(100),
    business_state VARCHAR(50),
    business_zip VARCHAR(20),
    business_country VARCHAR(2),

    -- Contact
    contact_name VARCHAR(255),
    contact_phone VARCHAR(20),
    contact_email VARCHAR(255),

    -- License & Bonding
    business_license_number VARCHAR(100),
    license_expiry DATE,
    bond_certificate JSONB,

    -- Payment
    stripe_customer_id VARCHAR(255),
    paypal_merchant_id VARCHAR(255),
    crypto_wallet_address VARCHAR(255),

    -- Spending & Limits
    total_spent DECIMAL(15, 2) DEFAULT 0,
    monthly_budget DECIMAL(15, 2),
    daily_budget DECIMAL(15, 2),

    -- Rating
    rating DECIMAL(3, 2) DEFAULT 5.0,
    total_reviews INT DEFAULT 0,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP
);

CREATE INDEX idx_advertisers_user_id ON advertisers(user_id);
CREATE INDEX idx_advertisers_company ON advertisers(company_name);
```

#### Loyalty Viewers Table

```sql
CREATE TABLE loyalty_viewers (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES users(id) ON DELETE CASCADE,

    -- Viewer Profile
    gender VARCHAR(20),
    age_range VARCHAR(20),
    interests JSONB,

    -- Preferred Viewing Locations
    preferred_districts TEXT[],
    preferred_cities TEXT[],
    preferred_states TEXT[],
    preferred_countries TEXT[],

    -- Payment Information
    paypal_email VARCHAR(255),
    cashapp_tag VARCHAR(100),
    venmo_username VARCHAR(100),
    crypto_wallet_address VARCHAR(255),
    payment_method VARCHAR(50),

    -- Earnings
    total_earned DECIMAL(15, 2) DEFAULT 0,
    pending_earnings DECIMAL(15, 2) DEFAULT 0,
    paid_out DECIMAL(15, 2) DEFAULT 0,

    -- Viewing Stats
    total_views INT DEFAULT 0,
    total_watch_time INT DEFAULT 0, -- in seconds
    average_completion_rate DECIMAL(5, 2) DEFAULT 0,

    -- Trust Score (for fraud detection)
    trust_score DECIMAL(5, 2) DEFAULT 100.0,
    violations_count INT DEFAULT 0,

    -- Status
    is_verified BOOLEAN DEFAULT false,
    verification_date TIMESTAMP,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP
);

CREATE INDEX idx_viewers_user_id ON loyalty_viewers(user_id);
CREATE INDEX idx_viewers_location ON loyalty_viewers(preferred_states);
CREATE INDEX idx_viewers_trust_score ON loyalty_viewers(trust_score);
```

#### Campaigns Table

```sql
CREATE TABLE campaigns (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID UNIQUE DEFAULT gen_random_uuid(),
    advertiser_id BIGINT REFERENCES advertisers(id) ON DELETE CASCADE,

    -- Campaign Details
    title VARCHAR(255) NOT NULL,
    description TEXT,
    campaign_type VARCHAR(50) CHECK (campaign_type IN ('video', 'audio', 'text', 'live')),

    -- Media
    media_file_url VARCHAR(500),
    media_duration INT, -- in seconds (10-20 for this use case)
    media_file_size BIGINT,
    media_format VARCHAR(20),
    thumbnail_url VARCHAR(500),

    -- Budget & Pricing
    total_budget DECIMAL(15, 2) NOT NULL,
    payment_per_view DECIMAL(10, 2) NOT NULL,
    head_enterprises_fee_percent DECIMAL(5, 2) DEFAULT 15.0,
    total_views_requested INT,
    views_completed INT DEFAULT 0,

    -- Targeting
    target_districts TEXT[],
    target_cities TEXT[],
    target_counties TEXT[],
    target_states TEXT[],
    target_zip_codes TEXT[],
    target_countries TEXT[],

    target_age_ranges TEXT[],
    target_genders TEXT[],
    target_interests TEXT[],

    -- Scheduling
    viewing_days TEXT[], -- ['monday', 'tuesday', ...]
    start_date DATE,
    end_date DATE,
    daily_view_limit INT,

    -- Frequency
    max_views_per_viewer INT DEFAULT 1,
    min_watch_time_percent INT DEFAULT 80, -- Must watch 80% to get paid

    -- Status
    status VARCHAR(50) DEFAULT 'draft' CHECK (status IN ('draft', 'pending_approval', 'active', 'paused', 'completed', 'cancelled')),
    approval_status VARCHAR(50) DEFAULT 'pending' CHECK (approval_status IN ('pending', 'approved', 'rejected')),
    rejection_reason TEXT,

    -- Payment
    payment_status VARCHAR(50) DEFAULT 'pending' CHECK (payment_status IN ('pending', 'authorized', 'captured', 'refunded')),
    stripe_payment_intent_id VARCHAR(255),

    -- Analytics
    total_impressions INT DEFAULT 0,
    unique_viewers INT DEFAULT 0,
    average_watch_time DECIMAL(5, 2),
    click_through_rate DECIMAL(5, 2),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP
);

CREATE INDEX idx_campaigns_advertiser ON campaigns(advertiser_id);
CREATE INDEX idx_campaigns_status ON campaigns(status);
CREATE INDEX idx_campaigns_dates ON campaigns(start_date, end_date);
CREATE INDEX idx_campaigns_location ON campaigns USING GIN (target_states);
```

#### Ad Views Table (View Tracking)

```sql
CREATE TABLE ad_views (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID UNIQUE DEFAULT gen_random_uuid(),
    campaign_id BIGINT REFERENCES campaigns(id) ON DELETE CASCADE,
    viewer_id BIGINT REFERENCES loyalty_viewers(id) ON DELETE CASCADE,

    -- View Details
    started_at TIMESTAMP NOT NULL,
    completed_at TIMESTAMP,
    watch_duration INT, -- actual seconds watched
    completion_percentage DECIMAL(5, 2),

    -- Location Verification
    viewer_ip INET,
    viewer_latitude DECIMAL(10, 8),
    viewer_longitude DECIMAL(11, 8),
    viewer_city VARCHAR(100),
    viewer_state VARCHAR(50),
    viewer_country VARCHAR(2),

    -- Device Information (Fraud Detection)
    device_fingerprint VARCHAR(255),
    user_agent TEXT,
    browser VARCHAR(100),
    os VARCHAR(100),
    device_type VARCHAR(50),

    -- Verification
    is_verified BOOLEAN DEFAULT false,
    verification_method VARCHAR(100), -- 'blockchain', 'ai', 'manual'
    verification_hash VARCHAR(255), -- Blockchain transaction hash
    fraud_score DECIMAL(5, 2),
    fraud_flags JSONB,

    -- Payment
    payment_amount DECIMAL(10, 2),
    payment_status VARCHAR(50) DEFAULT 'pending' CHECK (payment_status IN ('pending', 'approved', 'paid', 'rejected')),
    paid_at TIMESTAMP,

    -- Engagement
    interacted BOOLEAN DEFAULT false, -- Clicked, chatted, etc.
    rating INT CHECK (rating BETWEEN 1 AND 5),
    feedback TEXT,

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_views_campaign ON ad_views(campaign_id);
CREATE INDEX idx_views_viewer ON ad_views(viewer_id);
CREATE INDEX idx_views_dates ON ad_views(started_at);
CREATE INDEX idx_views_payment_status ON ad_views(payment_status);
CREATE INDEX idx_views_verification ON ad_views(is_verified);
```

#### Payments Table

```sql
CREATE TABLE payments (
    id BIGSERIAL PRIMARY KEY,
    uuid UUID UNIQUE DEFAULT gen_random_uuid(),

    -- Payer/Payee
    payer_id BIGINT REFERENCES users(id),
    payee_id BIGINT REFERENCES users(id),

    -- Payment Details
    amount DECIMAL(15, 2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    payment_type VARCHAR(50) CHECK (payment_type IN ('campaign_funding', 'viewer_payout', 'refund', 'fee')),

    -- Payment Method
    payment_method VARCHAR(50), -- 'stripe', 'paypal', 'crypto'

    -- External References
    stripe_payment_intent_id VARCHAR(255),
    stripe_charge_id VARCHAR(255),
    paypal_order_id VARCHAR(255),
    crypto_transaction_hash VARCHAR(255),
    crypto_wallet_from VARCHAR(255),
    crypto_wallet_to VARCHAR(255),

    -- Status
    status VARCHAR(50) DEFAULT 'pending' CHECK (status IN ('pending', 'processing', 'completed', 'failed', 'refunded')),

    -- Related Entities
    campaign_id BIGINT REFERENCES campaigns(id),
    ad_view_id BIGINT REFERENCES ad_views(id),

    -- Metadata
    metadata JSONB,
    error_message TEXT,

    processed_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_payments_payer ON payments(payer_id);
CREATE INDEX idx_payments_payee ON payments(payee_id);
CREATE INDEX idx_payments_status ON payments(status);
CREATE INDEX idx_payments_campaign ON payments(campaign_id);
```

#### View Verifications Table (Fraud Detection)

```sql
CREATE TABLE view_verifications (
    id BIGSERIAL PRIMARY KEY,
    ad_view_id BIGINT REFERENCES ad_views(id) ON DELETE CASCADE,

    -- Verification Methods
    device_fingerprint_check BOOLEAN,
    ip_geolocation_check BOOLEAN,
    watch_time_check BOOLEAN,
    mouse_movement_check BOOLEAN,
    tab_focus_check BOOLEAN,

    -- AI Fraud Detection
    bot_probability DECIMAL(5, 2),
    vpn_detected BOOLEAN,
    datacenter_ip BOOLEAN,
    suspicious_patterns JSONB,

    -- Blockchain Verification
    blockchain_hash VARCHAR(255),
    blockchain_network VARCHAR(50),
    blockchain_timestamp TIMESTAMP,

    -- Manual Review
    requires_manual_review BOOLEAN DEFAULT false,
    reviewed_by BIGINT REFERENCES users(id),
    review_decision VARCHAR(50),
    review_notes TEXT,
    reviewed_at TIMESTAMP,

    -- Final Decision
    is_legitimate BOOLEAN,
    confidence_score DECIMAL(5, 2),

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_verifications_view ON view_verifications(ad_view_id);
CREATE INDEX idx_verifications_legitimate ON view_verifications(is_legitimate);
```

### 6.2 MongoDB Schema (Ad Metadata & Analytics)

```javascript
// campaigns_analytics collection
{
  _id: ObjectId,
  campaign_id: String,  // References PostgreSQL campaigns.uuid
  date: ISODate,

  metrics: {
    impressions: Number,
    unique_viewers: Number,
    total_watch_time: Number,
    average_watch_time: Number,
    completion_rate: Number,
    bounce_rate: Number,
    interaction_rate: Number
  },

  demographics: {
    age_ranges: {
      "18-24": Number,
      "25-34":  Number,
      "35-44": Number,
      "45-54": Number,
      "55+": Number
    },
    genders: {
      male: Number,
      female: Number,
      other: Number
    },
    locations: [
      { city: String, state: String, count: Number }
    ]
  },

  devices: {
    desktop: Number,
    mobile: Number,
    tablet: Number
  },

  browsers: {
    chrome: Number,
    firefox: Number,
    safari: Number,
    other: Number
  },

  hourly_distribution: [
    { hour: Number, views: Number }
  ],

  updated_at: ISODate
}
```

---

## 7. User Flows & Features

### 7.1 Advertiser Journey

#### Step 1: Registration & Onboarding

```
┌─────────────────────────────────────────────────┐
│ 1. Sign Up (Email, Password)                   │
│    └─> Email verification                       │
├─────────────────────────────────────────────────┤
│ 2. Choose Account Type:  "Advertiser"           │
├─────────────────────────────────────────────────┤
│ 3. Business Information Form                    │
│    - Company name, business type                │
│    - Physical address, phone, website           │
│    - Tax ID (optional)                          │
├─────────────────────────────────────────────────┤
│ 4. KYC Verification (for bonding compliance)   │
│    - Upload business license                    │
│    - Owner ID verification                      │
│    - Head Enterprises approval (~24-48 hrs)     │
├─────────────────────────────────────────────────┤
│ 5. Payment Method Setup                         │
│    - Add credit card (Stripe)                   │
│    - Connect PayPal business account            │
│    - Connect crypto wallet (optional)           │
└─────────────────────────────────────────────────┘
```

**UI Components:**

- Multi-step form with progress indicator
- Document upload with drag-and-drop
- Payment method cards with selection
- Onboarding checklist dashboard

#### Step 2: Create Campaign

```
┌─────────────────────────────────────────────────┐
│ CAMPAIGN CREATION WIZARD                        │
├─────────────────────────────────────────────────┤
│ Tab 1: Campaign Details                         │
│   - Title, description                          │
│   - Campaign type (video/audio/text/live)       │
│                                                 │
│ Tab 2: Upload Media                             │
│   - Video/audio file (10-20 sec, MP3/MP4)      │
│   - Auto-validation of duration                 │
│   - Thumbnail auto-generation                   │
│                                                 │
│ Tab 3: Budget & Pricing                         │
│   - Total budget ($)                            │
│   - Desired number of views                     │
│   - Auto-calculated:  Pay per view               │
│   - Head Enterprises fee display (15%)          │
│                                                 │
│ Tab 4: Targeting                                │
│   - Location picker (map + filters)             │
│   - Demographics (age, gender, interests)       │
│   - Viewing schedule (days of week)             │
│   - Max views per viewer (default 1)            │
│                                                 │
│ Tab 5: Review & Submit                          │
│   - Campaign summary                            │
│   - Cost breakdown                              │
│   - Terms of service agreement                  │
│   - Payment authorization                       │
└─────────────────────────────────────────────────┘
          │
          ▼
┌─────────────────────────────────────────────────┐
│ Payment Processing                              │
│   - Stripe payment intent authorization         │
│   - Funds held in escrow                        │
│   - Campaign submitted for Head approval        │
└─────────────────────────────────────────────────┘
          │
          ▼
┌─────────────────────────────────────────────────┐
│ Head Enterprises Review (Admin Dashboard)       │
│   - Content compliance check                    │
│   - Business verification                       │
│   - Approve/Reject (email notification)         │
└─────────────────────────────────────────────────┘
          │
          ▼
┌─────────────────────────────────────────────────┐
│ Campaign Goes LIVE                              │
│   - Matched with eligible Loyalty Viewers       │
│   - Real-time analytics dashboard available     │
└─────────────────────────────────────────────────┘
```

#### Step 3: Monitor Campaign

**Advertiser Dashboard Features:**

1. **Campaign Overview**
   - Active, paused, completed campaigns
   - Budget spent vs. remaining
   - Views completed vs. requested

2. **Real-time Analytics**

   ```
   ┌─────────────────────────────────────┐
   │ Total Views: 1,247 / 2,000          │
   │ Unique Viewers: 892                 │
   │ Avg Watch Time: 18. 4 sec (92%)      │
   │ Completion Rate: 94. 3%              │
   │ Budget Spent: $1,247 / $2,000       │
   │ Cost Per View: $1.00                │
   └─────────────────────────────────────┘

   Geographic Heat Map
   Hourly View Distribution Chart
   Viewer Demographics Breakdown
   ```

3. **Live Interaction (Optional)**
   - See when viewers are watching NOW
   - Join live video call with viewer
   - Live chat with viewers
   - Answer questions in real-time

4. **Reports**
   - PDF/CSV export of campaign metrics
   - Blockchain verification certificates
   - Viewer authenticity reports

### 7.2 Loyalty Viewer Journey

#### Step 1: Registration & Onboarding

```
┌─────────────────────────────────────────────────┐
│ 1. Sign Up (Email, Password)                   │
│    └─> Email verification                       │
├─────────────────────────────────────────────────┤
│ 2. Choose Account Type: "Loyalty Viewer"       │
├─────────────────────────────────────────────────┤
│ 3. Personal Information Form                    │
│    - Legal name (for KYC)                       │
│    - Residential address                        │
│    - Phone number (SMS verification)            │
│    - Date of birth (18+ requirement)            │
├─────────────────────────────────────────────────┤
│ 4. Identity Verification                        │
│    - Upload government ID (driver's license)    │
│    - Selfie verification (liveness check)       │
│    - Address proof (utility bill)               │
├─────────────────────────────────────────────────┤
│ 5. Location Preferences                         │
│    - Preferred cities/states for ad matching    │
│    - Current location (GPS)                     │
├─────────────────────────────────────────────────┤
│ 6. Payment Setup (Receive Money)                │
│    - PayPal email                               │
│    - Cash App tag                               │
│    - Crypto wallet address (optional)           │
│    - Choose preferred payout method             │
└─────────────────────────────────────────────────┘
```

**UI Components:**

- Simple, mobile-friendly forms
- ID document scanner (OCR)
- Webcam selfie capture
- Interactive map for location selection

#### Step 2: Browse & Watch Ads

**Viewer Dashboard:**

```
┌─────────────────────────────────────────────────┐
│ LOYALTY VIEWER DASHBOARD                        │
├─────────────────────────────────────────────────┤
│ Your Earnings Today:  $12. 50                     │
│ Available Ads: 8                                │
│ Next Payout: Jan 20, 2026 (Fridays)            │
├─────────────────────────────────────────────────┤
│ AVAILABLE ADS FOR YOU                           │
│                                                 │
│ ┌───────────────────────────────────────────┐   │
│ │ [Thumbnail] Joe's Pizza                   │   │
│ │ Duration: 15 sec | Pay:  $1.50             │   │
│ │ Location: San Francisco, CA               │   │
│ │ [ Watch Now ]                             │   │
│ └───────────────────────────────────────────┘   │
│                                                 │
│ ┌───────────────────────────────────────────┐   │
│ │ [Thumbnail] Smith Law Firm                │   │
│ │ Duration: 18 sec | Pay: $2.00             │   │
│ │ Location: Los Angeles, CA                 │   │
│ │ [ Watch Now ]                             │   │
│ └───────────────────────────────────────────┘   │
└─────────────────────────────────────────────────┘
```

#### Step 3: Ad Viewing Experience (The Core Portal)

```
┌─────────────────────────────────────────────────────────────┐
│ AD VIEWING PORTAL                                           │
│                                                             │
│  ┌───────────────────────────────────┐  ┌───────────────┐  │
│  │                                   │  │  LIVE CHAT    │  │
│  │                                   │  │               │  │
│  │        VIDEO PLAYER               │  │  Advertiser:   │  │
│  │      (15 second ad)               │  │  "Hi!  Any     │  │
│  │                                   │  │  questions?"  │  │
│  │   [===Progress====>         ]     │  │               │  │
│  │   🔊 Mute  ⏸️ Pause             │  │  You:          │  │
│  │                                   │  │  [Type...]    │  │
│  │   Timer: 12 / 15 sec              │  │               │  │
│  │                                   │  │  [Send]       │  │
│  └───────────────────────────────────┘  │               │  │
│                                         │  [📞 Video    │  │
│  ⚠️ REQUIREMENTS:                        │    Call]      │  │
│  ✅ Watch at least 80% (12 sec)         └───────────────┘  │
│  ✅ Keep tab in focus                                      │
│  ✅ No VPN/Proxy                                           │
│  ✅ Location must match ad targeting                       │
│                                                            │
│  Earnings for this ad: $1.50                               │
└────────────────────────────────────────────────────────────┘
```

**Key Features:**

1. **Video Player Controls**
   - Auto-play when viewer enters portal
   - Cannot skip forward
   - Pause allowed (but extends required time)
   - Volume control

2. **Watch Time Tracking**
   - Real-time progress bar
   - Minimum watch time indicator (80%)
   - Tab focus detection (penalize if tab switched)

3. **Live Chat Box**
   - Real-time chat with advertiser (if they're online)
   - Viewer can ask questions about product/service
   - Chat history saved for both parties

4. **Live Video Call (Optional)**
   - If advertiser is online, viewer can request video call
   - WebRTC peer-to-peer connection
   - 1-on-1 or group call support
   - Call duration tracked (bonus pay for longer engagement)

5. **Verification Checks (Background)**
   - Device fingerprinting (prevent multi-account fraud)
   - IP geolocation (confirm location)
   - Mouse movement tracking (detect bots)
   - Webcam snapshot (optional, for high-value ads)

#### Step 4: Completion & Payment

```
┌─────────────────────────────────────────────────┐
│ ✅ AD COMPLETED!                                 │
│                                                 │
│ You watched: Joe's Pizza (15 sec)               │
│ Earnings: +$1.50                                │
│                                                 │
│ ⏳ Pending Verification (1-5 min)               │
│    └─> Checking view authenticity...            │
│                                                 │
│ [ Rate This Ad:  ⭐⭐⭐⭐⭐ ]                       │
│                                                 │
│ [ Watch Next Ad ]  [ View Earnings ]            │
└─────────────────────────────────────────────────┘
          │
          ▼
┌─────────────────────────────────────────────────┐
│ Verification Complete                           │
│   ✅ Device check passed                        │
│   ✅ Location verified                          │
│   ✅ Watch time:  15/15 sec (100%)               │
│   ✅ Blockchain logged                          │
│                                                 │
│ Payment approved!  $1.50 added to balance.        │
└─────────────────────────────────────────────────┘
          │
          ▼
┌─────────────────────────────────────────────────┐
│ EARNINGS DASHBOARD                              │
│   Pending:  $0.00                                │
│   Available: $47.50                             │
│   Paid Out (Lifetime): $1,234.00                │
│                                                 │
│ [ Request Payout ] (Min: $25)                   │
└─────────────────────────────────────────────────┘
```

**Payout Schedule:**

- Minimum balance: $25
- Payout frequency: Weekly (Fridays)
- Methods: PayPal (instant), Cash App (instant), Crypto (1-2 hrs)

### 7.3 Head Enterprises (Admin) Journey

**Admin Dashboard Features:**

1. **Campaign Approval Queue**

   ```
   ┌──────────────────────────────────────────────┐
   │ PENDING CAMPAIGNS (Review Required)          │
   ├──────────────────────────────────────────────┤
   │ 1. [NEW] Joe's Pizza - San Francisco         │
   │    Budget: $2,000 | 2,000 views              │
   │    [ Review ] [ Approve ] [ Reject ]         │
   │                                              │
   │ 2. [NEW] Smith Law - Los Angeles             │
   │    Budget: $5,000 | 2,500 views              │
   │    [ Review ] [ Approve ] [ Reject ]         │
   └──────────────────────────────────────────────┘
   ```

2. **Viewer Verification Queue**
   - Review flagged accounts
   - Approve/reject KYC documents
   - Investigate fraud reports

3. **Analytics & Reporting**
   - Total platform revenue
   - Active campaigns
   - Total views delivered
   - Viewer trust scores
   - Fraud detection metrics

4. **Dispute Resolution**
   - Advertiser refund requests
   - Viewer payment disputes
   - Manual view verification

5. **Blockchain Verification Management**
   - View blockchain records
   - Generate verification certificates
   - Export compliance reports

---

## 8. Implementation Roadmap

### Phase 1: Foundation (Weeks 1-4)

#### Week 1-2: Backend Setup

**Tasks:**

- [ ] Set up Laravel 11 project
- [ ] Configure PostgreSQL + Redis + MongoDB
- [ ] Implement authentication (Sanctum)
- [ ] Create migration files for all tables
- [ ] Set up Spatie Permissions for roles

**Deliverables:**

- Working API with user registration/login
- Database schema fully migrated
- Role-based access control (Advertiser, Viewer, Admin)

**Code Example:**

```php
// app/Models/User.php
<? php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasRoles;

    protected $fillable = [
        'email', 'phone', 'password', 'user_type',
        'first_name', 'last_name', 'kyc_status'
    ];

    // Relationships
    public function advertiser()
    {
        return $this->hasOne(Advertiser::class);
    }

    public function viewer()
    {
        return $this->hasOne(LoyaltyViewer::class);
    }

    // Scopes
    public function scopeAdvertisers($query)
    {
        return $query->where('user_type', 'advertiser');
    }

    public function scopeViewers($query)
    {
        return $query->where('user_type', 'viewer');
    }
}
```

#### Week 3: Payment Integration

**Tasks:**

- [ ] Integrate Stripe (Laravel Cashier)
- [ ] Integrate PayPal SDK
- [ ] Create payment escrow system
- [ ] Implement payout queue

**Deliverables:**

- Advertisers can fund campaigns
- Viewers can receive payouts
- Admin can manage transactions

#### Week 4: Media Upload & Storage

**Tasks:**

- [ ] Set up AWS S3 bucket (or Cloudflare R2)
- [ ] Create media upload API endpoint
- [ ] Validate video duration (10-20 sec)
- [ ] Auto-generate thumbnails
- [ ] CDN integration

**Deliverables:**

- Media upload works
- Files stored securely
- Fast delivery via CDN

### Phase 2: Core Features (Weeks 5-8)

#### Week 5-6: Campaign Management

**Tasks:**

- [ ] Campaign CRUD API endpoints
- [ ] Location targeting algorithm
- [ ] Viewer matching system
- [ ] Campaign scheduling

**Code Example:**

```php
// app/Services/ViewerMatchingService.php
<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\LoyaltyViewer;

class ViewerMatchingService
{
    /**
     * Find eligible viewers for a campaign
     */
    public function findEligibleViewers(Campaign $campaign): Collection
    {
        return LoyaltyViewer::query()
            ->where('is_verified', true)
            ->where('trust_score', '>=', 70)
            // Location matching
            ->where(function ($query) use ($campaign) {
                if ($campaign->target_states) {
                    $query->whereJsonContains('preferred_states', $campaign->target_states);
                }
            })
            // Not already viewed max times
            ->whereDoesntHave('adViews', function ($query) use ($campaign) {
                $query->where('campaign_id', $campaign->id)
                      ->havingRaw('COUNT(*) >= ?', [$campaign->max_views_per_viewer]);
            })
            ->get();
    }
}
```

#### Week 7: Fraud Detection System

**Tasks:**

- [ ] Device fingerprinting integration
- [ ] IP geolocation check
- [ ] Bot detection (mouse movement, timing)
- [ ] Trust score algorithm

**Deliverables:**

- Fraud detection running on every view
- Trust scores calculated
- Suspicious views flagged

#### Week 8: View Verification

**Tasks:**

- [ ] Real-time view tracking API
- [ ] Watch time calculation
- [ ] Tab focus detection
- [ ] View completion logic

### Phase 3: Real-time Features (Weeks 9-12)

#### Week 9-10: Node.js Real-time Server

**Tasks:**

- [ ] Set up Express + Socket.io server
- [ ] Implement live chat functionality
- [ ] Real-time presence system
- [ ] Integrate with Laravel API

**Code Example:**

```javascript
// server/index.js
const express = require("express");
const http = require("http");
const socketIO = require("socket.io");
const axios = require("axios");

const app = express();
const server = http.createServer(app);
const io = socketIO(server, {
  cors: { origin: process.env.FRONTEND_URL },
});

// Chat rooms per campaign
io.on("connection", (socket) => {
  console.log("User connected:", socket.id);

  // Join campaign chat room
  socket.on("join_campaign", async ({ campaignId, userId, token }) => {
    // Verify user with Laravel API
    const verified = await verifyUserToken(token);
    if (!verified) {
      socket.emit("error", "Unauthorized");
      return;
    }

    socket.join(`campaign_${campaignId}`);
    socket.emit("joined", { campaignId });

    // Notify advertiser that viewer is online
    socket.to(`campaign_${campaignId}`).emit("viewer_online", { userId });
  });

  // Send chat message
  socket.on("send_message", ({ campaignId, message }) => {
    io.to(`campaign_${campaignId}`).emit("new_message", {
      userId: socket.userId,
      message,
      timestamp: new Date(),
    });
  });

  socket.on("disconnect", () => {
    console.log("User disconnected:", socket.id);
  });
});

server.listen(3001, () => {
  console.log("Real-time server running on port 3001");
});
```

#### Week 11-12: WebRTC Video Integration

**Tasks:**

- [ ] Integrate Agora. io SDK (or Daily.co)
- [ ] Implement 1-on-1 video calls
- [ ] Call recording (optional)
- [ ] Call duration tracking

**Deliverables:**

- Advertisers can video call viewers
- Calls are stable and low-latency
- Call metrics tracked

### Phase 4: Frontend Development (Weeks 13-16)

#### Week 13-14: Next.js Setup & UI Components

**Tasks:**

- [ ] Bootstrap Next.js 14 project
- [ ] Install Shadcn UI + Tailwind
- [ ] Create design system
- [ ] Build reusable components

**Component Example:**

```typescript
// src/components/viewer/AdPlayer.tsx
'use client';

import { useEffect, useRef, useState } from 'react';
import { useWebSocket } from '@/hooks/useWebSocket';
import { Button } from '@/components/ui/button';

interface AdPlayerProps {
  campaignId: string;
  mediaUrl: string;
  duration: number;
  requiredWatchPercent: number;
}

export function AdPlayer({ campaignId, mediaUrl, duration, requiredWatchPercent }: AdPlayerProps) {
  const videoRef = useRef<HTMLVideoElement>(null);
  const [watchTime, setWatchTime] = useState(0);
  const [completed, setCompleted] = useState(false);
  const { emit } = useWebSocket();

  // Track watch time
  useEffect(() => {
    const interval = setInterval(() => {
      if (videoRef.current && ! videoRef.current.paused) {
        setWatchTime(prev => {
          const newTime = prev + 1;
          const percent = (newTime / duration) * 100;

          // Emit progress to server
          emit('view_progress', { campaignId, watchTime: newTime, percent });

          // Check completion
          if (percent >= requiredWatchPercent && !completed) {
            setCompleted(true);
            emit('view_completed', { campaignId, watchTime: newTime });
          }

          return newTime;
        });
      }
    }, 1000);

    return () => clearInterval(interval);
  }, [completed, campaignId, duration, requiredWatchPercent]);

  return (
    <div className="relative">
      <video
        ref={videoRef}
        src={mediaUrl}
        className="w-full rounded-lg"
        autoPlay
        onContextMenu={(e) => e.preventDefault()} // Disable right-click
      />

      <div className="mt-4 flex justify-between items-center">
        <div className="text-sm">
          Watch Time: {watchTime}s / {duration}s ({Math.round((watchTime / duration) * 100)}%)
        </div>

        {completed && (
          <div className="text-green-600 font-semibold">
            ✅ View Completed!
          </div>
        )}
      </div>
    </div>
  );
}
```

#### Week 15-16: Complete User Interfaces

**Tasks:**

- [ ] Build advertiser dashboard
- [ ] Build viewer dashboard
- [ ] Build admin panel
- [ ] Integrate with Laravel API

**Deliverables:**

- Fully functional UIs for all user types
- Responsive design (mobile + desktop)
- Dark mode support

### Phase 5: Web3 Integration (Weeks 17-18)

#### Week 17: Smart Contract Development

**Tasks:**

- [ ] Write Solidity smart contract for view verification
- [ ] Deploy to Polygon (lower fees than Ethereum)
- [ ] Create escrow contract for payments

**Smart Contract Example:**

```solidity
// contracts/ViewVerification.sol
// SPDX-License-Identifier: MIT
pragma solidity ^0.8.0;

contract ViewVerification {
    struct AdView {
        address viewer;
        string campaignId;
        uint256 watchTime;
        uint256 timestamp;
        bool verified;
    }

    mapping(bytes32 => AdView) public views;
    address public headEnterprisesWallet;

    event ViewVerified(
        bytes32 indexed viewId,
        address indexed viewer,
        string campaignId,
        uint256 timestamp
    );

    constructor(address _headEnterprisesWallet) {
        headEnterprisesWallet = _headEnterprisesWallet;
    }

    /**
     * Record a verified view on-chain
     */
    function recordView(
        string memory viewId,
        address viewer,
        string memory campaignId,
        uint256 watchTime
    ) public {
        require(msg.sender == headEnterprisesWallet, "Only Head Enterprises can record views");

        bytes32 viewHash = keccak256(abi.encodePacked(viewId));

        views[viewHash] = AdView({
            viewer: viewer,
            campaignId: campaignId,
            watchTime: watchTime,
            timestamp: block. timestamp,
            verified: true
        });

        emit ViewVerified(viewHash, viewer, campaignId, block.timestamp);
    }

    /**
     * Verify a view exists on-chain
     */
    function verifyView(string memory viewId) public view returns (bool) {
        bytes32 viewHash = keccak256(abi.encodePacked(viewId));
        return views[viewHash].verified;
    }
}
```

#### Week 18: Web3 Payment Integration

**Tasks:**

- [ ] Integrate Thirdweb SDK
- [ ] Wallet connection (MetaMask, Coinbase Wallet)
- [ ] Crypto payment flow
- [ ] USDC/ETH payment acceptance

**Laravel Service Example:**

```php
// app/Services/BlockchainService.php
<?php

namespace App\Services;

use Web3\Web3;
use Web3\Contract;

class BlockchainService
{
    protected $web3;
    protected $contract;

    public function __construct()
    {
        $this->web3 = new Web3(config('blockchain.rpc_url'));
        $this->contract = new Contract(
            config('blockchain.rpc_url'),
            config('blockchain.verification_contract_abi')
        );
    }

    /**
     * Record a view verification on blockchain
     */
    public function recordViewVerification(AdView $adView): string
    {
        $viewId = $adView->uuid;
        $viewerAddress = $adView->viewer->crypto_wallet_address;
        $campaignId = $adView->campaign->uuid;
        $watchTime = $adView->watch_duration;

        // Call smart contract method
        $transactionHash = $this->contract->at(config('blockchain.verification_contract_address'))
            ->send('recordView', $viewId, $viewerAddress, $campaignId, $watchTime, [
                'from' => config('blockchain.head_enterprises_wallet'),
                'gas' => '200000'
            ]);

        // Store transaction hash in database
        $adView->update(['verification_hash' => $transactionHash]);

        return $transactionHash;
    }
}
```

### Phase 6: Testing & Security (Weeks 19-20)

#### Week 19: Testing

**Tasks:**

- [ ] Unit tests (Pest/PHPUnit)
- [ ] Integration tests (API endpoints)
- [ ] E2E tests (Playwright)
- [ ] Load testing (Apache JMeter)

**Test Example:**

```php
// tests/Feature/CampaignTest.php
<?php

use App\Models\User;
use App\Models\Advertiser;
use App\Models\Campaign;

test('advertiser can create campaign', function () {
    $user = User::factory()->create(['user_type' => 'advertiser']);
    $advertiser = Advertiser::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)->postJson('/api/campaigns', [
        'title' => 'Test Campaign',
        'media_file_url' => 'https://s3.../video.mp4',
        'total_budget' => 1000,
        'target_states' => ['CA', 'NY'],
    ]);

    $response->assertStatus(201);
    $this->assertDatabaseHas('campaigns', ['title' => 'Test Campaign']);
});

test('viewer cannot create campaign', function () {
    $user = User::factory()->create(['user_type' => 'viewer']);

    $response = $this->actingAs($user)->postJson('/api/campaigns', [
        'title' => 'Test Campaign',
    ]);

    $response->assertStatus(403);
});
```

#### Week 20: Security Audit

**Tasks:**

- [ ] SQL injection prevention audit
- [ ] XSS protection audit
- [ ] CSRF token validation
- [ ] Rate limiting implementation
- [ ] API authentication hardening
- [ ] Penetration testing

**Security Checklist:**

- ✅ All inputs validated and sanitized
- ✅ Database queries use parameterized statements
- ✅ API rate limiting (100 requests/min)
- ✅ File upload validation (type, size, malware scan)
- ✅ HTTPS enforced everywhere
- ✅ Secure password hashing (Bcrypt)
- ✅ 2FA available for all users
- ✅ Session management secure
- ✅ Blockchain private keys in secure vault

### Phase 7: Launch & Compliance (Weeks 21-22)

#### Week 21: California Compliance

**Tasks:**

- [ ] Legal review of terms of service
- [ ] Privacy policy (CCPA compliance)
- [ ] Business license documentation
- [ ] Surety bond setup ($50k minimum for CA)
- [ ] DMCA compliance

**Required Documents:**

- Business license (California)
- Surety bon
