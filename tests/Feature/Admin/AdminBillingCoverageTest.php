<?php

namespace Tests\Feature\Admin;

use App\Enums\PaymentChargeInitiator;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Support\SumUpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class AdminBillingCoverageTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);

        parent::tearDown();
    }

    public function test_guest_is_redirected_from_coverage_and_charge(): void
    {
        $this->get(route('app.admin.billing.coverage'))
            ->assertRedirect('/auth/login');

        $this->post(route('app.admin.billing.charge'), [])
            ->assertRedirect('/auth/login');
    }

    public function test_non_admin_is_forbidden_on_coverage_and_charge(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('app.admin.billing.coverage'))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('app.admin.billing.charge'), [
                'subscription_id' => 1,
                'week_start_date' => '2026-03-30',
                'confirm_charge' => '1',
            ])
            ->assertForbidden();
    }

    public function test_admin_coverage_page_ok_and_does_not_emit_sumup_strings(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-24 12:00:00', 'UTC'));

        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $customer->id,
            'status' => SubscriptionStatus::Active,
            'plan' => SubscriptionPlan::Weekly,
            'sumup_token' => 'coverage_secret_token_abc',
            'sumup_customer_id' => 'coverage_cust_xyz',
            'next_charge_date' => Carbon::parse('2026-03-20'),
        ]);

        $html = $this->actingAs($admin)
            ->get(route('app.admin.billing.coverage'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('coverage_secret_token_abc', $html);
        $this->assertStringNotContainsString('coverage_cust_xyz', $html);
        $this->assertStringNotContainsString('sumup_token', $html);
    }

    public function test_admin_manual_charge_creates_payment_with_expected_metadata(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-24 12:00:00', 'UTC'));

        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $customer->id,
            'status' => SubscriptionStatus::Active,
            'plan' => SubscriptionPlan::Weekly,
            'box_size' => 'medium',
            'sumup_token' => 'tok_manual_charge',
            'sumup_customer_id' => 'cust_manual_charge',
            'next_charge_date' => Carbon::parse('2026-03-20'),
        ]);

        $weekMonday = Carbon::parse('2026-03-30')->startOfDay();

        $sumUpService = Mockery::mock(SumUpService::class);
        $sumUpService->shouldReceive('createRecurringCheckout')
            ->once()
            ->andReturn((object) ['id' => 'checkout_manual_1']);
        $sumUpService->shouldReceive('processCheckout')
            ->once()
            ->with('checkout_manual_1', 'tok_manual_charge', 'cust_manual_charge')
            ->andReturn((object) ['status' => 'PENDING']);
        $this->app->instance(SumUpService::class, $sumUpService);

        $this->actingAs($admin)
            ->post(route('app.admin.billing.charge'), [
                'subscription_id' => $subscription->id,
                'week_start_date' => $weekMonday->toDateString(),
                'confirm_charge' => '1',
            ])
            ->assertRedirect(route('app.admin.billing.coverage'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('payments', [
            'subscription_id' => $subscription->id,
            'status' => Payment::STATUS_PENDING,
            'charge_initiator' => PaymentChargeInitiator::AdminManual->value,
            'initiated_by_user_id' => $admin->id,
            'sumup_checkout_id' => 'checkout_manual_1',
        ]);

        $payment = Payment::query()->where('subscription_id', $subscription->id)->orderByDesc('id')->first();
        $this->assertNotNull($payment);
        $this->assertSame($weekMonday->toDateString(), $payment->fulfillment_week_start?->toDateString());
    }

    public function test_manual_charge_rejected_when_week_already_paid(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-24 12:00:00', 'UTC'));

        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $customer->id,
            'status' => SubscriptionStatus::Active,
            'plan' => SubscriptionPlan::Weekly,
            'sumup_token' => 'tok_paid_week',
            'sumup_customer_id' => 'cust_paid_week',
            'next_charge_date' => Carbon::parse('2026-03-20'),
        ]);

        $weekMonday = Carbon::parse('2026-03-30')->startOfDay();

        Payment::factory()->create([
            'subscription_id' => $subscription->id,
            'status' => Payment::STATUS_PAID,
            'fulfillment_week_start' => $weekMonday->toDateString(),
            'charge_initiator' => PaymentChargeInitiator::Scheduled,
            'attempt_number' => 1,
            'paid_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('app.admin.billing.charge'), [
                'subscription_id' => $subscription->id,
                'week_start_date' => $weekMonday->toDateString(),
                'confirm_charge' => '1',
            ])
            ->assertSessionHasErrors('week_start_date');
    }
}
