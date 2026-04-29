<?php

namespace Tests\Feature\Admin;

use App\Enums\BoxStatus;
use App\Enums\SubscriptionPlan;
use App\Enums\SubscriptionStatus;
use App\Models\Box;
use App\Models\BoxItem;
use App\Models\Product;
use App\Models\ProductWeekStock;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class AdminBoxItemTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        Config::set('admin.box_line_edit_override', false);

        parent::tearDown();
    }

    public function test_guest_is_redirected_from_item_mutations(): void
    {
        $box = Box::factory()->create();
        $item = BoxItem::factory()->create(['box_id' => $box->id]);

        $this->post(route('app.admin.boxes.items.store', $box), [])->assertRedirect('/auth/login');
        $this->patch(route('app.admin.boxes.items.update', [$box, $item]), [])->assertRedirect('/auth/login');
        $this->delete(route('app.admin.boxes.items.destroy', [$box, $item]))->assertRedirect('/auth/login');
    }

    public function test_non_admin_forbidden_on_item_mutations(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $box = Box::factory()->create();
        $product = Product::factory()->create();
        $item = BoxItem::factory()->create(['box_id' => $box->id]);

        $this->actingAs($user)
            ->post(route('app.admin.boxes.items.store', $box), [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->patch(route('app.admin.boxes.items.update', [$box, $item]), ['quantity' => 2])
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('app.admin.boxes.items.destroy', [$box, $item]))
            ->assertForbidden();
    }

    public function test_admin_can_add_update_remove_on_editable_box_before_deadline(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-24 12:00:00'));

        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $weekStart = Carbon::parse('2026-03-30');
        $box = Box::factory()->create([
            'user_id' => $customer->id,
            'week_start_date' => $weekStart->toDateString(),
            'status' => BoxStatus::Editable,
            'modification_deadline_at' => $weekStart->copy()->addDays(5),
            'estimated_value' => 10.00,
        ]);
        $product = Product::factory()->create([
            'is_available' => true,
            'is_published' => true,
        ]);
        $product->translations()->where('locale', 'en')->update(['name' => 'Admin Catalog Apple']);
        ProductWeekStock::create([
            'product_id' => $product->id,
            'week_start_date' => $weekStart->toDateString(),
            'planned_quantity' => 10,
            'unit' => 'kg',
        ]);

        $this->actingAs($admin)
            ->post(route('app.admin.boxes.items.store', $box), [
                'product_id' => $product->id,
                'quantity' => 1.5,
            ])
            ->assertRedirect(route('app.admin.boxes.show', $box))
            ->assertSessionHas('status');

        $item = BoxItem::query()->where('box_id', $box->id)->where('product_id', $product->id)->first();
        $this->assertNotNull($item);
        $this->assertSame(1.5, (float) $item->quantity);
        $this->assertTrue($item->is_extra);

        $this->actingAs($admin)
            ->patch(route('app.admin.boxes.items.update', [$box, $item]), ['quantity' => 2.25])
            ->assertRedirect(route('app.admin.boxes.show', $box));

        $item->refresh();
        $this->assertSame(2.25, (float) $item->quantity);

        $this->actingAs($admin)
            ->delete(route('app.admin.boxes.items.destroy', [$box, $item]))
            ->assertRedirect(route('app.admin.boxes.show', $box));

        $this->assertDatabaseMissing('box_items', ['id' => $item->id]);
    }

    public function test_mutations_forbidden_when_locked_without_override(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $box = Box::factory()->create([
            'user_id' => $customer->id,
            'status' => BoxStatus::Locked,
            'modification_deadline_at' => now()->addDay(),
        ]);
        $product = Product::factory()->create();
        $item = BoxItem::factory()->create(['box_id' => $box->id]);

        $this->actingAs($admin)
            ->post(route('app.admin.boxes.items.store', $box), [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->patch(route('app.admin.boxes.items.update', [$box, $item]), ['quantity' => 2])
            ->assertForbidden();

        $this->actingAs($admin)
            ->delete(route('app.admin.boxes.items.destroy', [$box, $item]))
            ->assertForbidden();
    }

    public function test_mutations_allowed_when_locked_with_override(): void
    {
        Config::set('admin.box_line_edit_override', true);

        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $weekStart = Carbon::today()->next(Carbon::MONDAY);
        $box = Box::factory()->create([
            'user_id' => $customer->id,
            'week_start_date' => $weekStart->toDateString(),
            'status' => BoxStatus::Locked,
            'modification_deadline_at' => now()->subDay(),
        ]);
        $product = Product::factory()->create([
            'is_available' => true,
            'is_published' => true,
        ]);
        ProductWeekStock::create([
            'product_id' => $product->id,
            'week_start_date' => $weekStart->toDateString(),
            'planned_quantity' => 5,
            'unit' => 'piece',
        ]);

        $this->actingAs($admin)
            ->post(route('app.admin.boxes.items.store', $box), [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertRedirect(route('app.admin.boxes.show', $box));
    }

    public function test_admin_cannot_add_product_without_weekly_stock(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-24 12:00:00'));

        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $weekStart = Carbon::parse('2026-03-30');
        $box = Box::factory()->create([
            'user_id' => $customer->id,
            'week_start_date' => $weekStart->toDateString(),
            'status' => BoxStatus::Editable,
            'modification_deadline_at' => $weekStart->copy()->addDays(5),
        ]);

        $product = Product::factory()->create([
            'is_available' => true,
            'is_published' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('app.admin.boxes.items.store', $box), [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertSessionHasErrors(['product_id']);

        $this->assertDatabaseMissing('box_items', [
            'box_id' => $box->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_admin_box_show_dropdown_only_lists_products_with_weekly_stock(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-24 12:00:00'));

        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $weekStart = Carbon::parse('2026-03-30');
        $box = Box::factory()->create([
            'user_id' => $customer->id,
            'week_start_date' => $weekStart->toDateString(),
            'status' => BoxStatus::Editable,
            'modification_deadline_at' => $weekStart->copy()->addDays(5),
        ]);

        $available = Product::factory()->create([
            'is_available' => true,
            'is_published' => true,
        ]);
        $available->translations()->where('locale', 'en')->update(['name' => 'Available-Week-Product']);
        ProductWeekStock::create([
            'product_id' => $available->id,
            'week_start_date' => $weekStart->toDateString(),
            'planned_quantity' => 10,
            'unit' => 'piece',
        ]);

        $unavailable = Product::factory()->create([
            'is_available' => true,
            'is_published' => true,
        ]);
        $unavailable->translations()->where('locale', 'en')->update(['name' => 'Not-In-Week-Product']);

        $html = $this->actingAs($admin)
            ->get(route('app.admin.boxes.show', $box))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Available-Week-Product', $html);
        $this->assertStringNotContainsString('Not-In-Week-Product', $html);
    }

    public function test_admin_box_show_does_not_emit_sumup_strings(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-24'));

        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $customer->id,
            'status' => SubscriptionStatus::Active,
            'plan' => SubscriptionPlan::Weekly,
            'sumup_token' => 'admin_box_item_secret_tok',
            'sumup_customer_id' => 'admin_box_item_cust',
        ]);
        $monday = Carbon::today()->next(Carbon::MONDAY)->toDateString();
        $box = Box::factory()->create([
            'user_id' => $customer->id,
            'week_start_date' => $monday,
        ]);

        $html = $this->actingAs($admin)
            ->get(route('app.admin.boxes.show', $box))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('admin_box_item_secret_tok', $html);
        $this->assertStringNotContainsString('sumup_token', $html);
    }

    public function test_show_renders_conflict_chip_for_items_conflicting_with_snapshot(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();

        $monday = Carbon::today()->next(Carbon::MONDAY)->toDateString();
        $box = Box::factory()->create([
            'user_id' => $customer->id,
            'week_start_date' => $monday,
            'allergies_snapshot' => ['nuts'],
            'preferences_snapshot' => ['veg_only' => true, 'eggs_dairy_add_on' => false],
            'status' => BoxStatus::Editable,
            'modification_deadline_at' => now()->addDays(7),
        ]);

        $nutty = Product::factory()->create([
            'allergens' => ['nuts'],
            'dietary_tags' => ['vegetarian'],
        ]);
        $nutty->translations()->where('locale', 'en')->update(['name' => 'Walnut Bread']);
        $nuttyItem = BoxItem::factory()->create(['box_id' => $box->id, 'product_id' => $nutty->id]);

        $safe = Product::factory()->create([
            'allergens' => [],
            'dietary_tags' => ['vegan'],
        ]);
        $safe->translations()->where('locale', 'en')->update(['name' => 'Plain Carrot']);
        BoxItem::factory()->create(['box_id' => $box->id, 'product_id' => $safe->id]);

        // Page is now Alpine-rendered: rows are produced client-side from the
        // `initialState` JSON, so we can't position-test against rendered
        // <td> offsets. Instead assert the conflict primitive is wired up:
        //   - both products and the customer's allergens are in the payload,
        //   - the conflict chip's data-testid is in the page (once — inside
        //     the Alpine `<template>`, the runtime instantiates it per row).
        $html = $this->actingAs($admin)
            ->get(route('app.admin.boxes.show', $box))
            ->assertOk()
            ->assertSee('Walnut Bread')
            ->assertSee('Plain Carrot')
            ->assertSee('admin-box-item-conflict-chip', false)
            ->assertSee('admin-box-force-include-hint', false)
            ->getContent();

        // The customer's allergies are encoded into the Alpine initial state.
        // The @js() directive uses JSON_HEX_QUOT, so quotes become " in
        // the embedded JS literal — checking for the bare token is the
        // simplest portable assertion.
        $this->assertStringContainsString('nuts', $html);

        // The conflict primitive renders once in the source (the `<template>`
        // body); Alpine clones it per matching row at runtime.
        $this->assertSame(1, substr_count($html, 'admin-box-item-conflict-chip'));
    }

    public function test_bulk_update_replaces_lines_and_persists_ok_marker(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $monday = Carbon::today()->next(Carbon::MONDAY);

        $box = Box::factory()->create([
            'user_id' => $customer->id,
            'week_start_date' => $monday->toDateString(),
            'status' => BoxStatus::Editable,
            'modification_deadline_at' => $monday->copy()->addDays(5),
        ]);

        $oldProduct = Product::factory()->create(['unit_price' => 1.00, 'is_available' => true, 'is_published' => true]);
        $oldItem = BoxItem::factory()->create([
            'box_id' => $box->id,
            'product_id' => $oldProduct->id,
            'is_extra' => false,
            'quantity' => 2,
        ]);
        // An extra is preserved across the bulk replace (only is_extra=false rows are wiped).
        $extra = BoxItem::factory()->create([
            'box_id' => $box->id,
            'is_extra' => true,
            'quantity' => 1,
        ]);

        $newProduct = Product::factory()->create(['unit_price' => 3.00, 'is_available' => true, 'is_published' => true]);

        $this->actingAs($admin)
            ->patch(route('app.admin.boxes.update', $box), [
                'lines' => [
                    ['product_id' => $newProduct->id, 'quantity' => 4, 'is_extra' => false],
                ],
                'ok' => '1',
            ])
            ->assertRedirect(route('app.admin.boxes.show', $box));

        $box->refresh();
        $this->assertDatabaseMissing('box_items', ['id' => $oldItem->id]);
        $this->assertDatabaseHas('box_items', ['id' => $extra->id]);
        $this->assertDatabaseHas('box_items', [
            'box_id' => $box->id,
            'product_id' => $newProduct->id,
            'quantity' => 4,
            'is_extra' => false,
        ]);
        $this->assertNotNull($box->composition_admin_ok_at);
        $this->assertSame((int) $admin->id, (int) $box->composition_admin_ok_by);
    }

    public function test_bulk_update_clears_ok_marker_when_unchecked(): void
    {
        $admin = User::factory()->admin()->create();
        $customer = User::factory()->create();
        $monday = Carbon::today()->next(Carbon::MONDAY);

        $box = Box::factory()->create([
            'user_id' => $customer->id,
            'week_start_date' => $monday->toDateString(),
            'status' => BoxStatus::Editable,
            'modification_deadline_at' => $monday->copy()->addDays(5),
            'composition_admin_ok_at' => now(),
            'composition_admin_ok_by' => $admin->id,
        ]);

        $product = Product::factory()->create(['is_available' => true, 'is_published' => true]);

        $this->actingAs($admin)
            ->patch(route('app.admin.boxes.update', $box), [
                'lines' => [
                    ['product_id' => $product->id, 'quantity' => 1],
                ],
                // ok flag absent → cleared
            ])
            ->assertRedirect();

        $box->refresh();
        $this->assertNull($box->composition_admin_ok_at);
        $this->assertNull($box->composition_admin_ok_by);
    }
}
