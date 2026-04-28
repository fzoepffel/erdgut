<?php

namespace Tests\Feature\Admin;

use App\Enums\BoxStatus;
use App\Enums\DeliveryStatus;
use App\Enums\OrderProductType;
use App\Enums\OrderStatus;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Address;
use App\Models\Box;
use App\Models\BoxItem;
use App\Models\Delivery;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductWeekStock;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AdminBoxSetupTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);

        parent::tearDown();
    }

    public function test_guest_is_redirected_from_index_and_setup(): void
    {
        $monday = '2026-05-04';

        $this->get(route('app.admin.boxes.index'))->assertRedirect('/auth/login');
        $this->get(route('app.admin.boxes.setup.show', ['week' => $monday]))->assertRedirect('/auth/login');
        $this->post(route('app.admin.boxes.setup.apply', ['week' => $monday]), [])->assertRedirect('/auth/login');
        $this->get(route('app.admin.boxes.week.show', ['week' => $monday]))->assertRedirect('/auth/login');
    }

    public function test_non_admin_receives_forbidden(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $monday = '2026-05-04';

        $this->actingAs($user)
            ->get(route('app.admin.boxes.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('app.admin.boxes.setup.show', ['week' => $monday]))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('app.admin.boxes.setup.apply', ['week' => $monday]), [
                'week_start_date' => $monday,
                'sizes' => [],
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('app.admin.boxes.week.show', ['week' => $monday]))
            ->assertForbidden();
    }

    public function test_admin_sees_calendar_overview_index(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-27'));

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get(route('app.admin.boxes.index'))
            ->assertOk()
            ->assertSee(__('admin.box_setup.title'));
    }

    public function test_calendar_overview_includes_trials_and_eggs_in_revenue(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-27'));

        $admin = User::factory()->admin()->create();
        $monday = Carbon::parse('2026-05-04');

        // 1 active medium subscription with eggs add-on flag.
        $subUser = User::factory()->create();
        \App\Models\Subscription::factory()->create([
            'user_id' => $subUser->id,
            'box_size' => 'medium',
            'plan' => \App\Enums\SubscriptionPlan::Weekly,
            'status' => \App\Enums\SubscriptionStatus::Active,
            'trial_completed_at' => now(),
            'preferences' => ['eggs_dairy_add_on' => true],
        ]);

        // 1 paid trial order scheduled for that week, size small, no eggs.
        $trialUser = User::factory()->create();
        \App\Models\Order::create([
            'user_id' => $trialUser->id,
            'product_type' => \App\Enums\OrderProductType::TrialBox->value,
            'status' => \App\Enums\OrderStatus::Paid->value,
            'box_size' => 'small',
            'amount' => 20,
            'currency' => 'EUR',
            'scheduled_for_date' => $monday->copy()->addDays(2)->toDateString(),
            'preferences' => [],
            'is_test' => true,
        ]);

        $service = app(\App\Services\Admin\WeeklyBoxSetupService::class);
        $rows = $service->calendarOverview();

        $weekRow = collect($rows)->firstWhere('week_start_date', $monday->toDateString());

        $this->assertNotNull($weekRow);
        $this->assertSame(1, $weekRow['eligible_count']);
        $this->assertSame(1, $weekRow['trial_eligible_count']);
        $this->assertSame(2, $weekRow['total_eligible_count']);
        $this->assertSame(1, $weekRow['eggs_addon_count']);

        // Revenue: 1 medium (35) + 1 small (20) + 1 eggs add-on (4.50) = 59.50
        $expectedRevenue = (float) config('box.included_value_by_size.medium')
            + (float) config('box.included_value_by_size.small')
            + (float) config('box.eggs_dairy_add_on_eur');
        $this->assertSame(round($expectedRevenue, 2), $weekRow['total_estimated_value']);

        // Per-size includes the trial in 'small' eligible.
        $this->assertSame(1, $weekRow['per_size']['small']['eligible']);
        $this->assertSame(1, $weekRow['per_size']['medium']['eligible']);
    }

    public function test_admin_can_open_setup_dialog_with_initial_distribution(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-27'));

        $admin = User::factory()->admin()->create();
        $monday = Carbon::parse('2026-05-04');

        $customer = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $customer->id,
            'box_size' => 'medium',
            'plan' => SubscriptionPlan::Weekly,
            'status' => SubscriptionStatus::Active,
            'trial_completed_at' => now(),
        ]);

        $product = Product::factory()->create([
            'unit_price' => 2.50,
            'is_available' => true,
            'is_published' => true,
            'metadata' => null,
        ]);
        $product->translations()->where('locale', 'en')->update(['name' => 'Setup Dialog Carrot']);
        ProductWeekStock::create([
            'product_id' => $product->id,
            'week_start_date' => $monday->toDateString(),
            'planned_quantity' => 20,
            'unit' => 'bundle',
        ]);

        $this->actingAs($admin)
            ->get(route('app.admin.boxes.setup.show', ['week' => $monday->toDateString()]))
            ->assertOk()
            ->assertSee('Setup Dialog Carrot');
    }

    public function test_apply_rejects_non_monday_week(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('app.admin.boxes.setup.apply', ['week' => '2026-05-04']), [
                'week_start_date' => '2026-05-06',
                'sizes' => ['small' => []],
            ])
            ->assertSessionHasErrors(['week_start_date']);
    }

    public function test_apply_rejects_product_without_weekly_stock(): void
    {
        $admin = User::factory()->admin()->create();
        $monday = Carbon::parse('2026-05-04');

        $product = Product::factory()->create([
            'is_available' => true,
            'is_published' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('app.admin.boxes.setup.apply', ['week' => $monday->toDateString()]), [
                'week_start_date' => $monday->toDateString(),
                'sizes' => [
                    'small' => [
                        ['product_id' => $product->id, 'quantity' => 1],
                    ],
                ],
            ])
            ->assertSessionHasErrors(['sizes.small.0.product_id']);
    }

    public function test_apply_creates_box_items_for_eligible_subscriptions(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-27'));

        $admin = User::factory()->admin()->create();
        $monday = Carbon::parse('2026-05-04');

        $customer = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $customer->id,
            'box_size' => 'small',
            'plan' => SubscriptionPlan::Weekly,
            'status' => SubscriptionStatus::Active,
            'trial_completed_at' => now(),
        ]);

        $product = Product::factory()->create([
            'unit_price' => 2.00,
            'is_available' => true,
            'is_published' => true,
            'metadata' => ['unit' => 'piece'],
        ]);
        ProductWeekStock::create([
            'product_id' => $product->id,
            'week_start_date' => $monday->toDateString(),
            'planned_quantity' => 10,
            'unit' => 'piece',
        ]);

        $this->actingAs($admin)
            ->post(route('app.admin.boxes.setup.apply', ['week' => $monday->toDateString()]), [
                'week_start_date' => $monday->toDateString(),
                'sizes' => [
                    'small' => [
                        ['product_id' => $product->id, 'quantity' => 3],
                    ],
                ],
            ])
            ->assertRedirect(route('app.admin.boxes.week.show', ['week' => $monday->toDateString()]))
            ->assertSessionHas('status');

        $box = Box::query()
            ->where('user_id', $customer->id)
            ->whereDate('week_start_date', $monday->toDateString())
            ->first();

        $this->assertNotNull($box);
        $this->assertSame(BoxStatus::Editable, $box->status);
        $this->assertSame(1, $box->items()->count());

        $item = $box->items()->first();
        $this->assertSame($product->id, $item->product_id);
        $this->assertEqualsWithDelta(3.0, (float) $item->quantity, 0.001);
    }

    public function test_apply_auto_projects_delivery_for_each_box(): void
    {
        // Regression: prior to the WeeklyBoxSetupService projector hookup,
        // saving boxes left a same-day gap where customers saw the upcoming
        // box on their dashboard but no scheduled delivery in their
        // Lieferzeitplan. Admins had to remember to manually run the
        // "Lieferungen aus Boxen synchronisieren" sync.
        Carbon::setTestNow(Carbon::parse('2026-04-27'));

        $admin = User::factory()->admin()->create();
        $monday = Carbon::parse('2026-05-04');

        $customer = User::factory()->create();
        Address::factory()->create(['user_id' => $customer->id, 'is_primary' => true]);
        Subscription::factory()->create([
            'user_id' => $customer->id,
            'box_size' => 'small',
            'plan' => SubscriptionPlan::Weekly,
            'status' => SubscriptionStatus::Active,
            'trial_completed_at' => now(),
        ]);

        $product = Product::factory()->create([
            'unit_price' => 2.00,
            'is_available' => true,
            'is_published' => true,
            'metadata' => ['unit' => 'piece'],
        ]);
        ProductWeekStock::create([
            'product_id' => $product->id,
            'week_start_date' => $monday->toDateString(),
            'planned_quantity' => 10,
            'unit' => 'piece',
        ]);

        $this->assertSame(0, Delivery::query()->where('user_id', $customer->id)->count());

        $this->actingAs($admin)
            ->post(route('app.admin.boxes.setup.apply', ['week' => $monday->toDateString()]), [
                'week_start_date' => $monday->toDateString(),
                'sizes' => [
                    'small' => [
                        ['product_id' => $product->id, 'quantity' => 3],
                    ],
                ],
            ])
            ->assertRedirect(route('app.admin.boxes.week.show', ['week' => $monday->toDateString()]));

        $delivery = Delivery::query()
            ->where('user_id', $customer->id)
            ->where('is_weekly_override', false)
            ->first();

        $this->assertNotNull($delivery, 'Saving boxes must auto-project a delivery row.');
        $this->assertSame(DeliveryStatus::Scheduled, $delivery->status);

        $expectedScheduledDate = $monday->copy()->addDays(2)->toDateString();
        $this->assertSame($expectedScheduledDate, $delivery->scheduled_for_date->toDateString());

        $box = Box::query()->where('user_id', $customer->id)->first();
        $this->assertSame($box->id, $delivery->box_id);
    }

    public function test_apply_replaces_non_extra_items_but_keeps_extras(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-27'));

        $admin = User::factory()->admin()->create();
        $monday = Carbon::parse('2026-05-04');

        $customer = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $customer->id,
            'box_size' => 'small',
            'plan' => SubscriptionPlan::Weekly,
            'status' => SubscriptionStatus::Active,
            'trial_completed_at' => now(),
        ]);

        $existingBox = Box::factory()->create([
            'user_id' => $customer->id,
            'week_start_date' => $monday->toDateString(),
            'status' => BoxStatus::Editable,
            'modification_deadline_at' => $monday->copy()->subDays(2),
        ]);
        $oldProduct = Product::factory()->create([
            'is_available' => true,
            'is_published' => true,
        ]);
        $extraProduct = Product::factory()->create([
            'is_available' => true,
            'is_published' => true,
        ]);
        BoxItem::factory()->create([
            'box_id' => $existingBox->id,
            'product_id' => $oldProduct->id,
            'quantity' => 5,
            'is_extra' => false,
        ]);
        BoxItem::factory()->create([
            'box_id' => $existingBox->id,
            'product_id' => $extraProduct->id,
            'quantity' => 1,
            'is_extra' => true,
        ]);

        $newProduct = Product::factory()->create([
            'unit_price' => 2.00,
            'is_available' => true,
            'is_published' => true,
            'metadata' => ['unit' => 'piece'],
        ]);
        ProductWeekStock::create([
            'product_id' => $newProduct->id,
            'week_start_date' => $monday->toDateString(),
            'planned_quantity' => 10,
            'unit' => 'piece',
        ]);

        $this->actingAs($admin)
            ->post(route('app.admin.boxes.setup.apply', ['week' => $monday->toDateString()]), [
                'week_start_date' => $monday->toDateString(),
                'sizes' => [
                    'small' => [
                        ['product_id' => $newProduct->id, 'quantity' => 2],
                    ],
                ],
            ])
            ->assertRedirect(route('app.admin.boxes.week.show', ['week' => $monday->toDateString()]));

        $this->assertDatabaseMissing('box_items', [
            'box_id' => $existingBox->id,
            'product_id' => $oldProduct->id,
        ]);
        $this->assertDatabaseHas('box_items', [
            'box_id' => $existingBox->id,
            'product_id' => $extraProduct->id,
            'is_extra' => true,
        ]);
        $this->assertDatabaseHas('box_items', [
            'box_id' => $existingBox->id,
            'product_id' => $newProduct->id,
            'is_extra' => false,
        ]);
    }

    public function test_apply_skips_locked_or_delivered_boxes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-27'));

        $admin = User::factory()->admin()->create();
        $monday = Carbon::parse('2026-05-04');

        $customer = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $customer->id,
            'box_size' => 'small',
            'plan' => SubscriptionPlan::Weekly,
            'status' => SubscriptionStatus::Active,
            'trial_completed_at' => now(),
        ]);

        $lockedBox = Box::factory()->create([
            'user_id' => $customer->id,
            'week_start_date' => $monday->toDateString(),
            'status' => BoxStatus::Locked,
        ]);
        $existingItem = BoxItem::factory()->create([
            'box_id' => $lockedBox->id,
            'is_extra' => false,
        ]);

        $product = Product::factory()->create([
            'unit_price' => 2.00,
            'is_available' => true,
            'is_published' => true,
            'metadata' => ['unit' => 'piece'],
        ]);
        ProductWeekStock::create([
            'product_id' => $product->id,
            'week_start_date' => $monday->toDateString(),
            'planned_quantity' => 10,
            'unit' => 'piece',
        ]);

        $this->actingAs($admin)
            ->post(route('app.admin.boxes.setup.apply', ['week' => $monday->toDateString()]), [
                'week_start_date' => $monday->toDateString(),
                'sizes' => [
                    'small' => [
                        ['product_id' => $product->id, 'quantity' => 2],
                    ],
                ],
            ])
            ->assertRedirect(route('app.admin.boxes.week.show', ['week' => $monday->toDateString()]));

        $this->assertDatabaseHas('box_items', ['id' => $existingItem->id]);
        $this->assertDatabaseMissing('box_items', [
            'box_id' => $lockedBox->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_reset_wipes_editable_subscription_boxes_for_the_week(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-27'));

        $admin = User::factory()->admin()->create();
        $monday = Carbon::parse('2026-05-04');

        $customer = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $customer->id,
            'box_size' => 'small',
            'plan' => SubscriptionPlan::Weekly,
            'status' => SubscriptionStatus::Active,
            'trial_completed_at' => now(),
        ]);

        $editableBox = Box::factory()->create([
            'user_id' => $customer->id,
            'week_start_date' => $monday->toDateString(),
            'status' => BoxStatus::Editable,
            'order_id' => null,
            'composition_admin_ok_at' => now(),
            'composition_admin_ok_by' => $admin->id,
        ]);
        BoxItem::factory()->create(['box_id' => $editableBox->id, 'is_extra' => false]);

        // Locked box must NOT be touched.
        $lockedBox = Box::factory()->create([
            'user_id' => $customer->id,
            'week_start_date' => $monday->toDateString(),
            'status' => BoxStatus::Locked,
            'order_id' => null,
        ]);
        $lockedItem = BoxItem::factory()->create(['box_id' => $lockedBox->id, 'is_extra' => false]);

        $this->actingAs($admin)
            ->post(route('app.admin.boxes.setup.reset', ['week' => $monday->toDateString()]))
            ->assertRedirect(route('app.admin.boxes.setup.show', ['week' => $monday->toDateString()]));

        $this->assertDatabaseMissing('boxes', ['id' => $editableBox->id]);
        $this->assertDatabaseHas('boxes', ['id' => $lockedBox->id]);
        $this->assertDatabaseHas('box_items', ['id' => $lockedItem->id]);
    }

    public function test_reset_requires_admin(): void
    {
        $monday = '2026-05-04';

        // Guest must be redirected to login before the action runs.
        $this->post(route('app.admin.boxes.setup.reset', ['week' => $monday]))
            ->assertRedirect('/auth/login');

        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->post(route('app.admin.boxes.setup.reset', ['week' => $monday]))
            ->assertForbidden();
    }

    public function test_setup_view_includes_trial_orders_for_the_week(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-27'));

        $admin = User::factory()->admin()->create();
        $monday = Carbon::parse('2026-05-04');

        // Trial customer with delivery scheduled for that week.
        $trialUser = User::factory()->create([
            'name' => 'Trial Tina',
            'email' => 'trial.tina@example.test',
        ]);
        Order::factory()->create([
            'user_id' => $trialUser->id,
            'product_type' => OrderProductType::TrialBox,
            'status' => OrderStatus::Paid,
            'box_size' => 'small',
            'scheduled_for_date' => $monday->copy()->addDays(2)->toDateString(),
        ]);

        // Subscription customer for comparison.
        $subUser = User::factory()->create(['email' => 'sub@example.test']);
        Subscription::factory()->create([
            'user_id' => $subUser->id,
            'box_size' => 'small',
            'plan' => SubscriptionPlan::Weekly,
            'status' => SubscriptionStatus::Active,
            'trial_completed_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('app.admin.boxes.setup.show', ['week' => $monday->toDateString()]))
            ->assertOk()
            ->assertSee('trial.tina@example.test')
            ->assertSee('sub@example.test')
            ->assertSee(__('admin.box_setup.kind_trial_badge'));
    }

    public function test_apply_creates_trial_box_via_t_prefixed_key(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-27'));

        $admin = User::factory()->admin()->create();
        $monday = Carbon::parse('2026-05-04');

        $trialUser = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $trialUser->id,
            'product_type' => OrderProductType::TrialBox,
            'status' => OrderStatus::Paid,
            'box_size' => 'small',
            'scheduled_for_date' => $monday->copy()->addDays(2)->toDateString(),
        ]);

        $product = Product::factory()->create([
            'unit_price' => 3.00,
            'is_available' => true,
            'is_published' => true,
            'metadata' => ['unit' => 'piece'],
        ]);
        ProductWeekStock::create([
            'product_id' => $product->id,
            'week_start_date' => $monday->toDateString(),
            'planned_quantity' => 5,
            'unit' => 'piece',
        ]);

        $this->actingAs($admin)
            ->post(route('app.admin.boxes.setup.apply', ['week' => $monday->toDateString()]), [
                'week_start_date' => $monday->toDateString(),
                'customers' => [
                    "t_{$order->id}" => [
                        ['product_id' => $product->id, 'quantity' => 2],
                    ],
                ],
                'customer_ok' => ["t_{$order->id}" => '1'],
            ])
            ->assertRedirect(route('app.admin.boxes.week.show', ['week' => $monday->toDateString()]));

        $this->assertDatabaseHas('boxes', [
            'order_id' => $order->id,
            'status' => BoxStatus::Editable->value,
        ]);
        $box = Box::query()->where('order_id', $order->id)->first();
        $this->assertNotNull($box);
        $this->assertSame($monday->toDateString(), Carbon::parse($box->week_start_date)->toDateString());
        $this->assertDatabaseHas('box_items', [
            'box_id' => $box->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
    }

    public function test_week_boxes_page_lists_boxes_with_links_to_single_box_editor(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-27'));

        $admin = User::factory()->admin()->create();
        $monday = Carbon::parse('2026-05-04');

        $customer = User::factory()->create(['email' => 'weekly-customer@example.test']);
        Subscription::factory()->create([
            'user_id' => $customer->id,
            'box_size' => 'small',
            'plan' => SubscriptionPlan::Weekly,
            'status' => SubscriptionStatus::Active,
            'trial_completed_at' => now(),
        ]);

        $box = Box::factory()->create([
            'user_id' => $customer->id,
            'week_start_date' => $monday->toDateString(),
            'status' => BoxStatus::Editable,
        ]);

        $this->actingAs($admin)
            ->get(route('app.admin.boxes.week.show', ['week' => $monday->toDateString()]))
            ->assertOk()
            ->assertSee('weekly-customer@example.test')
            ->assertSee(route('app.admin.boxes.show', $box), false);
    }

    public function test_week_boxes_page_renders_empty_state_when_no_boxes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-27'));

        $admin = User::factory()->admin()->create();
        $monday = Carbon::parse('2026-05-04');

        $this->actingAs($admin)
            ->get(route('app.admin.boxes.week.show', ['week' => $monday->toDateString()]))
            ->assertOk()
            ->assertSee(__('admin.box_setup.week_boxes_empty'));
    }
}
