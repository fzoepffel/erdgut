<?php

namespace Tests\Feature\Dashboard;

use App\Enums\OrderProductType;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTrialVariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_trial_only_user_sees_trial_dashboard(): void
    {
        $user = User::factory()->create();

        Order::create([
            'user_id' => $user->id,
            'product_type' => OrderProductType::TrialBox->value,
            'box_size' => 'medium',
            'amount' => 35,
            'currency' => 'EUR',
            'status' => OrderStatus::Paid->value,
            'is_test' => true,
        ]);

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('Your Erdgut trial box')
            ->assertSee('Turn your trial into a weekly box');
    }

    public function test_user_without_any_purchase_sees_welcome_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('app.dashboard'));
        $response->assertOk();
        $response->assertDontSee('Your Erdgut trial box');
    }

    public function test_user_with_only_pending_payment_order_sees_welcome_dashboard(): void
    {
        // Regression: abandoning the wizard at the payment step used to leave a
        // PendingPayment order that would then show up on the dashboard as a real trial.
        $user = User::factory()->create();

        Order::create([
            'user_id' => $user->id,
            'product_type' => OrderProductType::TrialBox->value,
            'box_size' => 'medium',
            'amount' => 35,
            'currency' => 'EUR',
            'status' => OrderStatus::PendingPayment->value,
            'is_test' => true,
        ]);

        $response = $this->actingAs($user)->get(route('app.dashboard'));

        $response->assertOk();
        $response->assertDontSee('Your Erdgut trial box');
        $response->assertSee('Start your subscription');
    }
}
