# Dashboard Pages Creation Prompt - U9itus Wix App

## Project Context

You are building **Dashboard Pages** for **U9itus**, a Wix App Extension that connects politicians with voters through paid video messages. Politicians pay $0.60 per view; voters earn $0.25 for watching full messages. The platform uses secure token-based delivery with one-time use tokens delivered via email/SMS/push notifications to prevent fraud.

**Tech Stack:**

- **Backend:** Laravel 11 + MySQL (Railway production)
- **Frontend:** Wix App Extension (Wix Design System, React-based)
- **Wix SDKs:** @wix/dashboard, @wix/design-system, @wix/members
- **Authentication:** Wix OAuth + Laravel JWT for API calls
- **Security:** Token-based ad delivery (SHA-256), device fingerprinting, rate limiting

**Dashboard Location:** These pages appear in the **Wix Dashboard** (backend admin area) under "Develop → Extensions" and are accessed through Wix Dashboard navigation.

**Current Routes (Laravel backend):**

```php
// Dashboard Pages Protected by wix.verify middleware
GET /wix/dashboard          → Main overview (redirect based on role)
GET /wix/dashboard/politician → Politician campaign management
GET /wix/dashboard/voter     → Voter earnings & activity
GET /wix/dashboard/admin     → Admin controls & fraud management
```

**Current Config (wix.config.json):**

```json
"dashboardPages": [
  {
    "id": "main-dashboard",
    "title": "U9itus Dashboard",
    "url": "/wix/dashboard"
  },
  {
    "id": "politician-dashboard",
    "title": "Politician Dashboard",
    "url": "/wix/dashboard/politician"
  },
  {
    "id": "voter-dashboard",
    "title": "Voter Dashboard",
    "url": "/wix/dashboard/voter"
  },
  {
    "id": "admin-dashboard",
    "title": "Admin Panel",
    "url": "/wix/dashboard/admin"
  }
]
```

---

## Your Task

Create **three fully functional Dashboard Pages** with modern UI/UX, secure API integration, comprehensive data visualization, and real-time updates. Each dashboard should follow Wix Design System guidelines and provide role-specific management interfaces.

---

## 1. POLITICIAN DASHBOARD (`/wix/dashboard/politician`)

### Purpose

Enable politicians/governance officials to create campaigns, upload video messages, target audiences, monitor performance, and manage billing.

### Target Users

- Politicians (Federal, State, County, City, School Board)
- Campaign managers
- Political offices: Mayor, Governor, City Council, US Senator, State Rep, etc.

### Key Features to Build

#### A. Campaign Management

- **Create New Campaign:**
    - Campaign name, description, office type (Mayor, Governor, etc.)
    - Governance level (Federal, State, County, City, School Board, Special District)
    - Start/end dates with calendar picker
    - Budget allocation ($0.60 per view)
    - Geographic targeting: State, city, congressional district, ZIP codes
    - Demographics: Age range, interests (optional)
- **Video Upload:**
    - Drag-and-drop video uploader (MP4, max 5 min, 100MB limit)
    - Thumbnail auto-generation or custom upload
    - Video preview before publishing
    - Title, description, call-to-action text
- **Campaign Status:**
    - Draft, Pending Admin Approval, Active, Paused, Completed, Rejected
    - Status badge with color coding
    - Actions: Edit, Pause, Resume, Duplicate, Delete

- **Campaign List View:**
    - Sortable table: Campaign Name, Status, Views, Spent, CTR, Created Date
    - Search and filter by status, date range, office type
    - Bulk actions: Pause multiple, delete multiple

#### B. Analytics & Reporting

- **Performance Dashboard:**
    - Total campaigns, active campaigns, total views, total spent
    - Views over time (line chart with daily/weekly/monthly toggle)
    - Geographic heatmap: Views by state/city
    - Top-performing campaigns (by views, completion rate, engagement)
    - Engagement metrics: Average watch time, completion rate, click-through rate
- **Real-Time Metrics:**
    - Live view counter for active campaigns
    - Views per hour graph
    - Notification sent vs. viewed ratio
    - Token redemption rate

