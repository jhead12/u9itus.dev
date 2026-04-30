# Financial Logic Remediation Plan

Date: 2026-04-29
Linked Audit: [FINANCIAL_LOGIC_AUDIT_2026-04-29.md](./FINANCIAL_LOGIC_AUDIT_2026-04-29.md)

---

## Overview

This plan addresses all nine findings from the financial logic audit. Work is organized into five phases ordered by severity and dependency. Each item includes the affected files, exact changes required, and acceptance criteria.

---

## Phase 1 — Critical Security (Target: 1–2 days)

### 1.1 Enforce Stripe Webhook Signature Verification (Finding #1)

**Files:**
- `app/Services/StripePaymentService.php`

**Problem:** `parseWebhook()` falls back to raw `json_decode()` when `webhook_secret` is not set, allowing forged payloads.

**Changes:**

```php
// BEFORE (around L127–133)
public function parseWebhook(string $payload, string $signature): array
{
    if (empty($this->webhookSecret)) {
        return json_decode($payload, true);
    }
    // ...
}
```

```php
// AFTER
public function parseWebhook(string $payload, string $signature): array
{
    if (empty($this->webhookSecret)) {
        // Fail closed — never accept unverified payloads outside local dev
        if (app()->environment('production', 'staging')) {
            throw new \RuntimeException(
                'Stripe webhook secret is not configured. Cannot process webhook.'
            );
        }
        // Local/test only: log a loud warning and return decoded payload
        \Log::warning('STRIPE WEBHOOK: No secret configured. Signature check skipped (dev/test only).');
        return json_decode($payload, true) ?? [];
    }

    $event = \Stripe\Webhook::constructEvent($payload, $signature, $this->webhookSecret);
    return $event->toArray();
}
```

**Add startup health check** in `app/Providers/AppServiceProvider.php` (or a dedicated `HealthCheckServiceProvider`):

```php
// In boot(), for non-local environments
if (!app()->environment('local', 'testing')) {
    if (empty(config('services.stripe.webhook_secret'))) {
        \Log::critical('Missing STRIPE_WEBHOOK_SECRET — financial webhook verification is disabled.');
    }
}
```

**Acceptance Criteria:**
- `parseWebhook()` throws on production/staging when secret is absent.
- Unit test: forged payload with no secret returns 500/400, not a credit event.
- Unit test: valid HMAC passes verification.

---

### 1.2 Add Admin Role Gate to Financial Routes (Finding #2)

**Files:**
- `routes/api.php`
- `app/Http/Requests/PurchaseCreditsRequest.php`
- `app/Http/Controllers/Api/BillingController.php`

**Problem:** Admin route groups use only `auth:sanctum`. `PurchaseCreditsRequest::authorize()` returns `true` unconditionally. Billing controller does not verify politician ownership.

**Changes:**

`routes/api.php` — add `role:admin` (Spatie permission) to admin groups:

```php
// BEFORE
Route::prefix('admin')->middleware(['auth:sanctum'])->group(function () {

// AFTER
Route::prefix('admin')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
```

`app/Http/Requests/PurchaseCreditsRequest.php`:

```php
// BEFORE
public function authorize(): bool
{
    return true;
}

// AFTER
public function authorize(): bool
{
    /** @var \App\Models\User $user */
    $user = $this->user();
    $politician = $this->route('politician'); // adjust to your route parameter name

    // User must be authenticated and must own the politician record
    return $user !== null
        && $politician !== null
        && (int) $politician->user_id === (int) $user->id;
}
```

`app/Http/Controllers/Api/BillingController.php` — add explicit ownership check before processing:

```php
public function purchase(PurchaseCreditsRequest $request, Politician $politician): JsonResponse
{
    // Ownership already validated in FormRequest, but guard here too for defence-in-depth
    if ((int) $politician->user_id !== (int) $request->user()->id) {
        abort(403, 'You do not own this politician record.');
    }
    // ... existing logic
}
```

**Acceptance Criteria:**
- Non-admin authenticated user receives 403 on admin financial endpoints.
- Authenticated user cannot purchase credits for another user's politician record.
- Feature test covering both cases.

---

## Phase 2 — Payout Atomicity & Idempotency (Target: 3–5 days)

### 2.1 Introduce Payout Attempt Ledger (Finding #3)

**New migration:** `database/migrations/YYYY_MM_DD_create_payout_attempts_table.php`

```php
Schema::create('payout_attempts', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('voter_id');
    $table->string('idempotency_key')->unique();           // deterministic hash
    $table->string('processor')->default('stripe');        // stripe|paypal|cashapp
    $table->string('status')->default('pending');          // pending|submitted|paid|failed
    $table->decimal('amount', 12, 2);
    $table->string('processor_reference')->nullable();     // Stripe transfer ID / PayPal batch ID
    $table->json('session_ids');                           // which sessions this covers
    $table->timestamps();

    $table->index(['voter_id', 'status']);
});
```

