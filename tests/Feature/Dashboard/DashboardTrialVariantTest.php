<?php

namespace Tests\Feature\Dashboard;

use App\Enums\DeliveryStatus;
use App\Enums\OrderProductType;
use App\Enums\OrderStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Address;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTrialVariantTest extends TestCase
{
    use RefreshDatabase;

    public function test_trial_only_user_sees_unified_dashboard_with_convert_cta(): void
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
            // The thin strip was replaced by a richer trial block that
            // renders trial-state-aware copy + composition + convert CTA.
            ->assertSee('dashboard-trial-block', false)
            ->assertSee('dashboard-trial-convert-cta', false)
            ->assertSee('Turn your trial into a weekly box');
    }

    public function test_user_without_any_purchase_sees_welcome_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('app.dashboard'));
        $response->assertOk();
        $response->assertDontSee('Your Erdgut trial box');
    }

    public function test_mid_trial_and_mid_subscription_user_sees_both_in_one_timeline(): void
    {
        $user = User::factory()->create();
        $address = Address::factory()->create(['user_id' => $user->id, 'is_primary' => true]);

        Order::create([
            'user_id' => $user->id,
            'product_type' => OrderProductType::TrialBox->value,
            'box_size' => 'medium',
            'amount' => 35,
            'currency' => 'EUR',
            'status' => OrderStatus::Paid->value,
            'is_test' => true,
        ]);
        Subscription::factory()->withPaymentToken()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
        ]);

        Delivery::create([
            'user_id' => $user->id,
            'address_id' => $address->id,
            'scheduled_for_date' => now()->addDays(3)->toDateString(),
            'timeslot' => '08:00-12:00',
            'status' => DeliveryStatus::Scheduled,
            'is_weekly_override' => false,
        ]);
        Delivery::create([
            'user_id' => $user->id,
            'address_id' => $address->id,
            'scheduled_for_date' => now()->addDays(10)->toDateString(),
            'timeslot' => '08:00-12:00',
            'status' => DeliveryStatus::Scheduled,
            'is_weekly_override' => false,
        ]);

        $response = $this->actingAs($user)->get(route('app.dashboard'));
        $response->assertOk();

        $content = $response->getContent();
        // Dashboard now renders dates via Carbon::isoFormat('dd, D. MMM YYYY')
        // for proper locale support. Match that format here so the test isn't
        // tied to PHP's English-only DateTime format characters.
        $trialDate = now()->addDays(3)->locale(app()->getLocale())->isoFormat('dd, D. MMM YYYY');
        $subDate = now()->addDays(10)->locale(app()->getLocale())->isoFormat('dd, D. MMM YYYY');
        $trialPos = strpos($content, $trialDate);
        $subPos = strpos($content, $subDate);

        $this->assertNotFalse($trialPos);
        $this->assertNotFalse($subPos);
        $this->assertLessThan($subPos, $trialPos, 'Earlier delivery should appear first in the timeline.');
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