- **Exportable Reports:**
    - CSV/PDF export: Campaign summary, views by date, geographic breakdown
    - Custom date range selector
    - Scheduled email reports (daily/weekly)

#### C. Billing & Payments

- **Payment Methods:**
    - Add/edit credit cards (Stripe integration)
    - Billing history table: Date, amount, campaign, invoice download
    - Auto-charge settings: Pre-fund $100/500/1000 views
- **Budget Management:**
    - Current balance display with "Add Funds" button
    - Low balance alert (<$50 remaining)
    - Per-campaign budget limits
    - Spending trends chart (monthly bar chart)

#### D. Voter Engagement Tools

- **Send Campaign Notification:**
    - Button to trigger batch notification sending
    - Select delivery method: Email, SMS, Push (via Wix APIs)
    - Preview notification template
    - Confirmation modal: "Send to 5,432 targeted voters?"
- **Targeting Preview:**
    - Map visualization of targeted voters
    - Estimated reach: "~5,000 voters match your criteria"
    - Cost estimate: "Estimated spend: $3,000 (5,000 views × $0.60)"

#### E. UI Components Needed

- **Cards:** Campaign overview cards with stats
- **Tables:** DataTable with sorting, pagination, search
- **Charts:** Line charts (views over time), bar charts (spending), heatmap (geography)
- **Forms:** Campaign creation wizard (multi-step form)
- **Modals:** Confirmation dialogs, video preview, notification preview
- **Buttons:** Primary (Create Campaign), Secondary (Pause), Danger (Delete)
- **Badges:** Status indicators (Active=green, Paused=yellow, Rejected=red)
- **Alerts:** Success, error, warning banners for user feedback

---

## 2. VOTER DASHBOARD (`/wix/dashboard/voter`)

### Purpose

Display earnings, available ads (via secure tokens), watch history, referral tracking, and payout management.

### Target Users

- Registered voters who watch political messages to earn money
- Referrers earning commission from friends' views

### Key Features to Build

#### A. Earnings Overview

- **Dashboard Header:**
    - Total earnings to date (large number, $XXX.XX)
    - Pending earnings (awaiting 48-hour verification)
    - Available for payout (cleared funds)
    - Referral earnings (10% commission from referrals)
- **Earnings Chart:**
    - Line chart: Earnings over time (daily/weekly/monthly)
    - Bar chart: Earnings by campaign type (Federal, State, Local)
    - Pie chart: Earnings breakdown (Watch earnings vs. Referral commission)

#### B. Watch Messages (Token-Based)

- **Important Security Note:**
    - No panel-based ad browsing allowed (prevents click abuse)
    - Voters receive secure one-time tokens via Email/SMS/Push
    - Dashboard shows messages accessed via redeemed tokens only
- **Available Messages Section:**
    - List of messages accessed via valid tokens (24-hour validity)
    - Each message card shows:
        - Politician name, office, thumbnail
        - Watch earn amount: "$0.25"
        - Watch time: "2:30 min"
        - "Watch Now" button (only if token is valid)
    - Token expiration countdown: "Expires in 4 hours 23 min"
- **Video Player:**
    - Full-screen video player with must-watch verification
    - No skip button (must watch 100% to earn)
    - Engagement tracking: Pauses, rewinds tracked for fraud detection
    - Completion confirmation: "You earned $0.25! ✓"

#### C. Watch History & Activity

- **History Table:**
    - Columns: Date, Politician, Office, Watch Time, Earned, Status
    - Status: Completed (green), Pending (yellow), Fraud Hold (red)
    - Search and filter by date, politician, status
    - Pagination (20 per page)
- **Activity Feed:**
    - Real-time feed: "You watched Mayor Smith's message – Earned $0.25"
    - Referral activity: "Your referral John watched 3 ads – You earned $0.075"
    - Payout activity: "Payout of $50 sent to PayPal – Confirmed"

#### D. Referral Program

- **Referral Link Generator:**
    - Unique referral code: `U9itus.com/signup?ref=VOTER123`
    - Copy link button with success toast
    - QR code generator for easy sharing
