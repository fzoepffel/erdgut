<?php

namespace Tests\Feature\Subscription;

use App\Enums\SubscriptionPlan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SubscriptionUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_subscription_edit_and_update(): void
    {
        $this->get(route('app.subscriptions.edit'))
            ->assertRedirect('/auth/login');

        $this->patch(route('app.subscriptions.update'), [])
            ->assertRedirect('/auth/login');
    }

    public function test_authenticated_user_can_open_subscription_edit_page(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'box_size' => 'medium',
            'preferences' => ['veg_only' => true, 'meat_add_on' => false],
            'allergies' => ['nuts'],
        ]);

        $this->actingAs($user)
            ->get(route('app.subscriptions.edit'))
            ->assertOk()
            ->assertSee('Edit subscription')
            ->assertSee('Medium')
            ->assertSee('nuts');
    }

    public function test_user_can_update_subscription_settings(): void
    {
        Log::spy();

        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'box_size' => 'small',
            'preferences' => ['veg_only' => false, 'meat_add_on' => false],
            'allergies' => [],
        ]);

        $this->actingAs($user)
            ->patch(route('app.subscriptions.update'), [
                'plan' => 'weekly',
                'box_size' => 'large',
                'preferences' => [
                    'veg_only' => '1',
                ],
                'allergies' => 'nuts, gluten',
            ])
            ->assertRedirect(route('app.subscriptions.show'))
            ->assertSessionHas('status', 'subscription-updated');

        $subscription->refresh();

        $this->assertSame('large', $subscription->box_size);
        $this->assertSame(
            ['veg_only' => true, 'meat_add_on' => false],
            $subscription->preferences
        );
        $this->assertSame(['nuts', 'gluten'], $subscription->allergies);

        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(function (string $message, array $context) use ($subscription, $user): bool {
                return $message === 'subscription.updated'
                    && $context['subscription_id'] === $subscription->id
                    && $context['subscription_user_id'] === $subscription->user_id
                    && $context['actor_user_id'] === $user->id
                    && in_array('box_size', $context['changed_fields'], true)
                    && in_array('preferences', $context['changed_fields'], true)
                    && in_array('allergies', $context['changed_fields'], true);
            });
    }

    public function test_update_validates_box_size_and_keeps_existing_values(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'box_size' => 'medium',
            'preferences' => ['veg_only' => true, 'meat_add_on' => false],
            'allergies' => ['nuts'],
        ]);

        $this->actingAs($user)
            ->from(route('app.subscriptions.edit'))
            ->patch(route('app.subscriptions.update'), [
                'plan' => 'weekly',
                'box_size' => 'invalid-size',
                'preferences' => [
                    'veg_only' => '1',
                ],
                'allergies' => 'nuts',
            ])
            ->assertRedirect(route('app.subscriptions.edit'))
            ->assertSessionHasErrors(['box_size']);

        $subscription->refresh();

        $this->assertSame('medium', $subscription->box_size);
        $this->assertSame(['veg_only' => true, 'meat_add_on' => false], $subscription->preferences);
        $this->assertSame(['nuts'], $subscription->allergies);
    }

    public function test_update_requires_box_size(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'box_size' => 'small',
            'preferences' => ['veg_only' => false, 'meat_add_on' => false],
            'allergies' => [],
        ]);

        $this->actingAs($user)
            ->from(route('app.subscriptions.edit'))
            ->patch(route('app.subscriptions.update'), [])
            ->assertRedirect(route('app.subscriptions.edit'))
            ->assertSessionHasErrors(['plan', 'box_size']);
    }

    public function test_update_rejects_allergy_exceeding_max_length(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'box_size' => 'small',
            'preferences' => ['veg_only' => false, 'meat_add_on' => false],
            'allergies' => [],
        ]);

        $this->actingAs($user)
            ->from(route('app.subscriptions.edit'))
            ->patch(route('app.subscriptions.update'), [
                'plan' => 'weekly',
                'box_size' => 'medium',
                'preferences' => ['veg_only' => '1', 'meat_add_on' => '0'],
                'allergies' => [str_repeat('a', 256)],
            ])
            ->assertRedirect(route('app.subscriptions.edit'))
            ->assertSessionHasErrors(['allergies.0']);
    }

    public function test_successful_update_persists_preferences_and_allergies(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'box_size' => 'small',
            'preferences' => ['veg_only' => false, 'meat_add_on' => false],
            'allergies' => [],
        ]);

        $this->actingAs($user)
            ->patch(route('app.subscriptions.update'), [
                'plan' => 'weekly',
                'box_size' => 'small',
                'preferences' => ['veg_only' => '1'],
                'allergies' => 'gluten, lactose, shellfish',
            ])
            ->assertRedirect(route('app.subscriptions.show'))
            ->assertSessionHas('status', 'subscription-updated');

        $subscription->refresh();

        $this->assertTrue($subscription->preferences['veg_only']);
        $this->assertFalse($subscription->preferences['meat_add_on']);
        $this->assertSame(['gluten', 'lactose', 'shellfish'], $subscription->allergies);
    }

    public function test_edit_and_update_redirect_when_user_has_no_subscription(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $otherSubscription = Subscription::factory()->create([
            'user_id' => $otherUser->id,
            'box_size' => 'small',
        ]);

        $this->actingAs($user)
            ->get(route('app.subscriptions.edit'))
            ->assertRedirect(route('app.subscriptions.show'))
            ->assertSessionHas('status', 'subscription-missing');

        $this->actingAs($user)
            ->patch(route('app.subscriptions.update'), [
                'plan' => 'weekly',
                'box_size' => 'large',
                'preferences' => [
                    'veg_only' => true,
                    'meat_add_on' => true,
                ],
                'allergies' => 'soy',
            ])
            ->assertRedirect(route('app.subscriptions.show'))
            ->assertSessionHas('status', 'subscription-missing');

        $otherSubscription->refresh();
        $this->assertSame('small', $otherSubscription->box_size);
    }

    public function test_update_rejects_invalid_plan(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'box_size' => 'medium',
            'plan' => SubscriptionPlan::Weekly,
            'preferences' => ['veg_only' => false, 'meat_add_on' => false],
            'allergies' => [],
        ]);

        $this->actingAs($user)
            ->from(route('app.subscriptions.edit'))
            ->patch(route('app.subscriptions.update'), [
                'plan' => 'invalid',
                'box_size' => 'medium',
                'preferences' => ['veg_only' => '0', 'meat_add_on' => '0'],
                'allergies' => '',
            ])
            ->assertRedirect(route('app.subscriptions.edit'))
            ->assertSessionHasErrors(['plan']);
    }

    public function test_active_plan_change_recalculates_next_charge_date(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan' => SubscriptionPlan::Weekly,
            'box_size' => 'small',
            'preferences' => ['veg_only' => false, 'meat_add_on' => false],
            'allergies' => [],
            'next_charge_date' => null,
        ]);

        $expectedNext = now()->addWeeks(2)->toDateString();

        $this->actingAs($user)
            ->patch(route('app.subscriptions.update'), [
                'plan' => 'biweekly',
                'box_size' => 'small',
                'preferences' => ['veg_only' => '0', 'meat_add_on' => '0'],
                'allergies' => '',
            ])
            ->assertRedirect(route('app.subscriptions.show'))
            ->assertSessionHas('status', 'subscription-updated');

        $subscription->refresh();

        $this->assertSame(SubscriptionPlan::Biweekly, $subscription->plan);
        $this->assertSame($expectedNext, $subscription->next_charge_date?->toDateString());
    }
}
