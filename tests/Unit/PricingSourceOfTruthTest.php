<?php

use Symfony\Component\Finder\Finder;

/*
 * Admin-editable rates must be read through PlatformSettingsService with no
 * inline fallback, so config/u9itus.php is the single default and an admin
 * override applies everywhere.
 */
const PRICING_KEYS = 'revenue_per_view|citizen_revenue_per_view|ballot_issue_revenue_per_view|viewer_payout_per_view|citizen_voter_payout_per_view|ballot_issue_voter_payout_per_view|referral_commission_percent|procurement_commission_percent|batch_payout_min|min_payout_amount|head_enterprises_fee_percent';

it('reads pricing only through PlatformSettingsService without inline fallbacks', function (): void {
    $root = dirname(__DIR__, 2);
    $patterns = [
        // A third argument is an inline fallback (the second is the user tier).
        "/PlatformSettingsService::get\\(\\s*'(" . PRICING_KEYS . ")'\\s*,[^,()]*,/",
        "/config\\(\\s*'u9itus\\.(" . PRICING_KEYS . ")'/",
    ];

    $offenders = [];
    foreach (Finder::create()->files()->name('*.php')->in([$root.'/app', $root.'/resources/views', $root.'/routes'])->notName('PlatformSettingsService.php') as $file) {
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $file->getContents(), $m)) {
                $offenders[] = $file->getRelativePathname().': '.implode(', ', array_unique($m[1]));
            }
        }
    }

    expect($offenders)->toBe([]);
});
