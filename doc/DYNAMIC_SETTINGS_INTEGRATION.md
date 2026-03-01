# Dynamic Platform Settings Integration Guide

## Overview

The new Platform Settings system allows admins to adjust pricing, commissions, and thresholds in real-time without code deployments. This guide shows how to integrate `PlatformSettingsService` into your existing services.

## Quick Start

### Basic Usage (Global Settings)

Replace hardcoded `config()` calls with `PlatformSettingsService::get()`:

**Before:**
```php
$revenuePerView = config('u9itus.revenue_per_view', 0.60);
$payoutPerView = config('u9itus.viewer_payout_per_view', 0.25);
```

**After:**
```php
use App\Services\PlatformSettingsService;

$revenuePerView = PlatformSettingsService::get('revenue_per_view', null, 0.60);
$payoutPerView = PlatformSettingsService::get('viewer_payout_per_view', null, 0.25);
```

**Benefits:**
- Falls back to config value if no DB override exists
- Admins can create promotions without code changes
- Cached for performance (5-minute TTL, configurable)

## User Tier Support (Early Adopters)

### 1. Add User Tier Field

Add `user_tier` to voters or politicians table:

```php
// Migration
Schema::table('voters', function (Blueprint $table) {
    $table->string('user_tier')->nullable()->after('referral_code'); // 'early_adopter', 'regular', null
    $table->timestamp('early_adopter_until')->nullable(); // Optional expiry
});
```

### 2. Use Tier-Specific Settings

```php
use App\Services\PlatformSettingsService;

// Example: Early adopters get higher payouts during campaign
$voterTier = $session->voter->user_tier; // 'early_adopter' or null
$payoutPerView = PlatformSettingsService::get('viewer_payout_per_view', $voterTier, 0.25);

// If admin set early_adopter override to $0.35, early adopters get that
// Regular users get global setting or config default
```

## Integration Examples

### Example 1: PoliticalViewService

**File:** `app/Services/PoliticalViewService.php`

```php
use App\Services\PlatformSettingsService;

public function completeView(ViewSession $session, int $totalWatchTimeSeconds): ViewSession
{
    // ... existing code ...
    
    // Get user tier if implementing tier-based payouts
    $userTier = $session->voter->user_tier ?? null;
    
    // Use dynamic settings instead of hardcoded config values
    $referralCommissionPct = PlatformSettingsService::get(
        'referral_commission_percent',
        $userTier,
        10
    );
    
    if ($qualifies && $session->voter->referred_by_voter_id) {
        $referralCommission = $voterPayout * ($referralCommissionPct / 100);
        $platformRevenue -= $referralCommission;
    }
    
    // ... rest of method ...
}
```

### Example 2: Fraud Prevention

**File:** `app/Services/FraudPreventionService.php`

```php
use App\Services\PlatformSettingsService;

public function calculateFraudScore(ViewSession $session, Request $request): int
{
    $score = 0;
    
    // Use dynamic fraud limits
    $maxPerDay = PlatformSettingsService::get('fraud_max_views_per_day', null, 50);
    
    $viewsToday = ViewSession::where('voter_id', $session->voter_id)
        ->whereDate('created_at', today())
        ->count();
    
    if ($viewsToday > $maxPerDay) {
        $score += 25;
        $this->recordFraudSignal(/* ... */);
    }
    
    // ... rest of method ...
}
```

### Example 3: Payment Service

**File:** `app/Services/PoliticalPaymentService.php`

```php
use App\Services\PlatformSettingsService;

public function processBatchPayouts(): array
{
    // Use dynamic payout threshold
    $minPayout = PlatformSettingsService::get('batch_payout_min', null, 5.00);
    $holdHours = PlatformSettingsService::get('fraud_payout_hold_hours', null, 48);
    
    $sessions = ViewSession::where('payment_status', ViewPaymentStatus::Approved)
        ->where('created_at', '<', now()->subHours($holdHours))
        ->with('voter')
        ->get()
        ->groupBy('voter_id');
    
    // ... rest of method ...
}
```

## Admin Panel Usage

### Create a Promotion

Admin navigates to `/admin/platform-settings` and can:

1. **Global Pricing Update:**
   - Set `viewer_payout_per_view` to `0.35`
   - Description: "Spring 2026 bonus - higher payouts"
   - Effective Until: 2026-04-01
   - User Tier: (blank for all users)

2. **Early Adopter Bonus:**
   - Set `referral_commission_percent` to `15`
   - Description: "Early adopter loyalty bonus"
   - Effective Until: 2026-12-31
   - User Tier: `early_adopter`

3. **Politician Acquisition Special:**
   - Set `procurement_commission_percent` to `20`
   - Description: "Q1 2026 politician recruitment incentive"
   - Effective From: 2026-03-01
   - Effective Until: 2026-03-31
   - User Tier: (blank)

### Settings Hierarchy

When `PlatformSettingsService::get()` is called:

1. **Check DB for user-tier-specific active setting** (if `$userTier` provided)
2. **Check DB for global active setting** (where `user_tier` is null)
3. **Fall back to config file** (e.g., `config('u9itus.revenue_per_view')`)

This means early adopters can get special rates while regular users get standard rates, and if neither exists, the system uses the hardcoded config value as a safe default.

## Migration Strategy

You don't need to update all `config()` calls at once. The system works in three stages:

### Stage 1: Admin Can Override (Current)
- Admin sets `revenue_per_view` = 0.75 in DB
- Services still use `config('u9itus.revenue_per_view')` → gets 0.60
- **No effect yet** until services are updated

### Stage 2: Gradual Service Updates
- Update `PoliticalViewService` to use `PlatformSettingsService::get()`
- Now view payouts respect DB overrides
- Other services still use old config values

### Stage 3: Full Integration
- All services updated
- Admin has full control over all pricing/limits
- Config values become safe defaults only

## Testing

```php
use App\Services\PlatformSettingsService;

// Create a test promotion
PlatformSettingsService::set('viewer_payout_per_view', 0.50, [
    'description' => 'Test promo',
    'effective_from' => now(),
    'effective_until' => now()->addHour(),
]);

// Verify it's active
$payout = PlatformSettingsService::get('viewer_payout_per_view'); // 0.50

// Clear the setting
PlatformSettingsService::delete('viewer_payout_per_view');

// Falls back to config
$payout = PlatformSettingsService::get('viewer_payout_per_view'); // 0.25 (from config)
```

## Performance

- Settings are cached for 5 minutes (`PlatformSettingsService::CACHE_TTL`)
- Cache is automatically invalidated when settings are updated
- Admin can manually clear all cache via UI button
- Negligible performance impact vs. direct `config()` calls

## Security

- Only admins can access `/admin/platform-settings`
- All updates are logged with admin ID
- Settings are validated before storage
- Date ranges prevent accidental permanent overrides

## Future Enhancements

1. **A/B Testing:** Add `ab_test_group` field for split testing pricing
2. **User Segments:** Beyond tiers, segment by state, registration date, etc.
3. **Scheduled Campaigns:** Auto-activate/deactivate promotions via cron
4. **Analytics:** Track revenue impact of pricing changes
5. **Rollback:** Version history with one-click revert
