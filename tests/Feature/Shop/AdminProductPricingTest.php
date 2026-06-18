<?php

namespace Tests\Feature\Shop;

use App\Enums\ProductUnit;
use App\Models\Product;
use App\Models\ProductPriceRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminProductPricingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function product(): Product
    {
        return Product::factory()->create([
            'unit' => ProductUnit::Piece->value,
            'price_display_unit' => 'piece',
            'unit_price' => 10.0,
            'is_available' => true,
            'is_published' => true,
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-06-10 09:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    public function test_calendar_page_renders_for_admin(): void
    {
        $product = $this->product();

        $this->actingAs($this->admin())
            ->get(route('app.admin.products.pricing', $product->id))
            ->assertOk()
            ->assertViewHas('defaultPrice', 10.0);
    }

    public function test_calendar_colours_cheaper_and_dearer_days(): void
    {
        $product = $this->product(); // default 10.00 / piece
        ProductPriceRule::factory()->create([
            'product_id' => $product->id, 'starts_on' => '2026-06-15', 'ends_on' => '2026-06-16', 'unit_price' => 5.0,
        ]);
        ProductPriceRule::factory()->create([
            'product_id' => $product->id, 'starts_on' => '2026-06-20', 'ends_on' => '2026-06-21', 'unit_price' => 14.0,
        ]);

        $this->actingAs($this->admin())
            ->get(route('app.admin.products.pricing', $product->id))
            ->assertOk()
            ->assertSee('bg-green-800', false)   // -50% → darkest green
            ->assertSee('bg-red-600', false);    // +40% → level 3 red
    }

    public function test_calendar_requires_admin(): void
    {
        $product = $this->product();
        $this->actingAs(User::factory()->create())
            ->get(route('app.admin.products.pricing', $product->id))
            ->assertForbidden();
    }

    public function test_store_creates_a_range_rule(): void
    {
        $product = $this->product();

        $this->actingAs($this->admin())
            ->post(route('app.admin.products.pricing.store', $product->id), [
                'starts_on' => '2026-06-15',
                'ends_on' => '2026-06-20',
                'unit_price' => 7.5,
                'label' => 'Thin batch',
            ])->assertRedirect();

        $rule = ProductPriceRule::where('product_id', $product->id)->sole();
        $this->assertSame('2026-06-15', $rule->starts_on->toDateString());
        $this->assertSame('2026-06-20', $rule->ends_on->toDateString());
        $this->assertEqualsWithDelta(7.5, (float) $rule->unit_price, 0.001);
        $this->assertSame('Thin batch', $rule->label);
    }

    public function test_store_accepts_a_range_spanning_multiple_months(): void
    {
        $product = $this->product();

        // A range from one month well into a later one (cross-month selection).
        $this->actingAs($this->admin())
            ->post(route('app.admin.products.pricing.store', $product->id), [
                'starts_on' => '2026-06-24',
                'ends_on' => '2026-09-03',
                'unit_price' => 8.0,
            ])->assertRedirect();

        $rule = ProductPriceRule::where('product_id', $product->id)->sole();
        $this->assertSame('2026-06-24', $rule->starts_on->toDateString());
        $this->assertSame('2026-09-03', $rule->ends_on->toDateString());

        // The override is effective on a day deep inside the range.
        $resolver = app(\App\Services\Shop\PriceResolver::class);
        $resolved = $resolver->resolve($product->fresh(), \Illuminate\Support\Carbon::parse('2026-08-01'));
        $this->assertSame(8.0, $resolved->unitPriceEur);
        $this->assertSame('period_override', $resolved->reason);
    }

    public function test_store_converts_display_price_to_canonical_for_mass_products(): void
    {
        $product = Product::factory()->create([
            'unit' => ProductUnit::Mass->value,
            'price_display_unit' => 'kg',
            'unit_price' => 0.0200, // 20,00 €/kg canonical (€/g)
            'is_available' => true,
            'is_published' => true,
        ]);

        // Admin enters 15,00 (meaning €/kg) → stored as 0,015 €/g canonical.
        $this->actingAs($this->admin())
            ->post(route('app.admin.products.pricing.store', $product->id), [
                'starts_on' => '2026-06-15',
                'ends_on' => '2026-06-16',
                'unit_price' => 15,
            ])->assertRedirect();

        $rule = ProductPriceRule::where('product_id', $product->id)->sole();
        $this->assertEqualsWithDelta(0.015, (float) $rule->unit_price, 1e-6);
    }

    public function test_store_rejects_past_dates(): void
    {
        $product = $this->product();
        $this->actingAs($this->admin())
            ->post(route('app.admin.products.pricing.store', $product->id), [
                'starts_on' => '2026-06-01',
                'ends_on' => '2026-06-02',
                'unit_price' => 5.0,
            ])->assertSessionHasErrors('starts_on');

        $this->assertDatabaseCount('product_price_rules', 0);
    }

    public function test_overlapping_save_splits_the_existing_rule(): void
    {
        $product = $this->product();
        // Existing wide override Jun 12–25 @ 8.00
        ProductPriceRule::factory()->create([
            'product_id' => $product->id,
            'starts_on' => '2026-06-12',
            'ends_on' => '2026-06-25',
            'unit_price' => 8.0,
        ]);

        // Save a narrower window inside it at a different price.
        $this->actingAs($this->admin())
            ->post(route('app.admin.products.pricing.store', $product->id), [
                'starts_on' => '2026-06-16',
                'ends_on' => '2026-06-18',
                'unit_price' => 6.0,
            ])->assertRedirect();

        // Expect three rules: left remainder, the new one, right remainder.
        $rules = ProductPriceRule::where('product_id', $product->id)->orderBy('starts_on')->get();
        $this->assertCount(3, $rules);
        $this->assertEquals(['2026-06-12', '2026-06-16', '2026-06-19'], $rules->pluck('starts_on')->map->toDateString()->all());
        $this->assertEquals(['2026-06-15', '2026-06-18', '2026-06-25'], $rules->pluck('ends_on')->map->toDateString()->all());
        $this->assertEqualsWithDelta(6.0, (float) $rules[1]->unit_price, 0.001);
    }

    public function test_destroy_clears_overrides_in_range(): void
    {
        $product = $this->product();
        ProductPriceRule::factory()->create([
            'product_id' => $product->id,
            'starts_on' => '2026-06-16',
            'ends_on' => '2026-06-18',
            'unit_price' => 6.0,
        ]);

        $this->actingAs($this->admin())
            ->delete(route('app.admin.products.pricing.destroy', $product->id), [
                'starts_on' => '2026-06-16',
                'ends_on' => '2026-06-18',
            ])->assertRedirect();

        $this->assertDatabaseCount('product_price_rules', 0);
    }
}
