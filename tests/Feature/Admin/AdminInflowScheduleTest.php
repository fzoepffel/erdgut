<?php

namespace Tests\Feature\Admin;

use App\Models\Product;
use App\Models\ProductStockInflowSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminInflowScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_wednesday_schedule(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create([
            'unit' => 'mass',
            'price_display_unit' => 'kg',
        ]);

        $this->actingAs($admin)
            ->post(route('app.admin.inflow-schedules.store', $product), [
                'weekday' => 3,
                'planned_quantity' => 10, // 10 kg → 10000 g
                'freshness_window_days' => 1,
                'starts_on' => '2026-05-27',
                'notes' => 'Mid-week harvest',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('product_stock_inflow_schedules', [
            'product_id' => $product->id,
            'weekday' => 3,
            'planned_quantity' => 10000.0,
            'freshness_window_days' => 1,
            'starts_on' => '2026-05-27',
        ]);

        // Materialization writes inflow rows on creation.
        $this->assertDatabaseHas('product_stock_inflows', [
            'product_id' => $product->id,
            'received_on' => '2026-05-27',
            'quantity' => 10000.0,
            'freshness_window_days' => 1,
        ]);
    }

    public function test_admin_can_cancel_a_schedule_from_a_chosen_date(): void
    {
        $admin = User::factory()->admin()->create();
        $product = Product::factory()->create();

        $schedule = ProductStockInflowSchedule::create([
            'product_id' => $product->id,
            'weekday' => 3,
            'planned_quantity' => 5000,
            'freshness_window_days' => 1,
            'starts_on' => '2026-05-27',
        ]);

        $this->actingAs($admin)
            ->post(route('app.admin.inflow-schedules.cancel', ['product' => $product, 'schedule' => $schedule]), [
                'from_date' => '2026-06-03',
            ])
            ->assertRedirect();

        $this->assertSame('2026-06-02', $schedule->fresh()->ends_on?->toDateString());
    }
}
