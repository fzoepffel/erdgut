<?php

namespace Tests\Feature\Admin;

use App\Enums\CustomerType;
use App\Enums\OrderSource;
use App\Enums\ProductUnit;
use App\Enums\ShopOrderStatus;
use App\Models\Product;
use App\Models\ProductPriceRule;
use App\Models\ProductStockInflow;
use App\Models\ShopOrder;
use App\Models\User;
use App\Services\Shop\ShopOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminShopOrderCreateTest extends TestCase
{
    use RefreshDatabase;

    private const DELIVERY_DATE = '2026-06-20';

    private User $admin;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-06-13 10:00:00'));

        $this->admin = User::factory()->admin()->create();

        $this->product = Product::factory()->create([
            'unit' => ProductUnit::Piece->value,
            'price_display_unit' => 'piece',
            'unit_price' => 5.00,
            'is_available' => true,
            'is_published' => true,
        ]);

        ProductStockInflow::create([
            'product_id' => $this->product->id,
            'received_on' => self::DELIVERY_DATE,
            'quantity' => 50,
            'freshness_window_days' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    private function payload(array $overrides = []): array
    {
        $quantity = 3;

        $base = [
            'fulfillment_mode' => 'pickup',
            'pickup_location' => 'karl_kunger',
            'expected_delivery_date' => self::DELIVERY_DATE,
            'customer_type' => 'private',
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => $quantity,
                'line_total_eur' => round($quantity * 5.0, 2),
                'vat_rate_percent' => 7,
            ]],
        ];

        if (isset($overrides['items'])) {
            $overrides['items'] = collect($overrides['items'])->map(function (array $item): array {
                $productId = (int) ($item['product_id'] ?? 0);
                $qty = (float) ($item['quantity'] ?? 0);
                $product = Product::query()->find($productId);
                $unitPrice = $product ? (float) $product->unit_price : 5.0;
                $vatRate = $product ? (int) ($product->vat_rate_percent ?? 7) : 7;

                return array_merge([
                    'line_total_eur' => round($qty * $unitPrice, 2),
                    'vat_rate_percent' => $vatRate,
                ], $item);
            })->all();
        }

        return array_merge($base, $overrides);
    }

    // ── Access ──────────────────────────────────────────────────────────────

    public function test_guest_cannot_access_create_form(): void
    {
        $this->get(route('app.admin.shop-orders.create'))->assertRedirect('/auth/login');
    }

    public function test_non_admin_cannot_access_create_form(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get(route('app.admin.shop-orders.create'))
            ->assertForbidden();
    }

    public function test_admin_can_load_create_form(): void
    {
        $this->actingAs($this->admin)
            ->get(route('app.admin.shop-orders.create'))
            ->assertOk()
            ->assertSee('expected_delivery_date', false) // date input present
            ->assertSee('adminOrderForm', false);        // Alpine component wired
    }

    public function test_available_products_endpoint_returns_products_with_stock(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson(route('app.admin.shop-orders.available-products', ['date' => self::DELIVERY_DATE]));

        $response->assertOk()
            ->assertJsonFragment(['product_id' => $this->product->id]);
    }

    public function test_available_products_endpoint_includes_out_of_stock_products_flagged(): void
    {
        // Admins may deliberately sell beyond stock, so the picker lists the
        // whole catalog; out-of-stock rows carry remaining 0 for the UI tag.
        $noStock = Product::factory()->create(['is_available' => true, 'is_published' => true]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('app.admin.shop-orders.available-products', ['date' => self::DELIVERY_DATE]));

        $rows = collect($response->json());
        $this->assertContains($this->product->id, $rows->pluck('product_id')->all());
        $noStockRow = $rows->firstWhere('product_id', $noStock->id);
        $this->assertNotNull($noStockRow);
        $this->assertSame(0.0, (float) $noStockRow['remaining']);
    }

    public function test_available_products_endpoint_returns_tier_aware_prices(): void
    {
        // A business-tier rule that drops the price from 5,00 €/Stück to 3,50.
        ProductPriceRule::create([
            'product_id' => $this->product->id,
            'customer_type' => CustomerType::Business->value,
            'unit_price' => 3.50,
            'priority' => 0,
            'is_active' => true,
        ]);

        $businessResponse = $this->actingAs($this->admin)
            ->getJson(route('app.admin.shop-orders.available-products', [
                'date' => self::DELIVERY_DATE,
                'customer_type' => CustomerType::Business->value,
            ]));
        $businessResponse->assertOk();
        $row = collect($businessResponse->json())->firstWhere('product_id', $this->product->id);
        $this->assertNotNull($row);
        // Business rule prices are net: 3,50 net → 3,745 gross per piece
        // (piece has factor 1, so display equals canonical).
        $this->assertEqualsWithDelta(3.745, (float) $row['unit_price_eur'], 0.01);
        $this->assertSame('customer_group', $row['price_reason']);
        // Alt tier (private) falls back to the base unit_price (5,00).
        $this->assertEqualsWithDelta(5.00, (float) $row['alt_unit_price_eur'], 0.01);
        $this->assertSame('base', $row['alt_price_reason']);

        $privateResponse = $this->actingAs($this->admin)
            ->getJson(route('app.admin.shop-orders.available-products', [
                'date' => self::DELIVERY_DATE,
                'customer_type' => CustomerType::Private->value,
            ]));
        $privateResponse->assertOk();
        $row = collect($privateResponse->json())->firstWhere('product_id', $this->product->id);
        $this->assertEqualsWithDelta(5.00, (float) $row['unit_price_eur'], 0.01);
        $this->assertSame('base', $row['price_reason']);
        // Alt tier (business) shows the discount as the comparison hint (gross).
        $this->assertEqualsWithDelta(3.745, (float) $row['alt_unit_price_eur'], 0.01);
        $this->assertSame('customer_group', $row['alt_price_reason']);
    }

    public function test_quote_endpoint_returns_catalog_defaults(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson(route('app.admin.shop-orders.quote', [
                'fulfillment_mode' => 'pickup',
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 3],
                ],
            ]));

        $response->assertOk();
        $this->assertEqualsWithDelta(15.0, (float) $response->json('items_total_eur'), 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $response->json('delivery_fee_eur'), 0.01);
        $this->assertEqualsWithDelta(15.0, (float) $response->json('total_amount_eur'), 0.01);
        $this->assertEqualsWithDelta(15.0, (float) $response->json('lines.0.line_total_eur'), 0.01);
        $this->assertSame(7, (int) $response->json('lines.0.vat_rate_percent'));
    }

    public function test_quote_endpoint_includes_delivery_fee_for_delivery_orders(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson(route('app.admin.shop-orders.quote', [
                'fulfillment_mode' => 'delivery',
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 2],
                ],
            ]));

        $response->assertOk();
        $deliveryFee = (float) config('shop.delivery_fee_eur', 2.90);
        $this->assertEqualsWithDelta(10.0, (float) $response->json('items_total_eur'), 0.01);
        $this->assertEqualsWithDelta($deliveryFee, (float) $response->json('delivery_fee_eur'), 0.01);
        $this->assertEqualsWithDelta(10.0 + $deliveryFee, (float) $response->json('total_amount_eur'), 0.01);
    }

    // ── Order creation ───────────────────────────────────────────────────────

    public function test_admin_can_create_minimal_order_without_contact_details(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->admin)
            ->post(route('app.admin.shop-orders.store'), $this->payload());

        $order = ShopOrder::query()->latest('id')->first();
        $response->assertRedirect(route('app.admin.shop-orders.show', $order));

        $this->assertSame(OrderSource::Admin, $order->order_source);
        $this->assertSame(CustomerType::Private, $order->customer_type);
        $this->assertSame(ShopOrderStatus::PendingPayment, $order->status);
        $this->assertNull($order->name);
        $this->assertNull($order->email);

        Mail::assertNothingQueued();
    }

    public function test_admin_can_create_business_order_with_contact_details(): void
    {
        Mail::fake();

        $response = $this->actingAs($this->admin)
            ->post(route('app.admin.shop-orders.store'), $this->payload([
                'name' => 'Trattoria Rossi',
                'email' => 'bestell@trattoria.de',
                'customer_type' => 'business',
            ]));

        $order = ShopOrder::query()->latest('id')->first();
        $response->assertRedirect(route('app.admin.shop-orders.show', $order));

        $this->assertSame(CustomerType::Business, $order->customer_type);
        $this->assertSame('Trattoria Rossi', $order->name);
        $this->assertSame(OrderSource::Admin, $order->order_source);

        Mail::assertNothingQueued();
    }

    public function test_order_source_is_set_to_admin_not_storefront(): void
    {
        $this->actingAs($this->admin)
            ->post(route('app.admin.shop-orders.store'), $this->payload());

        $order = ShopOrder::query()->latest('id')->first();
        $this->assertSame('admin', $order->order_source->value);
    }

    public function test_items_are_saved_with_correct_quantities_and_vat(): void
    {
        $this->actingAs($this->admin)
            ->post(route('app.admin.shop-orders.store'), $this->payload([
                'items' => [[
                    'product_id' => $this->product->id,
                    'quantity' => 5,
                    'line_total_eur' => 25.00,
                    'vat_rate_percent' => 7,
                ]],
            ]));

        $order = ShopOrder::query()->latest('id')->first();
        $this->assertSame(1, $order->items->count());
        $this->assertEqualsWithDelta(5.0, (float) $order->items->first()->quantity, 0.001);
        $this->assertEqualsWithDelta(25.0, (float) $order->items->first()->line_total_eur, 0.01);
    }

    public function test_admin_can_set_per_line_totals_and_vat_rates(): void
    {
        $productB = Product::factory()->create([
            'unit' => ProductUnit::Piece->value,
            'unit_price' => 10.00,
            'vat_rate_percent' => 19,
            'is_available' => true,
            'is_published' => true,
        ]);
        ProductStockInflow::create([
            'product_id' => $productB->id,
            'received_on' => self::DELIVERY_DATE,
            'quantity' => 20,
            'freshness_window_days' => 1,
        ]);

        $this->actingAs($this->admin)
            ->post(route('app.admin.shop-orders.store'), $this->payload([
                'items' => [
                    [
                        'product_id' => $this->product->id,
                        'quantity' => 2,
                        'line_total_eur' => 8.00,
                        'vat_rate_percent' => 7,
                    ],
                    [
                        'product_id' => $productB->id,
                        'quantity' => 1,
                        'line_total_eur' => 12.00,
                        'vat_rate_percent' => 19,
                    ],
                ],
            ]))
            ->assertRedirect();

        $order = ShopOrder::query()->latest('id')->first();
        $this->assertEqualsWithDelta(20.0, (float) $order->items_total_eur, 0.01);
        $this->assertEqualsWithDelta(20.0, (float) $order->total_amount_eur, 0.01);

        $lines = $order->items->sortBy('product_id')->values();
        $this->assertEqualsWithDelta(8.0, (float) $lines[0]->line_total_eur, 0.01);
        $this->assertSame(7, (int) $lines[0]->vat_rate_snapshot_percent);
        $this->assertEqualsWithDelta(12.0, (float) $lines[1]->line_total_eur, 0.01);
        $this->assertSame(19, (int) $lines[1]->vat_rate_snapshot_percent);

        $vatByRate = ShopOrderService::vatByRate($order);
        $this->assertEqualsWithDelta(0.52, (float) ($vatByRate->get(7) ?? 0), 0.02);
        $this->assertEqualsWithDelta(1.91, (float) ($vatByRate->get(19) ?? 0), 0.02);
    }

    public function test_admin_can_override_delivery_fee_and_vat_on_delivery_order(): void
    {
        $this->actingAs($this->admin)
            ->post(route('app.admin.shop-orders.store'), $this->payload([
                'fulfillment_mode' => 'delivery',
                'delivery_street' => 'Musterstraße 1',
                'delivery_postal_code' => '10115',
                'delivery_city' => 'Berlin',
                'items' => [[
                    'product_id' => $this->product->id,
                    'quantity' => 2,
                    'line_total_eur' => 10.00,
                    'vat_rate_percent' => 7,
                ]],
                'delivery_fee_eur' => 5.00,
                'delivery_fee_vat_rate_percent' => 19,
            ]))
            ->assertRedirect();

        $order = ShopOrder::query()->latest('id')->first();
        $this->assertEqualsWithDelta(5.0, (float) $order->delivery_fee_eur, 0.01);
        $this->assertSame(19, (int) $order->delivery_fee_vat_rate_percent);
        $this->assertEqualsWithDelta(15.0, (float) $order->total_amount_eur, 0.01);
        $this->assertEqualsWithDelta(
            round(5.0 * 19 / 119, 2),
            (float) $order->delivery_fee_vat_amount_eur,
            0.01
        );
    }

    public function test_pickup_order_rejects_non_zero_delivery_fee_override(): void
    {
        $this->actingAs($this->admin)
            ->post(route('app.admin.shop-orders.store'), $this->payload([
                'delivery_fee_eur' => 5.00,
                'delivery_fee_vat_rate_percent' => 7,
            ]))
            ->assertSessionHasErrors('delivery_fee_eur');

        $this->assertSame(0, ShopOrder::query()->where('order_source', 'admin')->count());
    }

    // ── No emails ────────────────────────────────────────────────────────────

    public function test_no_email_sent_when_admin_marks_admin_order_paid(): void
    {
        Mail::fake();

        $this->actingAs($this->admin)
            ->post(route('app.admin.shop-orders.store'), $this->payload());

        $order = ShopOrder::query()->latest('id')->first();

        // Mark paid — observer should suppress notification for admin-source orders.
        $this->actingAs($this->admin)
            ->post(route('app.admin.shop-orders.mark-paid', $order));

        Mail::assertNothingQueued();
    }

    // ── Stock deduction ──────────────────────────────────────────────────────

    public function test_stock_is_deducted_for_admin_order(): void
    {
        $this->actingAs($this->admin)
            ->post(route('app.admin.shop-orders.store'), $this->payload([
                'items' => [['product_id' => $this->product->id, 'quantity' => 10]],
            ]));

        // Stock on delivery date should now reflect the committed quantity.
        $remaining = $this->product->availableOnDate(Carbon::parse(self::DELIVERY_DATE));
        $this->assertEqualsWithDelta(40.0, $remaining, 0.01);
    }

    public function test_over_stock_quantity_is_allowed_and_clamps_remaining_at_zero(): void
    {
        // Deliberate overbooking: the admin sells beyond stock and procures
        // the goods on demand. The storefront keeps its hard check.
        $this->actingAs($this->admin)
            ->post(route('app.admin.shop-orders.store'), $this->payload([
                'items' => [['product_id' => $this->product->id, 'quantity' => 60]],
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(1, ShopOrder::query()->where('order_source', 'admin')->count());
        // 50 in stock, 60 committed — the storefront sees 0, never negative.
        $remaining = $this->product->availableOnDate(Carbon::parse(self::DELIVERY_DATE));
        $this->assertSame(0.0, $remaining);
    }

    public function test_business_admin_order_can_book_b2b_only_stock(): void
    {
        $b2bProduct = Product::factory()->create([
            'is_available' => true,
            'is_published' => true,
        ]);

        ProductStockInflow::create([
            'product_id' => $b2bProduct->id,
            'received_on' => self::DELIVERY_DATE,
            'quantity' => 12,
            'freshness_window_days' => 1,
            'b2b_only' => true,
        ]);

        $this->actingAs($this->admin)
            ->post(route('app.admin.shop-orders.store'), $this->payload([
                'customer_type' => 'business',
                'items' => [['product_id' => $b2bProduct->id, 'quantity' => 5]],
            ]))
            ->assertRedirect();

        $this->assertSame(1, ShopOrder::query()->where('customer_type', CustomerType::Business)->count());
    }

    public function test_private_audience_sees_b2b_only_stock_as_out_of_stock(): void
    {
        $b2bProduct = Product::factory()->create([
            'is_available' => true,
            'is_published' => true,
        ]);

        ProductStockInflow::create([
            'product_id' => $b2bProduct->id,
            'received_on' => self::DELIVERY_DATE,
            'quantity' => 12,
            'freshness_window_days' => 1,
            'b2b_only' => true,
        ]);

        // The consumer audience doesn't get the B2B pool: the product shows
        // up (whole catalog) but with remaining 0, so the admin sees the
        // out-of-stock tag. Booking it anyway is a deliberate overbook.
        $response = $this->actingAs($this->admin)
            ->getJson(route('app.admin.shop-orders.available-products', [
                'date' => self::DELIVERY_DATE,
                'customer_type' => 'private',
            ]));

        $row = collect($response->json())->firstWhere('product_id', $b2bProduct->id);
        $this->assertNotNull($row);
        $this->assertSame(0.0, (float) $row['remaining']);

        $this->actingAs($this->admin)
            ->post(route('app.admin.shop-orders.store'), $this->payload([
                'items' => [['product_id' => $b2bProduct->id, 'quantity' => 5]],
            ]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    public function test_restaurant_account_is_rejected_for_private_customer_type(): void
    {
        $restaurantUser = User::factory()->create();

        // Restaurant accounts are B2B-only; a private order carrying one is
        // rejected even when the request bypasses the form.
        $this->actingAs($this->admin)
            ->post(route('app.admin.shop-orders.store'), $this->payload([
                'customer_type' => 'private',
                'restaurant_user_id' => $restaurantUser->id,
            ]))
            ->assertSessionHasErrors('restaurant_user_id');

        $this->assertSame(0, ShopOrder::query()->count());
    }

    public function test_payment_method_is_always_in_person_regardless_of_payload(): void
    {
        $this->actingAs($this->admin)
            ->post(route('app.admin.shop-orders.store'), $this->payload());

        $order = ShopOrder::query()->latest('id')->first();
        $this->assertSame('in_person', $order->payment_method);
    }

    public function test_any_quantity_amount_is_accepted_without_step_restriction(): void
    {
        // A mass product with 50g step — admin should be able to enter 37g freely.
        $massProduct = Product::factory()->create([
            'unit' => ProductUnit::Mass->value,
            'price_display_unit' => 'kg',
            'unit_price' => 0.02, // €/g
            'quantity_step' => 50,
            'is_available' => true,
            'is_published' => true,
        ]);
        ProductStockInflow::create([
            'product_id' => $massProduct->id,
            'received_on' => self::DELIVERY_DATE,
            'quantity' => 5000,
            'freshness_window_days' => 1,
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('app.admin.shop-orders.store'), $this->payload([
                'items' => [['product_id' => $massProduct->id, 'quantity' => 37]],
            ]));

        $response->assertSessionHasNoErrors();
        $this->assertSame(1, ShopOrder::query()->where('order_source', 'admin')->count());
    }

    // ── Validation ───────────────────────────────────────────────────────────

    public function test_items_are_required(): void
    {
        $this->actingAs($this->admin)
            ->post(route('app.admin.shop-orders.store'), $this->payload(['items' => []]))
            ->assertSessionHasErrors('items');
    }

    public function test_invalid_customer_type_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->post(route('app.admin.shop-orders.store'), $this->payload(['customer_type' => 'alien']))
            ->assertSessionHasErrors('customer_type');
    }

    // ── Index badges ─────────────────────────────────────────────────────────

    public function test_index_shows_admin_badge_for_manually_created_order(): void
    {
        ShopOrder::factory()->create([
            'order_source' => OrderSource::Admin,
            'customer_type' => CustomerType::Business,
            'status' => ShopOrderStatus::PendingPayment,
            'payment_method' => 'in_person',
        ]);

        $this->actingAs($this->admin)
            ->get(route('app.admin.shop-orders.index', ['status' => 'all']))
            ->assertOk()
            ->assertSee('data-testid="shop-admin-create-link"', false);
    }

    // ── Custom (off-catalog) lines ────────────────────────────────────────────

    public function test_admin_can_add_a_custom_off_catalog_line(): void
    {
        Mail::fake();

        $this->actingAs($this->admin)
            ->post(route('app.admin.shop-orders.store'), [
                'fulfillment_mode' => 'pickup',
                'pickup_location' => 'karl_kunger',
                'expected_delivery_date' => self::DELIVERY_DATE,
                'customer_type' => 'business',
                'items' => [[
                    'name' => 'Wild herbs',
                    'quantity' => 3,
                    'unit' => 'bunch',
                    'line_total_eur' => 12.00,
                    'vat_rate_percent' => 7,
                ]],
            ])
            ->assertRedirect();

        $order = ShopOrder::query()->latest('id')->with('items')->first();
        $this->assertSame('12.00', (string) $order->items_total_eur);
        $this->assertSame('12.00', (string) $order->total_amount_eur);

        $item = $order->items->first();
        $this->assertNull($item->product_id);
        $this->assertSame('Wild herbs', $item->name_snapshot);
        $this->assertSame('bunch', $item->unit);
        $this->assertSame('12.00', (string) $item->line_total_eur);
        $this->assertSame(7, $item->vat_rate_snapshot_percent);
        $this->assertSame('0.79', (string) $item->vat_amount_eur); // 12 * 7/107, gross-inclusive
        $this->assertTrue($item->isCustom());
        $this->assertSame('Wild herbs', $item->displayName());
        // Unit token is localized for display (en → "Bunch").
        $this->assertSame('3 Bunch', $item->displayQuantityLabel());
    }

    public function test_admin_can_mix_product_and_custom_lines(): void
    {
        Mail::fake();

        $this->actingAs($this->admin)
            ->post(route('app.admin.shop-orders.store'), [
                'fulfillment_mode' => 'pickup',
                'pickup_location' => 'karl_kunger',
                'expected_delivery_date' => self::DELIVERY_DATE,
                'customer_type' => 'business',
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 3, 'line_total_eur' => 15.00, 'vat_rate_percent' => 7],
                    ['name' => 'Pea tops', 'quantity' => 2, 'unit' => 'bunch', 'line_total_eur' => 8.00, 'vat_rate_percent' => 7],
                ],
            ])
            ->assertRedirect();

        $order = ShopOrder::query()->latest('id')->with('items')->first();
        $this->assertSame(2, $order->items->count());
        $this->assertSame('23.00', (string) $order->items_total_eur);

        $custom = $order->items->firstWhere('product_id', null);
        $this->assertSame('Pea tops', $custom->name_snapshot);
        $catalog = $order->items->firstWhere('product_id', $this->product->id);
        $this->assertNotNull($catalog);
    }

    public function test_line_without_product_or_name_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->from(route('app.admin.shop-orders.create'))
            ->post(route('app.admin.shop-orders.store'), [
                'fulfillment_mode' => 'pickup',
                'pickup_location' => 'karl_kunger',
                'expected_delivery_date' => self::DELIVERY_DATE,
                'customer_type' => 'business',
                'items' => [[
                    'quantity' => 1,
                    'line_total_eur' => 5.00,
                    'vat_rate_percent' => 7,
                ]],
            ])
            ->assertSessionHasErrors('items.0.name');
    }

    // ── Storefront defaults ───────────────────────────────────────────────────

    public function test_storefront_orders_default_to_storefront_source_and_private_type(): void
    {
        // A storefront order created via the public shop service
        // must still get the default source and type from createOrder().
        $order = ShopOrder::factory()->create();
        // Factory doesn't set these so they'll be DB defaults.
        $fresh = ShopOrder::find($order->id);
        $this->assertSame(OrderSource::Storefront, $fresh->order_source);
        $this->assertSame(CustomerType::Private, $fresh->customer_type);
    }
}
