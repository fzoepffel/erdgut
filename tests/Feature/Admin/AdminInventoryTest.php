<?php

namespace Tests\Feature\Admin;

use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Models\ProductStockInflowSchedule;
use App\Models\User;
use App\Services\Admin\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminInventoryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('app.admin.inventory.index'))
            ->assertRedirect('/auth/login');
    }

    public function test_non_admin_receives_forbidden(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('app.admin.inventory.index'))
            ->assertForbidden();
    }

    public function test_admin_can_open_inventory_page(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-19'));
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('app.admin.inventory.index'))
            ->assertOk()
            ->assertSee(__('admin.inventory.title'));
    }

    public function test_multi_farm_product_row_collapses_to_an_expandable_toggle(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-19'));
        $admin = User::factory()->admin()->create();
        $farmA = Producer::factory()->create();
        $farmB = Producer::factory()->create();
        $product = Product::factory()->create([
            'producer_id' => $farmA->id, 'is_available' => true, 'is_published' => true,
        ]);
        $product->syncProducers([$farmA->id, $farmB->id]);
        foreach ([[$farmA->id, 6], [$farmB->id, 4]] as [$farmId, $qty]) {
            ProductStockInflow::create([
                'product_id' => $product->id, 'producer_id' => $farmId,
                'received_on' => '2026-05-20', 'quantity' => $qty, 'freshness_window_days' => 1,
            ]);
        }

        $html = $this->actingAs($admin)
            ->get(route('app.admin.inventory.index'))
            ->assertOk()
            ->getContent();

        // Toggle instead of the primary farm name on the row.
        $this->assertStringContainsString('inventory-farm-toggle-'.$product->id, $html);
        $this->assertStringContainsString(__('shop.producers_multiple'), $html);
        // One lane per farm carrying its own quantity.
        $this->assertStringContainsString('inventory-farm-lane-'.$product->id.'-'.$farmA->id, $html);
        $this->assertStringContainsString('inventory-farm-lane-'.$product->id.'-'.$farmB->id, $html);
        $this->assertStringContainsString(e($farmA->name), $html);
        $this->assertStringContainsString(e($farmB->name), $html);
    }

    public function test_row_names_the_actual_stocking_farm_when_it_is_not_the_primary(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-19'));
        $admin = User::factory()->admin()->create();
        $farmA = Producer::factory()->create();
        $farmB = Producer::factory()->create();
        $product = Product::factory()->create([
            'producer_id' => $farmA->id, 'is_available' => true, 'is_published' => true,
        ]);
        $product->syncProducers([$farmA->id, $farmB->id]);
        // The ONLY stock in the window comes from farm B.
        ProductStockInflow::create([
            'product_id' => $product->id, 'producer_id' => $farmB->id,
            'received_on' => '2026-05-20', 'quantity' => 7, 'freshness_window_days' => 1,
        ]);

        $html = $this->actingAs($admin)
            ->get(route('app.admin.inventory.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('inventory-farm-single-'.$product->id, $html);
        $this->assertStringContainsString(e($farmB->name), $html);
        $this->assertStringNotContainsString('inventory-farm-toggle-'.$product->id, $html);
    }

    public function test_single_farm_product_keeps_its_plain_producer_line(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-19'));
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['is_available' => true, 'is_published' => true]);
        ProductStockInflow::create([
            'product_id' => $product->id, 'producer_id' => $product->producer_id,
            'received_on' => '2026-05-20', 'quantity' => 5, 'freshness_window_days' => 1,
        ]);

        $html = $this->actingAs($admin)
            ->get(route('app.admin.inventory.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(e($product->producer->name), $html);
        $this->assertStringNotContainsString('inventory-farm-toggle-'.$product->id, $html);
    }

    public function test_b2b_only_stock_from_a_second_farm_counts_as_multi_farm(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-19'));
        $admin = User::factory()->admin()->create();
        $farmA = Producer::factory()->create();
        $farmB = Producer::factory()->create();
        $product = Product::factory()->create([
            'producer_id' => $farmA->id, 'is_available' => true, 'is_published' => true,
        ]);
        $product->syncProducers([$farmA->id, $farmB->id]);
        ProductStockInflow::create([
            'product_id' => $product->id, 'producer_id' => $farmA->id,
            'received_on' => '2026-05-20', 'quantity' => 6, 'freshness_window_days' => 1,
        ]);
        ProductStockInflow::create([
            'product_id' => $product->id, 'producer_id' => $farmB->id,
            'received_on' => '2026-05-20', 'quantity' => 9, 'freshness_window_days' => 1,
            'b2b_only' => true,
        ]);

        $html = $this->actingAs($admin)
            ->get(route('app.admin.inventory.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('inventory-farm-toggle-'.$product->id, $html);
        $this->assertStringContainsString('inventory-farm-lane-'.$product->id.'-'.$farmB->id, $html);
        // Farm B's lane shows its stock as the amber trade-only figure.
        $this->assertStringContainsString(e($farmB->name), $html);
    }

    public function test_matrix_shows_the_producer_next_to_the_product(): void
    {
        // Two same-named products from different farms must be tellable apart.
        Carbon::setTestNow(Carbon::parse('2026-05-19'));
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['is_available' => true, 'is_published' => true]);

        $this->actingAs($admin)
            ->get(route('app.admin.inventory.index'))
            ->assertOk()
            ->assertSee($product->producer->name);
    }

    public function test_inflow_stores_and_validates_the_supplying_producer(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['unit' => 'mass', 'price_display_unit' => 'kg']);
        $secondFarm = Producer::factory()->create();
        $foreignFarm = Producer::factory()->create();
        $product->syncProducers([$product->producer_id, $secondFarm->id]);

        // A farm outside the product's supplier set is rejected.
        $this->actingAs($admin)->post(route('app.admin.inventory.inflows.store'), [
            'product_id' => $product->id, 'received_on' => '2026-07-20',
            'quantity' => 5, 'producer_id' => $foreignFarm->id,
        ])->assertSessionHasErrors('producer_id');

        // A registered supplier is stored on the batch.
        $this->actingAs($admin)->post(route('app.admin.inventory.inflows.store'), [
            'product_id' => $product->id, 'received_on' => '2026-07-20',
            'quantity' => 5, 'producer_id' => $secondFarm->id,
        ])->assertRedirect();
        $this->assertSame($secondFarm->id, (int) ProductStockInflow::query()->latest('id')->firstOrFail()->producer_id);

        // Omitting the field defaults to the primary producer.
        $this->actingAs($admin)->post(route('app.admin.inventory.inflows.store'), [
            'product_id' => $product->id, 'received_on' => '2026-07-21', 'quantity' => 3,
        ])->assertRedirect();
        $this->assertSame($product->producer_id, (int) ProductStockInflow::query()->latest('id')->firstOrFail()->producer_id);
    }

    public function test_schedule_carries_its_producer_into_materialized_inflows(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-13'));
        try {
            $admin = User::factory()->admin()->create();
            $product = Product::factory()->create(['unit' => 'mass', 'price_display_unit' => 'kg']);
            $secondFarm = Producer::factory()->create();
            $product->syncProducers([$product->producer_id, $secondFarm->id]);

            $this->actingAs($admin)->post(route('app.admin.inflow-schedules.store', $product), [
                'weekday' => 3, 'planned_quantity' => 10, 'starts_on' => '2026-07-15',
                'producer_id' => $secondFarm->id,
            ])->assertRedirect();

            $schedule = ProductStockInflowSchedule::query()->firstOrFail();
            $this->assertSame($secondFarm->id, (int) $schedule->producer_id);
            $materialized = ProductStockInflow::query()->where('source_schedule_id', $schedule->id)->get();
            $this->assertNotEmpty($materialized);
            foreach ($materialized as $inflow) {
                $this->assertSame($secondFarm->id, (int) $inflow->producer_id);
            }
        } finally {
            Carbon::setTestNow(null);
        }
    }

    public function test_inflow_list_shows_the_batch_producer(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-19'));
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();
        $secondFarm = Producer::factory()->create();
        $product->syncProducers([$product->producer_id, $secondFarm->id]);
        $inflow = ProductStockInflow::create([
            'product_id' => $product->id, 'producer_id' => $secondFarm->id,
            'received_on' => '2026-05-20', 'quantity' => 5, 'freshness_window_days' => 2,
        ]);

        $this->actingAs($admin)
            ->get(route('app.admin.inventory.index'))
            ->assertOk()
            ->assertSee('data-testid="inflow-producer-'.$inflow->id.'"', false)
            ->assertSee($secondFarm->name);
    }

    public function test_matrix_shows_b2b_only_stock_in_amber(): void
    {
        // A pure trade inflow was invisible before: the matrix only computed
        // the consumer audience. It must render as gastro-reserved stock.
        Carbon::setTestNow(Carbon::parse('2026-05-19'));
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create(['is_available' => true, 'is_published' => true]);
        ProductStockInflow::create([
            'product_id' => $product->id,
            'received_on' => '2026-05-20',
            'quantity' => 5,
            'freshness_window_days' => 2,
            'b2b_only' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('app.admin.inventory.index'))
            ->assertOk()
            ->assertSee('data-testid="inventory-b2b-cell-'.$product->id.'-2026-05-20"', false)
            ->assertSee(__('admin.inventory.b2b_stock_legend'));
    }

    public function test_admin_can_record_a_manual_inflow_and_redirect_back(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create([
            'unit' => 'mass',
            'price_display_unit' => 'kg',
        ]);

        $this->actingAs($admin)
            ->post(route('app.admin.inventory.inflows.store'), [
                'product_id' => $product->id,
                'received_on' => '2026-05-25',
                'quantity' => 10, // 10 kg → 10000 g canonical
                'freshness_window_days' => 3,
                'notes' => 'Today harvest',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('product_stock_inflows', [
            'product_id' => $product->id,
            'received_on' => '2026-05-25',
            'quantity' => 10000.0,
            'freshness_window_days' => 3,
        ]);
    }

    public function test_admin_can_delete_an_inflow(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();
        $inflow = ProductStockInflow::create([
            'product_id' => $product->id,
            'received_on' => '2026-05-25',
            'quantity' => 100,
            'freshness_window_days' => 1,
        ]);

        $this->actingAs($admin)
            ->delete(route('app.admin.inventory.inflows.destroy', $inflow))
            ->assertRedirect();

        $this->assertDatabaseMissing('product_stock_inflows', ['id' => $inflow->id]);
    }

    public function test_admin_can_edit_an_inflow_quantity_and_date(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create([
            'unit' => 'mass',
            'price_display_unit' => 'kg',
        ]);
        $inflow = ProductStockInflow::create([
            'product_id' => $product->id,
            'received_on' => '2026-05-25',
            'quantity' => 10000.0, // 10 kg
            'freshness_window_days' => 1,
        ]);

        // Admin enters 7.5 in the display unit (kg) — controller should
        // multiply by the kg→g factor (1000) to get 7500 grams stored.
        $this->actingAs($admin)
            ->patch(route('app.admin.inventory.inflows.update', $inflow), [
                'received_on' => '2026-05-26',
                'quantity' => 7.5,
                'freshness_window_days' => 2,
                'notes' => 'Moved to Tuesday — Monday was a holiday',
            ])
            ->assertRedirect();

        $inflow->refresh();
        $this->assertSame('2026-05-26', $inflow->received_on->toDateString());
        $this->assertEqualsWithDelta(7500.0, (float) $inflow->quantity, 0.001);
        $this->assertSame(2, $inflow->freshness_window_days);
        $this->assertSame('Moved to Tuesday — Monday was a holiday', $inflow->notes);
        // original_received_on stays at the originally-stored date for
        // one-offs (which equals the original received_on at creation).
        $this->assertSame('2026-05-25', $inflow->original_received_on->toDateString());
    }

    public function test_materializer_respects_admin_edited_dates(): void
    {
        // Setup: a Monday-weekday schedule materialises an inflow for
        // 2026-06-01. Admin shifts that row to Tuesday 2026-06-02
        // (Monday was a holiday). The next cron run must NOT recreate
        // a row for 2026-06-01.
        $product = Product::factory()->create();
        $schedule = ProductStockInflowSchedule::create([
            'product_id' => $product->id,
            'weekday' => 1, // Carbon Monday
            'planned_quantity' => 5000,
            'freshness_window_days' => 1,
            'starts_on' => '2026-06-01',
        ]);

        // First materialisation creates the Monday row.
        app(InventoryService::class)
            ->materializeSchedule($schedule, Carbon::parse('2026-06-01'));

        $row = ProductStockInflow::query()
            ->where('source_schedule_id', $schedule->id)
            ->whereDate('original_received_on', '2026-06-01')
            ->first();
        $this->assertNotNull($row);
        $this->assertSame('2026-06-01', $row->received_on->toDateString());

        // Admin shifts the row to Tuesday — only `received_on` changes.
        $row->update(['received_on' => '2026-06-02']);

        // Re-run the materializer for the same horizon. It must see
        // that the 2026-06-01 occurrence is already accounted for
        // (via original_received_on) and skip it.
        $written = app(InventoryService::class)
            ->materializeSchedule($schedule->fresh(), Carbon::parse('2026-06-01'));

        $this->assertSame(0, $written);
        $this->assertSame(
            1,
            ProductStockInflow::query()
                ->where('source_schedule_id', $schedule->id)
                ->count(),
        );
    }

    public function test_legacy_weekly_catalog_route_no_longer_exists(): void
    {
        $this->assertFalse(Route::has('app.admin.products.weekly.show'));
        $this->assertFalse(Route::has('app.admin.products.weekly.save'));
    }

    public function test_inflow_filter_by_product_name_narrows_the_table(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-19'));
        $admin = User::factory()->admin()->create();
        // Two distinct products, each with their own inflow batch this week.
        $spargel = Product::factory()->create(['is_available' => true, 'is_published' => true]);
        $spargel->translations()->update(['name' => 'Weißer Spargel']);
        $rhabarber = Product::factory()->create(['is_available' => true, 'is_published' => true]);
        $rhabarber->translations()->update(['name' => 'Rhabarber']);
        foreach ([$spargel, $rhabarber] as $p) {
            ProductStockInflow::create([
                'product_id' => $p->id, 'producer_id' => $p->producer_id,
                'received_on' => '2026-05-20', 'quantity' => 5, 'freshness_window_days' => 3,
            ]);
        }

        $spargelInflow = ProductStockInflow::where('product_id', $spargel->id)->firstOrFail();
        $rhabarberInflow = ProductStockInflow::where('product_id', $rhabarber->id)->firstOrFail();

        $html = $this->actingAs($admin)
            ->get(route('app.admin.inventory.index', ['inflow_product' => 'spargel']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('inflow-producer-'.$spargelInflow->id, $html);
        $this->assertStringNotContainsString('inflow-producer-'.$rhabarberInflow->id, $html);
    }

    public function test_inflow_filter_by_producer_matches_delivering_farm_and_pivot(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-19'));
        $admin = User::factory()->admin()->create();
        $farmA = Producer::factory()->create();
        $farmB = Producer::factory()->create();
        $farmC = Producer::factory()->create();

        // Multi-producer product: primary A, also supplied by B. The batch
        // this week is delivered by B (non-primary supplier).
        $multi = Product::factory()->create([
            'producer_id' => $farmA->id, 'is_available' => true, 'is_published' => true,
        ]);
        $multi->syncProducers([$farmA->id, $farmB->id]);
        $multiInflow = ProductStockInflow::create([
            'product_id' => $multi->id, 'producer_id' => $farmB->id,
            'received_on' => '2026-05-20', 'quantity' => 4, 'freshness_window_days' => 3,
        ]);

        // Unrelated product from farm C — must never appear.
        $unrelated = Product::factory()->create([
            'producer_id' => $farmC->id, 'is_available' => true, 'is_published' => true,
        ]);
        $unrelatedInflow = ProductStockInflow::create([
            'product_id' => $unrelated->id, 'producer_id' => $farmC->id,
            'received_on' => '2026-05-20', 'quantity' => 2, 'freshness_window_days' => 3,
        ]);

        // Filter by farm A: the primary supplier for the multi-producer
        // product, even though this batch was delivered by farm B.
        $htmlA = $this->actingAs($admin)
            ->get(route('app.admin.inventory.index', ['inflow_producer' => $farmA->id]))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('inflow-producer-'.$multiInflow->id, $htmlA);
        $this->assertStringNotContainsString('inflow-producer-'.$unrelatedInflow->id, $htmlA);

        // Filter by farm B: matches via the delivering farm on the batch.
        $htmlB = $this->actingAs($admin)
            ->get(route('app.admin.inventory.index', ['inflow_producer' => $farmB->id]))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('inflow-producer-'.$multiInflow->id, $htmlB);
        $this->assertStringNotContainsString('inflow-producer-'.$unrelatedInflow->id, $htmlB);
    }
}
