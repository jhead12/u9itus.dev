# U9itus Platform - Test Scenarios

**Version:** 3.0.0  
**Last Updated:** March 18, 2026  
**Framework:** Laravel 12 + Pest/PHPUnit

## Overview

This document outlines comprehensive test scenarios for the U9itus Political Loyalty Ads platform. These scenarios cover all critical user flows, edge cases, and system requirements to ensure platform reliability, security, and performance.

---

## **1. User Registration & Authentication**

### **Test Scenario 1.1: Voter Registration**

- **Given:** A new user visits the voter registration page
- **When:** They complete the registration form with valid data
- **Then:** Account is created, confirmation email sent, user can log in
- **Edge Cases:**
    - Duplicate email addresses
    - Invalid email formats
    - Missing required fields
    - Registration with referral code (`?ref=<code>`)

### **Test Scenario 1.2: Politician Registration**

- **Given:** A politician wants to create an account
- **When:** They provide political affiliation, office, governance level
- **Then:** Account pending admin approval, notification sent to admin
- **Edge Cases:**
    - Invalid political office for governance level
    - Missing verification documents
    - Duplicate campaign profiles

### **Test Scenario 1.3: Multi-Factor Authentication**

- **Given:** User has 2FA enabled
- **When:** They attempt login with correct password
- **Then:** 2FA code requested, sent via email/SMS
- **Verify:** Login fails with wrong code, succeeds with valid code

### **Test Scenario 1.4: Logout with Stale CSRF Token**

- **Given:** An authenticated user (politician or admin) with a valid session
- **When:** They attempt to logout with an expired/stale CSRF token
- **Then:** Session is invalidated, user is logged out, redirected to login page with 302 status
- **Verify:**
    - 302 redirect to login (not 419 error)
    - Session properly destroyed
    - Token regenerated on login page
    - No error message displayed to user
- **Context:** Resolves previous issue where stale CSRF tokens on logout routes returned 419 errors instead of gracefully handling session cleanup

---

## **2. Campaign Management**

### **Test Scenario 2.1: Create Video Campaign**

- **Given:** Politician has sufficient credits
- **When:** They create a campaign with YouTube URL, target demographics
- **Then:** Campaign saved as draft, pending admin approval
- **Verify:**
    - YouTube URL parsing (short, watch, embed formats)
    - Geographic targeting (state, city, district)
    - Budget allocation validation

### **Test Scenario 2.2: Campaign Approval Workflow**

- **Given:** Admin reviews pending campaign
- **When:** Admin approves/rejects with notes
- **Then:** Status updated, politician notified
- **Test:**
    - Approved campaigns become active
    - Rejected campaigns with feedback
    - Audit log created for admin action

### **Test Scenario 2.3: Campaign Budget Depletion**

- **Given:** Active campaign with 10 views remaining (budget)
- **When:** 10 voters watch the video
- **Then:** Campaign automatically paused, politician notified
- **Verify:** No additional views assigned when balance = $0

### **Test Scenario 2.4: Campaign Media Duration Boundary Enforcement**

- **Given:** Politician attempts to create/upload campaign video
- **When:** They submit video with duration outside configured range (< 30s or > 300s)
- **Then:** Form submission rejected with validation error
- **Verify:**
    - Minimum boundary: 30 seconds enforced (reject 29s)
    - Maximum boundary: 300 seconds enforced (reject 301s)
    - Valid range 30-300: accepted without error
    - UI constraints match server validation (min/max attributes, helper text)
    - Error message clearly states duration requirements
- **Context:** Ensures consistent video duration policy across all config, controllers, validators, and UI surfaces

---

## **3. Token-Based Ad Delivery System**

### **Test Scenario 3.1: Token Generation**

- **Given:** Voter eligible for new ad
- **When:** System generates ad token
- **Then:** SHA-256 token created with 24-hour expiration
- **Verify:**
    - Token is unique (collision check)
    - Token includes voter_id, campaign_id, timestamp
    - Token stored in `ad_view_tokens` table

