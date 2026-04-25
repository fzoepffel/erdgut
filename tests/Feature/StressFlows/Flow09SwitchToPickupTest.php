<?php

namespace Tests\Feature\StressFlows;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stress-flow 9 (ERD-342): Switch fulfillment mode delivery → pickup
 * via the subscription update form. Outcome: persisted fulfillment_mode
 * reads "pickup".
 */
class Flow09SwitchToPickupTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_switches_to_pickup_and_subscription_reflects_change(): void
    {
        $user = User::factory()->create();
        $subscription = $this->subscribeUserFully($user);
        $subscription->update(['fulfillment_mode' => 'delivery']);

        $this->actingAs($user)
            ->patch(route('app.subscriptions.update'), [
                'plan' => 'weekly',
                'fulfillment_mode' => 'pickup',
                'box_size' => $subscription->box_size,
                'preferences' => [
                    'veg_only' => '0',
                    'meat_add_on' => '0',
                    'eggs_dairy_add_on' => '0',
                ],
                'allergies' => '',
            ])
            ->assertRedirect(route('app.subscriptions.show'));

        $this->assertSame('pickup', $subscription->fresh()->fulfillment_mode->value);
    }
}
