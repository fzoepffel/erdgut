<?php

namespace Tests\Feature\Database;

use App\Models\Address;
use App\Models\DeliveryPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryPreferenceModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_and_delivery_preference_relationship_contracts_are_exposed(): void
    {
        $user = new User;
        $deliveryPreference = new DeliveryPreference;

        $this->assertInstanceOf(HasOne::class, $user->deliveryPreference());
        $this->assertInstanceOf(BelongsTo::class, $deliveryPreference->user());
    }

    public function test_delivery_preference_can_be_persisted_and_resolved_for_user(): void
    {
        $user = User::factory()->create();
        Address::factory()->create([
            'user_id' => $user->id,
            'is_primary' => true,
            'building_code' => 'C3',
            'floor' => '6',
        ]);

        $deliveryPreference = DeliveryPreference::query()->create([
            'user_id' => $user->id,
            'preferred_timeslot' => 'Wednesday 18:00-20:00',
            'phone' => '+4912345678',
            'instructions' => 'Use side entrance',
        ]);

        $this->assertDatabaseHas('delivery_preferences', [
            'id' => $deliveryPreference->id,
            'user_id' => $user->id,
            'preferred_timeslot' => 'Wednesday 18:00-20:00',
        ]);

        $this->assertSame($deliveryPreference->id, $user->deliveryPreference?->id);
        $this->assertSame($user->id, $deliveryPreference->user->id);
        $this->assertSame('C3', $user->primaryAddress?->building_code);
        $this->assertSame('6', $user->primaryAddress?->floor);
    }
}
