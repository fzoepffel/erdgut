<?php

namespace Tests\Feature\Database;

use App\Enums\DeliveryStatus;
use App\Models\Address;
use App\Models\Delivery;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryModelWeeklyOverrideTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_persists_and_links_to_user_and_address(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $user->id]);

        $delivery = Delivery::create([
            'user_id' => $user->id,
            'address_id' => $address->id,
            'scheduled_for_date' => '2026-04-15',
            'timeslot' => '12:00-16:00',
            'status' => DeliveryStatus::Scheduled,
            'special_instructions' => 'Ring bell at side door.',
            'is_weekly_override' => false,
        ]);

        $this->assertDatabaseHas('deliveries', [
            'id' => $delivery->id,
            'user_id' => $user->id,
            'address_id' => $address->id,
            'scheduled_for_date' => '2026-04-15 00:00:00',
            'timeslot' => '12:00-16:00',
            'status' => DeliveryStatus::Scheduled->value,
        ]);

        $this->assertSame(DeliveryStatus::Scheduled, $delivery->fresh()->status);
        $this->assertSame($user->id, $delivery->user->id);
        $this->assertSame($address->id, $delivery->address->id);
    }

    public function test_delivery_supports_weekly_override_linkage(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $user->id]);

        $baseDelivery = Delivery::factory()->create([
            'user_id' => $user->id,
            'address_id' => $address->id,
            'is_weekly_override' => false,
        ]);

        $overrideDelivery = Delivery::factory()->create([
            'user_id' => $user->id,
            'address_id' => $address->id,
            'is_weekly_override' => true,
            'overrides_delivery_id' => $baseDelivery->id,
            'timeslot' => '16:00-20:00',
        ]);

        $this->assertTrue($overrideDelivery->is_weekly_override);
        $this->assertSame($baseDelivery->id, $overrideDelivery->overridesDelivery?->id);
        $this->assertCount(1, $baseDelivery->fresh()->overrideDeliveries);
    }

    public function test_relationship_contracts_are_exposed(): void
    {
        $user = new User;
        $address = new Address;
        $delivery = new Delivery;

        $this->assertInstanceOf(HasMany::class, $user->deliveries());
        $this->assertInstanceOf(HasMany::class, $address->deliveries());
        $this->assertInstanceOf(BelongsTo::class, $delivery->user());
        $this->assertInstanceOf(BelongsTo::class, $delivery->address());
        $this->assertInstanceOf(BelongsTo::class, $delivery->overridesDelivery());
        $this->assertInstanceOf(HasMany::class, $delivery->overrideDeliveries());
    }
}
