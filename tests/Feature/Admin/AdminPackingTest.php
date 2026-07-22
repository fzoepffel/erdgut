<?php

namespace Tests\Feature\Admin;

use App\Enums\ProductUnit;
use App\Enums\ShopOrderStatus;
use App\Models\Product;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminPackingTest extends TestCase
{
    use RefreshDatabase;

    private const DAY = '2026-06-22';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-06-19 09:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    private function pieceProduct(): Product
    {
        return Product::factory()->create([
            'unit' => ProductUnit::Piece->value, 'price_display_unit' => 'piece', 'unit_price' => 3.0,
            'is_available' => true, 'is_published' => true,
        ]);
    }

    private function order(string $status = ShopOrderStatus::Paid->value, string $payment = 'online'): ShopOrder
    {
        return ShopOrder::create([
            'name' => 'Anna', 'email' => 'a@example.com',
            'fulfillment_mode' => 'pickup', 'pickup_location' => 'karl_kunger',
            'expected_delivery_date' => self::DAY,
            'payment_method' => $payment, 'status' => $status,
            'items_total_eur' => 0, 'total_amount_eur' => 0,
        ]);
    }

    private function item(ShopOrder $order, Product $product, float $qty): ShopOrderItem
    {
        return $order->items()->create([
            'product_id' => $product->id, 'quantity' => $qty, 'unit' => $product->unit->value,
            'unit_price_snapshot_eur' => 3.0, 'line_total_eur' => 3.0 * $qty,
            'vat_rate_snapshot_percent' => 7, 'vat_amount_eur' => 0,
        ]);
    }

    public function test_requires_admin(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('app.admin.packing.index', ['date' => self::DAY]))
            ->assertForbidden();
    }

    public function test_index_groups_two_orders_by_shared_product(): void
    {
        $carrots = $this->pieceProduct();
        $o1 = $this->order();
        $o2 = $this->order();
        $this->item($o1, $carrots, 2);
        $this->item($o2, $carrots, 1);

        $response = $this->actingAs($this->admin())
            ->get(route('app.admin.packing.index', ['date' => self::DAY]))
            ->assertOk();

        $products = collect($response->viewData('products'));
        $this->assertCount(1, $products);
        $group = $products->first();
        $this->assertSame(2, $group['total_count']);   // two order lines
        $this->assertSame(0, $group['packed_count']);
        $this->assertCount(2, $group['lines']);
    }

    public function test_toggle_item_persists_packed_state(): void
    {
        $admin = $this->admin();
        $item = $this->item($this->order(), $this->pieceProduct(), 2);

        $this->actingAs($admin)
            ->postJson(route('app.admin.packing.toggle', $item))
            ->assertOk()
            ->assertJsonPath('packed', true)
            ->assertJsonPath('order.ready', true);

        $item->refresh();
        $this->assertNotNull($item->packed_at);
        $this->assertSame($admin->id, $item->packed_by_user_id);

        // Toggling again unpacks it.
        $this->actingAs($admin)
            ->postJson(route('app.admin.packing.toggle', $item))
            ->assertJsonPath('packed', false);
        $this->assertNull($item->fresh()->packed_at);
    }

    public function test_pack_product_marks_all_lines_across_orders(): void
    {
        $carrots = $this->pieceProduct();
        $o1 = $this->order();
        $o2 = $this->order();
        $i1 = $this->item($o1, $carrots, 2);
        $i2 = $this->item($o2, $carrots, 1);

        $this->actingAs($this->admin())
            ->postJson(route('app.admin.packing.product'), [
                'date' => self::DAY, 'product_id' => $carrots->id,
            ])->assertOk();

        $this->assertNotNull($i1->fresh()->packed_at);
        $this->assertNotNull($i2->fresh()->packed_at);
    }

    public function test_order_ready_when_all_items_packed_but_status_unchanged(): void
    {
        $product = $this->pieceProduct();
        $other = $this->pieceProduct();
        $order = $this->order();
        $a = $this->item($order, $product, 2);
        $b = $this->item($order, $other, 1);

        $admin = $this->admin();
        $this->actingAs($admin)->postJson(route('app.admin.packing.toggle', $a))->assertJsonPath('order.ready', false);
        $this->actingAs($admin)->postJson(route('app.admin.packing.toggle', $b))->assertJsonPath('order.ready', true);

        // Packing does not change the order status.
        $this->assertSame(ShopOrderStatus::Paid, $order->fresh()->status);
    }

    public function test_index_groups_products_by_client(): void
    {
        $carrots = $this->pieceProduct();
        $potatoes = $this->pieceProduct();
        $anna = $this->order();
        $bob = $this->order();
        $this->item($anna, $carrots, 2);
        $this->item($anna, $potatoes, 1);
        $this->item($bob, $carrots, 3);

        $response = $this->actingAs($this->admin())
            ->get(route('app.admin.packing.index', ['date' => self::DAY]))
            ->assertOk();

        $clients = collect($response->viewData('clients'));
        $this->assertCount(2, $clients); // one entry per order

        $annaClient = $clients->firstWhere('id', $anna->id);
        $this->assertSame('s'.$anna->id, $annaClient['key']);
        $this->assertSame(2, $annaClient['total_count']); // both her products
        $this->assertSame(0, $annaClient['packed_count']);
        $this->assertCount(2, $annaClient['lines']);

        $bobClient = $clients->firstWhere('id', $bob->id);
        $this->assertSame(1, $bobClient['total_count']);
    }

    public function test_pack_order_marks_all_lines_of_that_order_only(): void
    {
        $carrots = $this->pieceProduct();
        $potatoes = $this->pieceProduct();
        $anna = $this->order();
        $bob = $this->order();
        $a1 = $this->item($anna, $carrots, 2);
        $a2 = $this->item($anna, $potatoes, 1);
        $b1 = $this->item($bob, $carrots, 3);

        $this->actingAs($this->admin())
            ->postJson(route('app.admin.packing.order'), ['order' => 's'.$anna->id])
            ->assertOk()
            ->assertJsonPath('order.ready', true);

        // Anna's whole order is packed…
        $this->assertNotNull($a1->fresh()->packed_at);
        $this->assertNotNull($a2->fresh()->packed_at);
        // …but Bob's order is untouched.
        $this->assertNull($b1->fresh()->packed_at);
    }

    public function test_pack_order_rejects_unpackable_order(): void
    {
        $fulfilled = $this->order(ShopOrderStatus::Fulfilled->value);
        $this->item($fulfilled, $this->pieceProduct(), 1);

        $this->actingAs($this->admin())
            ->postJson(route('app.admin.packing.order'), ['order' => 's'.$fulfilled->id])
            ->assertNotFound();
    }

    public function test_excludes_fulfilled_and_unpaid_online_orders(): void
    {
        $product = $this->pieceProduct();
        // Fulfilled (done) and an unpaid online order both sit on the day…
        $this->item($this->order(ShopOrderStatus::Fulfilled->value), $product, 1);
        $this->item($this->order(ShopOrderStatus::PendingPayment->value, 'online'), $product, 1);
        // …and one genuinely packable order.
        $this->item($this->order(ShopOrderStatus::Paid->value), $product, 1);

        $response = $this->actingAs($this->admin())
            ->get(route('app.admin.packing.index', ['date' => self::DAY]))
            ->assertOk();

        $orders = collect($response->viewData('orders'));
        $this->assertCount(1, $orders); // only the Paid one is packable
    }
}
