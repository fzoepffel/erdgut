<?php

namespace Tests\Feature\Dashboard;

use App\Enums\BoxStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Box;
use App\Models\BoxItem;
use App\Models\Producer;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardManifestTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);

        parent::tearDown();
    }

    public function test_active_subscriber_with_composed_box_sees_manifest_items_with_quantities_and_numbering(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-18 11:00:00'));

        $user = User::factory()->create(['locale' => 'de']);
        $subscription = $this->subscribeUserFully($user);

        $producer = Producer::factory()->create(['is_test' => true]);
        // ProducerFactory auto-seeds translations for all locales — update the
        // German one in place so the dashboard renders deterministic copy.
        $producer->translations()->where('locale', 'de')->update([
            'name' => 'Hof Apfeltraum',
            'region' => 'Müncheberg',
            'description' => 'Familienbetrieb seit 1962.',
        ]);

        $product = Product::factory()->create([
            'producer_id' => $producer->id,
            'is_available' => true,
            'is_published' => true,
            'is_test' => true,
        ]);
        $product->translations()->where('locale', 'de')->update([
            'name' => 'Radieschen',
            'taste_profile' => 'Pfeffrig und knackig.',
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
            ->assertSee('customer-manifest-grid', false)
            ->assertSee('customer-item-card', false)
            ->assertSee(__('customer.dashboard.manifest.eyebrow_composed'))
            ->assertSee(__('customer.dashboard.manifest.title'))
            ->assertSee('Radieschen')
            // Item number chip starts at 01 for a composed box's first item.
            ->assertSee('№ 01')
            // Producer name is linked; region is a plain suffix. assertSeeText
            // strips tags so the middot join still matches.
            ->assertSeeText('Hof Apfeltraum · MÜNCHEBERG')
            ->assertSee(route('public.producers.show', $producer), false);
    }

    public function test_active_subscriber_without_composed_box_sees_portfolio_preview_without_quantities_or_numbering(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-18 11:00:00'));

        $user = User::factory()->create(['locale' => 'de']);
        $this->subscribeUserFully($user);

        $producer = Producer::factory()->create(['is_test' => true]);
        $producer->translations()->where('locale', 'de')->update([
            'name' => 'Spargelhof Kremmen',
            'region' => 'Kremmen',
            'description' => 'Vier Generationen Spargel.',
        ]);

        // Seed 6 catalog products so the portfolio query can fill the grid.
        Product::factory()->count(6)->create([
            'producer_id' => $producer->id,
            'is_available' => true,
            'is_published' => true,
            'is_egg_addon' => false,
            'is_test' => true,
        ]);

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('customer-manifest-grid', false)
            ->assertSee('customer-item-card', false)
            // Uncomposed eyebrow + matching subtitle copy.
            ->assertSee(__('customer.dashboard.manifest.eyebrow_uncomposed'))
            ->assertSee(__('customer.dashboard.manifest.subtitle_uncomposed'))
            // No item-number chips when there is no box ordering yet.
            ->assertDontSee('№ 01');
    }

    public function test_paused_subscriber_does_not_render_manifest_section(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-18 11:00:00'));

        $user = User::factory()->create(['locale' => 'de']);
        $subscription = $this->subscribeUserFully($user);
        $subscription->update([
            'status' => SubscriptionStatus::Paused,
            'pause_from' => Carbon::today()->subDay()->toDateString(),
            'pause_until' => Carbon::today()->addWeeks(2)->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertDontSee('customer-manifest-grid', false)
            ->assertDontSee(__('customer.dashboard.manifest.title'));
    }

    public function test_new_visitor_does_not_render_manifest_section(): void
    {
        // No subscription, no trial → NewVisitor state. Manifest is gated on
        // the new-hero kinds (Active / FirstWeek / Paused), so a new visitor
        // sees neither the new hero nor the manifest.
        $user = User::factory()->create(['locale' => 'de']);

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertDontSee('customer-manifest-grid', false);
    }
}
