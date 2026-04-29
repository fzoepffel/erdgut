<?php

namespace Tests\Feature;

use App\Enums\BoxStatus;
use App\Models\Box;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\View\ComponentAttributeBag;
use Tests\TestCase;

/**
 * W2-12 (visual treatment): the box-size illustrations land on three
 * surfaces — the onboarding preferences card, the dashboard 'current
 * week box' tile, and the admin Abos size column. These tests assert
 * each surface emits the right `<picture>` for the right size.
 */
class BoxSizeIllustrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_onboarding_preferences_renders_all_three_box_illustrations(): void
    {
        $user = User::factory()->create();

        $html = $this->actingAs($user)
            ->get(route('app.onboarding.step.preferences'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('images/box-sizes/box-small.png', $html);
        $this->assertStringContainsString('images/box-sizes/box-medium.png', $html);
        $this->assertStringContainsString('images/box-sizes/box-large.png', $html);
        // WebP source for modern browsers.
        $this->assertStringContainsString('images/box-sizes/box-small.webp', $html);
    }

    public function test_onboarding_preferences_uses_per_size_chapter_palette_on_card_chrome(): void
    {
        // Card border / hover ring / eyebrow: produce → bread → almanach
        // for small → medium → large.
        $user = User::factory()->create();

        $html = $this->actingAs($user)
            ->get(route('app.onboarding.step.preferences'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('has-[:checked]:border-chapter-produce', $html);
        $this->assertStringContainsString('has-[:checked]:border-chapter-bread', $html);
        $this->assertStringContainsString('has-[:checked]:border-chapter-almanach', $html);
    }

    public function test_dashboard_current_week_box_tile_shows_subscribers_chosen_size(): void
    {
        // Lock 'now' to a Wednesday so we can be deterministic about
        // which week_start_date the dashboard query will accept.
        Carbon::setTestNow(Carbon::parse('2026-04-29 12:00:00'));

        try {
            $user = User::factory()->create();
            $this->subscribeUserFully($user);

            $sub = $user->subscriptions()->first();
            $sub->update(['box_size' => 'large']);

            $box = Box::factory()->create([
                'user_id' => $user->id,
                'week_start_date' => '2026-04-27', // this Monday
                'status' => BoxStatus::Locked,
                'estimated_value' => 42.00,
            ]);

            // Sanity: confirm the box exists and the dashboard query would match it.
            $this->assertDatabaseHas('boxes', ['id' => $box->id, 'user_id' => $user->id]);

            $html = $this->actingAs($user)
                ->get(route('app.dashboard'))
                ->assertOk()
                ->getContent();

            // Subscriber chose the 'large' box — that's the artwork that lands
            // on the current-week tile, not a placeholder.
            $this->assertStringContainsString('images/box-sizes/box-large.png', $html);
        } finally {
            Carbon::setTestNow(null);
        }
    }

    public function test_admin_abos_table_renders_thumbnail_per_subscription_size(): void
    {
        $admin = User::factory()->admin()->create();

        $small = User::factory()->create();
        Subscription::factory()->for($small)->create(['box_size' => 'small']);

        $medium = User::factory()->create();
        Subscription::factory()->for($medium)->create(['box_size' => 'medium']);

        $html = $this->actingAs($admin)
            ->get(route('app.admin.subscriptions.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('images/box-sizes/box-small.png', $html);
        $this->assertStringContainsString('images/box-sizes/box-medium.png', $html);
    }

    public function test_unknown_size_falls_back_to_medium(): void
    {
        // Defensive fallback in the component: a typo or a future
        // size-name should not 404 the asset request.
        $html = view('components.box-size-illustration', [
            'size' => 'humongous',
            'variant' => 'card',
            'attributes' => new ComponentAttributeBag,
        ])->render();

        $this->assertStringContainsString('images/box-sizes/box-medium.png', $html);
    }
}
