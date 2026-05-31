<?php

namespace Tests\Feature\Subscriber;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriberFullMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_deliveries_index_redirects_when_user_has_no_subscription(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('app.deliveries.index'))
            ->assertRedirect(route('app.dashboard'))
            ->assertSessionHas('status', 'subscriber-area-requires-setup');
    }

    public function test_deliveries_index_redirects_when_subscription_has_no_payment_token(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'sumup_token' => null,
        ]);

        $this->actingAs($user)
            ->get(route('app.deliveries.index'))
            ->assertRedirect(route('app.dashboard'))
            ->assertSessionHas('status', 'subscriber-area-requires-setup');
    }

    public function test_deliveries_index_allows_active_subscription_with_payment_token(): void
    {
        $user = User::factory()->create();
        $this->subscribeUserFully($user);

        $this->actingAs($user)
            ->get(route('app.deliveries.index'))
            ->assertOk();
    }

    public function test_authenticated_user_without_subscription_sees_welcome_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            // Welcome dashboard hero copy is split across markup
            // ("Welcome to <em>Erdgut</em>"), so assert the stable
            // welcome surface marker instead of a contiguous string.
            ->assertSee('data-testid="welcome-subscription-card"', false);
    }
}
