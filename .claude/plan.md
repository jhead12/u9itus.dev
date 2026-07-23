# Plan: Fix Referral System Attribution, Reporting, and Contract Clarity

## Goal
Make the referral system behave consistently with its documented Early-bank delegation model: attribution never gets dropped, internal dashboards show the money Early-bank actually reports, and the outbound webhook contract tells Early-bank exactly how much commission to pay.

## Issues to fix

### 1. `?ref=` collision can drop internal attribution (bug)
`CaptureReferralContext` and `CaptureEarlyBankReferral` both read `?ref=`. The internal middleware validates the value as an uppercase referral code and clears the session/cookie if it is invalid. An Early-bank link uses a UUID in `?ref=<uuid>`, so the internal middleware treats it as invalid and **wipes** any existing internal referral context before the EB middleware runs. This means a visitor who clicked a U9itus referrer link and then an EB link loses the original referrer.

### 2. Internal referral dashboards report stale `$0` totals (ux/data quality)
`ReferralEarning` rows are no longer created for new activity — commissions are handled by Early-bank.com. Yet voter/politician referral pages and admin analytics still sum `ReferralEarning`. After legacy rows age out, every dashboard will show `$0` referral earnings even though real money is moving through Early-bank. `earlybank_earnings` already stores what EB reports, so we should surface it.

### 3. Procurement webhook does not state the commission terms (contract clarity)
`notifyPoliticianPurchased` sends the raw `purchase_amount` and relies on Early-bank to know it should compute 10%. We should include the configured `procurement_commission_percent` and the computed commission amount in the payload so the contract is explicit.

### 4. Misleading / missing tests (quality)
- `ViewSessionLifecycleTest` has a test titled `completing a view creates referral earning for referrer` that asserts the **opposite**.
- No test covers the internal-vs-Early-bank `?ref=` precedence behavior.
- No test verifies the `politician.purchased` payload includes commission terms.
- No end-to-end test exercises view completion → Early-bank inbound `payout.commission` → `earlybank_earnings`.

## Proposed implementation

### A. Fix `?ref=` precedence
In `CaptureReferralContext::handle`, before treating the incoming code as invalid, check whether it looks like a UUID. If it does, **skip the internal handling entirely** (do not set/clear the internal session/cookie); let `CaptureEarlyBankReferral` handle it. Only the internal alphanumeric code path should clear the internal session when the code is invalid.

```php
// pseudocode
$incomingCode = $request->query('ref') ?: $request->query('referral_code');
if (is_string($incomingCode) && trim($incomingCode) !== '') {
    $normalizedCode = strtoupper(trim($incomingCode));

    if (Str::isUuid($normalizedCode)) {
        return $next($request); // leave internal attribution untouched
    }

    if ($this->hasValidFormat($normalizedCode) && $this->referralExists($normalizedCode)) {
        // set session/cookie and track visit
    } else {
        // clear internal session/cookie
    }
}
```

Add a feature test that:
1. Lands with an internal U9itus referrer code (`?ref=VOTER123`) and asserts the session is stored.
2. Then lands with an Early-bank UUID (`?ref=<uuid>`) and asserts the internal session is **still** present.
3. Lands with only an EB UUID and asserts no internal session is created.

### B. Surface Early-bank earnings in referral dashboards
**Scope: minimal and additive.** We will not remove `ReferralEarning` reporting yet (it is intentionally retained for backwards-compatible admin exports). Instead we add Early-bank-reported totals and label them clearly.

#### Voter referrals page (`VoterController::referrals`)
Add variables:
- `$ebViewCommissionTotal` — sum of `earlybank_earnings.payout_amount` for this voter where `event_type = payout.commission`.
- `$ebBonusTotal` — sum of `earlybank_earnings.payout_amount` for this voter where `event_type = payout.bonus`.
- `$ebProcurementTotal` — sum of `earlybank_earnings.payout_amount` for this voter where `event_type = payout.commission` and a procurement can be inferred, OR where `event_type = politician.purchased`.

Update `standalone/voter/referrals.blade.php` to show:
- "Early-bank view commissions" next to "Voter-view commissions".
- "Early-bank referral bonuses".
- "Early-bank procurement commissions".

#### Politician referrals page (`PoliticianController::referrals`)
Add analogous totals from `earlybank_earnings` where `politician_id = $politician->id`. Update `standalone/politician/referrals.blade.php` similarly.

#### Admin API analytics (`Api/AdminController::analytics`)
Add a new key:
```json
"earlybank": {
  "total_referral_commissions": <sum of earlybank_earnings.payout_amount for payout.commission>,
  "total_referral_bonuses": <sum of earlybank_earnings.payout_amount for payout.bonus>
}
```
Keep the existing `total_referral_commissions` from `ReferralEarning` so existing consumers do not break.

