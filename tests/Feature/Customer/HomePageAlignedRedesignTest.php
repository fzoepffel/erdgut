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

    public function test_dashboard_renders_hero_band_story_block_and_producer_spotlight(): void
    {
        $user = User::factory()->create(['name' => 'Maria Müller']);
        $this->subscribeUserFully($user);

        $html = $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-testid="customer-hero-band"', $html);
        $this->assertStringContainsString('data-testid="customer-story-block"', $html);
        $this->assertStringContainsString('data-testid="customer-producer-spotlight"', $html);

        // Hero band carries the dashboard greeting personalised with the user's name.
        $this->assertStringContainsString('Maria', $html);
        // Producer spotlight CTA copy is present.
        $this->assertStringContainsString(__('customer.story.box.read_more_producers'), $html);
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
}