**`app/Services/PoliticalPaymentService.php`** — restructure payout dispatch to follow this order:

```
1. Compute eligible sessions + total amount
2. Generate deterministic idempotency_key (see 2.2)
3. INSERT payout_attempt (status=pending) — fail here if duplicate key exists
4. Call external processor
5. UPDATE payout_attempt (status=submitted, processor_reference=xxx)
6. UPDATE view_sessions payment_status = Paid/Processing
```

If step 4 throws, the attempt remains `pending` and a retry will reuse the same key (idempotent re-submit to processor).

**Acceptance Criteria:**
- Killing the process between steps 4 and 6 leaves sessions in `pending`, attempt in `submitted`, no duplicate payment on retry.
- Duplicate call with same session set does not create a second payout_attempt row.

---

### 2.2 Deterministic Payout Idempotency Key (Finding #4)

**Files:**
- `app/Services/PoliticalPaymentService.php`

**Replace timestamp-based batch ID:**

```php
// BEFORE (around L194)
$batchId = 'payout_' . time() . '_' . $voterId;

// AFTER
private function buildPayoutKey(int $voterId, array $sessionIds): string
{
    sort($sessionIds);
    return hash('sha256', 'payout:' . $voterId . ':' . implode(',', $sessionIds));
}
```

Pass this key as both the local `idempotency_key` and the `sender_batch_id` sent to PayPal / idempotency header sent to Stripe.

**Acceptance Criteria:**
- Same voter + same session set always produces the same key.
- Key changes if session set changes (new sessions eligible).
- PayPal API call uses the hash as `sender_batch_id`.

---

### 2.3 Align Force-Payout Readiness Checks (Finding #5)

**Files:**
- `app/Services/PoliticalPaymentService.php` (around L452)

```php
// BEFORE
if ($this->paypalService) {

// AFTER
if ($this->paypalService && $this->paypalService->isConfigured()) {
```

Apply the same pattern for any other force-payout processor checks.

**Acceptance Criteria:**
- Force payout with unconfigured PayPal credentials gracefully skips/falls back rather than crashing.

---

## Phase 3 — Validation & API Correctness (Target: 1–2 days)

### 3.1 Strengthen Purchase Amount Validation (Finding #6a)

**Files:**
- `app/Http/Requests/PurchaseCreditsRequest.php`

```php
// BEFORE
'amount' => ['required', 'numeric', 'min:1'],

// AFTER
'amount' => ['required', 'numeric', 'min:1', 'max:100000', 'regex:/^\d+(\.\d{1,2})?$/'],
```

> `max:100000` is a placeholder; align with actual platform limits from `config/u9itus.php`.

**Acceptance Criteria:**
- Amounts like `1.999`, `0`, `-5`, `999999999` are rejected with a validation error.
- Edge cases `1.00`, `100000` pass.

---

### 3.2 Pass `payment_method_id` Through Purchase Flow (Finding #6b)

**Files:**
- `app/Http/Controllers/Api/BillingController.php`
- `app/Services/CampaignBillingService.php`

`BillingController.php`:

```php
// BEFORE
$result = $this->billingService->createPurchaseIntent($politician, $amount, []);

// AFTER
$result = $this->billingService->createPurchaseIntent($politician, $amount, [
    'payment_method_id' => $request->validated('payment_method_id'),
]);
```

`CampaignBillingService.php` — ensure `createPurchaseIntent()` reads and uses the option:

```php
$paymentMethodId = $options['payment_method_id'] ?? null;
// Pass to Stripe PaymentIntent creation as `payment_method` if present
```

**Acceptance Criteria:**
- Integration test: providing a `payment_method_id` routes it to the Stripe PaymentIntent.
- Omitting it still works (backward compatible).

---

## Phase 4 — Money Arithmetic (Target: 3–5 days)

### 4.1 Introduce Integer-Cents Arithmetic (Finding #7)

**Files:**
- `app/Services/CampaignBillingService.php` (L230–231)
- `app/Services/PoliticalViewService.php` (L106, L111)
- `app/Services/PoliticalPaymentService.php` (L174)

**Strategy:** Create a lightweight `Money` helper (or use `brick/money` if already available in Composer) to enforce integer-cent operations.

Check if `brick/money` is already in the project:

```bash
composer show brick/money
```

If available, use it directly. If not, a minimal helper suffices:

```php
// app/Support/Money.php
class Money
{
    public static function toCents(float|string $amount): int
    {
        return (int) round(bcmul((string) $amount, '100', 10));
    }

    public static function fromCents(int $cents): string
    {
        return bcdiv((string) $cents, '100', 2);
    }

    public static function add(int ...$cents): int
    {
        return array_sum($cents);
    }
}
```