### **Test Scenario 3.2: Token Delivery Methods**

- **Test:** Token sent via:
    - Email notification
    - SMS (if phone verified)
    - Push notification (if app installed)
    - In-app notification banner
- **Verify:** User can click token link to watch page

### **Test Scenario 3.3: Token Security**

- **Given:** Valid token URL
- **When:** User attempts to:
    - Use token twice (replay attack)
    - Use expired token (>24 hours old)
    - Use token for different voter_id
- **Then:** Access denied, fraud signal logged

---

## **4. Video Viewing & Earnings**

### **Test Scenario 4.1: Complete Video View**

- **Given:** Voter clicks valid token link
- **When:** They watch entire video (100% completion)
- **Then:**
    - $0.25 credited to voter's balance
    - $0.60 deducted from politician's credits
    - ViewSession marked as `completed`
    - Token marked as `used`

### **Test Scenario 4.2: YouTube Player Integration**

- **Given:** Campaign with YouTube video
- **When:** Player loads
- **Then:**
    - Video auto-detects YouTube URL format
    - IFrame API initialized
    - Progress tracked every 5 seconds (heartbeat)
    - Seek-forward disabled (anti-skip)

### **Test Scenario 4.3: Incomplete View**

- **Given:** Voter starts video but closes tab at 50%
- **When:** Session expires (15 minutes)
- **Then:**
    - No earnings credited
    - No politician charge
    - Session marked as `abandoned`
    - Token marked as `expired`

### **Test Scenario 4.4: Video Progress Tracking**

- **Given:** Active viewing session
- **When:** Heartbeat pings every 5 seconds
- **Then:**
    - `progress_percent` updated in ViewSession
    - Timestamp of last heartbeat recorded
    - Fraud detection checks (too fast progress)

---

## **5. Referral System**

### **Test Scenario 5.1: Voter Referral Commission**

- **Given:** User A refers User B (voter) via `?ref=USER_A_CODE`
- **When:** User B watches an ad and earns $0.25
- **Then:**
    - User A earns $0.025 (10% commission)
    - Commission recorded in `referral_earnings`
    - Commission is recurring on every view

### **Test Scenario 5.2: Politician Referral Bonus**

- **Given:** User A refers Politician P via politician referral link
- **When:** Politician P makes first credit purchase ($100)
- **Then:**
    - User A earns $10 (10% one-time bonus)
    - `procurement_commission_paid` flag set to `true`
    - No commission on subsequent purchases

### **Test Scenario 5.3: Referral Code Generation**

- **Given:** New user registered
- **When:** System generates referral code
- **Then:**
    - Unique 8-character alphanumeric code
    - Stored in `users.referral_code`
    - Two link variants available (voter & politician)

---

## **6. Fraud Prevention**

### **Test Scenario 6.1: Device Fingerprinting**

- **Given:** Voter attempts to watch multiple ads
- **When:** System tracks device fingerprint
- **Then:** Detect multiple accounts from same device
- **Flag if:** >3 accounts from same IP/device

### **Test Scenario 6.2: Rapid View Detection**

- **Given:** Voter completes video
- **When:** Completion time < (video_duration \* 0.9)
- **Then:** Flag as suspicious, manual review required

### **Test Scenario 6.3: Token Abuse Detection**

- **Given:** User attempts to access token without valid session
- **When:** Multiple failed token attempts (>5)
- **Then:** Account temporarily locked, admin notified

### **Test Scenario 6.4: VPN/Proxy Detection**

- **Given:** User connects from known VPN IP
- **When:** System checks IP reputation
- **Then:** Require additional verification or reduce earnings

---

## **7. Payment & Billing**

### **Test Scenario 7.1: Politician Credit Purchase**

- **Given:** Politician buys $100 in credits via Stripe
- **When:** Payment succeeds
- **Then:**
    - Credits added to account
    - Transaction recorded
    - Receipt emailed
    - Referral bonus paid (if first purchase)

