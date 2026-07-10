<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_for_admin_dashboard(): void
    {
        $this->get(route('app.admin.dashboard'))
            ->assertRedirect('/auth/login');
    }

    public function test_non_admin_receives_forbidden_on_admin_dashboard(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        $this->actingAs($user)
            ->get(route('app.admin.dashboard'))
            ->assertForbidden();
    }

    public function test_admin_can_access_admin_dashboard(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->get(route('app.admin.dashboard'))
            ->assertOk()
            ->assertSee(route('app.admin.producers.index', absolute: false), false)
            ->assertSee(route('app.admin.products.index', absolute: false), false)
            ->assertSee(route('app.admin.users.index', absolute: false), false)
            ->assertSee(route('app.admin.payments.index', absolute: false), false)
            ->assertSee('admin-ops-kpis', false)
            ->assertSee('Administration', false);
    }

    public function test_unverified_admin_is_redirected_before_admin_middleware(): void
    {
        $user = User::factory()->admin()->unverified()->create();

        $this->actingAs($user)
            ->get(route('app.admin.dashboard'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_admin_is_redirected_from_customer_dashboard_to_admin_overview(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertRedirect(route('app.admin.dashboard'));
    }

    public function test_admin_admin_dashboard_shows_sidebar_catalog_links_without_subscriber_nav(): void
    {
        $user = User::factory()->admin()->create();

        $html = $this->actingAs($user)
            ->get(route('app.admin.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(route('app.admin.producers.index', [], false), $html);
        $this->assertStringContainsString(route('app.admin.products.index', [], false), $html);
        $this->assertStringContainsString(route('app.admin.users.index', [], false), $html);
        $this->assertStringContainsString(route('app.admin.billing.upcoming', [], false), $html);
        $this->assertStringNotContainsString(route('app.subscriptions.show', [], false), $html);
        $this->assertStringNotContainsString(route('app.payments.history', [], false), $html);
        $this->assertStringNotContainsString(route('app.deliveries.index', [], false), $html);
    }

    public function test_admin_producers_index_excludes_subscriber_payment_nav(): void
    {
        $user = User::factory()->admin()->create();

        $html = $this->actingAs($user)
            ->get(route('app.admin.producers.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(route('app.payments.history', [], false), $html);
        $this->assertStringNotContainsString(route('app.subscriptions.show', [], false), $html);
    }

    public function test_operator_account_profile_excludes_payments_and_subscription_nav(): void
    {
        $user = User::factory()->admin()->create();

        $html = $this->actingAs($user)
            ->get(route('account.profile.edit'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString(route('app.payments.history', [], false), $html);
        $this->assertStringNotContainsString(route('app.subscriptions.show', [], false), $html);
        $this->assertStringContainsString(route('app.admin.dashboard', [], false), $html);
    }

    public function test_non_admin_does_not_see_admin_nav_link_on_customer_dashboard(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertDontSee(route('app.admin.dashboard', absolute: false), false);
    }
}
