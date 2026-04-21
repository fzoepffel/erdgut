<?php

namespace Tests\Feature\Subscription;

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_subscription_overview(): void
    {
        $this->get(route('app.subscriptions.show'))
            ->assertRedirect('/auth/login');
    }

    public function test_authenticated_user_sees_own_subscription_overview(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'plan' => SubscriptionPlan::Weekly,
            'box_size' => 'medium',
            'status' => SubscriptionStatus::Active,
            'preferences' => ['veg_only' => true],
            'allergies' => ['nuts'],
        ]);

        $this->actingAs($user)
            ->get(route('app.subscriptions.show'))
            ->assertOk()
            ->assertSee('Subscription overview')
            ->assertSee('Weekly delivery')
            ->assertSee('Medium')
            ->assertSee('Active')
            ->assertSee('Veg option only')
            ->assertSee('Yes')
            ->assertSee('nuts')
            ->assertSee('Set after first charge');
    }

    public function test_subscription_overview_shows_empty_state_without_subscription(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('app.subscriptions.show'))
            ->assertOk()
            ->assertSee('No subscription yet')
            ->assertSee('Create subscription');
    }

    public function test_user_without_subscription_is_redirected_to_onboarding_wizard(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $wizard = route('app.onboarding.start', ['intent' => 'subscription']);

        $this->actingAs($user)
            ->get(route('app.subscriptions.create'))
            ->assertRedirect($wizard);

        $this->actingAs($user)
            ->get(route('app.subscriptions.onboard'))
            ->assertRedirect($wizard);
    }

    public function test_user_with_subscription_is_redirected_from_create_to_wizard_entry(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        Subscription::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get(route('app.subscriptions.create'))
            ->assertRedirect(route('app.onboarding.start', ['intent' => 'subscription']));
    }

    public function test_subscription_overview_does_not_show_other_users_subscription(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Subscription::factory()->create([
            'user_id' => $otherUser->id,
            'box_size' => 'large',
        ]);

        $this->actingAs($user)
            ->get(route('app.subscriptions.show'))
            ->assertOk()
            ->assertSee('No subscription yet')
            ->assertDontSee('Large');
    }

    public function test_store_redirects_to_wizard_and_does_not_create_subscription(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)
            ->post(route('app.subscriptions.store'), [
                'plan' => 'biweekly',
                'box_size' => 'small',
                'preferences' => ['veg_only' => '0', 'meat_add_on' => '0', 'eggs_dairy_add_on' => '0'],
                'allergies' => '',
            ])
            ->assertRedirect(route('app.onboarding.start', ['intent' => 'subscription']));

        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_store_rejects_invalid_plan(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('app.subscriptions.show'))
            ->post(route('app.subscriptions.store'), [
                'plan' => 'daily',
                'box_size' => 'medium',
                'preferences' => ['veg_only' => '0', 'meat_add_on' => '0', 'eggs_dairy_add_on' => '0'],
                'allergies' => '',
            ])
            ->assertSessionHasErrors(['plan']);

        $this->assertDatabaseCount('subscriptions', 0);
    }
}
