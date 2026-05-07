<?php

namespace Tests\Feature\Subscription;

use App\Enums\Timeslot;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionWeekdayTimeslotTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_page_renders_allowed_weekday_and_timeslot_options(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'delivery_weekday' => 3,
            'preferred_timeslot' => Timeslot::Afternoon->value,
        ]);

        $this->actingAs($user)
            ->get(route('app.subscriptions.edit'))
            ->assertOk()
            ->assertSee('name="delivery_weekday"', false)
            ->assertSee('name="preferred_timeslot"', false)
            ->assertSee(Timeslot::Afternoon->value)
            ->assertSee('Wednesday');
    }

    public function test_user_can_save_weekday_and_timeslot_within_zone_window(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'box_size' => 'medium',
            'preferences' => ['veg_only' => false, 'meat_add_on' => false],
            'allergies' => [],
            'delivery_weekday' => null,
            'preferred_timeslot' => null,
        ]);

        $this->actingAs($user)
            ->patch(route('app.subscriptions.update'), [
                'plan' => $subscription->plan->value,
                'fulfillment_mode' => 'delivery',
                'box_size' => 'medium',
                'preferences' => [
                    'veg_only' => '0',
                    'meat_add_on' => '0',
                    'eggs_dairy_add_on' => '0',
                ],
                'allergies' => '',
                // D2 (UX notes): zone now exposes only Wed × 12-16. Use
                // those real values; the older Thursday/Morning combo
                // is no longer in the picker.
                'delivery_weekday' => 3,
                'preferred_timeslot' => Timeslot::Afternoon->value,
            ])
            ->assertRedirect(route('app.subscriptions.show'));

        $subscription->refresh();
        $this->assertSame(3, $subscription->delivery_weekday);
        $this->assertSame(Timeslot::Afternoon, $subscription->preferred_timeslot);
    }

    public function test_update_rejects_weekday_outside_zone_window(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'box_size' => 'medium',
            'preferences' => ['veg_only' => false, 'meat_add_on' => false],
            'allergies' => [],
        ]);

        $response = $this->actingAs($user)
            ->patch(route('app.subscriptions.update'), [
                'plan' => $subscription->plan->value,
                'fulfillment_mode' => 'delivery',
                'box_size' => 'medium',
                'preferences' => [
                    'veg_only' => '0',
                    'meat_add_on' => '0',
                    'eggs_dairy_add_on' => '0',
                ],
                'allergies' => '',
                // zone allows 2-5 (Tue-Fri); Sunday (0) is outside window
                'delivery_weekday' => 0,
                'preferred_timeslot' => Timeslot::Morning->value,
            ]);

        $response->assertSessionHasErrors('delivery_weekday');

        $subscription->refresh();
        $this->assertNotSame(0, $subscription->delivery_weekday);
    }

    public function test_update_rejects_invalid_timeslot_string(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'box_size' => 'medium',
            'preferences' => ['veg_only' => false, 'meat_add_on' => false],
            'allergies' => [],
        ]);

        $response = $this->actingAs($user)
            ->patch(route('app.subscriptions.update'), [
                'plan' => $subscription->plan->value,
                'fulfillment_mode' => 'delivery',
                'box_size' => 'medium',
                'preferences' => [
                    'veg_only' => '0',
                    'meat_add_on' => '0',
                    'eggs_dairy_add_on' => '0',
                ],
                'allergies' => '',
                'delivery_weekday' => 3,
                'preferred_timeslot' => '20:00-23:59',
            ]);

        $response->assertSessionHasErrors('preferred_timeslot');
    }
}
