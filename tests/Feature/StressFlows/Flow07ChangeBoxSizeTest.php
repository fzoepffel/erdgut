<?php

namespace Tests\Feature\StressFlows;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stress-flow 7 (ERD-342): Change box size mid-subscription. Outcome:
 * the persisted box_size on the subscription matches the new value.
 */
class Flow07ChangeBoxSizeTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_changes_box_size_and_subscription_reflects_new_size(): void
    {
        $user = User::factory()->create();
        $subscription = $this->subscribeUserFully($user);
        $subscription->update(['box_size' => 'small']);

        $this->actingAs($user)
            ->patch(route('app.subscriptions.update'), [
                'plan' => 'weekly',
                'fulfillment_mode' => 'delivery',
                'box_size' => 'large',
                'preferences' => [
                    'veg_only' => '0',
                    'meat_add_on' => '0',
                    'eggs_dairy_add_on' => '0',
                ],
                'allergies' => '',
            ])
            ->assertRedirect(route('app.subscriptions.show'))
            ->assertSessionHas('status', 'subscription-updated');

        $this->assertSame('large', $subscription->fresh()->box_size);
    }
}