- **Referral Stats:**
    - Total referrals, active referrals, referral earnings
    - Referral leaderboard: Top 10 referrers with earnings
    - Commission structure: "Earn 10% of your referrals' watch earnings!"
- **Referral Activity:**
    - List of referred friends with stats:
        - Friend's name (anonymized: "User #4532")
        - Date joined, total views, your earnings from them
        - Status: Active, Inactive, Blocked

#### E. Payout Management

- **Payout Methods:**
    - Link PayPal account, CashApp, or bank account
    - Minimum payout: $20 threshold
    - Payout schedule: Weekly (Fridays) or manual request
- **Request Payout:**
    - "Request Payout" button (disabled if <$20)
    - Select payout method dropdown
    - Confirmation: "Request $45.75 payout to PayPal?"
    - Processing time: "Payouts process within 3-5 business days"
- **Payout History:**
    - Table: Date, Amount, Method, Status, Transaction ID
    - Status: Pending, Processing, Completed, Failed
    - Download receipt/invoice

#### F. Account & Security

- **Profile Settings:**
    - Email, phone number (verified for notifications)
    - State, city, ZIP code (for campaign targeting)
    - Communication preferences: Email, SMS, Push toggles
- **Security Settings:**
    - Device fingerprint list: "Logged in from 2 devices"
    - Login history: IP, device, date
    - Trust score: "Your account trust score: 95/100 (Excellent)"
    - Suspicious activity alerts

#### G. UI Components Needed

- **Stat Cards:** Total earnings, pending, available, referral earnings
- **Charts:** Line (earnings over time), bar (by campaign type), pie (breakdown)
- **Video Cards:** Thumbnail, politician info, watch button, timer
- **Video Player:** Custom player with tracking, no-skip enforcement
- **Tables:** Watch history, payout history, referrals
- **Forms:** Payout request, profile settings
- **Alerts:** Low balance, token expiration, fraud warning
- **Copy Link:** Referral link with one-click copy

---

## 3. ADMIN DASHBOARD (`/wix/dashboard/admin`)

### Purpose

Platform oversight, campaign approval, fraud detection, user management, and financial reporting.

### Target Users

- U9itus platform administrators
- Fraud analysts
- Finance/operations team

### Key Features to Build

#### A. Platform Overview

- **Top-Level KPIs:**
    - Total users (Politicians, Voters)
    - Active campaigns, pending approvals
    - Total views (today, this week, all-time)
    - Gross revenue, net profit margin
    - Platform balance (escrowed funds)
- **Activity Timeline:**
    - Real-time feed: Campaign created, video uploaded, campaign approved, fraud detected, payout processed
    - Filter by activity type, user role, severity

#### B. Campaign Approval Workflow

- **Pending Campaigns Table:**
    - Columns: Politician, Campaign Name, Office, Video, Target, Budget, Submitted Date
    - Actions: Preview Video, Approve, Reject, Request Changes
- **Review Modal:**
    - Play video preview
    - Check targeting criteria
    - Review politician profile and past campaigns
    - Approval checklist: Content appropriate, complies with policies, etc.
    - Approve/Reject buttons with reason textarea for rejection
- **Approval History:**
    - Searchable log of all reviewed campaigns
    - Filter by approver, decision (approved/rejected), date

#### C. Fraud Detection & Management

- **Fraud Dashboard:**
    - Active alerts count (high/medium/low severity)
    - Fraud detection metrics: Flagged accounts, blocked views, prevented payouts
    - Top fraud indicators: Rapid-fire viewing, device anomalies, IP mismatches
- **Flagged Activity Table:**
    - Columns: Voter, Flag Reason, Views, Earnings, Risk Score, Date
    - Flag reasons: "Multiple devices in 1 hour", "Geographic anomaly", "Token replica attempt"
    - Actions: Review, Block User, Clear Flag, Hold Payout
