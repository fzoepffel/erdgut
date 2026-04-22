<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderProductType;
use App\Enums\OrderStatus;
use App\Models\Box;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductWeekStock;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminTrialBoxSetupFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_preview_and_apply_trial_weekly_setup_creating_boxes_for_orders(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();

        $week = Carbon::parse('2026-05-04')->startOfDay(); // Monday

        $orderA = Order::create([
            'user_id' => $customer->id,
            'product_type' => OrderProductType::TrialBox->value,
            'box_size' => 'small',
            'amount' => 35,
            'currency' => 'EUR',
            'status' => OrderStatus::Paid->value,
            'is_test' => true,
        ]);

        $product = Product::factory()->create([
            'unit_price' => 2.0,
            'is_available' => true,
            'is_published' => true,
            'metadata' => null,
        ]);

        foreach (['de', 'en', 'fr'] as $locale) {
            $product->translations()->where('locale', $locale)->update([
                'name' => 'TrialProd',
                'unit' => 'piece',
                'origin' => 'regional',
                'variety' => '-',
                'taste_profile' => '-',
            ]);
        }

        ProductWeekStock::create([
            'product_id' => $product->id,
            'week_start_date' => $week->toDateString(),
            'planned_quantity' => 10,
            'unit' => 'piece',
        ]);

        $this->actingAs($admin)
            ->get(route('app.admin.trial-boxes.setup.show', ['week' => $week->toDateString(), 'order_ids' => [$orderA->id]]))
            ->assertOk()
            ->assertSee('TrialProd');

        $this->actingAs($admin)
            ->post(route('app.admin.trial-boxes.setup.apply', ['week' => $week->toDateString()]), [
                'week_start_date' => $week->toDateString(),
                'order_ids' => [$orderA->id],
                'sizes' => [
                    'small' => [
                        ['product_id' => $product->id, 'quantity' => 2],
                    ],
                ],
            ])
            ->assertRedirect(route('app.admin.trial-boxes.setup.select', ['week' => $week->toDateString()]));

        $orderA->refresh();
        $this->assertNotNull($orderA->box_id);
        $this->assertTrue(Box::query()
            ->whereKey($orderA->box_id)
            ->where('order_id', $orderA->id)
            ->whereDate('week_start_date', $week->toDateString())
            ->exists());
        $this->assertDatabaseHas('box_items', [
            'box_id' => $orderA->box_id,
            'product_id' => $product->id,
        ]);
    }
}
