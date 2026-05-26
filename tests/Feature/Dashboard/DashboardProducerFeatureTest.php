<?php

namespace Tests\Feature\Dashboard;

use App\Enums\BoxStatus;
use App\Models\Box;
use App\Models\BoxItem;
use App\Models\Producer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardProducerFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);

        parent::tearDown();
    }

    public function test_composed_box_anchors_producer_feature_to_a_matching_producer(): void
    {
        // ISO week 22 (2026-05-25) rotates to "marcus" by default. Build a box
        // whose first producer is "anna" instead and confirm the dashboard
        // surfaces anna — proving the box-item anchor wins over the rotation.
        Carbon::setTestNow(Carbon::parse('2026-05-25 11:00:00'));

        $user = User::factory()->create(['locale' => 'de']);
        $subscription = $this->subscribeUserFully($user);

        $producer = Producer::factory()->create(['is_test' => true]);
        // First word of name is what the pick rule compares against
        // EditorialContent::PRODUCER_SLUGS, so "Anna ..." matches the slug 'anna'.
        $producer->translations()->where('locale', 'de')->update([
            'name' => 'Anna Schmitz',
            'region' => 'Tegernsee',
        ]);

        $product = Product::factory()->create([
            'producer_id' => $producer->id,
            'is_available' => true,
            'is_published' => true,
            'is_test' => true,
        ]);

        $box = Box::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'week_start_date' => Carbon::today()->startOfWeek()->toDateString(),
            'status' => BoxStatus::Editable,
            'estimated_value' => 39.99,
        ]);
        BoxItem::factory()->create([
            'box_id' => $box->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit' => 'piece',
            'is_extra' => false,
        ]);

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('customer-producer-feature', false)
            // Anna's editorial quote — proves the per-box anchor surfaced.
            ->assertSee(__('customer.producer.spotlight.anna.quote'))
            // Marcus would render here if the rotation had won.
            ->assertDontSee(__('customer.producer.spotlight.marcus.quote'));
    }

    public function test_composed_box_without_matching_producer_falls_back_to_iso_week_rotation(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-25 11:00:00'));

        $user = User::factory()->create(['locale' => 'de']);
        $subscription = $this->subscribeUserFully($user);

        // "Apfeltraum Bauernhof" → first word lowered is "apfeltraum" → no slug match.
        $producer = Producer::factory()->create(['is_test' => true]);
        $producer->translations()->where('locale', 'de')->update([
            'name' => 'Apfeltraum Bauernhof',
            'region' => 'Brandenburg',
        ]);

        $product = Product::factory()->create([
            'producer_id' => $producer->id,
            'is_available' => true,
            'is_published' => true,
            'is_test' => true,
        ]);

        $box = Box::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'week_start_date' => Carbon::today()->startOfWeek()->toDateString(),
            'status' => BoxStatus::Editable,
            'estimated_value' => 39.99,
        ]);
        BoxItem::factory()->create([
            'box_id' => $box->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit' => 'piece',
        ]);

        // ISO week 22 → PRODUCER_SLUGS[(22 - 1) % 6] = PRODUCER_SLUGS[3] = 'marcus'.
        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('customer-producer-feature', false)
            ->assertSee(__('customer.producer.spotlight.marcus.quote'))
            ->assertDontSee(__('customer.producer.spotlight.anna.quote'));
    }

    public function test_active_subscriber_without_composed_box_renders_iso_week_rotation(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-25 11:00:00'));

        $user = User::factory()->create(['locale' => 'de']);
        $this->subscribeUserFully($user);

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('customer-producer-feature', false)
            // Eyebrow proves the landscape feature is rendering.
            ->assertSee(__('customer.dashboard.producer_feature.eyebrow'))
            // ISO week 22 → marcus.
            ->assertSee(__('customer.producer.spotlight.marcus.quote'));
    }

    public function test_landscape_producer_feature_suppresses_the_small_glance_spotlight(): void
    {
        // When the new producer-feature renders for steady-state subscribers,
        // the dashboard's "At a glance" card no longer also shows the small
        // <x-customer.producer-spotlight> at the bottom — avoids duplicate
        // producer surfacing in the same scroll.
        Carbon::setTestNow(Carbon::parse('2026-05-25 11:00:00'));

        $user = User::factory()->create(['locale' => 'de']);
        $this->subscribeUserFully($user);

        $response = $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('customer-producer-feature', false);

        // The small spotlight component sets data-testid="customer-producer-spotlight".
        // Confirm it's absent when the landscape feature is active.
        $response->assertDontSee('customer-producer-spotlight', false);
    }
}