#### Admin web analytics (`Standalone/AdminController`)
Add `earlybank_earnings`-based totals alongside the existing `ReferralEarning` totals in the analytics payload and export views. Label them "Early-bank reported".

### C. Include commission terms in outbound procurement webhook
In `EarlyBankWebhookService::notifyPoliticianPurchased`:
1. Read `config('u9itus.procurement_commission_percent', 10)`.
2. Compute `$commissionAmount = $purchaseAmount * ($percent / 100)`.
3. Add to the payload:
   ```php
   'commission_percent' => $percent,
   'commission_amount' => $commissionAmount,
   'purchase_amount' => $purchaseAmount,
   ```

Also add `referral_commission_percent` to the `voter.earned` payload so the contract is explicit there too.

### D. Fix and add tests
1. Rename `ViewSessionLifecycleTest` test `completing a view creates referral earning for referrer` to `completing a view does not create internal referral earning — commissions route to Early-bank`.
2. Add `tests/Feature/ReferralCookiePrecedenceTest.php` covering the `?ref=` collision fix.
3. Add/update `tests/Feature/EarlyBankWebhookLogTest.php` cases:
   - `politician.purchased payload includes commission percent and commission amount`.
   - `voter.earned payload includes referral commission percent`.
4. Add `tests/Feature/EarlyBankInboundWebhookTest.php` end-to-end case:
   - A voter completes a qualifying view.
   - Early-bank sends `payout.commission` for that voter.
   - `earlybank_earnings` row is created and the referrer's reported earnings increase.

## Files that will change

- `app/Http/Middleware/CaptureReferralContext.php`
- `app/Http/Controllers/Standalone/VoterController.php`
- `resources/views/standalone/voter/referrals.blade.php`
- `app/Http/Controllers/Standalone/PoliticianController.php`
- `resources/views/standalone/politician/referrals.blade.php`
- `app/Http/Controllers/Api/AdminController.php`
- `app/Http/Controllers/Standalone/AdminController.php`
- `app/Services/EarlyBankWebhookService.php`
- `tests/Feature/ReferralCookiePrecedenceTest.php` (new)
- `tests/Feature/EarlyBankOnboardingTest.php` or new `ReferralCookiePrecedenceTest.php`
- `tests/Feature/EarlyBankWebhookLogTest.php`
- `tests/Feature/EarlyBankInboundWebhookTest.php`
- `tests/Feature/Api/ViewSessionLifecycleTest.php`
- `app/Models/EarlyBankEarning.php` (possible new scopes for filtering)
- Optional: `app/Models/Voter.php` / `app/Models/Politician.php` (helper relations/scopes)

## Trade-offs considered

### Reporting scope
- **Option 1 (chosen): Additive.** Show Early-bank totals alongside legacy `ReferralEarning` totals. Safest — no existing tests/API consumers break, and users finally see real numbers.
- **Option 2: Replace.** Use `earlybank_earnings` as the primary total and demote `ReferralEarning`. Rejected because admin exports and older pages still reference `ReferralEarning`; doing a full migration would touch many more files and require a deprecation period.

### `?ref=` precedence
- **Chosen:** Detect UUIDs in the internal middleware and skip them. Keeps both systems coexisting with the same query key.
- **Alternative:** Use a separate `?eb_ref=` key for Early-bank. Rejected because it would require changes to Early-bank.com marketing links and existing QR codes; the collision can be resolved in U9itus code.

### Procurement commission in payload
- **Chosen:** Send both the configured percent and the computed amount. Gives Early-bank an explicit contract and removes ambiguity.
- **Alternative:** Send only the percent. Less useful for reconciliation; explicit amount is better.

## Acceptance criteria
1. Landing with `?ref=<uuid>` no longer clears an existing internal `referral.code` session.
2. Voter and politician referral pages display Early-bank-reported commission totals in addition to legacy totals.
3. Admin analytics API includes Early-bank referral commission totals under a new `earlybank` key.
4. `politician.purchased` outbound webhook payload contains `commission_percent` and `commission_amount`.
5. `voter.earned` outbound webhook payload contains `referral_commission_percent`.
6. The misleading `ViewSessionLifecycleTest` test name is corrected.
7. New tests cover cookie precedence, commission terms in webhooks, and end-to-end Early-bank earning recording.
8. The full referral-related test suite (`--filter="Referral|EarlyBank|CampaignBilling|PoliticalView"`) still passes.