### **Test Scenario 7.2: Stripe Webhook Processing**

- **Given:** Stripe sends `payment_intent.succeeded` webhook
- **When:** Webhook handler processes event
- **Then:**
    - Idempotency check (duplicate prevention)
    - Credits applied to politician account
    - Audit log created

### **Test Scenario 7.3: Voter Payout Request**

- **Given:** Voter has $50 balance (minimum threshold)
- **When:** They request PayPal payout
- **Then:**
    - Payout queued for batch processing
    - Status: `pending` → `processing` → `completed`
    - Email confirmation sent

### **Test Scenario 7.4: Batch Payout Processing**

- **Given:** Admin initiates weekly payout batch
- **When:** PayPal API called for each eligible voter
- **Then:**
    - Successful payouts marked as `paid`
    - Failed payouts marked for retry
    - Transaction records updated

---

## **8. Admin Dashboard**

### **Test Scenario 8.1: Campaign Moderation**

- **Given:** Admin views pending campaigns queue
- **When:** They review campaign content
- **Then:** Can approve/reject with reason
- **Verify:** Audit trail logged

### **Test Scenario 8.2: Fraud Review Queue**

- **Given:** Fraud signals triggered
- **When:** Admin reviews flagged accounts
- **Then:** Can suspend, warn, or clear flag
- **Actions:** Ban voter, refund politician

### **Test Scenario 8.3: Platform Settings Management**

- **Given:** Admin adjusts `viewer_payout_per_view`
- **When:** Changed from $0.25 to $0.30
- **Then:**
    - New value saved to `platform_settings`
    - Takes effect immediately for new views
    - Existing sessions use original rate

### **Test Scenario 8.4: Admin Logout with Session Expiry Safeguard**

- **Given:** Admin user is authenticated in dashboard
- **When:** They logout OR session token expires on logout request
- **Then:** Session is cleanly invalidated, admin redirected to login page
- **Verify:**
    - Stale/expired CSRF tokens handled gracefully (no 419 error)
    - Session destroyed and regenerated on subsequent login
    - Audit log records logout event
    - Admin cannot access protected routes after logout
- **Context:** Ensures admin users can logout reliably even with network delays or token expiration, maintaining security without exposing technical errors

---

## **9. API Endpoints**

### **Test Scenario 9.1: Voter API - Get Available Ads**

```http
GET /api/voter/available-ads
```

- **Verify:** Returns campaigns matching voter's location/demographics
- **Test:** Pagination, filtering, rate limiting

### **Test Scenario 9.2: Politician API - Campaign Stats**

```http
GET /api/politician/campaigns/{id}/stats
```

- **Verify:** Returns views, completion rate, budget remaining
- **Test:** Authorization (only campaign owner)

### **Test Scenario 9.3: Health Check**

```http
GET /api/health
```

- **Verify:** Returns database, cache, queue status

---

## **10. Performance & Load Testing**

### **Test Scenario 10.1: Concurrent Video Views**

- **Simulate:** 1000 simultaneous viewers
- **Measure:** Response time, database load, token generation speed

### **Test Scenario 10.2: Token Generation at Scale**

- **Test:** Generate 10,000 tokens/second
- **Verify:** No collisions, consistent performance

### **Test Scenario 10.3: Heartbeat API Load**

- **Given:** 5000 active viewing sessions
- **When:** All send heartbeat every 5 seconds
- **Then:** System handles 1000 requests/second without degradation

---

## **11. Edge Cases & Error Handling**

### **Test Scenario 11.1: YouTube Video Unavailable**

- **Given:** Campaign video deleted from YouTube
- **When:** Voter clicks token
- **Then:** Graceful error, campaign auto-paused

### **Test Scenario 11.2: Insufficient Politician Credits**

- **Given:** Campaign with $0.50 remaining (< $0.60 per view)
- **When:** System tries to assign ad
- **Then:** Campaign paused, politician notified

