<?php

namespace Tests\Feature\Admin;

use App\Enums\DeliveryStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Delivery;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminOperationsBrowsersTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string}>
     */
    public static function adminOperationsIndexRoutes(): array
    {
        return [
            'users' => ['app.admin.users.index'],
            'subscriptions' => ['app.admin.subscriptions.index'],
            'deliveries' => ['app.admin.deliveries.index'],
            'payments' => ['app.admin.payments.index'],
            'billing upcoming' => ['app.admin.billing.upcoming'],
        ];
    }

    /**
     * @dataProvider adminOperationsIndexRoutes
     */
    public function test_guest_is_redirected_to_login_for_operations_index(string $routeName): void
    {
        $this->get(route($routeName))
            ->assertRedirect('/auth/login');
    }

    /**
     * @dataProvider adminOperationsIndexRoutes
     */
    public function test_non_admin_receives_forbidden_on_operations_index(string $routeName): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route($routeName))
            ->assertForbidden();
    }

    /**
     * @dataProvider adminOperationsIndexRoutes
     */
    public function test_admin_receives_ok_on_operations_index_with_empty_database(string $routeName): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route($routeName))
            ->assertOk();
    }

    public function test_admin_payment_and_subscription_pages_do_not_leak_sumup_related_substrings_from_database(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();

        $subscription = Subscription::factory()->for($customer)->create([
            'status' => SubscriptionStatus::Active,
            'sumup_token' => 'plaintext_token_fixture_alpha',
            'sumup_customer_id' => 'cust_fixture_beta',
        ]);

        Payment::factory()->for($subscription)->create([
            'status' => Payment::STATUS_PENDING,
            'sumup_checkout_id' => 'chk_fixture_checkout_gamma',
            'sumup_payment_id' => 'pay_fixture_payment_delta',
        ]);

        $routes = [
            route('app.admin.payments.index'),
            route('app.admin.subscriptions.index'),
        ];

        foreach ($routes as $url) {
            $html = $this->actingAs($admin)->get($url)->assertOk()->getContent();
            $this->assertStringNotContainsString('plaintext_token_fixture_alpha', $html);
            $this->assertStringNotContainsString('cust_fixture_beta', $html);
            $this->assertStringNotContainsString('chk_fixture_checkout_gamma', $html);
            $this->assertStringNotContainsString('pay_fixture_payment_delta', $html);
            $this->assertStringNotContainsString('sumup_checkout_id', $html);
            $this->assertStringNotContainsString('sumup_payment_id', $html);
            $this->assertStringNotContainsString('sumup_token', $html);
        }
    }

    public function test_admin_deliveries_index_renders_without_leaking_payment_tokens(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        Subscription::factory()->for($customer)->create([
            'sumup_token' => 'tok_deliveries_should_not_appear',
        ]);
        Delivery::factory()->for($customer)->create([
            'status' => DeliveryStatus::Scheduled,
        ]);

        $html = $this->actingAs($admin)
            ->get(route('app.admin.deliveries.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('tok_deliveries_should_not_appear', $html);
    }
}
