<?php

namespace Tests\Feature\Payment;

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentHistoryControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_payment_history(): void
    {
        $this->get(route('app.payments.history'))
            ->assertRedirect('/auth/login');
    }

    public function test_authenticated_user_can_view_payment_history_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('app.payments.history'))
            ->assertOk()
            ->assertSee('Payments and billing', false)
            ->assertSee('Payment method status', false)
            ->assertSee('Charge history', false);
    }

    public function test_payment_history_shows_active_mandate_copy_when_token_present(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'sumup_token' => 'tok_active_123',
            'sumup_customer_id' => 'cust_1',
        ]);

        $this->actingAs($user)
            ->get(route('app.payments.history'))
            ->assertOk()
            ->assertSee('A payment method is saved for your subscription.', false);
    }

    public function test_payment_history_lists_payment_rows(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'plan' => SubscriptionPlan::Weekly,
            'sumup_token' => 'tok_x',
        ]);

        Payment::factory()->create([
            'subscription_id' => $subscription->id,
            'amount' => 42.5,
            'currency' => 'EUR',
            'status' => Payment::STATUS_PAID,
            'attempt_number' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('app.payments.history'))
            ->assertOk()
            ->assertSee('42,50', false)
            ->assertSee('EUR', false)
            ->assertSee('Paid', false);
    }

    public function test_payment_history_shows_generic_message_for_unsafe_failure_reason(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'sumup_token' => 'tok_x',
        ]);

        Payment::factory()->create([
            'subscription_id' => $subscription->id,
            'amount' => 10,
            'currency' => 'EUR',
            'status' => Payment::STATUS_FAILED,
            'attempt_number' => 2,
            'failure_reason' => 'SQLSTATE[HY000] exception trace',
        ]);

        $this->actingAs($user)
            ->get(route('app.payments.history'))
            ->assertOk()
            ->assertSee('Payment failed', false)
            ->assertDontSee('SQLSTATE', false);
    }

    public function test_payment_history_shows_safe_failure_reason(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'sumup_token' => 'tok_x',
        ]);

        Payment::factory()->create([
            'subscription_id' => $subscription->id,
            'amount' => 10,
            'currency' => 'EUR',
            'status' => Payment::STATUS_FAILED,
            'attempt_number' => 1,
            'failure_reason' => 'Card declined',
        ]);

        $this->actingAs($user)
            ->get(route('app.payments.history'))
            ->assertOk()
            ->assertSee('Card declined', false);
    }
}
