<?php

namespace Tests\Unit\Support;

use App\Support\Money;
use Tests\TestCase;

class MoneyTest extends TestCase
{
    public function test_formats_eur_with_german_decimal_and_thousand_separator(): void
    {
        $this->assertSame('12,50 €', Money::formatEur(12.5));
        $this->assertSame('1.234,50 €', Money::formatEur(1234.5));
        $this->assertSame('0,00 €', Money::formatEur(0));
        $this->assertSame('0,00 €', Money::formatEur(null));
    }

    public function test_formats_cents_into_eur(): void
    {
        $this->assertSame('1,50 €', Money::formatCents(150));
        $this->assertSame('0,00 €', Money::formatCents(0));
        $this->assertSame('0,00 €', Money::formatCents(null));
        // Largest realistic checkout sums round correctly.
        $this->assertSame('999,99 €', Money::formatCents(99999));
    }

    public function test_handles_string_input_safely(): void
    {
        // Some columns persist as strings (decimal types via PDO).
        $this->assertSame('12,50 €', Money::formatEur('12.5'));
        $this->assertSame('150,00 €', Money::formatCents('15000'));
    }

    public function test_format_returns_bare_german_number_without_currency_symbol(): void
    {
        $this->assertSame('12,50', Money::format(12.5));
        $this->assertSame('1.234,50', Money::format(1234.5));
        $this->assertSame('0,00', Money::format(0));
        $this->assertSame('0,00', Money::format(null));
    }

    public function test_format_respects_decimals_argument(): void
    {
        // For trial-card "35 EUR" style copy that omits decimals.
        $this->assertSame('35', Money::format(35.0, 0));
        $this->assertSame('35,000', Money::format(35.0, 3));
        $this->assertSame('0', Money::format(null, 0));
    }
}
