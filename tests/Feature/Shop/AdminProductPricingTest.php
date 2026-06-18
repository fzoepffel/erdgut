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
