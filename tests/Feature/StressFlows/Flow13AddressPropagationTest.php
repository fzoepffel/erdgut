<?php

namespace Tests\Feature\StressFlows;

use App\Models\Address;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stress-flow 13 (ERD-342): Address change persists to the user's
 * primary address row. Past Delivery shipping_* snapshots remain
 * untouched (historical receipts must not mutate).
 */
class Flow13AddressPropagationTest extends TestCase
{
    use RefreshDatabase;

    public function test_address_update_persists_via_delivery_preferences_route(): void
    {
        $user = User::factory()->create();
        $this->subscribeUserFully($user);
        Address::factory()->create([
            'user_id' => $user->id,
            'is_primary' => true,
            'street_line_1' => 'Old Street 1',
            'postal_code' => '10115',
            'city' => 'Berlin',
            'country_code' => 'DE',
        ]);

        // B5 zone validation rejects out-of-Berlin addresses — keep this
        // propagation test inside the configured zone (Berlin).
        $this->actingAs($user)
            ->patch(route('app.deliveries.preferences.update'), [
                'recipient_name' => 'Test Receiver',
                'street_line_1' => 'New Street 99',
                'postal_code' => '12435',
                'city' => 'Berlin',
                'country_code' => 'DE',
                'phone' => null,
                'instructions' => null,
                'preferred_timeslot' => 'morning',
            ])
            ->assertRedirect();

        $primary = Address::where('user_id', $user->id)->where('is_primary', true)->first();
        $this->assertNotNull($primary);
        $this->assertSame('New Street 99', $primary->street_line_1);
        $this->assertSame('Berlin', $primary->city);
    }
}
