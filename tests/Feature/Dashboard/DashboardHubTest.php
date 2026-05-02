<?php

namespace Tests\Feature\Dashboard;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_includes_payments_nav_link_and_producer_spotlight(): void
    {
        // The old "Quick actions" footer that linked to Payments / Producers /
        // Address was replaced by a producer spotlight card; payments stays
        // reachable via the user-dropdown nav link rendered for subscribers.
        $user = User::factory()->create();
        $this->subscribeUserFully($user);

        $html = $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('This week at a glance', false)
            ->assertSee(__('customer.story.producer_spotlight_eyebrow'), false)
            ->getContent();

        $this->assertStringContainsString(route('app.payments.history', [], false), $html);
    }

    public function test_non_admin_dashboard_does_not_include_admin_producers_nav_target(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $html = $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(route('app.admin.producers.index', [], false), $html);
        $this->assertStringNotContainsString(route('app.admin.products.index', [], false), $html);
    }

    public function test_admin_requesting_app_dashboard_redirects_to_admin_overview(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('app.dashboard'))
            ->assertRedirect(route('app.admin.dashboard'));
    }

    public function test_dashboard_shows_payment_setup_nudge_when_active_subscription_has_no_payment_token(): void
    {
        // Legacy data shape (Active+no_token) — DashboardStateResolver
        // classifies this as SubscriptionPendingNoTrial because billing
        // isn't actually wired up. The state-aware dashboard renders a
        // dedicated "Finish setting up your subscription" block instead
        // of the old thin "Add a payment method" alert.
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'sumup_token' => null,
            'sumup_customer_id' => null,
        ]);

        $html = $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-testid="dashboard-resume-subscription-setup"', $html);
        $this->assertStringContainsString(route('app.subscriptions.onboard.payment', [], false), $html);
    }

    public function test_dashboard_hides_payment_setup_nudge_when_payment_token_present(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
            'sumup_token' => 'tok_test_123',
            'sumup_customer_id' => 'cust_123',
        ]);

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertDontSee('Add a payment method');
    }
}
