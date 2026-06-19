<?php

namespace Tests\Feature\Shop;

use App\Enums\CustomerType;
use App\Enums\ProductUnit;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductPriceRule;
use App\Models\ProductStockInflow;
use App\Models\ProductVariant;
use App\Models\ShopOrderItem;
use App\Models\User;
use App\Services\Admin\InventoryService;
use App\Services\Shop\PriceResolver;
use App\Services\Shop\ShopOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Pricing Phase 3 — product grades/variants with their own price + own stock.
 */
class ProductVariantsTest extends TestCase
{
    use RefreshDatabase;

    private const DELIVERY_DATE = '2026-06-12';

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

    private function pieceProduct(float $price = 10.0): Product
    {
        return Product::factory()->create([
            'producer_id' => Producer::factory(),
            'category' => 'produce',
            'unit' => ProductUnit::Piece->value,
            'price_display_unit' => 'piece',
            'unit_price' => $price,
            'is_available' => true,
            'is_published' => true,
        ]);
    }

    private function inflow(Product $product, float $qty, ?int $variantId = null): void
    {
        ProductStockInflow::create([
            'product_id' => $product->id,
            'variant_id' => $variantId,
            'received_on' => self::DELIVERY_DATE,
            'quantity' => $qty,
            'freshness_window_days' => 1,
        ]);
    }

    public function test_grades_track_independent_stock(): void
    {
        $product = $this->pieceProduct(10.0);
        $thin = ProductVariant::factory()->create(['product_id' => $product->id, 'unit_price' => 5.0]);
        $this->inflow($product, 100);          // default grade
        $this->inflow($product, 20, $thin->id); // thin grade

        $inv = app(InventoryService::class);
        $date = Carbon::parse(self::DELIVERY_DATE);

        // Product total = sum of grades; each grade reports its own batch.
        $this->assertSame(120.0, $inv->availableOnDate($product, $date));
        $this->assertSame(100.0, $inv->remainingForGrade($product, $date, null));
        $this->assertSame(20.0, $inv->remainingForGrade($product, $date, $thin->id));

        // Order 5 of the thin grade.
        app(ShopOrderService::class)->createOrder([
            'name' => 'Anna', 'email' => 'a@example.com',
            'fulfillment_mode' => 'pickup', 'pickup_location' => 'karl_kunger',
            'payment_method' => 'online', 'expected_delivery_date' => self::DELIVERY_DATE,
            'items' => [['product_id' => $product->id, 'quantity' => 5, 'variant_id' => $thin->id]],
        ]);

        // Only the thin grade depletes; the default grade is untouched.
        $this->assertSame(15.0, $inv->remainingForGrade($product, $date, $thin->id));
        $this->assertSame(100.0, $inv->remainingForGrade($product, $date, null));
    }

    public function test_variant_availability_window_splits_by_grade(): void
    {
        $product = $this->pieceProduct(10.0);
        $thin = ProductVariant::factory()->create(['product_id' => $product->id, 'unit_price' => 5.0]);
        $this->inflow($product, 50);           // default grade
        $this->inflow($product, 30, $thin->id); // thin grade

        $date = Carbon::parse(self::DELIVERY_DATE);
        $lanes = app(InventoryService::class)->variantAvailabilityWindow($product, $date, $date);
        $iso = self::DELIVERY_DATE;

        $this->assertSame(50.0, $lanes[0][$iso]);          // default lane
        $this->assertSame(30.0, $lanes[$thin->id][$iso]);   // thin lane
        // Lanes sum to the product total.
        $this->assertSame(80.0, $lanes[0][$iso] + $lanes[$thin->id][$iso]);
    }

