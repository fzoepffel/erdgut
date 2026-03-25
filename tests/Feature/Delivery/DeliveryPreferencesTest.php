<?php

namespace Tests\Feature\Delivery;

use App\Models\Address;
use App\Models\DeliveryPreference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_delivery_preferences_pages(): void
    {
        $this->get(route('app.deliveries.preferences.edit'))
            ->assertRedirect('/auth/login');

        $this->patch(route('app.deliveries.preferences.update'), [])
            ->assertRedirect('/auth/login');
    }

    public function test_authenticated_user_can_open_delivery_preferences_form(): void
    {
        $user = User::factory()->create();
        $this->subscribeUserFully($user);
        Address::factory()->create([
            'user_id' => $user->id,
            'is_primary' => true,
            'building_code' => 'A1',
            'floor' => '2',
        ]);
        DeliveryPreference::query()->create([
            'user_id' => $user->id,
            'preferred_timeslot' => 'Monday 18:00-20:00',
            'phone' => '+491111111',
            'instructions' => 'Ring once.',
        ]);

        $this->actingAs($user)
            ->get(route('app.deliveries.preferences.edit'))
            ->assertOk()
            ->assertSee('Delivery preferences')
            ->assertSee('Monday 18:00-20:00')
            ->assertSee('A1');
    }

    public function test_user_can_save_delivery_preferences_and_primary_address(): void
    {
        $user = User::factory()->create();
        $this->subscribeUserFully($user);

        $this->actingAs($user)
            ->patch(route('app.deliveries.preferences.update'), [
                'recipient_name' => 'Max Mustermann',
                'street_line_1' => 'Musterweg 1',
                'street_line_2' => 'c/o Example',
                'postal_code' => '10115',
                'city' => 'Berlin',
                'country_code' => 'de',
                'phone' => '+49123456789',
                'building_code' => 'B2',
                'floor' => '4',
                'preferred_timeslot' => 'Tuesday 17:00-19:00',
                'instructions' => 'Leave at reception.',
            ])
            ->assertRedirect(route('app.deliveries.preferences.edit'))
            ->assertSessionHas('status', 'delivery-preferences-updated');

        $this->assertDatabaseHas('addresses', [
            'user_id' => $user->id,
            'recipient_name' => 'Max Mustermann',
            'street_line_1' => 'Musterweg 1',
            'street_line_2' => 'c/o Example',
            'postal_code' => '10115',
            'city' => 'Berlin',
            'country_code' => 'DE',
            'phone' => '+49123456789',
            'building_code' => 'B2',
            'floor' => '4',
            'delivery_instructions' => 'Leave at reception.',
            'is_primary' => true,
        ]);

        $this->assertDatabaseHas('delivery_preferences', [
            'user_id' => $user->id,
            'preferred_timeslot' => 'Tuesday 17:00-19:00',
            'phone' => '+49123456789',
            'instructions' => 'Leave at reception.',
        ]);
    }

    public function test_update_validates_required_delivery_preferences_fields(): void
    {
        $user = User::factory()->create();
        $this->subscribeUserFully($user);

        $this->actingAs($user)
            ->from(route('app.deliveries.preferences.edit'))
            ->patch(route('app.deliveries.preferences.update'), [
                'recipient_name' => '',
                'street_line_1' => '',
                'postal_code' => '',
                'city' => '',
                'country_code' => '',
                'preferred_timeslot' => '',
            ])
            ->assertRedirect(route('app.deliveries.preferences.edit'))
            ->assertSessionHasErrors([
                'recipient_name',
                'street_line_1',
                'postal_code',
                'city',
                'country_code',
                'preferred_timeslot',
            ]);
    }

    public function test_updating_preferences_does_not_change_other_users_records(): void
    {
        $user = User::factory()->create();
        $this->subscribeUserFully($user);
        $otherUser = User::factory()->create();

        Address::factory()->create([
            'user_id' => $otherUser->id,
            'is_primary' => true,
            'street_line_1' => 'Other Street 10',
        ]);
        DeliveryPreference::query()->create([
            'user_id' => $otherUser->id,
            'preferred_timeslot' => 'Friday 18:00-20:00',
        ]);

        $this->actingAs($user)
            ->patch(route('app.deliveries.preferences.update'), [
                'recipient_name' => 'Own User',
                'street_line_1' => 'Own Street 1',
                'street_line_2' => null,
                'postal_code' => '20095',
                'city' => 'Hamburg',
                'country_code' => 'DE',
                'phone' => null,
                'building_code' => null,
                'floor' => null,
                'preferred_timeslot' => 'Thursday 16:00-18:00',
                'instructions' => null,
            ])
            ->assertRedirect(route('app.deliveries.preferences.edit'));

        $this->assertDatabaseHas('addresses', [
            'user_id' => $otherUser->id,
            'street_line_1' => 'Other Street 10',
        ]);
        $this->assertDatabaseHas('delivery_preferences', [
            'user_id' => $otherUser->id,
            'preferred_timeslot' => 'Friday 18:00-20:00',
        ]);
    }
}
