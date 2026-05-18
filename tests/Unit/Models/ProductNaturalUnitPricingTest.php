<?php

namespace Tests\Unit\Models;

use App\Enums\ProductUnit;
use App\Models\Product;
use Tests\TestCase;

/**
 * `Product::displayPrice()` renders the admin-chosen display unit. The
 * storage is per canonical unit of the kind (€/g for mass, €/ml for
 * volume, €/piece, €/bunch); the display multiplies by the kind's
 * conversion factor when needed.
 */
class ProductNaturalUnitPricingTest extends TestCase
{
    public function test_mass_kind_with_kg_display_unit_renders_per_kg(): void
    {
        $product = new Product;
        $product->unit = ProductUnit::Mass;
        $product->price_display_unit = 'kg';
        $product->unit_price = '0.0215'; // €/g

        $this->assertStringContainsString('/kg', $product->displayPrice('de'));
        $this->assertStringContainsString('21,50', $product->displayPrice('de'));
    }

    public function test_mass_kind_with_gram_display_unit_renders_per_gram(): void
    {
        $product = new Product;
        $product->unit = ProductUnit::Mass;
        $product->price_display_unit = 'g';
        $product->unit_price = '0.0215'; // €/g

        $this->assertStringContainsString('/g', $product->displayPrice('de'));
        $this->assertStringContainsString('0,02', $product->displayPrice('de'));
    }

    public function test_volume_kind_with_litre_display_unit_renders_per_litre(): void
    {
        $product = new Product;
        $product->unit = ProductUnit::Volume;
        $product->price_display_unit = 'l';
        $product->unit_price = '0.0024'; // €/ml

        $this->assertStringContainsString('/L', $product->displayPrice('de'));
        $this->assertStringContainsString('2,40', $product->displayPrice('de'));
    }

    public function test_piece_and_bunch_render_their_canonical_unit(): void
    {
        $piece = new Product;
        $piece->unit = ProductUnit::Piece;
        $piece->price_display_unit = 'piece';
        $piece->unit_price = '0.8000';
        $this->assertStringContainsString('0,80', $piece->displayPrice('de'));

        $bunch = new Product;
        $bunch->unit = ProductUnit::Bunch;
        $bunch->price_display_unit = 'bunch';
        $bunch->unit_price = '3.5000';
        $this->assertStringContainsString('3,50', $bunch->displayPrice('de'));
    }
}
