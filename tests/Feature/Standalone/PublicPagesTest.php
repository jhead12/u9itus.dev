<?php

use App\Services\PlatformSettingsService;

it('renders the public info pages', function (string $routeName, string $heading): void {
    $this->get(route($routeName))->assertOk()->assertSee($heading);
})->with([
    ['about', 'About'],
    ['how-it-works', 'How it works'],
    ['pricing', 'Pay only for completed views'],
]);

it('shows the live admin-configured rates on the pricing page', function (): void {
    PlatformSettingsService::set('revenue_per_view', 1.25);
    PlatformSettingsService::set('viewer_payout_per_view', 0.40);

    $this->get(route('pricing'))
        ->assertOk()
        ->assertSee('$1.25')
        ->assertSee('$12.50') // 10-view minimum budget
        ->assertSee('$0.40');
});
