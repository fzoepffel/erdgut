<?php

namespace Tests\Feature\Payment;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use App\Support\SumUpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class PaymentSetupControllerTest extends TestCase
{
    use MockeryPHPUnitIntegration;
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

    public function test_legacy_payment_setup_redirects_to_onboard_payment_when_token_missing(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'sumup_token' => null,
            'sumup_customer_id' => null,
        ]);

        $this->actingAs($user)
            ->get(route('app.payments.setup'))
            ->assertRedirect(route('app.subscriptions.onboard.payment'));
    }

    public function test_legacy_payment_setup_redirects_to_onboard_payment_when_customer_id_exists_without_token(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'sumup_token' => null,
            'sumup_customer_id' => 'sub_123',
        ]);

        $this->actingAs($user)
            ->get(route('app.payments.setup'))
            ->assertRedirect(route('app.subscriptions.onboard.payment'));
    }

    public function test_complete_persists_token_and_redirects_to_payment_history(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'sumup_token' => null,
            'sumup_customer_id' => null,
        ]);

        $sumUpService = Mockery::mock(SumUpService::class);
        $sumUpService
            ->shouldReceive('getCheckout')
            ->once()
            ->with('checkout_ok')
            ->andReturn((object) [
                'id' => 'checkout_ok',
                'checkout_reference' => 'sub-'.$subscription->id.'-setup-123',
                'customer_id' => 'sumup_customer_1',
                'payment_instrument' => (object) ['token' => 'tok_123'],
            ]);

        $this->app->instance(SumUpService::class, $sumUpService);

        $this->actingAs($user)
            ->get(route('app.payments.setup.complete', ['checkout' => 'checkout_ok']))
            ->assertRedirect(route('app.payments.history'))
            ->assertSessionHas('status', 'payment-setup-success');

        $subscription->refresh();
        $this->assertSame('tok_123', $subscription->sumup_token);
        $this->assertSame('sumup_customer_1', $subscription->sumup_customer_id);
    }

    public function test_complete_rejects_checkout_for_another_subscription(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'sumup_token' => null,
        ]);

        $sumUpService = Mockery::mock(SumUpService::class);
        $sumUpService
            ->shouldReceive('getCheckout')
            ->once()
            ->with('checkout_wrong')
            ->andReturn((object) [
                'id' => 'checkout_wrong',
                'checkout_reference' => 'sub-9999-setup-123',
                'payment_instrument' => (object) ['token' => 'tok_other'],
            ]);

        $this->app->instance(SumUpService::class, $sumUpService);

        $this->actingAs($user)
            ->get(route('app.payments.setup.complete', ['checkout' => 'checkout_wrong']))
            ->assertRedirect(route('app.payments.history'))
            ->assertSessionHas('status', 'payment-setup-failed');

        $subscription->refresh();
        $this->assertNull($subscription->sumup_token);
    }

    public function test_update_redirects_when_no_token(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'sumup_token' => null,
        ]);

        $this->actingAs($user)
            ->get(route('app.payments.update'))
            ->assertRedirect(route('app.dashboard'))
            ->assertSessionHas('status', 'subscriber-area-requires-setup');
    }

    public function test_update_shows_setup_page_when_token_exists(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'sumup_token' => 'tok_existing',
            'sumup_customer_id' => 'sub_cust_1',
        ]);

        $sumUpService = Mockery::mock(SumUpService::class);
        $sumUpService->shouldNotReceive('createCustomer');
        $sumUpService
            ->shouldReceive('createSetupCheckout')
            ->once()
            ->withArgs(function ($customerId, $reference, $description) {
                return str_contains($reference, '-update-');
            })
            ->andReturn((object) ['id' => 'checkout_update_123']);

        $this->app->instance(SumUpService::class, $sumUpService);

        $this->actingAs($user)
            ->get(route('app.payments.update'))
            ->assertOk()
            ->assertSee('Update payment method')
            ->assertSee('You will replace the card used for subscription charges.')
            ->assertSee('checkout_update_123');
    }

    public function test_update_complete_replaces_token_without_activating(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Paused,
            'sumup_token' => 'tok_old',
            'sumup_customer_id' => 'sumup_cust_1',
        ]);

        $sumUpService = Mockery::mock(SumUpService::class);
        $sumUpService
            ->shouldReceive('getCheckout')
            ->once()
            ->with('checkout_update_ok')
            ->andReturn((object) [
                'id' => 'checkout_update_ok',
                'checkout_reference' => 'sub-'.$subscription->id.'-update-'.time(),
                'customer_id' => 'sumup_cust_1',
                'payment_instrument' => (object) ['token' => 'tok_new'],
            ]);

        $this->app->instance(SumUpService::class, $sumUpService);

        $this->actingAs($user)
            ->get(route('app.payments.setup.complete', [
                'checkout' => 'checkout_update_ok',
                'intent' => 'update',
            ]))
            ->assertRedirect(route('app.payments.history'))
            ->assertSessionHas('status', 'payment-updated');

        $subscription->refresh();
        $this->assertSame('tok_new', $subscription->sumup_token);
        $this->assertSame(SubscriptionStatus::Paused, $subscription->status);
    }

    public function test_complete_with_intent_update_redirects_with_payment_updated(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'sumup_token' => 'tok_old',
            'sumup_customer_id' => 'sumup_cust_1',
        ]);

        $sumUpService = Mockery::mock(SumUpService::class);
        $sumUpService
            ->shouldReceive('getCheckout')
            ->once()
            ->with('checkout_update_ok')
            ->andReturn((object) [
                'id' => 'checkout_update_ok',
                'checkout_reference' => 'sub-'.$subscription->id.'-update-123',
                'customer_id' => 'sumup_cust_1',
                'payment_instrument' => (object) ['token' => 'tok_new'],
            ]);

        $this->app->instance(SumUpService::class, $sumUpService);

        $response = $this->actingAs($user)
            ->get(route('app.payments.setup.complete', [
                'checkout' => 'checkout_update_ok',
                'intent' => 'update',
            ]));

        $response->assertRedirect(route('app.payments.history'));
        $response->assertSessionHas('status', 'payment-updated');
    }
}
