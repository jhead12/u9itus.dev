<?php

namespace App\Support;

/**
 * Lightweight integer-cent money helper.
 *
 * All arithmetic is performed in integer cents to avoid floating-point drift.
 * Only convert to/from cents at the application boundary (input validation and
 * database persistence).
 *
 * Usage:
 *   $cents = Money::toCents('19.99');       // → 1999
 *   $gross = Money::toCents('19.99') + 50;  // integer arithmetic
 *   $str   = Money::fromCents($gross);      // → '20.49'
 */
class Money
{
    /**
     * Convert a decimal dollar amount (string or float) to integer cents.
     * Uses bcmath for precision — never casts to float internally.
     */
    public static function toCents(string|float|int $amount): int
    {
        return (int) bcmul((string) $amount, '100', 0);
    }

    /**
     * Convert integer cents back to a 2-decimal-place string suitable for
     * DB persistence or Stripe API calls (Stripe takes cents directly as int).
     */
    public static function fromCents(int $cents): string
    {
        return bcdiv((string) $cents, '100', 2);
    }

    /**
     * Apply a percentage to a cent amount (e.g. platform fee, Stripe fee).
     * Returns the fee in cents, rounded half-up.
     */
    public static function percentOf(int $cents, string|float $percent): int
    {
        return (int) ceil(bcdiv(bcmul((string) $cents, (string) $percent, 10), '100', 10));
    }

    /**
     * Calculate the gross amount needed so that after deducting a percentage fee
     * the net equals the target cents (i.e. gross-up).
     *
     * Formula: gross = net / (1 - fee_rate)
     */
    public static function grossUp(int $netCents, string|float $feePercent): int
    {
        $feeRate = bcdiv((string) $feePercent, '100', 10);
        $divisor = bcsub('1', $feeRate, 10);
        return (int) ceil(bcdiv((string) $netCents, $divisor, 10));
    }

    /**
     * Parse a loosely-formatted currency string (e.g. "$1,234,000", scraped
     * from OpenSecrets/FEC display data) into a float dollar amount. Returns
     * 0.0 for empty or non-numeric input. Not precision-safe (uses float,
     * not bcmath) — only intended for display-side ranking/percentages, not
     * financial arithmetic.
     */
    public static function parseLoose(string $raw): float
    {
        $cleaned = preg_replace('/[^0-9.\-]/', '', $raw) ?? '';

        return $cleaned === '' || ! is_numeric($cleaned) ? 0.0 : (float) $cleaned;
    }
}
