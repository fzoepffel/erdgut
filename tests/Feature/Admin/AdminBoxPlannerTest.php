<?php

namespace Tests\Feature\Admin;

use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Box;
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
            ->post(route('app.admin.boxes.preset', $box), [
                'subscription_id' => $subscription->id,
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('app.admin.boxes.planner.bulk-preset'), [
                'week_start_date' => '2026-03-30',
                'confirm_bulk' => '1',
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

    public function test_admin_preset_fills_box_items_from_composition_rules(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-24'));

        Config::set('box.composition_by_size.small', [
            'vegetables' => 2,
            'fruit' => 1,
        ]);
        Product::factory()->count(4)->create(['category' => 'vegetables', 'is_available' => true]);
        Product::factory()->count(2)->create(['category' => 'fruit', 'is_available' => true]);

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

        $this->actingAs($admin)
            ->from(route('app.admin.boxes.planner'))
            ->post(route('app.admin.boxes.preset', $box), [
                'subscription_id' => $subscription->id,
            ])
            ->assertRedirect();

        $box->refresh();
        $this->assertSame(3, $box->items()->count());
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
}
