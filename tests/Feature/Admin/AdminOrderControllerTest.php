<?php

namespace Tests\Feature\Admin;

use App\Enums\BoxStatus;
use App\Enums\DeliveryStatus;
use App\Enums\OrderProductType;
use App\Enums\OrderStatus;
use App\Mail\TrialDeliveryScheduledMail;
use App\Models\Address;
use App\Models\Box;
use App\Models\BoxItem;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminOrderControllerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_index_lists_orders_and_filters_by_status(): void
    {
        $user = User::factory()->create();
        Order::create([
            'user_id' => $user->id,
            'product_type' => OrderProductType::TrialBox->value,
            'box_size' => 'medium',
            'amount' => 35,
            'currency' => 'EUR',
            'status' => OrderStatus::Paid->value,
            'is_test' => true,
        ]);

        $this->actingAs($this->admin())
            ->get(route('app.admin.orders.index'))
            ->assertOk()
            ->assertSee('Trial orders');

        $this->actingAs($this->admin())
            ->get(route('app.admin.orders.index', ['status' => OrderStatus::Paid->value]))
            ->assertOk();
    }

    public function test_schedule_creates_delivery_and_queues_notification(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $user->id]);
        $order = Order::create([
            'user_id' => $user->id,
            'address_id' => $address->id,
            'product_type' => OrderProductType::TrialBox->value,
            'box_size' => 'medium',
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

        $box = Box::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'week_start_date' => now()->addDays(3)->copy()->startOfWeek()->toDateString(),
            'status' => BoxStatus::Editable,
            'modification_deadline_at' => now(),
            'estimated_value' => 0,
            'is_test' => true,
        ]);
        $order->forceFill(['box_id' => $box->id])->save();

        BoxItem::create([
            'box_id' => $box->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit' => 'piece',
            'is_extra' => false,
            'is_test' => true,
        ]);

        $this->actingAs($this->admin())
            ->post(route('app.admin.orders.schedule', $order), [
                'scheduled_for_date' => now()->addDays(3)->toDateString(),
                'timeslot' => Delivery::allowedTimeslots()[0],
            ])
            ->assertRedirect(route('app.admin.orders.show', $order));

        $this->assertSame(OrderStatus::Scheduled, $order->fresh()->status);
        $this->assertDatabaseHas('deliveries', [
            'order_id' => $order->id,
            'status' => DeliveryStatus::Scheduled->value,
        ]);
        Mail::assertQueued(TrialDeliveryScheduledMail::class);
    }

    public function test_schedule_is_blocked_for_trial_orders_without_box_items(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $user->id]);
        $order = Order::create([
            'user_id' => $user->id,
            'address_id' => $address->id,
            'product_type' => OrderProductType::TrialBox->value,
            'box_size' => 'medium',
            'amount' => 35,
            'currency' => 'EUR',
            'status' => OrderStatus::Paid->value,
            'is_test' => true,
        ]);

        $this->actingAs($this->admin())
            ->post(route('app.admin.orders.schedule', $order), [
                'scheduled_for_date' => now()->addDays(3)->toDateString(),
                'timeslot' => Delivery::allowedTimeslots()[0],
            ])
            ->assertRedirect();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    public function test_mark_delivered_updates_order_and_deliveries(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $user->id]);
        $order = Order::create([
            'user_id' => $user->id,
            'address_id' => $address->id,
            'product_type' => OrderProductType::TrialBox->value,
            'box_size' => 'medium',
            'amount' => 35,
            'currency' => 'EUR',
            'status' => OrderStatus::Scheduled->value,
            'scheduled_for_date' => now()->addDays(2)->toDateString(),
            'is_test' => true,
        ]);

        Delivery::create([
            'user_id' => $user->id,
            'address_id' => $address->id,
            'order_id' => $order->id,
            'scheduled_for_date' => now()->addDays(2)->toDateString(),
            'timeslot' => Delivery::allowedTimeslots()[0],
            'status' => DeliveryStatus::Scheduled,
            'is_weekly_override' => false,
            'is_test' => true,
        ]);

        $this->actingAs($this->admin())
            ->post(route('app.admin.orders.mark-delivered', $order))
            ->assertRedirect(route('app.admin.orders.show', $order));

        $this->assertSame(OrderStatus::Delivered, $order->fresh()->status);
        $this->assertDatabaseHas('deliveries', [
            'order_id' => $order->id,
            'status' => DeliveryStatus::Delivered->value,
        ]);
    }
}
