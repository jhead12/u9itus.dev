Searched for files matching `**/app/Services/StripeConnectService.php`, 1 match

Read memory

Read [](file:///Users/jay/Documents/Github/u9itus.dev/app/Services/StripeConnectService.php#1-1), lines 1 to 200

Read [](file:///Users/jay/Documents/Github/u9itus.dev/app/Services/StripeConnectService.php#200-200), lines 200 to 419

Stripe Connect (Express accounts) is the backbone of your voter payout system, and in u9itus it actually does double duty — payouts **and** identity verification. Here's how the pattern helps, grounded in StripeConnectService.php:

## 1. Outsources money transmission & compliance
Voters earn $0.50/view, which means you'd otherwise be paying thousands of individuals — legally a money-transmitter problem. With Connect, each voter gets their own **Express account**, and Stripe handles:
- KYC/identity collection (SSN, DOB, bank account) via hosted onboarding (`createOnboardingLink`)
- Tax reporting (1099-K/NEC generation)
- Bank account validation and ACH rails

You never touch or store banking credentials — a huge OWASP/PCI surface you simply don't have.

## 2. Free identity verification ("Authentic User Verifier")
This is the clever part of your design: Stripe's Express onboarding *requires* government-grade identity verification, so you piggyback on it as your voter KYC layer. In `getAccountStatus()`:

```php
$voter->update([
    'stripe_account_status' => $status,
    'is_verified'           => $isActive,  // Stripe KYC = platform verification
]);
```

`is_verified` mirrors Stripe's `charges_enabled && payouts_enabled` bi-directionally — if Stripe revokes an account, the voter is automatically demoted. That's fraud prevention (one real human = one payable account) without paying for a separate KYC vendor like Id.me for the voter tier.

## 3. Concurrency-safe account creation
`ensureExpressAccount()` uses a **Cache lock + DB transaction with `lockForUpdate()`** ([StripeConnectService.php](app/Services/StripeConnectService.php#L52-L77)) so double-clicks or concurrent requests can't create duplicate Stripe accounts for the same voter — which would orphan money and break reconciliation.

## 4. Idempotent, auditable payouts
`sendTransfer()` uses Stripe **idempotency keys**, so a retried `ProcessViewerPayouts` batch run can't double-pay a voter. Combined with your `PayoutRun` / `PayoutAttempt` models, every transfer has a Stripe `transfer.id` reference for reconciliation.

## 5. Self-healing operational resilience
The service degrades gracefully rather than crashing:
- Stale/deleted Stripe accounts are detected ("no such account") and recreated automatically
- `account_update` link fallback for legacy accounts that can't re-enter onboarding
- `classifyStripeException()` maps every raw Stripe error to a user-safe message while keeping internals in logs only — no leaking of API details to end users

## 6. Cost efficiency vs. alternatives
Connect transfers to Express accounts return `'fee' => 0.00` in your implementation — platform→connected-account transfers are cheap compared to PayPal Payouts (your `PayPalPayoutService` fallback), which charge per-payout fees. Stripe becomes your preferred rail, with PayPal/CashApp as alternatives for voters who won't complete Stripe KYC.

**Net effect:** one integration gives you payout rails, KYC, fraud gating, tax compliance, and duplicate-account prevention — all of which would otherwise be separate systems on a platform paying out micro-earnings at scale.`is_verified` mirrors Stripe's `charges_enabled && payouts_enabled` bi-directionally — if Stripe revokes an account, the voter is automatically demoted. That's fraud prevention (one real human = one payable account) without paying for a separate KYC vendor like Id.me for the voter tier.

## 3. Concurrency-safe account creation
`ensureExpressAccount()` uses a **Cache lock + DB transaction with `lockForUpdate()`** ([StripeConnectService.php](app/Services/StripeConnectService.php#L52-L77)) so double-clicks or concurrent requests can't create duplicate Stripe accounts for the same voter — which would orphan money and break reconciliation.

## 4. Idempotent, auditable payouts
`sendTransfer()` uses Stripe **idempotency keys**, so a retried `ProcessViewerPayouts` batch run can't double-pay a voter. Combined with your `PayoutRun` / `PayoutAttempt` models, every transfer has a Stripe `transfer.id` reference for reconciliation.

## 5. Self-healing operational resilience
The service degrades gracefully rather than crashing:
- Stale/deleted Stripe accounts are detected ("no such account") and recreated automatically
- `account_update` link fallback for legacy accounts that can't re-enter onboarding
- `classifyStripeException()` maps every raw Stripe error to a user-safe message while keeping internals in logs only — no leaking of API details to end users

## 6. Cost efficiency vs. alternatives
Connect transfers to Express accounts return `'fee' => 0.00` in your implementation — platform→connected-account transfers are cheap compared to PayPal Payouts (your `PayPalPayoutService` fallback), which charge per-payout fees. Stripe becomes your preferred rail, with PayPal/CashApp as alternatives for voters who won't complete Stripe KYC.

**Net effect:** one integration gives you payout rails, KYC, fraud gating, tax compliance, and duplicate-account prevention — all of which would otherwise be separate systems on a platform paying out micro-earnings at scale.

Searched for files matching `**/app/Console/Commands/ProcessViewerPayouts.php`, 1 match

Searched for files matching `**/app/Http/Controllers/Voter/PayoutController.php`, no matches

Searched for regex `StripeConnectService|sendTransfer|ensureExpressAccount|createOnboardingLink` (`**/app/**`), 20 results

Read [](file:///Users/jay/Documents/Github/u9itus.dev/app/Console/Commands/ProcessViewerPayouts.php)

Searched for regex `authentic-user-verifier|AuthenticUserVerifier|onboarding.*stripe|stripe.*onboarding` (`**/app/**`), 20 results

Read [](file:///Users/jay/Documents/Github/u9itus.dev/app/Services/PoliticalPaymentService.php#210-210), lines 210 to 310

Searched for regex `payouts:process-viewer|schedule.*payout|payout.*schedule` (`**/app/Console/**`), 4 results

Searched for regex `stripe_account_id|stripe_account_status` (`**/database/migrations/**`), 11 results

Searched for text `payouts:process-viewer` (`**/app/Console/Kernel.php`), no results

Searched for files matching `**/app/Console/Kernel.php`, no matches

Searched for regex `payouts:process-viewer|ProcessViewerPayouts` (`**/routes/**`), 1 result

Searched for regex `schedule|ProcessViewerPayouts|payouts:process` (`**/bootstrap/**`), 14 results

Yes, the payout system is fully connected end-to-end. Here's the complete wiring:

## Voter Onboarding Side (entry point)
ManagesVoterAuxiliaryActions.php → `startAuthenticUserVerifier()` calls `StripeConnectService::createOnboardingLink()`, which creates the Express account and redirects the voter to Stripe's hosted KYC form. On return, `getAccountStatus()` syncs `is_verified` and `stripe_account_status` back.

An API endpoint also exposes this at VoterController.php.

Reminder emails for incomplete onboarding: `SendAuthenticUserVerifierReminders` command.

## Payout Execution Side
console.php:
```
Schedule::command('payouts:process-viewer')->daily();
```

The schedule triggers `ProcessViewerPayouts` → `PoliticalPaymentService::processBatchPayouts()` → checks `canReceivePayout($voter)` (requires `stripe_account_status = active`) → calls `StripeConnectService::sendTransfer()` with an idempotency key → records the Stripe `transfer.id` on the `PayoutAttempt`.

If Stripe fails or the voter hasn't completed KYC, it falls through to PayPal → CashApp → wallet credit (internal ledger fallback).

## Database
Migration 2026_04_22_120000_add_stripe_connect_fields_to_voters_table.php adds `stripe_account_id` and `stripe_account_status` (indexed) to `voters`.

**Everything is wired.** The only thing that needs to be true in production for it to fire is: `STRIPE_SECRET` is set, Stripe Connect is enabled on the platform account in the Stripe dashboard, and the Railway scheduler is running (`php artisan schedule:run` on cron).