- **Fraud Review Modal:**
    - User profile: Trust score, account age, total views, earnings
    - Device fingerprints: List of devices with IP, browser, OS
    - View timeline: Chart showing view patterns (normal vs. suspicious)
    - Recent activity: Last 20 views with timestamps, IPs, devices
    - Actions: Impose payout hold (48hrs/7days/permanent), suspend account, clear user
- **Blocked Users:**
    - List of suspended/banned users with reason
    - Unblock/appeal review option

#### D. User Management

- **User Search & Filter:**
    - Search by name, email, ID
    - Filter by role (Politician, Voter, Admin), status (Active, Suspended, Banned)
    - Advanced filters: Registration date, earnings range, view count
- **User Profile View:**
    - User info: Name, email, phone, address, registration date
    - Role & permissions editor (Spatie permission integration)
    - Activity summary: Total views (voter), campaigns created (politician)
    - Financial summary: Total spent (politician), total earned (voter)
    - Actions: Edit Profile, Change Role, Suspend, Delete, Send Message
- **Bulk Actions:**
    - Select multiple users
    - Bulk operations: Send notification, suspend, export data

#### E. Financial Reporting

- **Revenue Dashboard:**
    - Revenue chart: Daily/weekly/monthly revenue (bar chart)
    - Revenue by source: Politician payments, ad views
    - Expense breakdown: Voter payouts, fees, ops costs
    - Net profit margin chart over time
- **Transaction Log:**
    - Searchable table: Date, Type (Charge, Payout, Refund), User, Amount, Status
    - Filter by transaction type, date range, status
    - Export to CSV/Excel
- **Payout Management:**
    - Pending payouts queue (awaiting processing)
    - Process payouts: Approve batch payouts, mark as sent
    - Failed payouts: Retry, mark as resolved, contact user
    - Monthly payout summary report

#### F. System Settings

- **Platform Configuration:**
    - Per-view pricing: Politician rate ($0.60), Voter rate ($0.25)
    - Referral commission rate (10%)
    - Minimum payout threshold ($20)
    - Token expiration time (24 hours)
    - Rate limits: Max views per voter per 24 hrs (10)
- **Notification Templates:**
    - Edit email/SMS/push templates for: New ad token, payout complete, campaign approved, fraud alert
    - Variables: {{voter_name}}, {{campaign_name}}, {{earn_amount}}
- **Fraud Settings:**
    - Adjust fraud detection thresholds (risk scores, device limits, etc.)
    - Enable/disable specific fraud rules
    - Payout hold durations

#### G. Analytics & Reports

- **Custom Reports Builder:**
    - Select metrics: Users, views, earnings, revenue, fraud events
    - Date range selector
    - Group by: Day, week, month, state, office type
    - Visualization type: Table, line chart, bar chart
    - Export: CSV, PDF, Excel
- **Pre-Built Reports:**
    - Daily platform summary (emailed to admin)
    - Weekly revenue report
    - Monthly user growth report
    - Quarterly fraud analysis report

#### H. UI Components Needed

- **Dashboard Grid:** KPI cards for top-level metrics
- **Activity Feed:** Real-time scrollable feed with filters
- **Tables:** Campaigns, flagged activity, users, transactions (all with search, sort, pagination)
- **Modals:** Campaign review, fraud investigation, user profile editor
- **Charts:** Line (revenue), bar (expenses), pie (revenue sources), heatmap (fraud activity)
- **Forms:** Settings editor, notification template editor
- **Badges:** Status (Approved/Rejected/Pending), Severity (High/Medium/Low)
- **Alerts:** High-priority fraud alerts, low balance warnings
- **Tabs:** Organize admin sections (Users, Fraud, Finance, Settings)

---

## Technical Implementation Requirements

### Frontend (Wix Dashboard Pages)

#### Technology Stack

- **Framework:** React (via Wix App Extension)
- **UI Library:** Wix Design System (@wix/design-system)
    - Use WixDesignSystemProvider wrapper
    - Components: Card, Table, Button, Modal, Input, Select, TextArea, Badge, Alert, Loader
