<?php

namespace Tests\Feature\Customer;

use App\Enums\BoxStatus;
use App\Models\Box;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verifies that the homepage-aligned editorial components (hero band,
 * story block, producer spotlight) render on the logged-in customer
 * surfaces. Lock against accidental removal during future refactors.
 */
class HomePageAlignedRedesignTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_editorial_chrome_for_subscribed_users(): void
    {
        // ERD-351..355: subscribed dashboards moved off the generic
        // hero-band / story-block / producer-spotlight components and onto
        // a bespoke editorial chrome — dashboard-hero + producer-feature +
        // recipe-feature (+ manifest-grid when there's any product data,
        // which this test doesn't seed). Lock against accidental removal
        // of the always-on editorial sections.
        $user = User::factory()->create(['name' => 'Maria Müller']);
        $this->subscribeUserFully($user);

        $html = $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-testid="customer-dashboard-hero"', $html);
        $this->assertStringContainsString('data-testid="customer-producer-feature"', $html);
        $this->assertStringContainsString('data-testid="customer-recipe-feature"', $html);

        // Hero still personalises the greeting with the user's first name.
        $this->assertStringContainsString('Maria', $html);
    }

    public function test_box_show_renders_hero_band_and_producer_spotlight(): void
    {
        $user = User::factory()->create();
        $subscription = $this->subscribeUserFully($user);

        $box = Box::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'status' => BoxStatus::Editable,
        ]);

        $html = $this->actingAs($user)
            ->get(route('app.boxes.show', $box))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-testid="customer-hero-band"', $html);
        $this->assertStringContainsString('data-testid="customer-producer-spotlight"', $html);
    }

    public function test_dashboard_welcome_renders_editorial_hero(): void
    {
        // dashboard-welcome is shown to authenticated users without a subscription.
        // It was rebuilt onto a bespoke editorial hero (no shared hero-band
        // component) with the two-ways subscription/trial cards. Lock against
        // accidental removal of the always-on welcome surface.
        $user = User::factory()->create();

        $html = $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('data-testid="customer-hero-band"', $html);
        $this->assertStringContainsString('data-testid="welcome-subscription-card"', $html);
        $this->assertStringContainsString('data-testid="customer-editorial-footer-band"', $html);
    }

    public function test_subscription_show_renders_editorial_hero(): void
    {
        $user = User::factory()->create();
        $this->subscribeUserFully($user);

        $html = $this->actingAs($user)
            ->get(route('app.subscriptions.show'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('data-testid="customer-hero-band"', $html);
        $this->assertStringContainsString('data-testid="customer-editorial-footer-band"', $html);
        $this->assertStringContainsString('Subscription', $html);
    }

    public function test_payments_history_renders_editorial_hero(): void
    {
        $user = User::factory()->create();
        $this->subscribeUserFully($user);

        $html = $this->actingAs($user)
            ->get(route('app.payments.history'))
            ->assertOk()
            ->getContent();

        // Payments page rebuilt to editorial style — no hero-band/story-block,
        // instead text-only hero with payment method panel + editorial footer.
        $this->assertStringNotContainsString('data-testid="customer-hero-band"', $html);
        $this->assertStringNotContainsString('data-testid="customer-story-block"', $html);
        $this->assertStringContainsString('data-testid="customer-editorial-footer-band"', $html);
    }

    public function test_profile_edit_renders_editorial_hero(): void
    {
        // Profile/account page rebuilt to editorial style — no hero-band/story-block,
        // instead a text-only hero with editorial footer band.
        $user = User::factory()->create();

        $html = $this->actingAs($user)
            ->get(route('account.profile.edit'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('data-testid="customer-hero-band"', $html);
        $this->assertStringNotContainsString('data-testid="customer-story-block"', $html);
        $this->assertStringContainsString('data-testid="customer-editorial-footer-band"', $html);
    }

    public function test_deliveries_index_renders_editorial_hero(): void
    {
        $user = User::factory()->create();
        $this->subscribeUserFully($user);

        $html = $this->actingAs($user)
            ->get(route('app.deliveries.index'))
            ->assertOk()
            ->getContent();

        // Deliveries page was rebuilt to editorial style — no hero-band image,
        // instead a text-only hero with the editorial footer band.
        $this->assertStringNotContainsString('data-testid="customer-hero-band"', $html);
        $this->assertStringContainsString('data-testid="customer-editorial-footer-band"', $html);
    }

    public function test_onboarding_preferences_step_renders_editorial_box_size_tiles(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)
            ->get(route('app.onboarding.step.preferences'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-testid="onboarding-box-size-card-small"', $html);
        $this->assertStringContainsString('data-testid="onboarding-box-size-card-medium"', $html);
        $this->assertStringContainsString('data-testid="onboarding-box-size-card-large"', $html);

        // Redesigned choice tiles: monochrome cream surface, moss outline +
        // paper fill on selection, and a "chosen" marker — no chapter colour.
        $this->assertStringContainsString('bg-brand-cream', $html);
        $this->assertStringContainsString('has-[:checked]:border-chapter-produce', $html);
        $this->assertStringContainsString('has-[:checked]:bg-brand-paper', $html);
        $this->assertStringContainsString(__('onboarding.chosen'), $html);
    }
}
