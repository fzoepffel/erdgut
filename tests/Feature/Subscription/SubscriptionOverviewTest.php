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
            ->assertSee('Weekly')
            ->assertSee('Medium')
            ->assertSee('Active')
            ->assertSee('veg_only')
            ->assertSee('nuts')
            ->assertSee('Set after first charge');
    }

    public function test_subscription_overview_shows_empty_state_without_subscription(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('app.subscriptions.show'))
            ->assertOk()
            ->assertSee('No active subscription found yet.');
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
            ->assertSee('No active subscription found yet.')
            ->assertDontSee('Large');
    }
}
