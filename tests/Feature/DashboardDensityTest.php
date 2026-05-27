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

    // The three D9 dashboard-page density assertions (outer wrapper class
    // string, at-a-glance card grid, hero-band compact density on the
    // dashboard) were superseded by ERD-351..355: the wrapper became
    // `mx-auto max-w-[1400px] px-6 lg:px-12` with per-section py-*,
    // the at-a-glance card was removed in the dashboard cleanup, and
    // subscribed dashboards swapped <x-customer.hero-band> for
    // <x-customer.dashboard-hero>. The component-level compact-density
    // contract is still locked by test_d9_hero_band_component_compact_prop_changes_padding
    // below.

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
