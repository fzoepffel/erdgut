<?php

namespace Tests\Feature\Admin;

use App\Enums\ProductUnit;
use App\Enums\ShopOrderStatus;
use App\Models\Producer;
use App\Models\ProducerPositionOverride;
use App\Models\ProducerReceipt;
use App\Models\Product;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminProducerReceivingTest extends TestCase
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

    private function product(Producer $producer): Product
    {
        return Product::factory()->create([
            'unit' => ProductUnit::Piece->value, 'price_display_unit' => 'piece', 'unit_price' => 3.0,
            'is_available' => true, 'is_published' => true, 'producer_id' => $producer->id,
        ]);
    }

    private function order(string $status = ShopOrderStatus::Paid->value): ShopOrder
    {
        return ShopOrder::create([
            'name' => 'Anna', 'email' => 'a@example.com',
            'fulfillment_mode' => 'pickup', 'pickup_location' => 'karl_kunger',
            'expected_delivery_date' => self::DAY,
            'payment_method' => 'online', 'status' => $status,
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
            ->get(route('app.admin.producer-orders.index', ['delivery_from' => self::DAY, 'delivery_to' => self::DAY]))
            ->assertForbidden();
    }

    public function test_index_groups_products_by_producer(): void
    {
        $farmA = Producer::factory()->create();
        $farmB = Producer::factory()->create();
        $carrots = $this->product($farmA);
        $potatoes = $this->product($farmA);
        $apples = $this->product($farmB);

        $order = $this->order();
        $this->item($order, $carrots, 2);
        $this->item($order, $potatoes, 1);
        $this->item($order, $apples, 5);

        $response = $this->actingAs($this->admin())
            ->get(route('app.admin.producer-orders.index', ['delivery_from' => self::DAY, 'delivery_to' => self::DAY]))
            ->assertOk();

        $producers = collect($response->viewData('producers'));
        $this->assertCount(2, $producers);

        $a = $producers->firstWhere('id', $farmA->id);
        $this->assertSame(2, $a['total_count']); // carrots + potatoes
        $this->assertSame(0, $a['received_count']);
        $this->assertFalse($a['done']);

        $b = $producers->firstWhere('id', $farmB->id);
        $this->assertSame(1, $b['total_count']);
    }

    public function test_toggle_receipt_creates_and_removes_row(): void
    {
        $farm = Producer::factory()->create();
        $carrots = $this->product($farm);
        $this->item($this->order(), $carrots, 2);

        $admin = $this->admin();
        $payload = [
            'producer_id' => $farm->id,
            'product_id' => $carrots->id,
            'variant_id' => 0,
            'delivery_date' => self::DAY,
        ];

        // Tick on: a receipt row appears.
        $this->actingAs($admin)
            ->postJson(route('app.admin.producer-orders.receipt.toggle'), $payload)
            ->assertOk()
            ->assertJsonPath('received', true);

        $this->assertDatabaseHas('producer_receipts', [
            'producer_id' => $farm->id, 'product_id' => $carrots->id,
            'variant_id' => 0, 'delivery_date' => self::DAY,
            'received_by_user_id' => $admin->id,
        ]);

        // Tick off: the row is removed.
        $this->actingAs($admin)
            ->postJson(route('app.admin.producer-orders.receipt.toggle'), $payload)
            ->assertJsonPath('received', false);

        $this->assertDatabaseMissing('producer_receipts', [
            'producer_id' => $farm->id, 'product_id' => $carrots->id, 'delivery_date' => self::DAY,
        ]);
    }

    public function test_index_reflects_existing_receipts(): void
    {
        $farm = Producer::factory()->create();
        $carrots = $this->product($farm);
        $this->item($this->order(), $carrots, 2);

        ProducerReceipt::create([
            'producer_id' => $farm->id, 'product_id' => $carrots->id,
            'variant_id' => 0, 'delivery_date' => self::DAY,
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('app.admin.producer-orders.index', ['delivery_from' => self::DAY, 'delivery_to' => self::DAY]))
            ->assertOk();

        $producer = collect($response->viewData('producers'))->firstWhere('id', $farm->id);
        $this->assertSame(1, $producer['received_count']);
        $this->assertTrue($producer['done']);
        $this->assertTrue($producer['rows'][0]['received']);
    }

    public function test_receive_all_marks_every_line_of_that_producer(): void
    {
        $farm = Producer::factory()->create();
        $carrots = $this->product($farm);
        $potatoes = $this->product($farm);
        $order = $this->order();
        $this->item($order, $carrots, 2);
        $this->item($order, $potatoes, 1);

        $this->actingAs($this->admin())
            ->postJson(route('app.admin.producer-orders.receive-all', [
                'producer_id' => $farm->id,
                'delivery_from' => self::DAY,
                'delivery_to' => self::DAY,
            ]))
            ->assertOk();

        $this->assertDatabaseHas('producer_receipts', [
            'producer_id' => $farm->id, 'product_id' => $carrots->id, 'delivery_date' => self::DAY,
        ]);
        $this->assertDatabaseHas('producer_receipts', [
            'producer_id' => $farm->id, 'product_id' => $potatoes->id, 'delivery_date' => self::DAY,
        ]);
        $this->assertSame(2, ProducerReceipt::count());
    }

    /**
     * Regression: moving a look-alike position onto a farm that already
     * delivers that exact product+variant+day must merge into ONE row, not
     * emit a second row with a duplicate key (which blanked the whole panel
     * in Alpine's x-for). The merged row sums both quantities.
     */
    public function test_override_merges_into_existing_native_line_instead_of_duplicating(): void
    {
        $sourceFarm = Producer::factory()->create();
        $targetFarm = Producer::factory()->create();
        $moved = $this->product($sourceFarm);   // customer ordered this
        $native = $this->product($targetFarm);  // target farm already delivers this

        $order = $this->order();
        $this->item($order, $moved, 4);
        $this->item($order, $native, 3);

        ProducerPositionOverride::create([
            'product_id' => $moved->id,
            'variant_id' => 0,
            'delivery_date' => self::DAY,
            'target_product_id' => $native->id,
            'target_producer_id' => $targetFarm->id,
            'created_by_user_id' => $this->admin()->id,
        ]);

        $response = $this->actingAs($this->admin())
            ->get(route('app.admin.producer-orders.index', ['delivery_from' => self::DAY, 'delivery_to' => self::DAY]))
            ->assertOk();

        $producers = collect($response->viewData('producers'));

        // Source farm no longer shows the moved position.
        $source = $producers->firstWhere('id', $sourceFarm->id);
        if ($source !== null) {
            $this->assertNotContains($moved->id, array_column($source['rows'], 'product_id'));
        }

        // Target farm has exactly one row for the native product, summing 3 + 4.
        $target = $producers->firstWhere('id', $targetFarm->id);
        $this->assertNotNull($target);
        $nativeRows = array_values(array_filter($target['rows'], fn ($r) => $r['product_id'] === $native->id));
        $this->assertCount(1, $nativeRows);
        $this->assertSame(7.0, (float) $nativeRows[0]['qty_display']);
        $this->assertTrue($nativeRows[0]['overridden']);

        // No duplicate row keys anywhere in the target panel.
        $keys = array_column($target['rows'], 'key');
        $this->assertSame(count($keys), count(array_unique($keys)));
    }
}
