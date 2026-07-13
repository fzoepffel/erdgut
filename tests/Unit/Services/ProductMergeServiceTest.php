<?php

namespace Tests\Unit\Services;

use App\Enums\ProductUnit;
use App\Models\AuditEvent;
use App\Models\Box;
use App\Models\BoxItem;
use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Models\ProductVariant;
use App\Models\RestaurantOrder;
use App\Models\RestaurantProfile;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Models\User;
use App\Services\Admin\ProductMergeService;
use App\Services\ImageProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * ProductMergeService: the loser's inventory, orders, boxes, pairings
 * and engagement move to the winner; texts, images and price rules are
 * dropped; grades move or remap by name; farms are appended; the loser
 * is deleted.
 */
class ProductMergeServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProductMergeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ProductMergeService::class);
    }

    private function product(?Producer $producer = null): Product
    {
        return Product::factory()->create([
            'producer_id' => ($producer ?? Producer::factory()->create())->id,
            'unit' => ProductUnit::Piece->value,
            'price_display_unit' => 'piece',
        ]);
    }

    private function inflow(Product $product, ?int $producerId, array $attrs = []): ProductStockInflow
    {
        return ProductStockInflow::create(array_merge([
            'product_id' => $product->id,
            'producer_id' => $producerId,
            'received_on' => '2026-07-20',
            'quantity' => 10,
            'freshness_window_days' => 2,
        ], $attrs));
    }

    private function shopLine(Product $product, ?int $variantId = null): ShopOrderItem
    {
        $order = ShopOrder::factory()->paid()->create(['expected_delivery_date' => '2026-07-20']);

        return ShopOrderItem::factory()->create([
            'shop_order_id' => $order->id,
            'product_id' => $product->id,
            'variant_id' => $variantId,
            'quantity' => 2,
            'unit' => 'piece',
        ]);
    }

    public function test_inflows_and_schedules_move_and_keep_their_farm(): void
    {
        $farmB = Producer::factory()->create();
        $winner = $this->product();
        $loser = $this->product($farmB);
        $inflow = $this->inflow($loser, $farmB->id);
        $scheduleId = DB::table('product_stock_inflow_schedules')->insertGetId([
            'product_id' => $loser->id, 'producer_id' => $farmB->id,
            'weekday' => 1, 'planned_quantity' => 5, 'freshness_window_days' => 2,
            'starts_on' => '2026-07-01', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->service->merge($winner, $loser);

        $this->assertDatabaseHas('product_stock_inflows', [
            'id' => $inflow->id, 'product_id' => $winner->id, 'producer_id' => $farmB->id,
        ]);
        $this->assertDatabaseHas('product_stock_inflow_schedules', [
            'id' => $scheduleId, 'product_id' => $winner->id, 'producer_id' => $farmB->id,
        ]);
    }

    public function test_null_farm_inflows_get_stamped_with_the_losers_primary(): void
    {
        $farmB = Producer::factory()->create();
        $winner = $this->product();
        $loser = $this->product($farmB);
        $inflow = $this->inflow($loser, null);

        $this->service->merge($winner, $loser);

        $this->assertDatabaseHas('product_stock_inflows', [
            'id' => $inflow->id, 'product_id' => $winner->id, 'producer_id' => $farmB->id,
        ]);
    }

    public function test_null_farm_inflows_stay_null_when_the_loser_has_no_primary(): void
    {
        $winner = $this->product();
        $loser = $this->product();
        $inflow = $this->inflow($loser, null);
        DB::table('product_producer')->where('product_id', $loser->id)->delete();
        $loser->forceFill(['producer_id' => null])->save();
        $loser->refresh();

        $this->service->merge($winner, $loser);

        $this->assertDatabaseHas('product_stock_inflows', [
            'id' => $inflow->id, 'product_id' => $winner->id, 'producer_id' => null,
        ]);
    }

    public function test_unmatched_grade_moves_keeping_its_id_and_gets_appended(): void
    {
        $winner = $this->product();
        $loser = $this->product();
        $winnerVariant = ProductVariant::factory()->create([
            'product_id' => $winner->id, 'name' => ['de' => 'Dick'], 'position' => 0,
        ]);
        $loserVariant = ProductVariant::factory()->create([
            'product_id' => $loser->id, 'name' => ['de' => 'Dünn'], 'position' => 0,
        ]);

        $this->service->merge($winner, $loser);

        $this->assertDatabaseHas('product_variants', [
            'id' => $loserVariant->id, 'product_id' => $winner->id, 'position' => 1,
        ]);
        $this->assertDatabaseHas('product_variants', [
            'id' => $winnerVariant->id, 'product_id' => $winner->id, 'position' => 0,
        ]);
    }

    public function test_same_named_grade_remaps_references_and_dies(): void
    {
        $winner = $this->product();
        $loser = $this->product();
        $winnerVariant = ProductVariant::factory()->create([
            'product_id' => $winner->id, 'name' => ['de' => 'Dünn'],
        ]);
        $loserVariant = ProductVariant::factory()->create([
            'product_id' => $loser->id, 'name' => ['de' => ' dünn '], // case/space-insensitive
        ]);
        $line = $this->shopLine($loser, $loserVariant->id);
        $line->forceFill(['variant_name_snapshot' => 'Dünn'])->save();
        $inflow = $this->inflow($loser, null, ['variant_id' => $loserVariant->id]);

        $this->service->merge($winner, $loser);

        $this->assertDatabaseHas('shop_order_items', [
            'id' => $line->id, 'product_id' => $winner->id,
            'variant_id' => $winnerVariant->id, 'variant_name_snapshot' => 'Dünn',
        ]);
        $this->assertDatabaseHas('product_stock_inflows', [
            'id' => $inflow->id, 'variant_id' => $winnerVariant->id,
        ]);
        $this->assertDatabaseMissing('product_variants', ['id' => $loserVariant->id]);
    }

    public function test_engagement_moves_and_sums_on_colliding_days(): void
    {
        $winner = $this->product();
        $loser = $this->product();
        DB::table('product_engagement_daily')->insert([
            ['product_id' => $winner->id, 'day' => '2026-07-10', 'impressions' => 5, 'add_to_carts' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['product_id' => $loser->id, 'day' => '2026-07-10', 'impressions' => 3, 'add_to_carts' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['product_id' => $loser->id, 'day' => '2026-07-11', 'impressions' => 7, 'add_to_carts' => 0, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->service->merge($winner, $loser);

        $this->assertDatabaseHas('product_engagement_daily', [
            'product_id' => $winner->id, 'day' => '2026-07-10', 'impressions' => 8, 'add_to_carts' => 3,
        ]);
        $this->assertDatabaseHas('product_engagement_daily', [
            'product_id' => $winner->id, 'day' => '2026-07-11', 'impressions' => 7,
        ]);
        $this->assertSame(0, DB::table('product_engagement_daily')->where('product_id', $loser->id)->count());
    }

    public function test_pairings_transfer_dedupe_and_the_pair_with_the_winner_disappears(): void
    {
        $winner = $this->product();
        $loser = $this->product();
        $other = $this->product();
        $shared = $this->product();
        $now = now();
        DB::table('product_pairings')->insert([
            // loser <-> other (should become winner <-> other, note kept)
            ['product_id' => $loser->id, 'paired_product_id' => $other->id, 'note' => 'passt gut', 'created_at' => $now, 'updated_at' => $now],
            ['product_id' => $other->id, 'paired_product_id' => $loser->id, 'note' => 'passt gut', 'created_at' => $now, 'updated_at' => $now],
            // loser <-> winner (should vanish)
            ['product_id' => $loser->id, 'paired_product_id' => $winner->id, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['product_id' => $winner->id, 'paired_product_id' => $loser->id, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            // loser <-> shared AND winner <-> shared (no duplicate row after merge)
            ['product_id' => $loser->id, 'paired_product_id' => $shared->id, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['product_id' => $shared->id, 'paired_product_id' => $loser->id, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['product_id' => $winner->id, 'paired_product_id' => $shared->id, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
            ['product_id' => $shared->id, 'paired_product_id' => $winner->id, 'note' => null, 'created_at' => $now, 'updated_at' => $now],
        ]);

        $this->service->merge($winner, $loser);

        $this->assertDatabaseHas('product_pairings', [
            'product_id' => $winner->id, 'paired_product_id' => $other->id, 'note' => 'passt gut',
        ]);
        $this->assertDatabaseHas('product_pairings', [
            'product_id' => $other->id, 'paired_product_id' => $winner->id,
        ]);
        $this->assertSame(1, DB::table('product_pairings')
            ->where('product_id', $winner->id)->where('paired_product_id', $shared->id)->count());
        $this->assertSame(0, DB::table('product_pairings')
            ->where('product_id', $loser->id)->orWhere('paired_product_id', $loser->id)->count());
        $this->assertSame(0, DB::table('product_pairings')
            ->where('product_id', $winner->id)->where('paired_product_id', $winner->id)->count());
    }

    public function test_missing_farms_are_appended_and_the_winners_primary_stays(): void
    {
        $farmA = Producer::factory()->create();
        $farmB = Producer::factory()->create();
        $farmC = Producer::factory()->create();
        $winner = $this->product($farmA);
        $loser = $this->product($farmB);
        $loser->syncProducers([$farmB->id, $farmC->id]);

        $this->service->merge($winner, $loser);

        $winner->refresh();
        $this->assertSame($farmA->id, (int) $winner->producer_id);
        $this->assertSame(
            [$farmA->id, $farmB->id, $farmC->id],
            $winner->producers()->pluck('producers.id')->map(fn ($v) => (int) $v)->all(),
        );
    }

    public function test_losers_price_rules_are_dropped_and_pointers_nulled(): void
    {
        $winner = $this->product();
        $loser = $this->product();
        $ruleId = DB::table('product_price_rules')->insertGetId([
            'product_id' => $loser->id, 'unit_price' => 3.5, 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $line = $this->shopLine($loser);
        $line->forceFill(['price_rule_id' => $ruleId])->save();
        $lineTotal = (float) $line->line_total_eur;

        $this->service->merge($winner, $loser);

        $this->assertDatabaseMissing('product_price_rules', ['id' => $ruleId]);
        $line->refresh();
        $this->assertNull($line->price_rule_id);
        $this->assertSame($winner->id, (int) $line->product_id);
        $this->assertEqualsWithDelta($lineTotal, (float) $line->line_total_eur, 0.001);
    }

    public function test_box_items_remap_and_a_box_holding_both_keeps_two_lines(): void
    {
        $winner = $this->product();
        $loser = $this->product();
        $box = Box::factory()->create();
        BoxItem::factory()->create(['box_id' => $box->id, 'product_id' => $winner->id]);
        BoxItem::factory()->create(['box_id' => $box->id, 'product_id' => $loser->id]);

        $this->service->merge($winner, $loser);

        $this->assertSame(2, DB::table('box_items')
            ->where('box_id', $box->id)->where('product_id', $winner->id)->count());
        $this->assertSame(0, DB::table('box_items')->where('product_id', $loser->id)->count());
    }

    public function test_restaurant_and_standing_lines_move_and_custom_lines_stay(): void
    {
        $winner = $this->product();
        $loser = $this->product();
        $profile = RestaurantProfile::factory()->verified()->create();
        $order = RestaurantOrder::create([
            'user_id' => $profile->user_id, 'status' => 'confirmed', 'kind' => 'order',
            'expected_delivery_date' => '2026-07-20',
            'delivery_street' => 'S 1', 'delivery_postal_code' => '10115', 'delivery_city' => 'Berlin',
            'items_total_net_eur' => 10, 'items_total_gross_eur' => 10.7, 'vat_total_eur' => 0.7,
        ]);
        $productLine = $order->items()->create([
            'product_id' => $loser->id, 'quantity' => 3, 'unit' => 'piece',
            'track' => 'reserved', 'line_status' => 'confirmed',
            'unit_price_snapshot_eur' => 3, 'vat_rate_snapshot_percent' => 7,
            'line_total_eur' => 9, 'vat_amount_eur' => 0.59,
        ]);
        $customLine = $order->items()->create([
            'product_id' => null, 'name_snapshot' => 'Sonderposten', 'quantity' => 1, 'unit' => 'piece',
            'track' => 'requested', 'line_status' => 'requested',
            'unit_price_snapshot_eur' => 0, 'vat_rate_snapshot_percent' => 7,
            'line_total_eur' => 0, 'vat_amount_eur' => 0,
        ]);
        $standingOrderId = DB::table('restaurant_standing_orders')->insertGetId([
            'user_id' => $profile->user_id, 'delivery_weekday' => 2,
            'delivery_street' => 'S 1', 'delivery_postal_code' => '10115', 'delivery_city' => 'Berlin',
            'status' => 'confirmed', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $standingItemId = DB::table('restaurant_standing_order_items')->insertGetId([
            'restaurant_standing_order_id' => $standingOrderId, 'product_id' => $loser->id,
            'quantity' => 2, 'starts_on' => '2026-07-01', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->service->merge($winner, $loser);

        $this->assertDatabaseHas('restaurant_order_items', ['id' => $productLine->id, 'product_id' => $winner->id]);
        $this->assertDatabaseHas('restaurant_order_items', ['id' => $customLine->id, 'product_id' => null]);
        $this->assertDatabaseHas('restaurant_standing_order_items', ['id' => $standingItemId, 'product_id' => $winner->id]);
    }

    public function test_loser_texts_images_and_row_are_gone_and_winner_untouched(): void
    {
        Storage::fake('public');
        $winner = $this->product();
        $loser = $this->product();
        $winnerName = $winner->translations->firstWhere('locale', 'de')?->name;

        Storage::disk('public')->put('products/w.jpg', 'w');
        Storage::disk('public')->put('products/l.jpg', 'l');
        Storage::disk('public')->put(ImageProcessor::thumbPath('products/l.jpg'), 'lt');
        $winner->update(['image_path' => 'products/w.jpg']);
        $loser->update(['image_path' => 'products/l.jpg']);
        DB::table('product_images')->insert([
            'product_id' => $loser->id, 'path' => 'products/l.jpg', 'position' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->service->merge($winner->refresh(), $loser->refresh());

        $this->assertDatabaseMissing('products', ['id' => $loser->id]);
        $this->assertSame(0, DB::table('product_translations')->where('product_id', $loser->id)->count());
        $this->assertSame(0, DB::table('product_images')->where('product_id', $loser->id)->count());
        $this->assertFalse(Storage::disk('public')->exists('products/l.jpg'));
        $this->assertFalse(Storage::disk('public')->exists(ImageProcessor::thumbPath('products/l.jpg')));
        $this->assertTrue(Storage::disk('public')->exists('products/w.jpg'));
        $this->assertSame($winnerName, $winner->refresh()->translations->firstWhere('locale', 'de')?->name);
    }

    public function test_audit_events_are_written_for_both_products(): void
    {
        $winner = $this->product();
        $loser = $this->product();
        $this->inflow($loser, null);

        $actor = User::factory()->admin()->create();

        $this->service->merge($winner, $loser, $actor->id);

        $absorbed = AuditEvent::query()
            ->where('entity_type', 'product')->where('entity_id', $winner->id)
            ->where('action', 'merge_absorbed')->first();
        $this->assertNotNull($absorbed);
        $this->assertSame($loser->id, (int) $absorbed->metadata['merged_product_id']);
        $this->assertSame(1, (int) $absorbed->metadata['counts']['inflows']);

        $this->assertNotNull(AuditEvent::query()
            ->where('entity_type', 'product')->where('entity_id', $loser->id)
            ->where('action', 'merge_deleted')->first());
    }

    public function test_self_merge_and_protected_losers_are_rejected(): void
    {
        $winner = $this->product();
        $loser = $this->product();

        try {
            $this->service->merge($winner, $winner);
            $this->fail('Self merge should throw.');
        } catch (RuntimeException) {
        }

        foreach ([
            'spargel.white_product_id',
            'spargel.green_product_id',
            'box.eggs_dairy_add_on_product_id',
        ] as $key) {
            config()->set($key, $loser->id);
            try {
                $this->service->merge($winner, $loser);
                $this->fail("Protected loser via {$key} should throw.");
            } catch (RuntimeException) {
            }
            config()->set($key, null);
        }

        $this->assertDatabaseHas('products', ['id' => $loser->id]);
    }
}
