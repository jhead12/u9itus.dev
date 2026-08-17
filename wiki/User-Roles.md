# User Roles

U9itus has three distinct user roles, each with their own registration flow, dashboard, and permissions.

## Politician

**Registration:** `/register/politician`  
**Dashboard:** `/politician/dashboard`  
**Middleware:** `auth`, `verified`, `role:politician`

### What politicians can do

- Create and manage video ad campaigns
- Set campaign targeting (governance level, office, geography)
- Upload or link campaign videos (YouTube, Vimeo, direct file, S3)
- Submit campaigns for admin review
- Add credits via Stripe to fund campaigns
- View analytics (views, impressions, spend, earnings)
- View billing history and invoices
- Manage their public profile and page settings
- Participate in live feeds (Phase 12)

### Politician profile features (Phase 13 & 16)

- Public-facing campaign page with custom theme, layout, and hero banner
- Policy positions / platform planks
- Profile verification via government email (`.gov`/`.mil`)
- Transparency opt-ins: Ballotpedia, OpenSecrets, Vote Smart, FEC

### Credit billing flow

1. Politician adds credits via Stripe PaymentIntent
2. Stripe fee is grossed-up (politician is charged `amount / 0.975`)
3. Platform nets the full credit amount
4. Credits are deducted **$1.00** for each completed voter view

---

## Voter

**Registration:** `/register/voter?ref=<optional_referral_code>`  
**Dashboard:** `/voter/dashboard`  
**Middleware:** `auth`, `verified`, `role:voter`

### What voters can do

- Receive ad tokens via email/SMS/push notification
- Watch assigned political video ads via secure one-time token
- Earn **$0.25** per completed view
- Request cash payouts (minimum balance: `$5.00`)
- Refer other voters and earn **10%** of their payout per view (recurring)
- Refer politicians and earn procurement commission on their first credit purchase
- Browse the public politician/campaign directory without logging in
- Submit post-view survey responses
- View referral earnings and wallet balance

### Voter trust scoring

Each voter has a `trust_score` that can be reduced by fraud signals. Voters with low trust scores may have payouts held or be flagged for manual review.

---

## Admin

**Login:** `/admin/login`  
**Dashboard:** `/admin/dashboard`  
**Middleware:** `auth`, `verified`, `role:admin`

### What admins can do

- Review and approve / reject / stop / reactivate campaigns
- Edit any campaign (with immutable audit log entry)
- View the immutable campaign audit log (field-level diffs)
- Manage all users (voter and politician accounts)
- View and act on fraud-flagged voters
- Run batch voter payout processing
- View platform-wide analytics
- Manage dynamic platform settings (rates, limits, feature flags)
- View California import run logs and health status

### Admin account management

```bash
# Create admin account
php artisan admin:create --email=admin@u9itus.com --name="Admin User"

# Reset admin password (CLI)
php artisan admin:reset-password --email=admin@u9itus.com

# Reset via web (link on /admin/login)
# → /forgot-password
```

---

← [Business Model](Business-Model.md) | [Routes and API →](Routes-and-API.md)
