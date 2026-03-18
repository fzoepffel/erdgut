<?php

namespace Tests\Feature\Database;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionModelLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_persists_required_fields_and_relation(): void
    {
        $user = User::factory()->create();

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan' => 'weekly',
            'box_size' => 'medium',
            'preferences' => ['veg_only' => true],
            'allergies' => ['nuts'],
            'status' => Subscription::STATUS_ACTIVE,
            'sumup_token' => 'tok_123',
            'sumup_customer_id' => 'cus_123',
        ]);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'user_id' => $user->id,
            'plan' => 'weekly',
            'box_size' => 'medium',
            'status' => Subscription::STATUS_ACTIVE,
        ]);
        $this->assertSame($user->id, $subscription->user->id);
        $this->assertTrue($subscription->preferences['veg_only']);
    }

    public function test_subscription_supports_active_paused_cancelled_lifecycle(): void
    {
        $subscription = Subscription::factory()->create([
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        $subscription->pause();
        $this->assertSame(Subscription::STATUS_PAUSED, $subscription->fresh()->status);

        $subscription->activate();
        $this->assertSame(Subscription::STATUS_ACTIVE, $subscription->fresh()->status);

        $subscription->cancel();
        $this->assertSame(Subscription::STATUS_CANCELLED, $subscription->fresh()->status);
    }

    public function test_user_and_subscription_relationship_contracts_are_exposed(): void
    {
        $user = new User;
        $subscription = new Subscription;

        $this->assertInstanceOf(HasMany::class, $user->subscriptions());
        $this->assertInstanceOf(BelongsTo::class, $subscription->user());
    }
}
