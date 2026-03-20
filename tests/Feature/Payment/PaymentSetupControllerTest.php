<?php

namespace Tests\Feature\Payment;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentSetupControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_payment_setup(): void
    {
        $this->get(route('app.payments.setup'))
            ->assertRedirect('/auth/login');
    }

    public function test_user_without_subscription_is_redirected_to_subscription_overview(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('app.payments.setup'))
            ->assertRedirect(route('app.subscriptions.show'))
            ->assertSessionHas('status', 'subscription-missing');
    }

    public function test_user_with_existing_payment_token_is_redirected_to_subscription_overview(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'sumup_token' => 'tok_12345',
        ]);

        $this->actingAs($user)
            ->get(route('app.payments.setup'))
            ->assertRedirect(route('app.subscriptions.show'))
            ->assertSessionHas('status', 'payment-already-setup');
    }

    public function test_eligible_user_can_view_payment_setup_page(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'sumup_token' => null,
        ]);

        $this->actingAs($user)
            ->get(route('app.payments.setup'))
            ->assertOk()
            ->assertSee('Set up payment method')
            ->assertSee('Add your payment details to activate your subscription.')
            ->assertSee('Back to subscription');
    }
}
