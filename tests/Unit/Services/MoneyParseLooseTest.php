<?php

namespace Tests\Unit\Services;

use App\Support\Money;
use Tests\TestCase;

class MoneyParseLooseTest extends TestCase
{
    public function test_parses_currency_string_with_commas(): void
    {
        $this->assertSame(1234000.0, Money::parseLoose('$1,234,000'));
    }

    public function test_parses_plain_number_string(): void
    {
        $this->assertSame(980500.0, Money::parseLoose('980500'));
    }

    public function test_parses_decimal_amount(): void
    {
        $this->assertSame(52000.5, Money::parseLoose('$52,000.50'));
    }

    public function test_empty_string_returns_zero(): void
    {
        $this->assertSame(0.0, Money::parseLoose(''));
    }

    public function test_non_numeric_string_returns_zero(): void
    {
        $this->assertSame(0.0, Money::parseLoose('N/A'));
        $this->assertSame(0.0, Money::parseLoose('unknown'));
    }
}
