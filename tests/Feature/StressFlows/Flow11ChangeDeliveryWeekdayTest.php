<?php

namespace Tests\Feature\StressFlows;

use App\Enums\Timeslot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stress-flow 11 (ERD-342): Change delivery weekday + timeslot via the
 * subscription update form. Outcome: subscription persists the new
 * weekday/timeslot, with values outside the zone window rejected.
 */
class Flow11ChangeDeliveryWeekdayTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_changes_weekday_and_timeslot_within_window(): void
    {
        $user = User::factory()->create();
        $subscription = $this->subscribeUserFully($user);

        $this->actingAs($user)
            ->patch(route('app.subscriptions.update'), [
                'plan' => 'weekly',
                'fulfillment_mode' => 'delivery',
                'box_size' => $subscription->box_size,
                'preferences' => [
                    'veg_only' => '0',
                    'meat_add_on' => '0',
                    'eggs_dairy_add_on' => '0',
                ],
                'allergies' => '',
                // D2 (UX notes): zone is now Wed × 12-16 only. Use those
                // values instead of the older Thursday/Afternoon combo.
                'delivery_weekday' => 3,
                'preferred_timeslot' => Timeslot::Afternoon->value,
            ])
            ->assertRedirect(route('app.subscriptions.show'));

        $subscription->refresh();
        $this->assertSame(3, $subscription->delivery_weekday);
    }

    public function test_weekday_outside_zone_window_is_rejected(): void
    {
        $user = User::factory()->create();
        $subscription = $this->subscribeUserFully($user);
        $original = $subscription->delivery_weekday;

        $this->actingAs($user)
            ->from(route('app.subscriptions.edit'))
            ->patch(route('app.subscriptions.update'), [
                'plan' => 'weekly',
                'fulfillment_mode' => 'delivery',
                'box_size' => $subscription->box_size,
                'preferences' => [
                    'veg_only' => '0',
                    'meat_add_on' => '0',
                    'eggs_dairy_add_on' => '0',
                ],
                'allergies' => '',
                'delivery_weekday' => 0, // Sunday — outside the zone window
                'preferred_timeslot' => Timeslot::Morning->value,
            ])
            ->assertSessionHasErrors('delivery_weekday');

        $this->assertSame($original, $subscription->fresh()->delivery_weekday);
    }
}
