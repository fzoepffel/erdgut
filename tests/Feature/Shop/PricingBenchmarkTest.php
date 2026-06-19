<?php

namespace Tests\Feature\Shop;

use App\Enums\CustomerType;
use App\Enums\ProductUnit;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductPriceRule;
use App\Models\ProductStockInflow;
use App\Models\ProductVariant;
use App\Services\Shop\PriceResolver;
use App\Services\Shop\ShopOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Full-system pricing benchmark — one product wired with every pricing
 * mechanism at once (base, business price, date override, quantity tier, all
 * also per grade), asserting the resolver, the /shop/quote ground-truth API
 * and checkout persistence all agree. Piece unit (canonical == display) keeps
 * the numbers clean.
 */
class PricingBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    private const NORMAL = '2026-06-15';   // ordinary day

    private const OVERRIDE = '2026-06-20'; // day with date overrides

    private Product $product;

    private ProductVariant $thin;   // grade with its own price + business + tier + override

    private ProductVariant $bundle; // grade with only a base price (no tier → no leak)

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-06-10 09:00:00'));

        $this->product = Product::factory()->create([
            'producer_id' => Producer::factory(),
            'category' => 'produce',
            'unit' => ProductUnit::Piece->value,
            'price_display_unit' => 'piece',
            'unit_price' => 10.0,
            'is_available' => true,
            'is_published' => true,
        ]);

        // Default-grade rules.
        ProductPriceRule::factory()->create(['product_id' => $this->product->id, 'customer_type' => CustomerType::Business->value, 'unit_price' => 8.0]);
        ProductPriceRule::factory()->create(['product_id' => $this->product->id, 'starts_on' => self::OVERRIDE, 'ends_on' => self::OVERRIDE, 'unit_price' => 7.0]);
        ProductPriceRule::factory()->create(['product_id' => $this->product->id, 'min_quantity' => 5, 'unit_price' => 6.0]);

        // "Thin" grade: own base + business + tier + date override.
        $this->thin = ProductVariant::factory()->create([
            'product_id' => $this->product->id, 'unit_price' => 6.0,
            'name' => ['de' => 'Dünn', 'en' => 'Thin', 'fr' => 'Fines'],
        ]);
        ProductPriceRule::factory()->create(['product_id' => $this->product->id, 'variant_id' => $this->thin->id, 'customer_type' => CustomerType::Business->value, 'unit_price' => 5.0]);
        ProductPriceRule::factory()->create(['product_id' => $this->product->id, 'variant_id' => $this->thin->id, 'min_quantity' => 4, 'unit_price' => 4.0]);
        ProductPriceRule::factory()->create(['product_id' => $this->product->id, 'variant_id' => $this->thin->id, 'starts_on' => self::OVERRIDE, 'ends_on' => self::OVERRIDE, 'unit_price' => 3.5]);

        // "Bundle" grade: base only — proves default tier does not leak to it.
        $this->bundle = ProductVariant::factory()->create([
            'product_id' => $this->product->id, 'unit_price' => 5.0,
            'name' => ['de' => 'Bund', 'en' => 'Bundle', 'fr' => 'Botte'],
        ]);

        // Stock for every grade on both days so checkout/quote can book them.
        foreach ([null, $this->thin->id, $this->bundle->id] as $vid) {
            foreach ([self::NORMAL, self::OVERRIDE] as $day) {
                ProductStockInflow::create([
                    'product_id' => $this->product->id, 'variant_id' => $vid,
                    'received_on' => $day, 'quantity' => 100, 'freshness_window_days' => 1,
                ]);
            }
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    private function resolve(string $date, CustomerType $type, float $qty, ?ProductVariant $variant = null): float
    {
        return app(PriceResolver::class)->resolve(
            $this->product->fresh()->load('priceRules'),
            Carbon::parse($date),
            $type,
            $qty,
            $variant?->id,
            $variant,
        )->unitPriceEur;
    }

    public function test_resolver_matrix(): void
    {
        $P = CustomerType::Private;
        $B = CustomerType::Business;

        // Default grade.
        $this->assertSame(10.0, $this->resolve(self::NORMAL, $P, 1), 'default/private/normal/low → base');
        $this->assertSame(6.0, $this->resolve(self::NORMAL, $P, 5), 'default/private/normal/qty≥5 → tier');
        $this->assertSame(8.0, $this->resolve(self::NORMAL, $B, 1), 'default/business → business price');
        $this->assertSame(7.0, $this->resolve(self::OVERRIDE, $P, 1), 'default/private/override day → date override');
        // Override (bounded window) outranks the tier on its day.
        $this->assertSame(7.0, $this->resolve(self::OVERRIDE, $P, 5), 'default/override day/qty≥5 → date override wins');

        // Thin grade.
        $this->assertSame(6.0, $this->resolve(self::NORMAL, $P, 1, $this->thin), 'thin/private/normal/low → grade base');
        $this->assertSame(4.0, $this->resolve(self::NORMAL, $P, 4, $this->thin), 'thin/private/normal/qty≥4 → grade tier');
        $this->assertSame(5.0, $this->resolve(self::NORMAL, $B, 1, $this->thin), 'thin/business → grade business price');
        $this->assertSame(3.5, $this->resolve(self::OVERRIDE, $P, 1, $this->thin), 'thin/override day → grade date override');

        // Bundle grade has no tier → the default tier must NOT leak to it.
        $this->assertSame(5.0, $this->resolve(self::NORMAL, $P, 10, $this->bundle), 'bundle/qty10 → grade base (no tier leak)');
    }

    public function test_quote_endpoint_matches_resolver(): void
    {
        // Normal day, private cart: default ×5 (tier 6 → 30) + thin ×4 (tier 4 → 16) = 46.
        $this->postJson(route('public.shop.quote'), [
            'fulfillment_mode' => 'pickup',
            'expected_delivery_date' => self::NORMAL,
            'quantities' => [$this->product->id => 5],
            'variant_quantities' => [$this->thin->id => 4],
        ])->assertOk()
            ->assertJsonPath('lines.'.$this->product->id.'.line_total_label', '30,00 €')
            ->assertJsonPath('lines.v'.$this->thin->id.'.line_total_label', '16,00 €')
            ->assertJsonPath('total_label', '46,00 €');

        // Override day, private: default ×5 → 7 each = 35; thin ×1 → 3,50.
        $this->postJson(route('public.shop.quote'), [
            'fulfillment_mode' => 'pickup',
            'expected_delivery_date' => self::OVERRIDE,
            'quantities' => [$this->product->id => 5],
            'variant_quantities' => [$this->thin->id => 1],
        ])->assertOk()
            ->assertJsonPath('lines.'.$this->product->id.'.line_total_label', '35,00 €')
            ->assertJsonPath('lines.v'.$this->thin->id.'.line_total_label', '3,50 €')
            ->assertJsonPath('total_label', '38,50 €');
    }

    public function test_checkout_persists_resolved_prices(): void
    {
        $order = app(ShopOrderService::class)->createOrder([
            'name' => 'Anna', 'email' => 'a@example.com',
            'fulfillment_mode' => 'pickup', 'pickup_location' => 'karl_kunger',
            'payment_method' => 'in_person', 'expected_delivery_date' => self::NORMAL,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 5],                          // default tier → 6
                ['product_id' => $this->product->id, 'quantity' => 4, 'variant_id' => $this->thin->id], // thin tier → 4
            ],
        ]);

        $default = $order->items->firstWhere('variant_id', null);
        $thin = $order->items->firstWhere('variant_id', $this->thin->id);

        $this->assertEqualsWithDelta(6.0, (float) $default->unit_price_snapshot_eur, 1e-6);
        $this->assertEqualsWithDelta(30.0, (float) $default->line_total_eur, 1e-6);
        $this->assertEqualsWithDelta(4.0, (float) $thin->unit_price_snapshot_eur, 1e-6);
        $this->assertEqualsWithDelta(16.0, (float) $thin->line_total_eur, 1e-6);
        $this->assertNotNull($thin->variant_name_snapshot);
        $this->assertEqualsWithDelta(46.0, (float) $order->items_total_eur, 1e-6);
    }
}
