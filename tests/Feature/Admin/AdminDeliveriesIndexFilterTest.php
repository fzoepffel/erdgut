<?php

namespace Tests\Feature\Admin;

use App\Enums\OrderProductType;
use App\Enums\OrderStatus;
use App\Models\Address;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDeliveriesIndexFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_trial_only_filter_limits_deliveries_to_order_deliveries(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['email' => 'trial-delivery@example.test']);
        $address = Address::factory()->create(['user_id' => $customer->id]);

        $order = Order::create([
            'user_id' => $customer->id,
            'address_id' => $address->id,
            'product_type' => OrderProductType::TrialBox->value,
            'box_size' => 'medium',
            'amount' => 35,
            'currency' => 'EUR',
            'status' => OrderStatus::Scheduled->value,
            'scheduled_for_date' => now()->addDays(2)->toDateString(),
            'preferred_timeslot' => '08:00-12:00',
            'is_test' => true,
        ]);

        $trialDelivery = Delivery::factory()->create([
            'user_id' => $customer->id,
            'address_id' => $address->id,
            'order_id' => $order->id,
        ]);

        $weeklyUser = User::factory()->create(['email' => 'weekly@example.test']);
        $weeklyDelivery = Delivery::factory()->create([
            'user_id' => $weeklyUser->id,
        ]);

        $this->actingAs($admin)
            ->get(route('app.admin.deliveries.index', ['trial_only' => '1']))
            ->assertOk()
            ->assertSee((string) $trialDelivery->id)
            ->assertDontSee($weeklyUser->email);
    }
}
