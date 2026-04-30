# Financial Logic Audit

Date: 2026-04-29
Scope: Platform billing, payouts, refunds, commissions, webhook handling, and financial authorization boundaries.

## Executive Summary

This audit found multiple high-severity risks in webhook trust, authorization boundaries, payout idempotency, and financial state consistency.

Priority outcomes:
- Enforce fail-closed webhook verification for Stripe.
- Harden admin and ownership authorization for financial endpoints.
- Introduce stronger payout idempotency and deterministic payout keys.
- Standardize money arithmetic (prefer integer cents in core calculations).

## Findings (Ordered by Severity)

### 1. Critical: Stripe webhook verification can be bypassed if secret is missing

Evidence:
- app/Services/StripePaymentService.php:130
- app/Services/StripePaymentService.php:133
- app/Http/Controllers/Api/StripeWebhookController.php:20

Details:
- Stripe webhook payloads are accepted as raw JSON when webhook secret is not configured.
- Downstream handlers can finalize payment intents and credit accounts using unverified data.

Risk:
- Forged webhook payloads could trigger unauthorized crediting.

Recommendation:
- Fail closed for webhook parsing when payments are enabled and webhook secret is missing.
- Add startup/runtime health check for missing webhook secrets in non-local environments.

### 2. Critical: Financial authorization boundaries appear too broad

Evidence:
- routes/api.php:114
- routes/api.php:136
- app/Http/Requests/PurchaseCreditsRequest.php:9
- app/Http/Controllers/Api/BillingController.php:33

Details:
- Admin routes are grouped under auth only in the API routes segment examined.
- Purchase request authorize method returns true.
- Billing purchase relies on route model binding but does not enforce politician ownership at controller layer.

Risk:
- Potential unauthorized access to admin financial operations and cross-tenant billing actions.

Recommendation:
- Add explicit admin middleware/policies on admin routes.
- Add ownership policy checks on politician billing actions.

### 3. High: Payout external calls and DB status transitions are not fully crash-safe

Evidence:
- app/Services/PoliticalPaymentService.php:217
- app/Services/PoliticalPaymentService.php:240
- app/Services/PoliticalPaymentService.php:250
- app/Services/PoliticalPaymentService.php:358

Details:
- External payout requests can succeed before local state transitions are persisted.
- A failure between external success and DB write can leave inconsistent state.

Risk:
- Duplicate payouts on retries, stale statuses, reconciliation drift.

Recommendation:
- Introduce a payout-attempt ledger with explicit submission states.
- Persist attempt and deterministic key before external call.
- Mark submitted state atomically immediately after external response.

### 4. High: PayPal payout idempotency key strategy is weak across retries

Evidence:
- app/Services/PoliticalPaymentService.php:194
- app/Services/PoliticalPaymentService.php:244
- app/Services/PayPalPayoutService.php:110

Details:
- Batch IDs are timestamp-based, so retries produce new IDs.
- No durable pre-submit uniqueness check tied to session-set hash.

Risk:
- Duplicate payout submission if a retry occurs after uncertain failure.

Recommendation:
- Build deterministic payout key from voter plus ordered eligible session IDs hash.
- Enforce uniqueness at DB layer for active payout attempts.

### 5. Medium: Force payout PayPal readiness check differs from main payout flow

Evidence:
- app/Services/PoliticalPaymentService.php:201
- app/Services/PoliticalPaymentService.php:452

Details:
- Main flow checks PayPal is configured.
- Force-payout flow checks only service presence.

Risk:
- Runtime failures and inconsistent behavior under operator actions.

Recommendation:
- Align force-payout checks with isConfigured logic used in main path.

### 6. Medium: Purchase validation is minimal and payment_method_id is dropped

Evidence:
- app/Http/Requests/PurchaseCreditsRequest.php:17
- app/Http/Requests/PurchaseCreditsRequest.php:18
- app/Http/Controllers/Api/BillingController.php:33
- app/Services/CampaignBillingService.php:258

Details:
- Validation only enforces required numeric min 1.
- payment_method_id is accepted but not passed from controller to billing service.

Risk:
- Potential UX and processing inconsistencies for intended payment method use.

Recommendation:
- Pass payment_method_id through purchase endpoint into createPurchaseIntent options.
- Add upper-bound and precision constraints for amount.

### 7. Medium: Float-based arithmetic appears in core money flows

Evidence:
- app/Services/CampaignBillingService.php:230
- app/Services/CampaignBillingService.php:231
- app/Services/PoliticalViewService.php:106
- app/Services/PoliticalViewService.php:111
- app/Services/PoliticalPaymentService.php:174

Details:
- Calculations use float casting and round operations in several core paths.

Risk:
- Long-term rounding drift and edge-case inconsistencies at scale.

Recommendation:
- Standardize internal arithmetic on integer cents for calculations and persistence transitions.
- Add edge-case tests for low-value and high-volume scenarios.

### 8. Medium: Schema-level uniqueness protections are limited for idempotency-sensitive paths

Evidence:
- database/migrations/2026_02_17_100000_create_campaign_transactions_table.php:24
- database/migrations/2026_02_17_100001_create_politician_credits_table.php:24

Details:
- No explicit uniqueness on stripe_payment_intent_id in campaign_transactions migration.
- No composite uniqueness constraint to enforce one logical credit entry per related transaction and type.

Risk:
- Increased dependence on application logic alone for duplicate prevention.

Recommendation:
- Add carefully designed unique indexes for idempotency-critical fields.
- Validate migration compatibility with existing production data before rollout.

### 9. Coverage Gap: Limited direct tests for payout orchestration failure modes

Evidence:
- tests/Unit/Services/CampaignBillingServiceTest.php:150
- tests/Feature/Billing/WebhookTest.php:66
- tests/Feature/Payout/AdminSkippedPayoutsTest.php:44

Details:
- Good coverage exists for parts of billing and webhook paths.
- Fewer direct tests found for end-to-end batch payout idempotency and crash-boundary behavior.

Risk:
- Regressions in complex payout logic may not be caught early.

Recommendation:
- Add tests for duplicate submission prevention, uncertain network outcomes, and reconciliation correctness.

## Refactor Plan

### Phase 1 (Immediate)
- Enforce strict Stripe webhook verification fail-closed behavior.
- Add role middleware for admin finance routes.
- Add ownership policy checks for politician billing endpoints.

### Phase 2 (Short-term)
- Implement payout-attempt ledger with deterministic idempotency keys.
- Align force payout processor readiness checks with primary flow.
- Pass payment_method_id through API purchase flow.

### Phase 3 (Medium-term)
- Migrate core calculations toward integer cents.
- Add DB uniqueness constraints supporting idempotency.
- Expand payout/reconciliation test coverage.

## Notes and Assumptions

- This is a source audit based on code inspection.
- No full runtime penetration or chaos testing was executed as part of this write-up.
- Severity may be reduced if unobserved global middleware/policies already enforce stricter access controls in deployment.