    public function test_storefront_card_shows_date_override_price(): void
    {
        $product = $this->pieceProduct(10.0);
        $this->inflow($product, 100);
        ProductPriceRule::create([
            'product_id' => $product->id,
            'variant_id' => null,
            'starts_on' => self::DELIVERY_DATE,
            'ends_on' => self::DELIVERY_DATE,
            'unit_price' => 7.0, // override for that day (piece → canonical == display)
            'is_active' => true,
        ]);

        // Pin the request locale via Accept-Language and compute the expected
        // price strings under the same locale — formatPrice's unit symbol uses
        // the active app locale, so both must agree to be deterministic.
        app()->setLocale('en');
        $override = $product->unit->formatPrice(7.0, 'piece', 'en');
        $base = $product->unit->formatPrice(10.0, 'piece', 'en');

        $this->get(route('public.shop.index', ['date' => self::DELIVERY_DATE]), ['Accept-Language' => 'en'])
            ->assertOk()
            ->assertSee($override, false)   // override shows on the card
            ->assertDontSee($base, false);  // base price no longer shown
    }

    public function test_checkout_snapshots_grade_and_its_price(): void
    {
        $product = $this->pieceProduct(10.0);
        $thin = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'unit_price' => 5.0,
            'name' => ['de' => 'Dünn', 'en' => 'Thin', 'fr' => 'Fines'],
        ]);
        $this->inflow($product, 100);
        $this->inflow($product, 20, $thin->id);

        $order = app(ShopOrderService::class)->createOrder([
            'name' => 'Anna', 'email' => 'a@example.com',
            'fulfillment_mode' => 'pickup', 'pickup_location' => 'karl_kunger',
            'payment_method' => 'online', 'expected_delivery_date' => self::DELIVERY_DATE,
            'items' => [['product_id' => $product->id, 'quantity' => 4, 'variant_id' => $thin->id]],
        ]);

        $item = $order->items()->first();
        $this->assertSame($thin->id, $item->variant_id);
        $this->assertNotNull($item->variant_name_snapshot);
        $this->assertEquals(5.0, (float) $item->unit_price_snapshot_eur);
        $this->assertEquals(20.0, (float) $item->line_total_eur);
    }

    public function test_storefront_post_with_variant_quantities_creates_grade_line(): void
    {
        $product = $this->pieceProduct(10.0);
        $thin = ProductVariant::factory()->create(['product_id' => $product->id, 'unit_price' => 5.0]);
        $this->inflow($product, 100);
        $this->inflow($product, 20, $thin->id);

        $this->post(route('public.shop.store'), [
            'name' => 'Anna Mustermann',
            'email' => 'anna@example.com',
            'fulfillment_mode' => 'pickup',
            'pickup_location' => 'karl_kunger',
            'payment_method' => 'online',
            'expected_delivery_date' => self::DELIVERY_DATE,
            'variant_quantities' => [$thin->id => 3],
            'consent' => '1',
        ])->assertRedirect();

        $line = ShopOrderItem::query()->where('product_id', $product->id)->sole();
        $this->assertSame($thin->id, $line->variant_id);
        $this->assertEquals(5.0, (float) $line->unit_price_snapshot_eur);
    }

    public function test_checkout_rejects_quantity_above_grade_stock(): void
    {
        $product = $this->pieceProduct(10.0);
        $thin = ProductVariant::factory()->create(['product_id' => $product->id, 'unit_price' => 5.0]);
        $this->inflow($product, 100);        // plenty of default
        $this->inflow($product, 2, $thin->id); // but only 2 thin

        $this->post(route('public.shop.store'), [
            'name' => 'Anna Mustermann',
            'email' => 'anna@example.com',
            'fulfillment_mode' => 'pickup',
            'pickup_location' => 'karl_kunger',
            'payment_method' => 'online',
            'expected_delivery_date' => self::DELIVERY_DATE,
            'variant_quantities' => [$thin->id => 5],
            'consent' => '1',
        ])->assertSessionHasErrors();

        $this->assertSame(0, ShopOrderItem::query()->count());
    }

    public function test_inactive_variant_is_rejected_at_checkout(): void
    {
        $product = $this->pieceProduct(10.0);
        $thin = ProductVariant::factory()->create(['product_id' => $product->id, 'unit_price' => 5.0, 'is_active' => false]);
        $this->inflow($product, 100);
        $this->inflow($product, 20, $thin->id);

        $this->post(route('public.shop.store'), [
            'name' => 'Anna Mustermann',
            'email' => 'anna@example.com',
            'fulfillment_mode' => 'pickup',
            'pickup_location' => 'karl_kunger',
            'payment_method' => 'online',
            'expected_delivery_date' => self::DELIVERY_DATE,
            'variant_quantities' => [$thin->id => 3],
            'consent' => '1',
        ])->assertSessionHasErrors();

        $this->assertSame(0, ShopOrderItem::query()->count());
    }

    /** Minimal valid product-form payload (mass / €kg), with overrides. */
    private function productFormPayload(array $overrides = []): array
    {
        $t = ['name' => 'Form Apples', 'origin' => 'regional', 'variety' => '—', 'taste_profile' => 'x', 'short_description' => 'x'];

        return array_merge([
            'producer_id' => Producer::factory()->create()->id,
            'translations' => ['de' => $t, 'en' => $t, 'fr' => $t],
            'category' => 'produce',
            'unit' => 'mass',
            'price_display_unit' => 'kg',
            'price' => '20.00',
        ], $overrides);
    }

    public function test_product_form_creates_grade_and_business_price(): void
    {
        $admin = User::factory()->admin()->create();

        // Admin enters grade 7,50 €/kg and business 16,00 €/kg → canonical €/g.
        $this->actingAs($admin)
            ->post(route('app.admin.products.store'), $this->productFormPayload([
                'business_price' => '16.00',
                'variants' => [
                    ['id' => '', 'name' => ['de' => 'Dünn', 'en' => 'Thin', 'fr' => 'Fines'], 'unit_price' => '7.50', 'is_active' => '1'],
                    ['id' => '', 'name' => ['de' => '', 'en' => '', 'fr' => ''], 'unit_price' => ''], // blank row → ignored
                ],
            ]))->assertRedirect();

        $product = Product::query()->latest('id')->firstOrFail();

        $variant = ProductVariant::where('product_id', $product->id)->sole();
        $this->assertEqualsWithDelta(0.0075, (float) $variant->unit_price, 1e-6);
        $this->assertSame('Thin', $variant->localizedName('en'));

        $business = $product->priceRules()->where('customer_type', 'business')->sole();
        $this->assertEqualsWithDelta(0.0160, (float) $business->unit_price, 1e-6);
    }

    public function test_product_form_sets_per_grade_business_price(): void
    {
        $admin = User::factory()->admin()->create();

        // Grade base 7,50 €/kg, grade business 6,00 €/kg → canonical €/g.
        $this->actingAs($admin)
            ->post(route('app.admin.products.store'), $this->productFormPayload([
                'variants' => [
                    ['id' => '', 'name' => ['de' => 'Dünn', 'en' => 'Thin', 'fr' => 'Fines'], 'unit_price' => '7.50', 'business_price' => '6.00', 'is_active' => '1'],
                ],
            ]))->assertRedirect();

        $product = Product::query()->latest('id')->firstOrFail();
        $variant = ProductVariant::where('product_id', $product->id)->sole();

        $businessRule = $product->priceRules()
            ->where('customer_type', 'business')
            ->where('variant_id', $variant->id)
            ->sole();
        $this->assertEqualsWithDelta(0.0060, (float) $businessRule->unit_price, 1e-6);

        // The resolver applies it to a business order of that grade. Prices
        // resolve in canonical units (€/g), so 6,00 €/kg → 0,006 €/g.
        $resolver = app(PriceResolver::class);
        $product->refresh()->load('priceRules');
        $resolved = $resolver->resolve($product, Carbon::today(), CustomerType::Business, null, $variant->id, $variant->fresh());
        $this->assertEqualsWithDelta(0.006, $resolved->unitPriceEur, 1e-6);
        $this->assertSame('customer_group', $resolved->reason);
    }

    public function test_product_form_creates_quantity_tiers_in_canonical_units(): void
    {
        $admin = User::factory()->admin()->create();

        // Mass/kg product; admin enters tier "from 3 kg → 2,20 €/kg".
        $this->actingAs($admin)
            ->post(route('app.admin.products.store'), $this->productFormPayload([
                'tiers' => [
                    ['id' => '', 'min_quantity' => '3', 'unit_price' => '2.20'],
                    ['id' => '', 'min_quantity' => '', 'unit_price' => ''], // blank → ignored
                ],
            ]))->assertRedirect();

        $product = Product::query()->latest('id')->firstOrFail();
        $tier = $product->priceRules()->whereNotNull('min_quantity')->sole();
        // 3 kg → 3000 g canonical; 2,20 €/kg → 0,0022 €/g canonical.
        $this->assertEqualsWithDelta(3000.0, (float) $tier->min_quantity, 1e-6);
        $this->assertEqualsWithDelta(0.0022, (float) $tier->unit_price, 1e-6);
    }

    public function test_checkout_applies_a_quantity_tier(): void
    {
        $product = $this->pieceProduct(10.0);
        ProductPriceRule::create([
            'product_id' => $product->id,
            'min_quantity' => 5, // piece → canonical == count
            'unit_price' => 8.0,
            'is_active' => true,
        ]);
        $this->inflow($product, 100);

        $order = app(ShopOrderService::class)->createOrder([
            'name' => 'Anna', 'email' => 'a@example.com',
            'fulfillment_mode' => 'pickup', 'pickup_location' => 'karl_kunger',
            'payment_method' => 'online', 'expected_delivery_date' => self::DELIVERY_DATE,
            'items' => [['product_id' => $product->id, 'quantity' => 6]], // ≥ 5 → tier
        ]);

        $item = $order->items()->first();
        $this->assertEquals(8.0, (float) $item->unit_price_snapshot_eur);
        $this->assertEquals(48.0, (float) $item->line_total_eur);
    }

    public function test_product_form_removing_a_grade_row_deletes_it(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create([
            'unit' => ProductUnit::Mass->value, 'price_display_unit' => 'kg', 'unit_price' => 0.0200,
            'is_available' => true, 'is_published' => true,
        ]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'unit_price' => 0.0075]);

        // Submit the form with no variants rows → the grade is removed.
        $this->actingAs($admin)
            ->put(route('app.admin.products.update', $product), $this->productFormPayload([
                'producer_id' => $product->producer_id ?? Producer::factory()->create()->id,
                'variants' => [],
            ]))->assertRedirect();

        $this->assertDatabaseMissing('product_variants', ['id' => $variant->id]);
    }

    public function test_admin_records_inflow_against_a_grade(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->pieceProduct(10.0);
        $thin = ProductVariant::factory()->create(['product_id' => $product->id, 'unit_price' => 5.0]);

        $this->actingAs($admin)
            ->post(route('app.admin.inventory.inflows.store'), [
                'product_id' => $product->id,
                'variant_id' => $thin->id,
                'received_on' => self::DELIVERY_DATE,
                'quantity' => 12,
                'freshness_window_days' => 1,
            ])->assertRedirect();

        $inflow = ProductStockInflow::where('product_id', $product->id)->sole();
        $this->assertSame($thin->id, $inflow->variant_id);

        $inv = app(InventoryService::class);
        $date = Carbon::parse(self::DELIVERY_DATE);
        $this->assertSame(12.0, $inv->remainingForGrade($product, $date, $thin->id));
        $this->assertSame(0.0, $inv->remainingForGrade($product, $date, null));
    }

    public function test_admin_cannot_attach_a_foreign_grade_to_an_inflow(): void
    {
        $admin = User::factory()->admin()->create();
        $product = $this->pieceProduct(10.0);
        $other = $this->pieceProduct(8.0);
        $foreign = ProductVariant::factory()->create(['product_id' => $other->id]);

        $this->actingAs($admin)
            ->post(route('app.admin.inventory.inflows.store'), [
                'product_id' => $product->id,
                'variant_id' => $foreign->id,
                'received_on' => self::DELIVERY_DATE,
                'quantity' => 5,
                'freshness_window_days' => 1,
            ])->assertStatus(422);

        $this->assertSame(0, ProductStockInflow::query()->count());
    }
}
