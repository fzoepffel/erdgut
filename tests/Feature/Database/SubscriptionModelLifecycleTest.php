<?php

namespace Tests\Feature\Database;

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
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
            'plan' => SubscriptionPlan::Weekly,
            'box_size' => 'medium',
            'preferences' => ['veg_only' => true],
            'allergies' => ['nuts'],
            'status' => SubscriptionStatus::Active,
            'sumup_token' => 'tok_123',
            'sumup_customer_id' => 'cus_123',
        ]);

        $this->assertDatabaseHas('subscriptions', [
            'id' => $subscription->id,
            'user_id' => $user->id,
            'plan' => SubscriptionPlan::Weekly->value,
            'box_size' => 'medium',
            'status' => SubscriptionStatus::Active->value,
        ]);
        $subscription->refresh();
        $this->assertSame($user->id, $subscription->user->id);
        $this->assertSame(SubscriptionPlan::Weekly, $subscription->plan);
        $this->assertSame(SubscriptionStatus::Active, $subscription->status);
        $this->assertTrue($subscription->preferences['veg_only']);
    }

    public function test_subscription_supports_active_paused_cancelled_lifecycle(): void
    {
        $subscription = Subscription::factory()->create([
            'status' => SubscriptionStatus::Active,
        ]);

        $subscription->pause();
        $this->assertSame(SubscriptionStatus::Paused, $subscription->fresh()->status);

        $subscription->activate();
        $this->assertSame(SubscriptionStatus::Active, $subscription->fresh()->status);

        $subscription->cancel();
        $this->assertSame(SubscriptionStatus::Cancelled, $subscription->fresh()->status);
    }

    public function test_user_and_subscription_relationship_contracts_are_exposed(): void
    {
        $user = new User;
        $subscription = new Subscription;

        $this->assertInstanceOf(HasMany::class, $user->subscriptions());
        $this->assertInstanceOf(HasManyThrough::class, $user->payments());
        $this->assertInstanceOf(BelongsTo::class, $subscription->user());
    }

    public function test_user_can_retrieve_payments_through_subscriptions(): void
    {
        $user = User::factory()->create();
        $firstSubscription = Subscription::factory()->create(['user_id' => $user->id]);
        $secondSubscription = Subscription::factory()->create(['user_id' => $user->id]);
        $otherUserSubscription = Subscription::factory()->create();

        $firstPayment = $firstSubscription->payments()->create([
            'amount' => 19.90,
            'currency' => 'EUR',
            'status' => Payment::STATUS_PENDING,
            'attempt_number' => 1,
        ]);
        $secondPayment = $secondSubscription->payments()->create([
            'amount' => 24.90,
            'currency' => 'EUR',
            'status' => Payment::STATUS_PENDING,
            'attempt_number' => 1,
        ]);
        $otherUserSubscription->payments()->create([
            'amount' => 29.90,
            'currency' => 'EUR',
            'status' => Payment::STATUS_PENDING,
            'attempt_number' => 1,
        ]);

        $paymentIds = $user->payments()
            ->orderBy('payments.id')
            ->pluck('payments.id')
            ->all();

        $this->assertSame([$firstPayment->id, $secondPayment->id], $paymentIds);
    }
}
