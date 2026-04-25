<?php

namespace Tests\Feature\StressFlows;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stress-flow 10 (ERD-342): Toggle eggs-dairy add-on. Outcome: the
 * preferences.eggs_dairy_add_on flag flips and (downstream) the
 * BoxPricing math will charge the supplement on the next recurring
 * charge.
 */
class Flow10EggsDairyAddOnTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_enables_eggs_dairy_add_on_and_preference_persists(): void
    {
        $user = User::factory()->create();
        $subscription = $this->subscribeUserFully($user);
        $subscription->update([
            'preferences' => array_merge((array) $subscription->preferences, ['eggs_dairy_add_on' => false]),
        ]);

        $this->actingAs($user)
            ->patch(route('app.subscriptions.update'), [
                'plan' => 'weekly',
                'fulfillment_mode' => 'delivery',
                'box_size' => $subscription->box_size,
                'preferences' => [
                    'veg_only' => '0',
                    'meat_add_on' => '0',
                    'eggs_dairy_add_on' => '1',
                ],
                'allergies' => '',
            ])
            ->assertRedirect(route('app.subscriptions.show'));

        $this->assertTrue(
            (bool) ($subscription->fresh()->preferences['eggs_dairy_add_on'] ?? false),
            'eggs_dairy_add_on should be true after the update.'
        );
    }
}
