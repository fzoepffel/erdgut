<?php

namespace Tests\Feature\Dashboard;

use App\Enums\BoxStatus;
use App\Models\Box;
use App\Models\BoxItem;
use App\Models\Producer;
use App\Models\Product;
use App\Models\User;
use App\Support\EditorialContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardRecipeFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);

        parent::tearDown();
    }

    public function test_active_subscriber_with_composed_box_sees_recipe_with_from_box_overlap(): void
    {
        // Pin to ISO week 19 of 2026 — (19 - 1) % 6 = 0 → recipe rotation
        // index 0 = "spargel-erdbeer". Its ingredient_slugs include 'spargel'
        // and 'radieschen' — seed a box whose product names contain those
        // tokens so the overlap > 0.
        Carbon::setTestNow(Carbon::parse('2026-05-04 11:00:00'));

        $user = User::factory()->create(['locale' => 'de']);
        $subscription = $this->subscribeUserFully($user);

        $producer = Producer::factory()->create(['is_test' => true]);

        $spargel = Product::factory()->create([
            'producer_id' => $producer->id,
            'is_available' => true,
            'is_published' => true,
            'is_test' => true,
        ]);
        $spargel->translations()->where('locale', 'de')->update(['name' => 'Grüner Spargel']);

        $radieschen = Product::factory()->create([
            'producer_id' => $producer->id,
            'is_available' => true,
            'is_published' => true,
            'is_test' => true,
        ]);
        $radieschen->translations()->where('locale', 'de')->update(['name' => 'Bunte Radieschen']);

        $box = Box::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'week_start_date' => Carbon::today()->startOfWeek()->toDateString(),
            'status' => BoxStatus::Editable,
            'estimated_value' => 39.99,
        ]);
        BoxItem::factory()->create([
            'box_id' => $box->id,
            'product_id' => $spargel->id,
            'quantity' => 1,
            'unit' => 'piece',
        ]);
        BoxItem::factory()->create([
            'box_id' => $box->id,
            'product_id' => $radieschen->id,
            'quantity' => 1,
            'unit' => 'bunch',
        ]);

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('customer-recipe-feature', false)
            ->assertSee(__('customer.dashboard.recipe_feature.eyebrow'))
            // Recipe title — substring without `&` to dodge HTML entity escaping.
            ->assertSee('Gegrillter Spargel mit Radieschen')
            // From-box metric — 2 of the 4 ingredient slugs match.
            ->assertSee(__('customer.dashboard.recipe_feature.metric_from_box'))
            ->assertSee(__('customer.dashboard.recipe_feature.from_box_value', ['matched' => 2, 'total' => 4]));
    }

    public function test_active_subscriber_without_composed_box_sees_recipe_without_overlap_metric(): void
    {
        // Same anchor week as the composed-box test so both surface the
        // spargel-erdbeer recipe; the only difference is the box state.
        Carbon::setTestNow(Carbon::parse('2026-05-04 11:00:00'));

        $user = User::factory()->create(['locale' => 'de']);
        $this->subscribeUserFully($user);

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee('customer-recipe-feature', false)
            ->assertSee('Gegrillter Spargel mit Radieschen')
            // No composed box → "Aus der Box" metric omitted entirely.
            ->assertDontSee(__('customer.dashboard.recipe_feature.metric_from_box'));
    }

    public function test_recipe_show_route_renders_editorial_payload(): void
    {
        // Use the second recipe in the rotation — its title doesn't contain
        // an "&" character, so the assertSee doesn't have to deal with HTML
        // entity escaping. The intent is just to verify the route resolves
        // and the editorial payload makes it through.
        $slug = EditorialContent::recipeSlugs()[1];

        $this->get(route('public.recipes.show', ['slug' => $slug]))
            ->assertOk()
            ->assertSee(__('customer.recipe.'.$slug.'.title'))
            ->assertSee(__('customer.recipe.'.$slug.'.description'));
    }

    public function test_recipe_show_route_returns_404_for_unknown_slug(): void
    {
        $this->get(route('public.recipes.show', ['slug' => 'made-up-slug']))
            ->assertNotFound();
    }
}
