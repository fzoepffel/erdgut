<?php

namespace Tests\Feature\StressFlows;

use App\Enums\OrderProductType;
use App\Models\Address;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stress-flow 14 (ERD-342): Admin creates a trial order on behalf of a
 * user. Outcome: an Order row exists for the target user, product type
 * is TrialBox, and an audit_events row records the admin actor.
 */
class Flow14AdminCreateTrialOnBehalfTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_trial_order_for_user_with_audit_event(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create(['is_admin' => false]);
        $address = Address::factory()->create(['user_id' => $customer->id]);

        $this->actingAs($admin)
            ->post(route('app.admin.orders.store'), [
                'user_id' => $customer->id,
                'address_id' => $address->id,
                'box_size' => 'medium',
                'preferences' => ['veg_only' => '0', 'meat_add_on' => '0', 'eggs_dairy_add_on' => '0'],
                'reason' => 'Phone order',
            ])
            ->assertRedirect();

        $order = Order::where('user_id', $customer->id)->first();
        $this->assertNotNull($order);
        $this->assertSame(OrderProductType::TrialBox, $order->product_type);

        $this->assertDatabaseHas('audit_events', [
            'entity_type' => 'order',
            'entity_id' => $order->id,
            'actor_user_id' => $admin->id,
        ]);
    }
}
