<?php

namespace Tests\Unit\Enums;

use App\Enums\ProductUnit;
use InvalidArgumentException;
use Tests\TestCase;

class ProductUnitFormatTest extends TestCase
{
    public function test_mass_quantity_renders_in_grams_below_threshold(): void
    {
        $this->assertSame('999 g', ProductUnit::Mass->formatQuantity(999, 'de'));
        $this->assertSame('500 g', ProductUnit::Mass->formatQuantity(500, 'de'));
        $this->assertSame('0 g', ProductUnit::Mass->formatQuantity(0, 'de'));
    }

    public function test_mass_quantity_renders_in_kilograms_at_or_above_threshold(): void
    {
        $this->assertSame('1 kg', ProductUnit::Mass->formatQuantity(1000, 'de'));
        $this->assertSame('1,5 kg', ProductUnit::Mass->formatQuantity(1500, 'de'));
        $this->assertSame('2 kg', ProductUnit::Mass->formatQuantity(2000, 'de'));
        $this->assertSame('10 kg', ProductUnit::Mass->formatQuantity(10000, 'de'));
    }

    public function test_adjacent_fifty_gram_steps_render_distinct_labels(): void
    {
        // Regression: with 1-decimal kg, 1050 g and 1100 g both rendered
        // "1,1 kg", producing duplicate-looking options in the stepper.
        $this->assertSame('1,05 kg', ProductUnit::Mass->formatQuantity(1050, 'de'));
        $this->assertSame('1,1 kg', ProductUnit::Mass->formatQuantity(1100, 'de'));
        $this->assertSame('1,15 kg', ProductUnit::Mass->formatQuantity(1150, 'de'));
        $this->assertSame('1,2 kg', ProductUnit::Mass->formatQuantity(1200, 'de'));
        $this->assertNotSame(
            ProductUnit::Mass->formatQuantity(1050, 'de'),
            ProductUnit::Mass->formatQuantity(1100, 'de'),
        );
    }

    public function test_volume_quantity_threshold_matches_mass(): void
    {
        $this->assertSame('999 ml', ProductUnit::Volume->formatQuantity(999, 'de'));
        $this->assertSame('1 L', ProductUnit::Volume->formatQuantity(1000, 'de'));
        $this->assertSame('2,5 L', ProductUnit::Volume->formatQuantity(2500, 'de'));
    }

    public function test_format_price_in_kilogram_display_unit(): void
    {
        // €/g stored = 0.0215 → €21,50/kg
        $this->assertSame('21,50 €/kg', ProductUnit::Mass->formatPrice(0.0215, 'kg', 'de'));
    }

    public function test_format_price_in_gram_display_unit(): void
    {
        $this->assertSame('0,02 €/g', ProductUnit::Mass->formatPrice(0.0215, 'g', 'de'));
    }

    public function test_format_price_in_litre_uppercases_the_symbol(): void
    {
        $this->assertSame('2,40 €/L', ProductUnit::Volume->formatPrice(0.0024, 'l', 'de'));
    }

    public function test_canonical_units_per_display_throws_for_invalid_combo(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ProductUnit::Mass->canonicalUnitsPerDisplay('ml');
    }

    public function test_allowed_display_units_per_kind(): void
    {
        $this->assertSame(['g', '100g', 'kg'], ProductUnit::Mass->allowedDisplayUnits());
        $this->assertSame(['ml', 'l'], ProductUnit::Volume->allowedDisplayUnits());
        $this->assertSame(['piece'], ProductUnit::Piece->allowedDisplayUnits());
        $this->assertSame(['bunch'], ProductUnit::Bunch->allowedDisplayUnits());
    }

    public function test_format_price_in_100g_display_unit(): void
    {
        // €/g stored = 0.0215 → €2,15 per 100 g
        $this->assertSame('2,15 €/100 g', ProductUnit::Mass->formatPrice(0.0215, '100g', 'de'));
    }

    public function test_canonical_units_per_100g_display_is_100(): void
    {
        $this->assertSame(100, ProductUnit::Mass->canonicalUnitsPerDisplay('100g'));
    }
}
