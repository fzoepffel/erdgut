<?php

namespace Tests\Feature;

use App\Enums\BoxStatus;
use App\Enums\DeliveryStatus;
use App\Enums\OrderProductType;
use App\Enums\OrderStatus;
use App\Enums\SubscriptionStatus;
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

/**
 * Locks the trial-pipeline correctness fixes from the UX review:
 *
 *  - I1: admin delivery-create form surfaces validation errors instead
 *    of redirecting silently.
 *  - I4: marking a Delivery as Delivered propagates the terminal state
 *    onto the linked Order and Box (so the customer dashboard, which
 *    reads Order.status / Box.status, doesn't drift out of sync).
 *  - J9: Delivery cannot be marked Delivered when the linked Box has
 *    no contents (and a `confirm_empty=1` escape hatch exists for the
 *    rare legitimate cases).
 *  - K14: when an admin saves a box without an eggs product for a
 *    customer who paid for the eggs add-on, the save flashes a
 *    distinct status key, the customer-side Box page surfaces the
 *    refund notice, and the eggs_addon_unfulfilled_at flag is stamped.
 */
class TrialPipelineTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::factory()->admin()->create();
    }

    private function makeCustomerWithSubscription(array $overrides = []): array
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $address = Address::factory()->create(['user_id' => $user->id, 'is_primary' => true]);
        $sub = $this->subscribeUserFully($user);
        $sub->update(array_merge([
            'status' => SubscriptionStatus::Active,
            'box_size' => 'medium',
        ], $overrides));

        return [$user, $address, $sub];
    }

    // ---------------------------------------------------------------------
    // I1 — admin delivery-create form surfaces validation errors.
    // ---------------------------------------------------------------------

    public function test_i1_admin_delivery_store_with_no_box_or_order_redirects_with_user_id_error(): void
    {
        Mail::fake();
        $admin = $this->makeAdmin();
        [$user, $address] = $this->makeCustomerWithSubscription();

        $this->actingAs($admin)
            ->from(route('app.admin.deliveries.create'))
            ->post(route('app.admin.deliveries.store'), [
                'user_id' => $user->id,
                'address_id' => $address->id,
                'scheduled_for_date' => '2026-05-13',
                'timeslot' => '12:00-16:00',
                'status' => DeliveryStatus::Scheduled->value,
            ])
            // Validator with custom rule rejects → redirect back to create
            // form with `user_id` error in the bag (not a 200 with no message).
            ->assertSessionHasErrors('user_id');

        // No delivery should have been written.
        $this->assertDatabaseMissing('deliveries', [
            'user_id' => $user->id,
            'scheduled_for_date' => '2026-05-13',
        ]);
    }

    public function test_i1_admin_delivery_create_view_renders_error_banner_when_validation_failed(): void
    {
        $admin = $this->makeAdmin();
        [$user, $address] = $this->makeCustomerWithSubscription();

        // followingRedirects() walks the validator-failure redirect back to
        // the create form so we can assert the banner is rendered (proves
        // the @if($errors->any()) block + data-testid both surface).
        $response = $this->actingAs($admin)
            ->from(route('app.admin.deliveries.create'))
            ->followingRedirects()
            ->post(route('app.admin.deliveries.store', ['user_id' => $user->id]), [
                'user_id' => $user->id,
                'address_id' => $address->id,
                'scheduled_for_date' => '2026-05-13',
                'timeslot' => '12:00-16:00',
                'status' => DeliveryStatus::Scheduled->value,
            ]);

        $response->assertOk();
        $response->assertSee('admin-deliveries-create-errors', false);
    }

    // ---------------------------------------------------------------------
    // J9 — delivered without a box is blocked.
    // ---------------------------------------------------------------------

    public function test_j9_marking_delivery_delivered_without_box_contents_is_blocked(): void
    {
        Mail::fake();
        $admin = $this->makeAdmin();
        [$user, $address] = $this->makeCustomerWithSubscription();

        // No Box linked, no Order linked → orphan delivery (the situation the
        // J9 guard is meant to catch).
        $delivery = Delivery::create([
            'user_id' => $user->id,
            'address_id' => $address->id,
            'scheduled_for_date' => '2026-05-13',
            'timeslot' => '12:00-16:00',
            'status' => DeliveryStatus::Scheduled,
            'is_weekly_override' => false,
        ]);

        $this->actingAs($admin)
            ->from(route('app.admin.deliveries.index'))
            ->patch(route('app.admin.deliveries.status.update', $delivery), ['status' => 'delivered'])
            ->assertSessionHasErrors('status')
            ->assertSessionHas('status', 'admin-delivery-blocked-no-box');

        $delivery->refresh();
        $this->assertSame(DeliveryStatus::Scheduled, $delivery->status);
        $this->assertNull($delivery->delivered_at);
    }

    public function test_j9_confirm_empty_flag_unblocks_delivered_without_box(): void
    {
        Mail::fake();
        $admin = $this->makeAdmin();
        [$user, $address] = $this->makeCustomerWithSubscription();

        $delivery = Delivery::create([
            'user_id' => $user->id,
            'address_id' => $address->id,
            'scheduled_for_date' => '2026-05-13',
            'timeslot' => '12:00-16:00',
            'status' => DeliveryStatus::Scheduled,
            'is_weekly_override' => false,
        ]);

        $this->actingAs($admin)
            ->from(route('app.admin.deliveries.index'))
            ->patch(route('app.admin.deliveries.status.update', $delivery), [
                'status' => 'delivered',
                'confirm_empty' => '1',
            ])
            ->assertSessionHas('status', 'admin-delivery-status-updated');

        $delivery->refresh();
        $this->assertSame(DeliveryStatus::Delivered, $delivery->status);
        $this->assertNotNull($delivery->delivered_at);
    }

    public function test_j9_delivery_with_box_items_is_allowed_through_to_delivered(): void
    {
        Mail::fake();
        $admin = $this->makeAdmin();
        [$user, $address, $sub] = $this->makeCustomerWithSubscription();

        $box = Box::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $sub->id,
            'status' => BoxStatus::Editable,
            'week_start_date' => '2026-05-11',
            'estimated_value' => 0,
        ]);
        BoxItem::factory()->create([
            'box_id' => $box->id,
            'product_id' => Product::factory()->create()->id,
            'quantity' => 1,
            'unit' => 'kg',
            'is_extra' => false,
        ]);

        $delivery = Delivery::create([
            'user_id' => $user->id,
            'box_id' => $box->id,
            'address_id' => $address->id,
            'scheduled_for_date' => '2026-05-13',
            'timeslot' => '12:00-16:00',
            'status' => DeliveryStatus::Scheduled,
            'is_weekly_override' => false,
        ]);

        $this->actingAs($admin)
            ->from(route('app.admin.deliveries.index'))
            ->patch(route('app.admin.deliveries.status.update', $delivery), ['status' => 'delivered'])
            ->assertSessionHas('status', 'admin-delivery-status-updated');

        $delivery->refresh();
        $this->assertSame(DeliveryStatus::Delivered, $delivery->status);
    }

    // ---------------------------------------------------------------------
    // I4 — propagation to Order/Box on delivery=delivered.
    // ---------------------------------------------------------------------

    public function test_i4_marking_trial_delivery_delivered_propagates_to_order(): void
    {
        Mail::fake();
        $admin = $this->makeAdmin();

        $user = User::factory()->create(['email_verified_at' => now()]);
        $address = Address::factory()->create(['user_id' => $user->id, 'is_primary' => true]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'product_type' => OrderProductType::TrialBox,
            'status' => OrderStatus::Scheduled,
            'scheduled_for_date' => '2026-05-13',
            'delivered_at' => null,
        ]);

        $delivery = Delivery::create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'address_id' => $address->id,
            'scheduled_for_date' => '2026-05-13',
            'timeslot' => '12:00-16:00',
            'status' => DeliveryStatus::Scheduled,
            'is_weekly_override' => false,
        ]);

        $this->actingAs($admin)
            ->from(route('app.admin.deliveries.index'))
            ->patch(route('app.admin.deliveries.status.update', $delivery), [
                'status' => 'delivered',
                'confirm_empty' => '1',
            ]);

        $order->refresh();
        $this->assertSame(OrderStatus::Delivered, $order->status);
        $this->assertNotNull($order->delivered_at, 'Order.delivered_at should be backfilled');
    }

    public function test_i4_marking_subscription_delivery_delivered_promotes_box_through_locked_to_delivered(): void
    {
        Mail::fake();
        $admin = $this->makeAdmin();
        [$user, $address, $sub] = $this->makeCustomerWithSubscription();

        $box = Box::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $sub->id,
            'status' => BoxStatus::Editable,
            'week_start_date' => '2026-05-11',
            'estimated_value' => 0,
        ]);
        BoxItem::factory()->create([
            'box_id' => $box->id,
            'product_id' => Product::factory()->create()->id,
            'quantity' => 1,
            'unit' => 'kg',
            'is_extra' => false,
        ]);

        $delivery = Delivery::create([
            'user_id' => $user->id,
            'box_id' => $box->id,
            'address_id' => $address->id,
            'scheduled_for_date' => '2026-05-13',
            'timeslot' => '12:00-16:00',
            'status' => DeliveryStatus::Scheduled,
            'is_weekly_override' => false,
        ]);

        $this->actingAs($admin)
            ->from(route('app.admin.deliveries.index'))
            ->patch(route('app.admin.deliveries.status.update', $delivery), ['status' => 'delivered']);

        $box->refresh();
        $this->assertSame(BoxStatus::Delivered, $box->status, 'Box should walk Editable→Locked→Delivered');
    }

    public function test_i4_does_not_propagate_when_delivery_is_an_override(): void
    {
        Mail::fake();
        $admin = $this->makeAdmin();
        [$user, $address] = $this->makeCustomerWithSubscription();

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'product_type' => OrderProductType::TrialBox,
            'status' => OrderStatus::Scheduled,
            'scheduled_for_date' => '2026-05-13',
        ]);

        $base = Delivery::create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'address_id' => $address->id,
            'scheduled_for_date' => '2026-05-13',
            'timeslot' => '12:00-16:00',
            'status' => DeliveryStatus::Scheduled,
            'is_weekly_override' => false,
        ]);

        $override = Delivery::create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'address_id' => $address->id,
            'scheduled_for_date' => '2026-05-15',
            'timeslot' => '12:00-16:00',
            'status' => DeliveryStatus::Scheduled,
            'is_weekly_override' => true,
            'overrides_delivery_id' => $base->id,
        ]);

        $this->actingAs($admin)
            ->from(route('app.admin.deliveries.index'))
            ->patch(route('app.admin.deliveries.status.update', $override), [
                'status' => 'delivered',
                'confirm_empty' => '1',
            ]);

        // Override delivered → Order should NOT be touched (the canonical
        // base delivery is still scheduled).
        $order->refresh();
        $this->assertSame(OrderStatus::Scheduled, $order->status);
        $this->assertNull($order->delivered_at);
    }

    // ---------------------------------------------------------------------
    // K14 — eggs add-on charged but not delivered.
    // ---------------------------------------------------------------------

    public function test_k14_customer_box_show_renders_eggs_unfulfilled_notice_when_flag_is_set(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $sub = $this->subscribeUserFully($user);
        $sub->update(['box_size' => 'medium']);

        $box = Box::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $sub->id,
            'week_start_date' => now()->startOfWeek()->toDateString(),
            'estimated_value' => 30,
            'eggs_addon_unfulfilled_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->get(route('app.boxes.show', $box))
            ->assertOk();

        $response->assertSee('customer-box-eggs-unfulfilled-notice', false);
    }

    public function test_k14_customer_box_show_does_not_render_notice_when_flag_is_null(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $sub = $this->subscribeUserFully($user);
        $sub->update(['box_size' => 'medium']);

        $box = Box::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $sub->id,
            'week_start_date' => now()->startOfWeek()->toDateString(),
            'estimated_value' => 30,
            'eggs_addon_unfulfilled_at' => null,
        ]);

        $response = $this->actingAs($user)
            ->get(route('app.boxes.show', $box))
            ->assertOk();

        $response->assertDontSee('customer-box-eggs-unfulfilled-notice', false);
    }
}
