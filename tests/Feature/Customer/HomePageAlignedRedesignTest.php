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

    public function test_dashboard_welcome_renders_hero_band(): void
    {
        // dashboard-welcome is shown to authenticated users without a subscription.
        $user = User::factory()->create();

        $html = $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-testid="customer-hero-band"', $html);
    }

    public function test_subscription_show_renders_hero_band_and_story_block(): void
    {
        $user = User::factory()->create();
        $this->subscribeUserFully($user);

        $html = $this->actingAs($user)
            ->get(route('app.subscriptions.show'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-testid="customer-hero-band"', $html);
        $this->assertStringContainsString('data-testid="customer-story-block"', $html);
    }

    public function test_payments_history_renders_hero_band_and_story_block(): void
    {
        $user = User::factory()->create();
        $this->subscribeUserFully($user);

        $html = $this->actingAs($user)
            ->get(route('app.payments.history'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-testid="customer-hero-band"', $html);
        $this->assertStringContainsString('data-testid="customer-story-block"', $html);
    }

    public function test_profile_edit_renders_hero_band_and_story_block(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)
            ->get(route('account.profile.edit'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-testid="customer-hero-band"', $html);
        $this->assertStringContainsString('data-testid="customer-story-block"', $html);
    }

    public function test_deliveries_index_renders_hero_band(): void
    {
        $user = User::factory()->create();
        $this->subscribeUserFully($user);

        $html = $this->actingAs($user)
            ->get(route('app.deliveries.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-testid="customer-hero-band"', $html);
    }

    public function test_onboarding_preferences_step_renders_chapter_style_box_size_cards(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)
            ->get(route('app.onboarding.step.preferences'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-testid="onboarding-box-size-card-small"', $html);
        $this->assertStringContainsString('data-testid="onboarding-box-size-card-medium"', $html);
        $this->assertStringContainsString('data-testid="onboarding-box-size-card-large"', $html);

        // Chapter-coloured backgrounds (one per size). The cards are now
        // pure typography over the chapter colour — no illustration.
        $this->assertStringContainsString('bg-chapter-bread', $html);
        $this->assertStringContainsString('bg-chapter-produce', $html);
        $this->assertStringContainsString('bg-chapter-meat', $html);
    }
}
