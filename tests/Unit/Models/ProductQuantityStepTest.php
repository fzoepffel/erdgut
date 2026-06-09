<?php

namespace Tests\Unit\Models;

use App\Enums\ProductUnit;
use App\Models\Product;
use Tests\TestCase;

/**
 * `Product::effectiveQuantityStep()` returns the right step for each
 * unit kind, with correct fallback to the config default.
 */
class ProductQuantityStepTest extends TestCase
{
    // ── Piece / Bunch always return 1 ────────────────────────────────────

    public function test_piece_unit_always_returns_step_of_1(): void
    {
        $product = new Product;
        $product->unit = ProductUnit::Piece;

        $this->assertSame(1, $product->effectiveQuantityStep());
    }

    public function test_piece_unit_ignores_quantity_step_field(): void
    {
        $product = new Product;
        $product->unit = ProductUnit::Piece;
        $product->quantity_step = 500; // should be ignored

        $this->assertSame(1, $product->effectiveQuantityStep());
    }

    public function test_bunch_unit_always_returns_step_of_1(): void
    {
        $product = new Product;
        $product->unit = ProductUnit::Bunch;

        $this->assertSame(1, $product->effectiveQuantityStep());
    }

    // ── Mass / Volume use per-product step when set ───────────────────────

    public function test_mass_unit_returns_custom_quantity_step(): void
    {
        $product = new Product;
        $product->unit = ProductUnit::Mass;
        $product->quantity_step = 500; // e.g. goliath rhubarb in 500 g steps

        $this->assertSame(500, $product->effectiveQuantityStep());
    }

    public function test_volume_unit_returns_custom_quantity_step(): void
    {
        $product = new Product;
        $product->unit = ProductUnit::Volume;
        $product->quantity_step = 250; // e.g. 250 ml steps

        $this->assertSame(250, $product->effectiveQuantityStep());
    }

    // ── Fallback to config default when quantity_step is null ────────────

    public function test_mass_unit_falls_back_to_config_default(): void
    {
        config(['shop.quantity_step' => 50]);

        $product = new Product;
        $product->unit = ProductUnit::Mass;
        $product->quantity_step = null;

        $this->assertSame(50, $product->effectiveQuantityStep());
    }

    public function test_volume_unit_falls_back_to_config_default(): void
    {
        config(['shop.quantity_step' => 100]);

        $product = new Product;
        $product->unit = ProductUnit::Volume;
        $product->quantity_step = null;

        $this->assertSame(100, $product->effectiveQuantityStep());
    }

    public function test_config_default_is_used_when_no_step_configured_at_all(): void
    {
        // Hard-coded fallback in effectiveQuantityStep() is 50.
        config(['shop.quantity_step' => null]);

        $product = new Product;
        $product->unit = ProductUnit::Mass;
        $product->quantity_step = null;

        $this->assertSame(50, $product->effectiveQuantityStep());
    }

    // ── Unit descriptors render as mass/volume, NOT the product's own unit ──

    public function test_bunch_product_weight_descriptor_renders_in_grams_not_bunch(): void
    {
        // Regression: previously rendered "100 Bund" because displayQuantity()
        // formats with the product's own unit. Must read "100 g".
        $product = new Product;
        $product->unit = ProductUnit::Bunch;
        $product->unit_weight_grams = 100;

        $rendered = $product->displayUnitWeight('de');

        $this->assertStringContainsString('100', $rendered);
        $this->assertStringContainsString('g', $rendered);
        $this->assertStringNotContainsString('Bund', $rendered);
    }

    public function test_bunch_product_weight_descriptor_switches_to_kg_above_threshold(): void
    {
        $product = new Product;
        $product->unit = ProductUnit::Bunch;
        $product->unit_weight_grams = 1200;

        $this->assertStringContainsString('kg', $product->displayUnitWeight('de'));
    }

    public function test_piece_product_volume_descriptor_renders_in_ml_not_piece(): void
    {
        $product = new Product;
        $product->unit = ProductUnit::Piece;
        $product->unit_volume_ml = 250;

        $rendered = $product->displayUnitVolume('de');

        $this->assertStringContainsString('250', $rendered);
        $this->assertStringContainsString('ml', $rendered);
        $this->assertStringNotContainsString('Stück', $rendered);
    }

    public function test_descriptor_helpers_return_null_when_unset(): void
    {
        $product = new Product;
        $product->unit = ProductUnit::Bunch;

        $this->assertNull($product->displayUnitWeight());
        $this->assertNull($product->displayUnitVolume());
    }
}
