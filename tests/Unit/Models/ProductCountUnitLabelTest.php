<?php

namespace Tests\Unit\Models;

use App\Enums\CountUnitLabel;
use App\Enums\ProductUnit;
use App\Models\Producer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The count-unit display word replaces "Stück" in every product-level
 * rendering while the unit math stays piece.
 */
class ProductCountUnitLabelTest extends TestCase
{
    use RefreshDatabase;

    private function pieceProduct(?CountUnitLabel $label): Product
    {
        Producer::factory()->create();

        return Product::factory()->create([
            'unit' => ProductUnit::Piece->value,
            'price_display_unit' => 'piece',
            'unit_price' => 4.50,
            'count_unit_label' => $label?->value,
        ]);
    }

    public function test_labelled_product_renders_the_word_everywhere(): void
    {
        app()->setLocale('de');
        $product = $this->pieceProduct(CountUnitLabel::Pot);

        $this->assertSame('1 Topf', $product->displayQuantity(1));
        $this->assertSame('3 Töpfe', $product->displayQuantity(3));
        $this->assertSame('Topf', $product->localizedUnit());
        $this->assertSame('Topf', $product->canonicalUnitLabel());
        $this->assertStringEndsWith('€/Topf', $product->displayPrice());
        $this->assertSame(1, $product->effectiveQuantityStep());
    }

    public function test_unlabelled_product_keeps_stueck(): void
    {
        app()->setLocale('de');
        $product = $this->pieceProduct(null);

        $this->assertSame('3 Stück', $product->displayQuantity(3));
        $this->assertSame('Stück', $product->localizedUnit());
        $this->assertSame('Stück', $product->canonicalUnitLabel());
        $this->assertStringEndsWith('€/Stück', $product->displayPrice());
    }

    public function test_stale_label_on_a_mass_product_is_ignored(): void
    {
        app()->setLocale('de');
        Producer::factory()->create();
        $product = Product::factory()->create([
            'unit' => ProductUnit::Mass->value,
            'price_display_unit' => 'kg',
            'unit_price' => 0.006,
        ]);
        $product->forceFill(['count_unit_label' => CountUnitLabel::Pot->value])->save();
        $product->refresh();

        $this->assertNull($product->countUnitWord());
        $this->assertSame('2 kg', $product->displayQuantity(2000));
        $this->assertStringEndsWith('€/kg', $product->displayPrice());
    }
}
