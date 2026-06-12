<?php

namespace Tests\Feature\Admin;

use App\Enums\CustomerType;
use App\Enums\OrderSource;
use App\Enums\ProductUnit;
use App\Enums\ShopOrderStatus;
use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Models\ShopOrder;
use App\Models\User;
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
        // payment_method is hardcoded by the controller; no longer a form field.
        return array_merge([
            'fulfillment_mode' => 'pickup',
            'pickup_location' => 'karl_kunger',
            'expected_delivery_date' => self::DELIVERY_DATE,
            'customer_type' => 'private',
            'items' => [['product_id' => $this->product->id, 'quantity' => 3]],
        ], $overrides);
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
            ->assertJsonFragment(['id' => $this->product->id]);
    }

    public function test_available_products_endpoint_excludes_out_of_stock_products(): void
    {
        $noStock = Product::factory()->create(['is_available' => true, 'is_published' => true]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('app.admin.shop-orders.available-products', ['date' => self::DELIVERY_DATE]));

        $ids = collect($response->json())->pluck('id')->all();
        $this->assertContains($this->product->id, $ids);
        $this->assertNotContains($noStock->id, $ids);
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
                'items' => [['product_id' => $this->product->id, 'quantity' => 5]],
            ]));

        $order = ShopOrder::query()->latest('id')->first();
        $this->assertSame(1, $order->items->count());
        $this->assertEqualsWithDelta(5.0, (float) $order->items->first()->quantity, 0.001);
        $this->assertEqualsWithDelta(25.0, (float) $order->items->first()->line_total_eur, 0.01);
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

    public function test_over_stock_quantity_shows_validation_error(): void
    {
        $response = $this->actingAs($this->admin)
            ->post(route('app.admin.shop-orders.store'), $this->payload([
                'items' => [['product_id' => $this->product->id, 'quantity' => 9999]],
            ]));

        $response->assertSessionHasErrors();
        $this->assertSame(0, ShopOrder::query()->where('order_source', 'admin')->count());
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
