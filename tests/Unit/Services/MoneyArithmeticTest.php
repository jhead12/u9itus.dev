<?php

namespace Tests\Unit\Services;

use App\Support\Money;
use Tests\TestCase;

class MoneyArithmeticTest extends TestCase
{
    // ── toCents ──────────────────────────────────────────────────────────────

    public function test_to_cents_whole_dollar(): void
    {
        $this->assertSame(100, Money::toCents('1.00'));
        $this->assertSame(5000, Money::toCents('50.00'));
        $this->assertSame(10000000, Money::toCents('100000.00'));
    }

    public function test_to_cents_fractional(): void
    {
        $this->assertSame(1999, Money::toCents('19.99'));
        $this->assertSame(1, Money::toCents('0.01'));
        $this->assertSame(50, Money::toCents('0.50'));
    }

    public function test_to_cents_from_float_input(): void
    {
        // Float input should still be handled without drift
        $this->assertSame(1999, Money::toCents(19.99));
        $this->assertSame(1, Money::toCents(0.01));
    }

    // ── fromCents ─────────────────────────────────────────────────────────────

    public function test_from_cents_whole_dollar(): void
    {
        $this->assertSame('1.00', Money::fromCents(100));
        $this->assertSame('50.00', Money::fromCents(5000));
    }

    public function test_from_cents_fractional(): void
    {
        $this->assertSame('0.01', Money::fromCents(1));
        $this->assertSame('19.99', Money::fromCents(1999));
    }

    // ── percentOf ─────────────────────────────────────────────────────────────

    public function test_percent_of_10_percent_of_dollar(): void
    {
        // 10% of $1.00 = $0.10
        $this->assertSame(10, Money::percentOf(100, 10));
    }

    public function test_percent_of_rounds_up_on_half_cent(): void
    {
        // 10% of $0.05 = $0.005 → rounds up to 1 cent
        $this->assertSame(1, Money::percentOf(5, 10));
    }

    public function test_referral_commission_1000_views(): void
    {
        // Voter payout $0.50/view, 10% referral = $0.05/view, 1000 views = $50.00
        $perView = Money::toCents('0.50');
        $total = 0;
        for ($i = 0; $i < 1000; $i++) {
            $total += Money::percentOf($perView, 10);
        }
        // Should be exactly 5000 cents = $50.00
        $this->assertSame(5000, $total);
        $this->assertSame('50.00', Money::fromCents($total));
    }

    // ── grossUp ───────────────────────────────────────────────────────────────

    public function test_gross_up_stripe_2_5_percent(): void
    {
        // Net $60.00, gross up at 2.5%: $60 / (1 - 0.025) = $61.538... → $61.54 (ceil in cents)
        $netCents = Money::toCents('60.00');
        $grossCents = Money::grossUp($netCents, 2.5);

        // After Stripe takes 2.5% of gross, net should be >= $60.00
        $stripeFee = Money::percentOf($grossCents, 2.5);
        $netAfterFee = $grossCents - $stripeFee;

        $this->assertGreaterThanOrEqual($netCents, $netAfterFee, 'Net after fee must cover the requested amount');
        $this->assertSame('61.54', Money::fromCents($grossCents));
    }

    public function test_gross_up_round_trip_small_amount(): void
    {
        // $1.00 gross-up at 2.5%
        $netCents  = Money::toCents('1.00');
        $grossCents = Money::grossUp($netCents, 2.5);
        $feeCents  = Money::percentOf($grossCents, 2.5);

        // Net must not be less than original amount
        $this->assertGreaterThanOrEqual($netCents, $grossCents - $feeCents);
    }

    public function test_gross_up_large_amount(): void
    {
        // $100,000 gross up at 2.5%
        $netCents   = Money::toCents('100000.00');
        $grossCents = Money::grossUp($netCents, 2.5);

        $this->assertGreaterThan($netCents, $grossCents);
        // Gross should be close to net / 0.975 = 102,564.10
        $this->assertEqualsWithDelta(10256410, $grossCents, 5, 'Gross-up within 5 cents of expected');
    }
}
