<?php

namespace Tests\Unit\Services;

use App\Enums\DeliveryStatus;
use App\Enums\OrderProductType;
use App\Enums\OrderStatus;
use App\Models\Address;
use App\Models\Box;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\User;
use App\Support\DeliveryProjector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DeliveryProjectorTest extends TestCase
{
    use RefreshDatabase;

    private function projector(): DeliveryProjector
    {
        return new DeliveryProjector;
    }

    public function test_projects_delivery_for_box_sets_box_id_and_ships_wednesday_of_week(): void
    {
        $user = User::factory()->create();
        Address::factory()->create(['user_id' => $user->id, 'is_primary' => true]);

        $box = Box::factory()->create([
            'user_id' => $user->id,
            'week_start_date' => '2026-05-11', // Monday
        ]);

        $delivery = $this->projector()->projectForBox($box);

        $this->assertNotNull($delivery);
        $this->assertSame($box->id, $delivery->box_id);
        $this->assertSame('2026-05-13', $delivery->scheduled_for_date->toDateString()); // Wednesday
        $this->assertSame(DeliveryStatus::Scheduled, $delivery->status);
        $this->assertFalse($delivery->is_weekly_override);
    }

    public function test_projects_upserts_existing_canonical_delivery_for_same_week(): void
    {
        $user = User::factory()->create();
        Address::factory()->create(['user_id' => $user->id, 'is_primary' => true]);

        $boxA = Box::factory()->create([
            'user_id' => $user->id,
            'week_start_date' => '2026-05-11',
        ]);
        $boxB = Box::factory()->create([
            'user_id' => $user->id,
            'week_start_date' => '2026-05-11',
        ]);

        $first = $this->projector()->projectForBox($boxA);
        $second = $this->projector()->projectForBox($boxB);

        $this->assertSame($first->id, $second->id);
        $this->assertSame($boxB->id, $second->box_id);
        $this->assertSame(1, Delivery::where('user_id', $user->id)->count());
    }

    public function test_projects_returns_null_when_user_has_no_primary_address(): void
    {
        $user = User::factory()->create();
        $box = Box::factory()->create(['user_id' => $user->id]);

        $this->assertNull($this->projector()->projectForBox($box));
        $this->assertSame(0, Delivery::count());
    }

    public function test_trial_order_creates_delivery_with_box_id_when_box_exists(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $user->id, 'is_primary' => true]);
        $box = Box::factory()->create(['user_id' => $user->id]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'address_id' => $address->id,
            'box_id' => $box->id,
            'product_type' => OrderProductType::TrialBox,
            'status' => OrderStatus::Paid,
        ]);

        $delivery = $this->projector()->projectForTrialOrder(
            $order,
            Carbon::parse('2026-05-13'),
            '12:00-16:00'
        );

        $this->assertSame($order->id, $delivery->order_id);
        $this->assertSame($box->id, $delivery->box_id);
        $this->assertSame('2026-05-13', $delivery->scheduled_for_date->toDateString());
        $this->assertSame('12:00-16:00', $delivery->timeslot);
    }

    public function test_trial_order_updates_existing_delivery_instead_of_duplicating(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $user->id, 'is_primary' => true]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'address_id' => $address->id,
            'product_type' => OrderProductType::TrialBox,
            'status' => OrderStatus::Paid,
        ]);

        $this->projector()->projectForTrialOrder($order, Carbon::parse('2026-05-13'), '08:00-12:00');
        $this->projector()->projectForTrialOrder($order, Carbon::parse('2026-05-14'), '16:00-20:00');

        $this->assertSame(1, Delivery::where('order_id', $order->id)->count());
        $delivery = Delivery::where('order_id', $order->id)->first();
        $this->assertSame('2026-05-14', $delivery->scheduled_for_date->toDateString());
        $this->assertSame('16:00-20:00', $delivery->timeslot);
    }

    public function test_scheduled_date_for_box_week_returns_wednesday(): void
    {
        $this->assertSame(
            '2026-05-13',
            DeliveryProjector::scheduledDateForBoxWeek(Carbon::parse('2026-05-11'))->toDateString()
        );
    }
}
