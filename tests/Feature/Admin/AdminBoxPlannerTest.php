<?php

namespace Tests\Feature\Admin;

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Box;
use App\Models\BoxItem;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AdminBoxPlannerTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);

        parent::tearDown();
    }

    public function test_guest_is_redirected_from_planner_and_show(): void
    {
        $box = Box::factory()->create();

        $this->get(route('app.admin.boxes.planner'))
            ->assertRedirect('/auth/login');

        $this->get(route('app.admin.boxes.show', $box))
            ->assertRedirect('/auth/login');
    }

    public function test_non_admin_receives_forbidden_on_planner_show_and_posts(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $customer = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $customer->id,
            'status' => SubscriptionStatus::Active,
            'plan' => SubscriptionPlan::Weekly,
        ]);
        $box = Box::factory()->for($customer)->create();
        $product = Product::factory()->create(['is_available' => true, 'is_published' => true]);

        $this->actingAs($user)
            ->get(route('app.admin.boxes.planner'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('app.admin.boxes.show', $box))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('app.admin.boxes.store'), [
                'subscription_id' => $subscription->id,
                'week_start_date' => '2026-03-30',
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('app.admin.boxes.planner.canonical-apply'), [
                'week_start_date' => '2026-03-30',
                'confirm_fill' => '1',
                'templates' => [
                    'small' => [
                        'lines' => [
                            ['product_id' => $product->id, 'quantity' => 1],
                        ],
                    ],
                ],
            ])
            ->assertForbidden();
    }

    public function test_admin_receives_ok_on_planner_with_no_subscriptions(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('app.admin.boxes.planner'))
            ->assertOk();
    }

    public function test_admin_can_create_box_for_eligible_week_with_expected_deadline(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-24'));

        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $customer->id,
            'status' => SubscriptionStatus::Active,
            'plan' => SubscriptionPlan::Weekly,
            'box_size' => 'small',
        ]);

        $monday = Carbon::today()->next(Carbon::MONDAY)->toDateString();

        $this->actingAs($admin)
            ->post(route('app.admin.boxes.store'), [
                'subscription_id' => $subscription->id,
                'week_start_date' => $monday,
            ])
            ->assertRedirect(route('app.admin.boxes.planner'))
            ->assertSessionHas('status');

        $box = Box::forUser($customer->id)->forWeek($monday)->first();
        $this->assertNotNull($box);
        $this->assertSame('2026-03-28', $box->modification_deadline_at->toDateString());
    }

    public function test_admin_canonical_apply_replaces_core_lines_with_custom_template(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-24'));

        $product = Product::factory()->create([
            'category' => 'vegetables',
            'is_available' => true,
            'is_published' => true,
            'unit_price' => 2.50,
        ]);

        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $customer->id,
            'status' => SubscriptionStatus::Active,
            'plan' => SubscriptionPlan::Weekly,
            'box_size' => 'small',
        ]);

        $monday = Carbon::today()->next(Carbon::MONDAY)->toDateString();

        $this->actingAs($admin)
            ->post(route('app.admin.boxes.store'), [
                'subscription_id' => $subscription->id,
                'week_start_date' => $monday,
            ])
            ->assertRedirect();

        $box = Box::forUser($customer->id)->forWeek($monday)->first();
        $this->assertNotNull($box);

        BoxItem::factory()->for($box)->create([
            'product_id' => $product->id,
            'quantity' => 1,
            'is_extra' => false,
        ]);

        $this->actingAs($admin)
            ->from(route('app.admin.boxes.planner'))
            ->post(route('app.admin.boxes.planner.canonical-apply'), [
                'week_start_date' => $monday,
                'confirm_fill' => '1',
                'templates' => [
                    'small' => [
                        'lines' => [
                            ['product_id' => $product->id, 'quantity' => 3],
                        ],
                    ],
                ],
            ])
            ->assertRedirect(route('app.admin.boxes.planner'));

        $box->refresh();
        $this->assertSame(1, $box->items()->where('is_extra', false)->count());
        $this->assertEqualsWithDelta(3.0, (float) $box->items()->where('is_extra', false)->first()->quantity, 0.001);
    }

    public function test_store_rejects_week_in_pause_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-24'));

        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $monday = Carbon::today()->next(Carbon::MONDAY);

        $subscription = Subscription::factory()->create([
            'user_id' => $customer->id,
            'status' => SubscriptionStatus::Active,
            'plan' => SubscriptionPlan::Weekly,
            'pause_from' => $monday->copy()->subDays(3),
            'pause_until' => $monday->copy()->addDays(3),
        ]);

        $this->actingAs($admin)
            ->post(route('app.admin.boxes.store'), [
                'subscription_id' => $subscription->id,
                'week_start_date' => $monday->toDateString(),
            ])
            ->assertSessionHasErrors('week_start_date');
    }

    public function test_store_rejects_biweekly_off_week(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-24'));

        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $customer->id,
            'status' => SubscriptionStatus::Active,
            'plan' => SubscriptionPlan::Biweekly,
        ]);

        $monday = Carbon::today()->next(Carbon::MONDAY);
        while ((int) $monday->weekOfYear % 2 === 0) {
            $monday->addWeek();
        }

        $this->actingAs($admin)
            ->post(route('app.admin.boxes.store'), [
                'subscription_id' => $subscription->id,
                'week_start_date' => $monday->toDateString(),
            ])
            ->assertSessionHasErrors('week_start_date');
    }

    public function test_planner_and_box_show_html_do_not_contain_sumup_fixture_strings(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-24'));

        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $customer->id,
            'status' => SubscriptionStatus::Active,
            'plan' => SubscriptionPlan::Weekly,
            'sumup_token' => 'planner_should_not_emit_token_xyz',
            'sumup_customer_id' => 'planner_cust_abc',
        ]);

        $monday = Carbon::today()->next(Carbon::MONDAY)->toDateString();
        $box = Box::factory()->for($customer)->create([
            'week_start_date' => $monday,
        ]);

        $plannerHtml = $this->actingAs($admin)
            ->get(route('app.admin.boxes.planner'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('planner_should_not_emit_token_xyz', $plannerHtml);
        $this->assertStringNotContainsString('planner_cust_abc', $plannerHtml);
        $this->assertStringNotContainsString('sumup_token', $plannerHtml);

        $showHtml = $this->actingAs($admin)
            ->get(route('app.admin.boxes.show', $box))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('planner_should_not_emit_token_xyz', $showHtml);
        $this->assertStringNotContainsString('sumup_token', $showHtml);
    }

    public function test_planner_shows_comma_separated_line_summary_for_box(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-24'));

        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $customer->id,
            'status' => SubscriptionStatus::Active,
            'plan' => SubscriptionPlan::Weekly,
            'box_size' => 'small',
        ]);

        $monday = Carbon::today()->next(Carbon::MONDAY)->toDateString();
        $p1 = Product::factory()->create(['name' => 'PlannerSummaryKarotte', 'is_available' => true, 'is_published' => true]);
        $p2 = Product::factory()->create(['name' => 'PlannerSummaryRadieschen', 'is_available' => true, 'is_published' => true]);
        $box = Box::factory()->for($customer)->create(['week_start_date' => $monday]);
        BoxItem::factory()->for($box)->create(['product_id' => $p1->id, 'quantity' => 2, 'is_extra' => false]);
        BoxItem::factory()->for($box)->create(['product_id' => $p2->id, 'quantity' => 3, 'is_extra' => false]);

        $html = $this->actingAs($admin)
            ->get(route('app.admin.boxes.planner'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('PlannerSummaryKarotte', $html);
        $this->assertStringContainsString('PlannerSummaryRadieschen', $html);
    }

    public function test_admin_canonical_preview_and_apply_uses_shared_template_from_post(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-24'));

        Config::set('box.composition_by_size.small', [
            'vegetables' => 2,
        ]);

        $cheap = Product::factory()->create([
            'name' => 'Cheap Veg',
            'category' => 'vegetables',
            'is_available' => true,
            'is_published' => true,
            'unit_price' => 1.00,
        ]);
        $pricey = Product::factory()->create([
            'name' => 'Pricey Veg',
            'category' => 'vegetables',
            'is_available' => true,
            'is_published' => true,
            'unit_price' => 5.00,
        ]);

        $admin = User::factory()->admin()->create();
        $monday = Carbon::today()->next(Carbon::MONDAY)->toDateString();

        $c1 = User::factory()->create();
        $c2 = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $c1->id,
            'status' => SubscriptionStatus::Active,
            'plan' => SubscriptionPlan::Weekly,
            'box_size' => 'small',
        ]);
        Subscription::factory()->create([
            'user_id' => $c2->id,
            'status' => SubscriptionStatus::Active,
            'plan' => SubscriptionPlan::Weekly,
            'box_size' => 'small',
        ]);

        $this->actingAs($admin)
            ->get(route('app.admin.boxes.planner.canonical-preview', ['week_start_date' => $monday]))
            ->assertOk()
            ->assertSee('Cheap Veg', false);

        $this->actingAs($admin)
            ->post(route('app.admin.boxes.planner.canonical-apply'), [
                'week_start_date' => $monday,
                'confirm_fill' => '1',
                'templates' => [
                    'small' => [
                        'lines' => [
                            ['product_id' => $cheap->id, 'quantity' => 1],
                            ['product_id' => $pricey->id, 'quantity' => 1],
                        ],
                    ],
                ],
            ])
            ->assertRedirect(route('app.admin.boxes.planner'));

        $box1 = Box::forUser($c1->id)->forWeek($monday)->first();
        $box2 = Box::forUser($c2->id)->forWeek($monday)->first();
        $this->assertNotNull($box1);
        $this->assertNotNull($box2);

        $ids1 = $box1->items()->orderBy('product_id')->pluck('product_id')->all();
        $ids2 = $box2->items()->orderBy('product_id')->pluck('product_id')->all();
        $this->assertSame($ids1, $ids2);
    }

    public function test_canonical_apply_rejects_unpublished_catalog_product(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-24'));

        $hidden = Product::factory()->create([
            'is_available' => true,
            'is_published' => false,
            'unit_price' => 1.00,
        ]);

        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $customer->id,
            'status' => SubscriptionStatus::Active,
            'plan' => SubscriptionPlan::Weekly,
            'box_size' => 'small',
        ]);

        $monday = Carbon::today()->next(Carbon::MONDAY)->toDateString();

        $this->actingAs($admin)
            ->from(route('app.admin.boxes.planner'))
            ->post(route('app.admin.boxes.planner.canonical-apply'), [
                'week_start_date' => $monday,
                'confirm_fill' => '1',
                'templates' => [
                    'small' => [
                        'lines' => [
                            ['product_id' => $hidden->id, 'quantity' => 1],
                        ],
                    ],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors();

        $this->assertNull(Box::forUser($customer->id)->forWeek($monday)->first());
    }
}
