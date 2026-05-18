# Routes and API

## Web Routes

### Authentication

| Method | URL | Purpose |
|--------|-----|---------|
| `GET` | `/login` | Shared login page (Politician / Voter tabs) |
| `POST` | `/login` | Authenticate and redirect by role |
| `GET` | `/admin/login` | Dedicated admin portal |
| `POST` | `/admin/login` | Admin-only authentication (role-enforced) |
| `GET` | `/register` | Role-chooser landing page |
| `GET` | `/register/politician` | Politician registration form |
| `POST` | `/register/politician` | Create politician account + profile |
| `GET` | `/register/voter` | Voter registration form |
| `POST` | `/register/voter` | Create voter account + profile |
| `POST` | `/logout` | Sign out |

### Politician Dashboard (`/politician/*`)

Requires `auth`, `verified`, and `role:politician` middleware.

| Method | URL | Purpose |
|--------|-----|---------|
| `GET` | `/politician/dashboard` | Overview stats |
| `GET` | `/politician/campaigns` | Campaign list |
| `GET` | `/politician/campaigns/create` | New campaign form |
| `POST` | `/politician/campaigns` | Store new campaign |
| `GET` | `/politician/campaigns/{id}` | Campaign detail |
| `GET` | `/politician/campaigns/{id}/edit` | Edit campaign form |
| `PUT` | `/politician/campaigns/{id}` | Update campaign |
| `POST` | `/politician/campaigns/{id}/submit-review` | Submit draft for admin review |
| `GET` | `/politician/analytics` | Platform-wide analytics overview |
| `GET` | `/politician/billing` | Credit balance + Stripe transaction history |
| `POST` | `/politician/billing/add-funds` | Create Stripe PaymentIntent to add credits |
| `GET` | `/politician/profile` | View/edit profile |
| `PUT` | `/politician/profile` | Update political profile |

### Voter Dashboard (`/voter/*`)

Requires `auth`, `verified`, and `role:voter` middleware.

| Method | URL | Purpose |
|--------|-----|---------|
| `GET` | `/voter/dashboard` | Earnings overview |
| `GET` | `/voter/watch/{token}` | Load ad via secure token |
| `POST` | `/voter/watch/{token}/start` | Start secure watch session |
| `POST` | `/voter/session/{uuid}/progress` | Heartbeat progress tracking |
| `POST` | `/voter/session/{uuid}/complete` | Mark session complete, trigger payout |
| `POST` | `/voter/session/{uuid}/survey` | Submit post-view engagement survey |
| `GET` | `/voter/earnings` | Earnings summary |
| `POST` | `/voter/earnings/request-payout` | Request cash payout |
| `GET` | `/voter/referrals` | Referral overview |
| `GET` | `/voter/profile` | Profile page |

### Admin Dashboard (`/admin/*`)

Requires `auth`, `verified`, and `role:admin` middleware.

| Method | URL | Purpose |
|--------|-----|---------|
| `GET` | `/admin/dashboard` | Admin overview |
| `GET` | `/admin/campaigns/pending` | Campaigns awaiting approval |
| `GET` | `/admin/campaigns/{id}/edit` | Edit any campaign |
| `PUT` | `/admin/campaigns/{id}` | Update campaign fields + write audit entry |
| `POST` | `/admin/campaigns/{id}/approve` | Approve campaign |
| `POST` | `/admin/campaigns/{id}/reject` | Reject campaign with reason |
| `POST` | `/admin/campaigns/{id}/stop` | Force-pause a live campaign |
| `POST` | `/admin/campaigns/{id}/reactivate` | Reactivate a stopped campaign |
| `GET` | `/admin/campaigns/{id}/audit` | Paginated immutable audit log |
| `GET` | `/admin/users` | User list |
| `GET` | `/admin/fraud` | Fraud dashboard |
| `GET` | `/admin/payouts` | Payout overview |
| `GET` | `/admin/payouts/pending` | Pending payout sessions |
| `POST` | `/admin/payouts/batch-process` | Run batch payout processing |
| `GET` | `/admin/imports` | California import run logs |
| `GET` | `/admin/analytics` | Platform analytics |
| `GET` | `/admin/settings` | System settings |

---

## REST API (`/api/v1/*`)

All API routes are protected by `auth:sanctum` middleware.

### Politician API

| Method | URL | Purpose |
|--------|-----|---------|
| `POST` | `/api/v1/politicians` | Create politician profile |
| `GET` | `/api/v1/politicians/{id}` | Get politician |
| `PUT` | `/api/v1/politicians/{id}` | Update profile |
| `POST` | `/api/v1/politicians/{id}/campaigns` | Create campaign |
| `GET` | `/api/v1/politicians/{id}/campaigns` | List campaigns |
| `GET` | `/api/v1/politicians/{id}/billing/balance` | Credit balance |
| `POST` | `/api/v1/politicians/{id}/billing/purchase` | Purchase credits |

### Voter API

| Method | URL | Purpose |
|--------|-----|---------|
| `POST` | `/api/v1/voters` | Register voter (with optional referral code) |
| `GET` | `/api/v1/voters/{id}` | Get voter profile |
| `GET` | `/api/v1/voters/{id}/earnings` | Earnings summary |
| `GET` | `/api/v1/voters/{id}/referrals` | Referral earnings |
| `GET` | `/api/v1/voters/{id}/campaigns` | Available campaigns |
| `POST` | `/api/v1/voters/{id}/campaigns/{cid}/watch` | Assign watch session |
| `POST` | `/api/v1/sessions/{session}/progress` | Progress heartbeat |
| `POST` | `/api/v1/sessions/{session}/complete` | Mark view completed |

### Admin API

| Method | URL | Purpose |
|--------|-----|---------|
| `GET` | `/api/v1/admin/analytics` | Platform-wide analytics |
| `GET` | `/api/v1/admin/campaigns/pending` | Pending approval queue |
| `POST` | `/api/v1/admin/campaigns/{id}/approve` | Approve a campaign |
| `POST` | `/api/v1/admin/campaigns/{id}/reject` | Reject a campaign |
| `POST` | `/api/v1/admin/payouts/process` | Run batch payout processing |
| `GET` | `/api/v1/admin/voters/flagged` | List fraud-flagged voters |
| `POST` | `/api/v1/admin/voters/{id}/clear-flag` | Clear fraud flag |

### Stripe Webhook

| Method | URL | Purpose |
|--------|-----|---------|
| `POST` | `/api/stripe/webhooks` | Handle `payment_intent.succeeded` / `payment_intent.payment_failed` |

---

← [User Roles](User-Roles.md) | [Database Schema →](Database-Schema.md)
