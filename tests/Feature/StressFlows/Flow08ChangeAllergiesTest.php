<?php

namespace Tests\Feature\StressFlows;

use App\Enums\Allergen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stress-flow 8 (ERD-342): Change allergies mid-subscription. Outcome:
 * the new allergies array is persisted in canonical enum-value form so
 * downstream composition can filter against it.
 */
class Flow08ChangeAllergiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_updates_allergies_and_persisted_array_matches(): void
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
                'allergies' => Allergen::Gluten->value.', '.Allergen::Milk->value,
            ])
            ->assertRedirect(route('app.subscriptions.show'));

        $persisted = $subscription->fresh()->allergies ?? [];
        $this->assertContains(Allergen::Gluten->value, $persisted);
        $this->assertContains(Allergen::Milk->value, $persisted);
    }
}
