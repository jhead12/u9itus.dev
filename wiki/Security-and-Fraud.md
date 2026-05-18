# Security and Fraud Prevention

## Authentication and Authorization

| Mechanism | Details |
|-----------|---------|
| Session auth | Laravel Sanctum + session-based login |
| Role-based access | Spatie Laravel Permission (`admin`, `politician`, `voter`) |
| Admin isolation | Admin portal at `/admin/login` enforces `role:admin` after authentication |
| API auth | Laravel Sanctum tokens (`auth:sanctum` middleware) |
| CSRF protection | Enabled on all web forms |
| SQL injection | Prevented by Eloquent ORM (parameterized queries) |
| Email verification | Signed URL links required before accessing the platform |

## Token-Based Ad Delivery

Instead of letting voters browse and click ads freely, U9itus uses **push notification-based delivery** with one-time tokens:

1. Admin or system assigns a campaign to a voter
2. Platform generates a **SHA-256 one-time use token** tied to that voter + campaign
3. Token is delivered via email / SMS / push notification
4. Voter clicks the link (`/voter/watch/{token}`) to start the ad
5. Token is consumed on use — cannot be replayed
6. Token expires after **24 hours**

This prevents:
- Ad click farming (users cannot browse and click repeatedly)
- Token replay attacks
- Unauthorized access to watch sessions

## Fraud Detection Signals

`FraudPreventionService` aggregates multiple signals into a composite fraud score:

| Signal | Description |
|--------|-------------|
| Rate limiting | Maximum 10 ads per 24 hours per voter |
| Device fingerprinting | Server-side composite fingerprint; flags if multiple accounts share a device |
| Bot user-agent detection | Detects known bot UA keywords and markers |
| IP anomaly detection | Detects rapid account creation / view attempts from the same IP |
| VPN / proxy detection | CIDR-based blocklist for known VPN/datacenter IP ranges |
| Tor exit-node detection | Blocks known Tor exit-node prefixes |
| Rapid-fire view detection | Flags unusually fast consecutive view completions |
| Payout hold period | 48-hour verification window before payouts are released |
| Trust score | Per-voter score reduced by fraud signals; low scores trigger holds |

All fraud events are persisted to the `fraud_signals` table for admin review and audit.

## Fraud Actions

| Action | Trigger |
|--------|---------|
| `flagVoter` | Composite score exceeds threshold → voter flagged for review |
| `holdPayouts` | Auto-applied when voter is flagged |
| `releasePayouts` | Admin manually clears hold after review |
| `clearFlag` | Admin clears fraud flag via `/api/v1/admin/voters/{id}/clear-flag` |
| `updateTrustScore` | Score adjusted after fraud signal resolution |
| `FraudFlagRaised` broadcast | Real-time alert sent to the `private-admin.monitor` channel |

## Payout Security

- Minimum payout threshold: **$5.00** wallet balance
- 48-hour hold on new payouts (fraud verification window)
- Batch payout processing (admin-initiated or scheduled)
- Per-view earnings only credited after 100% watch completion

## Anti-Abuse Measures

| Measure | Details |
|---------|---------|
| 100% watch requirement | Voter must watch the full video; no partial-view payouts |
| Anti-skip enforcement | JavaScript prevents seeking forward; server validates watch time |
| Progress heartbeat | Client sends progress every 5 seconds; server validates continuity |
| Token single-use | Each ad token is consumed on first use |
| Daily view cap | Max `fraud_daily_view_limit` (default: 50) views per day |
| Referral guard | Procurement commission fires only once per recruited politician |

## ipinfo.io Enrichment (Optional)

`IpReputationService` can optionally enrich IP checks with [ipinfo.io](https://ipinfo.io) data for enhanced VPN/proxy detection. Configure via the `IPINFO_TOKEN` environment variable.

---

← [Database Schema](Database-Schema.md) | [Development →](Development.md)
