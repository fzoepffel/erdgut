<?php

namespace Tests\Feature\Subscription;

use App\Enums\Allergen;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionAllergyEnumTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_update_rejects_non_enum_allergy(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'box_size' => 'medium',
            'preferences' => ['veg_only' => false, 'meat_add_on' => false, 'eggs_dairy_add_on' => false],
            'allergies' => [],
        ]);

        $this->actingAs($user)
            ->from(route('app.subscriptions.edit'))
            ->patch(route('app.subscriptions.update'), [
                'plan' => 'weekly',
                'fulfillment_mode' => 'delivery',
                'box_size' => 'medium',
                'preferences' => ['veg_only' => '0', 'meat_add_on' => '0', 'eggs_dairy_add_on' => '0'],
                'allergies' => ['not-a-real-allergen'],
            ])
            ->assertRedirect(route('app.subscriptions.edit'))
            ->assertSessionHasErrors(['allergies.0']);
    }

    public function test_subscription_update_accepts_enum_allergies_and_free_text_note(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'box_size' => 'medium',
            'preferences' => ['veg_only' => false, 'meat_add_on' => false, 'eggs_dairy_add_on' => false],
            'allergies' => [],
        ]);

        $this->actingAs($user)
            ->patch(route('app.subscriptions.update'), [
                'plan' => 'weekly',
                'fulfillment_mode' => 'delivery',
                'box_size' => 'medium',
                'preferences' => ['veg_only' => '0', 'meat_add_on' => '0', 'eggs_dairy_add_on' => '0'],
                'allergies' => [Allergen::Gluten->value, Allergen::Milk->value],
                'allergies_note' => 'Also no spicy peppers.',
            ])
            ->assertRedirect(route('app.subscriptions.show'))
            ->assertSessionHas('status', 'subscription-updated');

        $subscription->refresh();
        $this->assertSame(['gluten', 'milk'], $subscription->allergies);
        $this->assertSame('Also no spicy peppers.', $subscription->allergies_note);
    }

    public function test_edit_page_renders_enum_checkboxes_and_note_field(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'allergies' => [Allergen::Gluten->value],
            'allergies_note' => 'Plus chili.',
        ]);

        $this->actingAs($user)
            ->get(route('app.subscriptions.edit'))
            ->assertOk()
            ->assertSee('name="allergies[]"', false)
            ->assertSee('name="allergies_note"', false)
            ->assertSee('Plus chili.');
    }
}
