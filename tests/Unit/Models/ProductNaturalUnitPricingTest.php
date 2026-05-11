<?php

namespace Tests\Unit\Models;

use App\Enums\ProductUnit;
use App\Models\Product;
use Tests\TestCase;

/**
 * Phase 4: pricePerNaturalUnit() / displayPricePerNaturalUnit() bridge
 * the storage unit (g, ml, kg, l, piece, bunch) to the customer-facing
 * "natural" denomination (kg, l, kg, l, piece, bunch).
 */
class ProductNaturalUnitPricingTest extends TestCase
{
    public function test_gram_storage_unit_displays_per_kilogram(): void
    {
        $product = new Product;
        $product->unit = ProductUnit::Gram;
        $product->unit_price = '0.0215';

        $this->assertEqualsWithDelta(21.50, $product->pricePerNaturalUnit(), 0.0001);
        $this->assertStringContainsString('/kg', $product->displayPricePerNaturalUnit());
        $this->assertStringContainsString('21,50', $product->displayPricePerNaturalUnit());
    }

    public function test_milliliter_storage_unit_displays_per_liter(): void
    {
        $product = new Product;
        $product->unit = ProductUnit::Milliliter;
        $product->unit_price = '0.0024';

        $this->assertEqualsWithDelta(2.40, $product->pricePerNaturalUnit(), 0.0001);
        $this->assertStringContainsString('2,40', $product->displayPricePerNaturalUnit());
    }

    public function test_natural_storage_units_pass_through_unchanged(): void
    {
        foreach (
            [
                [ProductUnit::Kilogram, '2.4900'],
                [ProductUnit::Liter, '5.5000'],
                [ProductUnit::Piece, '0.8000'],
                [ProductUnit::Bunch, '3.5000'],
            ] as [$unit, $storedPrice]
        ) {
            $product = new Product;
            $product->unit = $unit;
            $product->unit_price = $storedPrice;

            $this->assertEqualsWithDelta(
                (float) $storedPrice,
                $product->pricePerNaturalUnit(),
                0.0001,
                "{$unit->value} should pass through unchanged",
            );
        }
    }
}