Replace all float casts and `round()` calls on money values with `Money::toCents()` at the boundary, perform arithmetic in cents, then convert back with `Money::fromCents()` for persistence.

**Migration note:** DB columns are already `DECIMAL(12,2)`, which is correct for final storage. The fix is only in the calculation layer — no schema change needed.

**Acceptance Criteria:**
- Gross-up calculation in `CampaignBillingService` produces identical results to Stripe's rounding rules.
- Referrer commission in `PoliticalViewService` rounds consistently for sub-cent values.
- Add unit tests for the edge cases: `$0.01` payout, `$0.005` rounding, 1000-view accumulation.

---

## Phase 5 — Schema Idempotency Constraints (Finding #8)

### 5.1 Add Unique Indexes to Idempotency-Critical Columns

**New migration:** `database/migrations/YYYY_MM_DD_add_idempotency_indexes.php`

```php
// campaign_transactions — prevent duplicate charge records
Schema::table('campaign_transactions', function (Blueprint $table) {
    $table->unique('stripe_payment_intent_id', 'uniq_campaign_tx_stripe_pi');
});

// politician_credits — prevent duplicate credit entries per transaction + type
Schema::table('politician_credits', function (Blueprint $table) {
    $table->unique(
        ['related_transaction_id', 'credit_type'],
        'uniq_politician_credits_tx_type'
    );
});
```

> **Pre-deployment check required:** Run `SELECT related_transaction_id, credit_type, COUNT(*) FROM politician_credits GROUP BY 1,2 HAVING COUNT(*) > 1` against production before applying. Clean up duplicates first if any exist.

**Acceptance Criteria:**
- Migration applies cleanly on a fresh database.
- Attempting a duplicate insert at the DB layer raises an integrity constraint exception.
- Application-level duplicate guards still exist (defense in depth); DB constraint is the backstop.

---

## Phase 6 — Test Coverage (Target: 3–5 days)

### 6.1 Webhook Security Tests

**File:** `tests/Feature/Billing/WebhookTest.php`

Add cases:
- Forged payload (no secret configured) → 400/500 in non-local env.
- Valid HMAC → event processed.
- Invalid HMAC → 400, no credit change.
- Replayed event (same `payment_intent_id`) → idempotent, no double credit.

### 6.2 Payout Idempotency & Crash Boundary Tests

**File:** `tests/Feature/Payout/BatchPayoutIdempotencyTest.php` (new)

Add cases:
- Same session set submitted twice → only one `payout_attempt` row, processor called once.
- Simulated crash between external call and DB update → retry picks up `submitted` attempt, skips re-submission.
- `processBatchPayouts()` with PayPal unconfigured → graceful skip, no exception.

### 6.3 Money Arithmetic Edge Cases

**File:** `tests/Unit/Services/MoneyArithmeticTest.php` (new)

Add cases:
- Gross-up at Stripe fee boundary (2.5%, various amounts).
- Viewer payout accumulation across 1, 100, 1000 views.
- Referrer commission at exactly `$0.005`.

---

## Execution Checklist

| # | Task | Phase | Owner | Done |
|---|------|-------|-------|------|
| 1 | Fail-closed webhook verification | 1 | | [ ] |
| 2 | Startup health check for webhook secret | 1 | | [ ] |
| 3 | Admin role middleware on admin routes | 1 | | [ ] |
| 4 | Ownership check in PurchaseCreditsRequest | 1 | | [ ] |
| 5 | Ownership guard in BillingController | 1 | | [ ] |
| 6 | Create payout_attempts migration | 2 | | [ ] |
| 7 | Restructure payout dispatch order | 2 | | [ ] |
| 8 | Deterministic payout idempotency key | 2 | | [ ] |
| 9 | Align force-payout readiness checks | 2 | | [ ] |
| 10 | Strengthen amount validation | 3 | | [ ] |
| 11 | Pass payment_method_id through flow | 3 | | [ ] |
| 12 | Introduce integer-cents Money helper | 4 | | [ ] |
| 13 | Replace float arithmetic in billing/view/payout | 4 | | [ ] |
| 14 | Audit production data for dup credits | 5 | | [ ] |
| 15 | Add unique index migration | 5 | | [ ] |
| 16 | Webhook security tests | 6 | | [ ] |
| 17 | Payout idempotency/crash tests | 6 | | [ ] |
| 18 | Money arithmetic edge case tests | 6 | | [ ] |

---

## Risk Notes

- Items 1.2 (ownership check) and 5.1 (unique index) carry **regression risk** — run full test suite and check production data before deploying.
- Items 2.1–2.2 (payout ledger) require a **deploy strategy**: old sessions in-flight during migration must not be double-processed. Consider a feature flag or maintenance window.
- Integer-cents migration (Phase 4) should be done **incrementally per service**, not all at once, to limit blast radius.