- **State Management:** React hooks (useState, useEffect, useContext) or Redux for complex state
- **API Communication:** Axios or Fetch API for Laravel backend
- **Charts:** Use Recharts or Chart.js for data visualization
- **Authentication:** Wix OAuth tokens passed in iframe context

#### File Structure

```
src/wix/dashboard/
├── politician/
│   ├── PoliticianDashboard.jsx       # Main component
│   ├── CampaignManager.jsx            # Campaign CRUD
│   ├── Analytics.jsx                  # Charts & reports
│   ├── BillingSection.jsx             # Payments
│   └── components/
│       ├── CampaignCard.jsx
│       ├── VideoUploader.jsx
│       ├── TargetingSelector.jsx
│       └── StatsWidget.jsx
├── voter/
│   ├── VoterDashboard.jsx
│   ├── EarningsOverview.jsx
│   ├── WatchMessages.jsx
│   ├── ReferralCenter.jsx
│   ├── PayoutManager.jsx
│   └── components/
│       ├── EarningsChart.jsx
│       ├── VideoPlayer.jsx
│       ├── ReferralLink.jsx
│       └── PayoutForm.jsx
├── admin/
│   ├── AdminDashboard.jsx
│   ├── CampaignApproval.jsx
│   ├── FraudDetection.jsx
│   ├── UserManagement.jsx
│   ├── FinancialReports.jsx
│   └── components/
│       ├── ApprovalModal.jsx
│       ├── FraudReviewModal.jsx
│       ├── UserProfileModal.jsx
│       └── RevenueChart.jsx
└── shared/
    ├── Header.jsx                     # Dashboard header with navigation
    ├── Sidebar.jsx                    # Role-based menu
    ├── DataTable.jsx                  # Reusable table component
    ├── DateRangePicker.jsx
    └── ExportButton.jsx
```

#### API Integration

- **Authentication:** Extract Wix instance token from iframe context, send as Bearer token
- **Endpoints to call:**

    ```
    GET  /api/v1/politician/campaigns        → List campaigns
    POST /api/v1/politician/campaigns        → Create campaign
    GET  /api/v1/politician/campaigns/{id}   → Get campaign details
    PUT  /api/v1/politician/campaigns/{id}   → Update campaign
    DELETE /api/v1/politician/campaigns/{id} → Delete campaign

    GET  /api/v1/voter/earnings              → Get earnings summary
    GET  /api/v1/voter/messages              → Get available messages (via tokens)
    POST /api/v1/voter/watch                 → Record watch event
    GET  /api/v1/voter/referrals             → Get referral stats
    POST /api/v1/voter/payout/request        → Request payout

    GET  /api/v1/admin/campaigns/pending     → Pending approvals
    POST /api/v1/admin/campaigns/{id}/approve → Approve campaign
    POST /api/v1/admin/campaigns/{id}/reject  → Reject campaign
    GET  /api/v1/admin/fraud/flagged         → Flagged activity
    GET  /api/v1/admin/users                 → User management
    POST /api/v1/admin/users/{id}/suspend    → Suspend user
    ```

- **Error Handling:** Show toast notifications for API errors using Wix SDK
- **Loading States:** Display skeleton loaders during data fetch
- **Real-Time Updates:** Consider WebSockets or polling for live stats

#### Wix SDK Integration

```javascript
import { dashboard } from "@wix/dashboard";

// Show notifications
dashboard.showToast({
    message: "Campaign created successfully!",
    type: "success", // success, error, warning, info
});

// Navigate between dashboard pages
dashboard.navigate({ pageId: "politician-dashboard" });

// Show confirmation dialog
const confirmed = await dashboard.showConfirmationDialog({
    title: "Delete Campaign?",
    content: "This action cannot be undone.",
    primaryAction: { label: "Delete" },
    secondaryAction: { label: "Cancel" },
});
```

---

### Backend (Laravel API)

#### Controllers to Build

1. **PoliticianController:**
    - Campaign CRUD (index, store, show, update, destroy)
    - Analytics (stats, charts data)
    - Billing (payment methods, history)

2. **VoterController:**
    - Earnings summary
    - Watch history
    - Referral management
    - Payout requests

