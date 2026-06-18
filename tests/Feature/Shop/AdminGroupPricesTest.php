<?php

namespace Tests\Feature\Shop;

use App\Enums\CustomerType;
use App\Enums\ProductUnit;
use App\Models\Product;
use App\Models\ProductPriceRule;
use App\Models\User;
use App\Services\Shop\PriceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Admin UI for per-product customer-group (business) prices.
 */
class AdminGroupPricesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_page_renders_with_private_price(): void
    {
        $product = Product::factory()->create([
            'unit' => ProductUnit::Piece->value,
            'price_display_unit' => 'piece',
            'unit_price' => 3.0,
        ]);

        $this->actingAs($this->admin())
            ->get(route('app.admin.products.group-prices', $product->id))
            ->assertOk()
            ->assertViewHas('privatePrice', 3.0);
    }

    public function test_requires_admin(): void
    {
        $product = Product::factory()->create();
        $this->actingAs(User::factory()->create())
            ->get(route('app.admin.products.group-prices', $product->id))
            ->assertForbidden();
    }

    public function test_store_creates_a_business_rule_in_canonical_units(): void
    {
        $product = Product::factory()->create([
            'unit' => ProductUnit::Mass->value,
            'price_display_unit' => 'kg',
            'unit_price' => 0.0300, // 30,00 €/kg canonical
        ]);

        // Admin enters 24,00 (€/kg) → stored canonical 0,024 €/g.
        $this->actingAs($this->admin())
            ->post(route('app.admin.products.group-prices.store', $product->id), [
                'unit_price' => 24,
                'label' => 'Gastro',
            ])->assertRedirect();

        $rule = ProductPriceRule::where('product_id', $product->id)->sole();
        $this->assertSame(CustomerType::Business, $rule->customer_type);
        $this->assertEqualsWithDelta(0.024, (float) $rule->unit_price, 1e-6);
        $this->assertNull($rule->starts_on);
        $this->assertNull($rule->min_quantity);
        $this->assertSame('Gastro', $rule->label);
    }

    public function test_store_replaces_an_existing_business_rule(): void
    {
        $product = Product::factory()->create([
            'unit' => ProductUnit::Piece->value, 'price_display_unit' => 'piece', 'unit_price' => 3.0,
        ]);
        ProductPriceRule::factory()->create([
            'product_id' => $product->id,
            'customer_type' => CustomerType::Business->value,
            'unit_price' => 2.5,
        ]);

        $this->actingAs($this->admin())
            ->post(route('app.admin.products.group-prices.store', $product->id), ['unit_price' => 2.0])
            ->assertRedirect();

        $rules = ProductPriceRule::where('product_id', $product->id)->get();
        $this->assertCount(1, $rules);
        $this->assertEqualsWithDelta(2.0, (float) $rules->first()->unit_price, 1e-6);
    }

    public function test_business_price_applies_only_to_business_orders(): void
    {
        Carbon::setTestNow('2026-06-10');
        $product = Product::factory()->create([
            'unit' => ProductUnit::Piece->value, 'price_display_unit' => 'piece', 'unit_price' => 3.0,
            'buying_price_cents' => null,
        ]);

        $this->actingAs($this->admin())
            ->post(route('app.admin.products.group-prices.store', $product->id), ['unit_price' => 2.4])
            ->assertRedirect();

        $resolver = app(PriceResolver::class);
        $product->refresh()->load('priceRules');

        $business = $resolver->resolve($product, Carbon::today(), CustomerType::Business);
        $private = $resolver->resolve($product, Carbon::today(), CustomerType::Private);

        $this->assertSame(2.4, $business->unitPriceEur);
        $this->assertSame('customer_group', $business->reason);
        $this->assertSame(3.0, $private->unitPriceEur);

        Carbon::setTestNow(null);
    }

    public function test_destroy_clears_the_business_price(): void
    {
        $product = Product::factory()->create([
            'unit' => ProductUnit::Piece->value, 'price_display_unit' => 'piece', 'unit_price' => 3.0,
        ]);
        ProductPriceRule::factory()->create([
            'product_id' => $product->id,
            'customer_type' => CustomerType::Business->value,
            'unit_price' => 2.5,
        ]);

        $this->actingAs($this->admin())
            ->delete(route('app.admin.products.group-prices.destroy', $product->id))
            ->assertRedirect();

        $this->assertDatabaseCount('product_price_rules', 0);
    }
}