### **Test Scenario 11.3: Voter Account Suspended**

- **Given:** Voter banned for fraud
- **When:** They attempt to use valid token
- **Then:** Access denied with explanation

---

## **12. Earnings Calculator Component**

### **Test Scenario 12.1: Calculation Accuracy**

- **Given:** Calculator set to:
    - 3 ads/day
    - 5 voter referrals
    - 1 politician referral
- **When:** Calculate monthly earnings
- **Then:**
    - Ad earnings: 3 × $0.25 × 30 = $22.50
    - Voter commissions: 5 × 3 × $0.025 × 30 = $3.375
    - Politician bonus: $10
    - **Total: $35.88**

### **Test Scenario 12.2: Slider Interactivity**

- **Test:** Sliders update values in real-time
- **Verify:** Values formatted with currency symbols & commas

---

## **Priority Test Execution Order**

### **Critical Path (P0)**

1. Token generation & validation
2. Complete video view with earnings
3. Politician credit purchase
4. Campaign approval workflow

### **High Priority (P1)**

5. Referral commission calculation
6. Fraud detection basics
7. Voter payout request
8. API authentication

### **Medium Priority (P2)**

9. Admin dashboard actions
10. YouTube player integration
11. Email/SMS notifications
12. Earnings calculator

### **Nice-to-Have (P3)**

13. Performance benchmarks
14. Multi-language support
15. Mobile responsiveness
16. Analytics dashboard

---

## **Test Data Requirements**

### **Sample Users**

```php
// Voter
- Email: voter1@example.com
- Balance: $25.00
- Views completed: 100
- Referrals: 5

// Politician
- Email: mayor.smith@example.com
- Office: Mayor
- Credits: $120.00
- Active campaigns: 2

// Admin
- Email: admin@u9itus.com
- Permissions: full access
```

### **Sample Campaigns**

```php
- Title: "Vote for Education Reform"
- YouTube URL: https://youtu.be/dQw4w9WgXcQ
- Budget: $300.00 (500 views)
- Target: California, District 12
- Status: active
```

### **Sample Tokens**

```php
- Token: sha256(voter_id.campaign_id.timestamp.salt)
- Created: 2026-03-03 10:00:00
- Expires: 2026-03-04 10:00:00
- Status: pending
```

---

## **Automation Recommendations**

### **CI/CD Pipeline**

- Run P0 tests on every commit
- Run P1 tests on PRs
- Full test suite on `main` branch
- Performance tests weekly

### **Test Frameworks**

- **Unit Tests:** Pest PHP
- **Feature Tests:** Laravel HTTP Tests
- **Browser Tests:** Laravel Dusk
- **Load Tests:** Apache JMeter / k6

### **Coverage Goals**

- Critical paths: 100%
- Service classes: 90%+
- Controllers: 85%+
- Models: 80%+

---

## **Bug Tracking Template**

When reporting issues during testing:

```markdown
**Test Scenario:** [Number and title]
**Expected Result:** [What should happen]
**Actual Result:** [What actually happened]
**Steps to Reproduce:**

1. Step one
2. Step two
3. Step three

**Environment:**

- PHP Version:
- Laravel Version:
- Database:
- Browser (if applicable):

**Screenshots/Logs:** [Attach if available]
**Priority:** [P0/P1/P2/P3]
```

---

## **Next Steps**

1. **Implement automated tests** for P0 scenarios
2. **Set up test database** with seeded data
3. **Configure CI/CD** for automated testing
4. **Create test execution checklist** for manual QA
5. **Document test results** in project tracking system

---

**Document Maintained By:** Engineering Team  
**Review Frequency:** Before each release cycle  
**Related Documents:**

- [DEVELOPMENT.md](DEVELOPMENT.md)
- [MVP_SUMMARY.md](MVP_SUMMARY.md)
- [DIAL4DOUGH_IMPLEMENTATION_PLAN.md](DIAL4DOUGH_IMPLEMENTATION_PLAN.md)