3. **AdminController:**
    - Campaign approval workflow
    - Fraud detection queries
    - User management
    - Financial reports

#### Models & Relationships

- **Campaign:** belongsTo(User), hasMany(View), hasMany(Notification)
- **View:** belongsTo(Voter), belongsTo(Campaign), hasOne(Payout)
- **Referral:** belongsTo(Referrer), belongsTo(Referred)
- **Payout:** belongsTo(Voter), hasMany(View)
- **FraudAlert:** belongsTo(Voter), belongsTo(View)

#### Middleware

- **wix.verify:** Verify Wix instance token for all dashboard routes
- **role:** Check user role (politician, voter, admin) using Spatie Permission
- **fraud.check:** Real-time fraud detection on watch events

#### Example Controller Method

```php
// PoliticianController.php
public function index(Request $request)
{
    $campaigns = Campaign::where('user_id', $request->user()->id)
        ->withCount('views')
        ->withSum('views as total_spent', 'cost')
        ->orderBy('created_at', 'desc')
        ->paginate(20);

    return response()->json([
        'campaigns' => $campaigns,
        'stats' => [
            'total_campaigns' => Campaign::where('user_id', $request->user()->id)->count(),
            'active_campaigns' => Campaign::where('user_id', $request->user()->id)->where('status', 'active')->count(),
            'total_views' => View::whereHas('campaign', function($q) use ($request) {
                $q->where('user_id', $request->user()->id);
            })->count(),
            'total_spent' => View::whereHas('campaign', function($q) use ($request) {
                $q->where('user_id', $request->user()->id);
            })->sum('cost')
        ]
    ]);
}
```

---

## Design Guidelines

### Visual Design (Wix Design System)

