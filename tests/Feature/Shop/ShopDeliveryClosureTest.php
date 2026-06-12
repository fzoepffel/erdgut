<?php

namespace Tests\Feature\Shop;

use App\Enums\ProductUnit;
use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Models\ShopDeliveryClosure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ShopDeliveryClosureTest extends TestCase
{
    use RefreshDatabase;

    private const DELIVERY_DATE = '2026-06-05';

    private const OPEN_DATE = '2026-06-06';

    private Product $product;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-05-30 10:00:00'));

        $this->admin = User::factory()->admin()->create();

        $this->product = Product::factory()->create([
            'unit' => ProductUnit::Piece->value,
            'price_display_unit' => 'piece',
            'unit_price' => 4.00,
            'is_available' => true,
            'is_published' => true,
        ]);

        foreach ([self::DELIVERY_DATE, self::OPEN_DATE] as $date) {
            ProductStockInflow::create([
                'product_id' => $this->product->id,
                'received_on' => $date,
                'quantity' => 10,
                'freshness_window_days' => 1,
            ]);
        }

        ShopDeliveryClosure::query()->create([
            'starts_on' => self::DELIVERY_DATE,
            'ends_on' => self::DELIVERY_DATE,
            'created_by_user_id' => $this->admin->id,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    public function test_closed_date_absent_from_storefront_date_picker(): void
    {
        $response = $this->get(route('public.shop.index'));

        $response->assertOk();
        $response->assertSee(self::OPEN_DATE, false);
        $response->assertDontSee('value="'.self::DELIVERY_DATE.'"', false);
    }

    public function test_store_rejects_order_on_closed_date(): void
    {
        $this->post(route('public.shop.store'), [
            'name' => 'Anna Mustermann',
            'email' => 'anna@example.com',
            'fulfillment_mode' => 'pickup',
            'pickup_location' => 'karl_kunger',
            'payment_method' => 'online',
            'expected_delivery_date' => self::DELIVERY_DATE,
            'quantities' => [$this->product->id => 1],
            'consent' => '1',
        ])->assertSessionHasErrors('expected_delivery_date');
    }

    public function test_admin_can_create_order_on_closed_date_when_stock_exists(): void
    {
        $this->actingAs($this->admin)
            ->post(route('app.admin.shop-orders.store'), [
                'fulfillment_mode' => 'pickup',
                'pickup_location' => 'karl_kunger',
                'expected_delivery_date' => self::DELIVERY_DATE,
                'customer_type' => 'private',
                'items' => [['product_id' => $this->product->id, 'quantity' => 2]],
            ])
            ->assertRedirect();
    }

    public function test_admin_closure_create_rejects_overlapping_range(): void
    {
        $this->actingAs($this->admin)
            ->post(route('app.admin.shop-closures.store'), [
                'starts_on' => self::DELIVERY_DATE,
                'ends_on' => self::DELIVERY_DATE,
            ])
            ->assertSessionHasErrors('starts_on');
    }

    public function test_storefront_falls_back_when_requested_date_is_closed(): void
    {
        $this->get(route('public.shop.index', ['date' => self::DELIVERY_DATE]))
            ->assertOk()
            ->assertSee($this->product->name);
    }
}
