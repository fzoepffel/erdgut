<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/**
 * Locks the dashboard-density batch (D8, D9, D11): nav-bar account
 * trigger uses a stable label, dashboard cards live in a tighter
 * vertical rhythm, and the hero-band component honours the new
 * density='compact' prop.
 */
class DashboardDensityTest extends TestCase
{
    use RefreshDatabase;

    public function test_d8_d11_authenticated_nav_renders_my_account_trigger_not_user_name(): void
    {
        $user = User::factory()->create([
            'name' => 'Sabine Subscriber',
            'email_verified_at' => now(),
        ]);
        $this->subscribeUserFully($user);

        $response = $this->actingAs($user)->get(route('app.dashboard'))->assertOk();

        $html = $response->getContent();
        // The button text is now a stable "Mein Konto" / "My account"
        // label so the trigger is discoverable and does not wrap on
        // long names.
        $this->assertStringContainsString('customer-header-account-trigger', $html);
        $this->assertStringContainsString(__('My account'), $html);

        // The user's full name still appears once inside the dropdown
        // header — but never in the trigger button (Sabine Subscriber
        // would otherwise wrap to two lines on narrow widths).
        $this->assertStringContainsString('customer-header-account-summary', $html);
        $this->assertStringContainsString('Sabine Subscriber', $html);

        // Sanity: the trigger button itself contains "My account", not
        // the user name. Grep just the button-block area — span sits
        // between two SVGs so the slice has to be large enough to span
        // the whole inner button HTML.
        $start = (int) strpos($html, 'customer-header-account-trigger');
        $end = (int) strpos($html, '</button>', $start);
        $this->assertGreaterThan(0, $start);
        $this->assertGreaterThan($start, $end);
        $buttonChunk = substr($html, $start, $end - $start);
        $this->assertStringContainsString(__('My account'), $buttonChunk);
        $this->assertStringNotContainsString('Sabine Subscriber', $buttonChunk);
    }

    public function test_d9_dashboard_outer_wrapper_uses_tighter_vertical_rhythm(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->subscribeUserFully($user);

        $html = $this->actingAs($user)->get(route('app.dashboard'))->assertOk()->getContent();

        // The wrapper now uses `space-y-6` / `pb-12` — the older
        // `space-y-8` / `pb-16` should be gone.
        $this->assertStringContainsString('space-y-6 px-4 pt-6 sm:px-6 sm:pt-8', $html);
        $this->assertStringContainsString('pb-8 sm:pb-12', $html);
        $this->assertStringNotContainsString('space-y-8 px-4 pt-8 sm:px-6 sm:pt-10', $html);
    }

    public function test_d9_at_a_glance_card_uses_tighter_grid_gap(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->subscribeUserFully($user);

        $html = $this->actingAs($user)->get(route('app.dashboard'))->assertOk()->getContent();

        // The at-a-glance card grid moved from gap-8/p-6 to gap-6/p-5.
        $this->assertStringContainsString('grid gap-6 p-5 sm:grid-cols-2 lg:grid-cols-3', $html);
        $this->assertStringNotContainsString('grid gap-8 p-6 sm:grid-cols-2 lg:grid-cols-3', $html);
    }

    public function test_d9_hero_band_renders_with_compact_density_on_dashboard(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->subscribeUserFully($user);

        $html = $this->actingAs($user)->get(route('app.dashboard'))->assertOk()->getContent();

        // Compact hero uses py-8/sm:py-10/lg:py-12 (vs spacious's
        // py-12/sm:py-16/lg:py-20). Check the compact set is rendered.
        $this->assertStringContainsString('py-8 sm:px-10 sm:py-10 lg:py-12', $html);
        $this->assertStringNotContainsString('py-12 sm:px-10 sm:py-16 lg:py-20', $html);
    }

    public function test_d9_hero_band_component_compact_prop_changes_padding(): void
    {
        $compactHtml = Blade::render(
            '<x-customer.hero-band density="compact" title="Test" eyebrow="EB" />'
        );
        $spaciousHtml = Blade::render(
            '<x-customer.hero-band density="spacious" title="Test" eyebrow="EB" />'
        );
        $defaultHtml = Blade::render(
            '<x-customer.hero-band title="Test" eyebrow="EB" />'
        );

        // Compact: smaller padding scale + smaller title.
        $this->assertStringContainsString('py-8 sm:px-10 sm:py-10 lg:py-12', $compactHtml);
        $this->assertStringContainsString('text-2xl', $compactHtml);

        // Spacious + default: original padding scale.
        $this->assertStringContainsString('py-12 sm:px-10 sm:py-16 lg:py-20', $spaciousHtml);
        $this->assertStringContainsString('py-12 sm:px-10 sm:py-16 lg:py-20', $defaultHtml);
        $this->assertStringContainsString('text-3xl', $spaciousHtml);
    }
}