- **Color Palette:**
    - Primary: Wix Blue (#116DFF)
    - Success: Green (#3ECF8E)
    - Warning: Orange (#FF9900)
    - Danger: Red (#FF4040)
    - Text: Dark Gray (#222222)
    - Background: White (#FFFFFF) / Light Gray (#F9F9F9)

- **Typography:**
    - Headers: Wix Madefor Display (22px-32px, bold)
    - Body: Wix Madefor Text (14px-16px, regular)
    - Labels: 12px, semibold, uppercase

- **Spacing:** Use 8px grid system (8px, 16px, 24px, 32px, 48px)

### Layout Patterns

- **Dashboard Structure:**
    - Top navigation bar with app logo, page title, user profile dropdown
    - Left sidebar with role-specific menu items
    - Main content area with grid layout
    - Footer with support links

- **Responsive Design:**
    - Desktop-first (dashboard pages are primarily desktop)
    - Mobile: Stack cards vertically, collapse sidebar to hamburger menu
    - Tablet: 2-column grid for cards

### Accessibility

- **WCAG 2.1 AA Compliance:**
    - Color contrast ratio ≥4.5:1 for text
    - Keyboard navigation support (Tab, Enter, Escape)
    - ARIA labels for all interactive elements
    - Focus indicators on buttons/links

- **Screen Reader Support:**
    - Semantic HTML (header, nav, main, footer)
    - Alt text for images
    - Descriptive button labels ("Delete Campaign X" not just "Delete")

---

## Security Requirements

### Authentication & Authorization

- **Wix Instance Token:**
    - Extract from iframe context: `const instance = Wix.Utils.getInstance()`
    - Pass in Authorization header: `Bearer {instance}`
    - Laravel middleware verifies token via Wix API

- **Role-Based Access:**
    - Politician: Only access own campaigns, no voter data
    - Voter: Only access own earnings, watch history, referrals
    - Admin: Full platform access with audit logging

### Data Privacy

- **GDPR Compliance:**
    - User data export tool (admin dashboard)
    - Account deletion with data purge
    - Cookie consent banner (if cookies used)

- **PII Protection:**
    - Anonymize voter data in referral lists (User #1234)
    - Encrypt sensitive fields (SSN, bank accounts) in database
    - No export of PII without admin approval

### API Security

- **Rate Limiting:**
    - 100 requests per minute per user
    - Stricter limits on fraud-prone endpoints (watch, payout)

- **Input Validation:**
    - Sanitize all user inputs (campaign name, video titles)
    - Validate file uploads (video format, size, malware scan)
    - Prevent SQL injection with Eloquent ORM

- **HTTPS Only:**
    - Force SSL on production (Railway handles this)
    - Set Secure flag on cookies

---

## Testing & Quality Assurance

### Unit Tests (Pest)

- **Controller Tests:**
    - Test all CRUD operations
    - Test authorization (users can only access own data)
    - Test edge cases (empty campaigns, invalid IDs)

- **Model Tests:**
    - Test relationships (Campaign hasMany Views)
    - Test scopes and accessors

### Integration Tests

- **Dashboard Page Tests:**
    - Test dashboard loads with correct data
    - Test campaign creation flow end-to-end
    - Test fraud detection triggers correctly

### Manual Testing Checklist

- [ ] Politician can create, edit, delete campaigns
- [ ] Video upload works (small/large files, error handling)
- [ ] Voter sees earnings update after watching message
- [ ] Referral link generates correctly and tracks referrals
- [ ] Admin can approve/reject campaigns with reasons
- [ ] Fraud alerts appear for suspicious activity
- [ ] Payout requests process correctly
- [ ] All charts render with correct data
- [ ] Mobile responsive layout works on iPhone/Android
- [ ] Accessibility: Keyboard navigation, screen reader support

---

## Deliverables

### Code Deliverables

1. **Frontend Components:**
    - PoliticianDashboard.jsx (with all sub-components)
    - VoterDashboard.jsx (with all sub-components)
    - AdminDashboard.jsx (with all sub-components)
    - Shared components (DataTable, charts, etc.)

2. **Backend API:**
    - Controllers: PoliticianController, VoterController, AdminController
    - Routes: /api/v1/politician/_, /api/v1/voter/_, /api/v1/admin/\*
    - Middleware: wix.verify, role checks, fraud checks

3. **Database:**
    - Seeders for demo data (sample campaigns, voters, views)
    - Migrations for any new tables (fraud_alerts, payouts, etc.)

4. **Testing:**
    - Pest tests for all API endpoints
    - Manual testing checklist completed

### Documentation Deliverables

1. **README.md:**
    - Dashboard setup instructions
    - Component usage examples
    - API endpoint documentation

2. **USER_GUIDE.md:**
    - How to use Politician Dashboard
    - How to use Voter Dashboard
    - How to use Admin Dashboard

3. **API_DOCUMENTATION.md:**
    - List all endpoints with request/response examples
    - Authentication requirements
    - Error codes and handling

---

## Success Criteria

### Functional Requirements

- ✅ All three dashboards load without errors
- ✅ Users can perform all role-specific actions (CRUD campaigns, watch videos, approve campaigns)
- ✅ Real-time data updates work (earnings increment, view counters)
- ✅ Charts display accurate data from backend
- ✅ File uploads work (video, images)
- ✅ Notifications work (toast messages on success/error)

### Non-Functional Requirements

- ⚡ Page load time <2 seconds
- 📱 Mobile responsive (graceful degradation)
- 🔒 Security: No unauthorized access, no XSS/CSRF vulnerabilities
- ♿ Accessibility: WCAG 2.1 AA compliant
- 🧪 Test coverage: >80% for backend, >60% for frontend

### User Experience

- 😊 Intuitive navigation (users can find features without help)
- 🎨 Consistent visual design (matches Wix Design System)
- 💬 Clear feedback (success messages, error explanations)
- 📊 Useful insights (actionable analytics, not just raw numbers)

---

## Additional Context

### Business Rules

- **Campaign Approval:** All campaigns must be admin-approved before going live (prevent spam/offensive content)
- **Watch Requirement:** Voters must watch 100% of video to earn (tracked via player events)
- **Payout Threshold:** Minimum $20 to request payout (prevents small transactions)
- **Token Security:** One-time use tokens expire after 24 hours
- **Fraud Prevention:** Max 10 views per voter per 24 hours, device fingerprinting required
- **Referral Commission:** 10% of referred voter's earnings (paid weekly)

### Platform Economics

```
Per View Breakdown:
- Politician pays: $0.60
- Voter earns: $0.25 (41.7%)
- Referrer earns: $0.025 (4.2%)
- Platform gross: $0.325 (54.1%)
  - Payment processing: ~$0.02 (3.3%)
  - Infrastructure: ~$0.03-$0.12 (5-20%)
  - Net profit: $0.18-$0.30 (30-50% margin)
```

### Wix Marketplace Considerations

- **App Listing:** Dashboard pages will be showcased in screenshots (use demo data)
- **User Onboarding:** First-time users see a welcome wizard (not in this scope, but design for it)
- **Support:** In-dashboard help tooltips ("?" icons with explanations)
- **Feedback:** "Send Feedback" button in footer to collect user suggestions

---

## Questions for Clarification (Answer Before Building)

1. **Video Hosting:** Where are videos stored? (AWS S3, Wix Media, Cloudflare Stream?)
2. **Payment Provider:** Stripe for politician billing confirmed? PayPal for voter payouts?
3. **Notification Delivery:** Using Wix Members API for emails/push? External SMS provider (Twilio)?
4. **Real-Time Updates:** Use WebSockets (Pusher/Laravel Echo) or polling for live stats?
5. **Admin Approval:** Auto-approve after X successful campaigns or always manual?
6. **Fraud Scoring:** Use third-party service (Sift, Forter) or custom algorithm?
7. **Analytics:** Integrate Google Analytics/Mixpanel for user behavior tracking?
8. **Localization:** Support multiple languages (i18n) or English only for MVP?

---

## Timeline Estimate

| Phase                       | Duration       | Description                                       |
| --------------------------- | -------------- | ------------------------------------------------- |
| Setup & Backend API         | 2-3 days       | Controllers, routes, migrations, seeders          |
| Politician Dashboard (80%)  | 3-4 days       | Campaign manager, analytics, billing              |
| Voter Dashboard (80%)       | 3-4 days       | Earnings, watch interface, referrals, payouts     |
| Admin Dashboard (80%)       | 4-5 days       | Approvals, fraud detection, user mgmt, financials |
| Charts & Data Visualization | 2 days         | Integrate Recharts, build custom chart components |
| Responsive Design & Polish  | 2 days         | Mobile layout, accessibility, design refinements  |
| Testing & Bug Fixes         | 2-3 days       | Unit tests, integration tests, manual QA          |
| Documentation               | 1 day          | README, user guide, API docs                      |
| **Total**                   | **19-26 days** | **~4-5 weeks** for one developer                  |

---

## References & Resources

### Wix Documentation

- [Wix Dashboard SDK](https://dev.wix.com/docs/sdk/wix-dashboard)
- [Wix Design System](https://www.wixdesignsystem.com/)
- [Creating Dashboard Pages](https://dev.wix.com/docs/develop-websites/articles/integrations/dashboard-pages)

### Laravel Documentation

- [Laravel 11 Docs](https://laravel.com/docs/11.x)
- [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission/v6)

### UI/UX Inspiration

- [Stripe Dashboard](https://dashboard.stripe.com/)
- [YouTube Studio](https://studio.youtube.com/)
- [Facebook Ads Manager](https://business.facebook.com/adsmanager)

---

## Final Notes

This is a **comprehensive end-to-end specification** for building production-ready dashboard pages. The dashboards should feel like native Wix components while providing all the functionality needed for U9itus's unique business model.

**Focus on:**

1. **Security first** (token-based delivery, fraud detection, role access)
2. **User experience** (intuitive navigation, clear feedback, fast performance)
3. **Data accuracy** (real-time updates, correct calculations, audit trails)
4. **Visual polish** (consistent design, smooth animations, responsive layout)

Build these dashboards to **delight users** while protecting platform integrity. Let's make political engagement profitable, secure, and user-friendly! 🚀

---

**Need more details on any section? Ask before starting! 🙋‍♂️**
