<?php

namespace Tests\Feature\Box;

use App\Enums\BoxStatus;
use App\Models\Box;
use App\Models\BoxItem;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BoxControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_open_box_detail_page(): void
    {
        $user = User::factory()->create();
        $box = Box::factory()->create([
            'user_id' => $user->id,
            'week_start_date' => now()->startOfWeek()->toDateString(),
            'estimated_value' => 45.80,
        ]);
        $product = Product::factory()->create(['name' => 'Test Tomatoes']);
        BoxItem::factory()->create([
            'box_id' => $box->id,
            'product_id' => $product->id,
            'quantity' => 1.5,
            'unit' => 'kg',
            'is_extra' => true,
        ]);

        $this->actingAs($user)
            ->get(route('app.boxes.show', $box))
            ->assertOk()
            ->assertSee('This week\'s box')
            ->assertSee('Test Tomatoes')
            ->assertSee('1.50 kg')
            ->assertSee('Estimated value')
            ->assertSee('45.80')
            ->assertSee('Extra add-on');
    }

    public function test_box_show_displays_budget_details_when_user_has_active_subscription(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'box_size' => 'medium',
        ]);
        $box = Box::factory()->create([
            'user_id' => $user->id,
            'week_start_date' => now()->startOfWeek()->toDateString(),
            'estimated_value' => 10.00,
        ]);

        $this->actingAs($user)
            ->get(route('app.boxes.show', $box))
            ->assertOk()
            ->assertSee('Included in subscription')
            ->assertSee('35.00')
            ->assertSee('Remaining budget')
            ->assertSee('25.00');
    }

    public function test_non_owner_cannot_open_box_placeholder_page(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $box = Box::factory()->create([
            'user_id' => $owner->id,
        ]);

        $this->actingAs($otherUser)
            ->get(route('app.boxes.show', $box))
            ->assertForbidden();
    }

    public function test_edit_mode_is_active_for_editable_box_before_deadline(): void
    {
        $user = User::factory()->create();
        $box = Box::factory()->create([
            'user_id' => $user->id,
            'status' => BoxStatus::Editable,
            'modification_deadline_at' => now()->addDay(),
        ]);

        $this->actingAs($user)
            ->get(route('app.boxes.show', ['box' => $box, 'mode' => 'edit']))
            ->assertOk()
            ->assertSee('Back to view mode')
            ->assertSee('Edit mode');
    }

    public function test_edit_mode_is_blocked_when_box_status_is_not_editable(): void
    {
        $user = User::factory()->create();
        $box = Box::factory()->create([
            'user_id' => $user->id,
            'status' => BoxStatus::Locked,
            'modification_deadline_at' => now()->addDay(),
        ]);

        $this->actingAs($user)
            ->get(route('app.boxes.show', ['box' => $box, 'mode' => 'edit']))
            ->assertOk()
            ->assertSee('Edit mode is blocked because this box status is not editable.')
            ->assertDontSee('Back to view mode');
    }

    public function test_edit_mode_is_blocked_when_deadline_has_passed(): void
    {
        $user = User::factory()->create();
        $box = Box::factory()->create([
            'user_id' => $user->id,
            'status' => BoxStatus::Editable,
            'modification_deadline_at' => now()->subHour(),
        ]);

        $this->actingAs($user)
            ->get(route('app.boxes.show', ['box' => $box, 'mode' => 'edit']))
            ->assertOk()
            ->assertSee('Edit mode is blocked because the modification deadline has passed.')
            ->assertDontSee('Back to view mode');
    }

    public function test_current_route_redirects_to_current_week_box_when_available(): void
    {
        $user = User::factory()->create();
        Box::factory()->create([
            'user_id' => $user->id,
            'week_start_date' => now()->subWeeks(2)->startOfWeek()->toDateString(),
        ]);
        $currentBox = Box::factory()->create([
            'user_id' => $user->id,
            'week_start_date' => now()->startOfWeek()->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('app.boxes.current'))
            ->assertRedirect(route('app.boxes.show', $currentBox));
    }

    public function test_current_route_redirects_to_dashboard_when_no_current_box_exists(): void
    {
        $user = User::factory()->create();
        Box::factory()->create([
            'user_id' => $user->id,
            'week_start_date' => now()->addWeek()->startOfWeek()->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('app.boxes.current'))
            ->assertRedirect(route('app.dashboard'))
            ->assertSessionHas('status', 'current-box-missing');
    }

    public function test_patch_quantity_increases_item_quantity_and_estimated_value(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['unit_price' => 5.00]);
        $box = Box::factory()->create([
            'user_id' => $user->id,
            'status' => BoxStatus::Editable,
            'modification_deadline_at' => now()->addDay(),
            'estimated_value' => 10.00,
        ]);
        $item = BoxItem::factory()->create([
            'box_id' => $box->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit' => 'kg',
            'is_extra' => false,
        ]);

        $response = $this->actingAs($user)
            ->patchJson(route('app.boxes.items.updateQuantity', [$box, $item]), ['delta' => 1]);

        $response->assertOk()
            ->assertJson([
                'quantity' => 3,
                'unit' => 'kg',
                'estimated_value' => 15.00,
            ]);

        $this->assertDatabaseHas('box_items', ['id' => $item->id, 'quantity' => 3]);
        $this->assertDatabaseHas('boxes', ['id' => $box->id, 'estimated_value' => 15.00]);
    }

    public function test_patch_quantity_decreases_item_quantity_and_estimated_value(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['unit_price' => 4.00]);
        $box = Box::factory()->create([
            'user_id' => $user->id,
            'status' => BoxStatus::Editable,
            'modification_deadline_at' => now()->addDay(),
            'estimated_value' => 12.00,
        ]);
        $item = BoxItem::factory()->create([
            'box_id' => $box->id,
            'product_id' => $product->id,
            'quantity' => 3,
            'unit' => 'kg',
            'is_extra' => false,
        ]);

        $response = $this->actingAs($user)
            ->patchJson(route('app.boxes.items.updateQuantity', [$box, $item]), ['delta' => -1]);

        $response->assertOk()
            ->assertJson([
                'quantity' => 2,
                'unit' => 'kg',
                'estimated_value' => 8.00,
            ]);

        $this->assertDatabaseHas('box_items', ['id' => $item->id, 'quantity' => 2]);
        $this->assertDatabaseHas('boxes', ['id' => $box->id, 'estimated_value' => 8.00]);
    }

    public function test_patch_quantity_minus_one_when_quantity_is_one_returns_422(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $box = Box::factory()->create([
            'user_id' => $user->id,
            'status' => BoxStatus::Editable,
            'modification_deadline_at' => now()->addDay(),
        ]);
        $item = BoxItem::factory()->create([
            'box_id' => $box->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit' => 'kg',
            'is_extra' => false,
        ]);

        $response = $this->actingAs($user)
            ->patchJson(route('app.boxes.items.updateQuantity', [$box, $item]), ['delta' => -1]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['delta']);
    }

    public function test_patch_quantity_with_invalid_delta_returns_422(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $box = Box::factory()->create([
            'user_id' => $user->id,
            'status' => BoxStatus::Editable,
            'modification_deadline_at' => now()->addDay(),
        ]);
        $item = BoxItem::factory()->create([
            'box_id' => $box->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit' => 'kg',
            'is_extra' => false,
        ]);

        $response = $this->actingAs($user)
            ->patchJson(route('app.boxes.items.updateQuantity', [$box, $item]), ['delta' => 5]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['delta']);
    }

    public function test_patch_quantity_on_another_users_box_returns_403(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $product = Product::factory()->create();
        $box = Box::factory()->create([
            'user_id' => $owner->id,
            'status' => BoxStatus::Editable,
            'modification_deadline_at' => now()->addDay(),
        ]);
        $item = BoxItem::factory()->create([
            'box_id' => $box->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit' => 'kg',
            'is_extra' => false,
        ]);

        $this->actingAs($otherUser)
            ->patchJson(route('app.boxes.items.updateQuantity', [$box, $item]), ['delta' => 1])
            ->assertForbidden();
    }

    public function test_patch_quantity_on_non_editable_box_returns_403(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $box = Box::factory()->create([
            'user_id' => $user->id,
            'status' => BoxStatus::Locked,
            'modification_deadline_at' => now()->addDay(),
        ]);
        $item = BoxItem::factory()->create([
            'box_id' => $box->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit' => 'kg',
            'is_extra' => false,
        ]);

        $this->actingAs($user)
            ->patchJson(route('app.boxes.items.updateQuantity', [$box, $item]), ['delta' => 1])
            ->assertForbidden();
    }

    public function test_patch_quantity_when_deadline_passed_returns_403(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $box = Box::factory()->create([
            'user_id' => $user->id,
            'status' => BoxStatus::Editable,
            'modification_deadline_at' => now()->subHour(),
        ]);
        $item = BoxItem::factory()->create([
            'box_id' => $box->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit' => 'kg',
            'is_extra' => false,
        ]);

        $this->actingAs($user)
            ->patchJson(route('app.boxes.items.updateQuantity', [$box, $item]), ['delta' => 1])
            ->assertForbidden();
    }

    public function test_patch_quantity_with_item_from_different_box_returns_404(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $box = Box::factory()->create([
            'user_id' => $user->id,
            'status' => BoxStatus::Editable,
            'modification_deadline_at' => now()->addDay(),
        ]);
        $otherBox = Box::factory()->create([
            'user_id' => $user->id,
            'status' => BoxStatus::Editable,
            'modification_deadline_at' => now()->addDay(),
        ]);
        $itemInOtherBox = BoxItem::factory()->create([
            'box_id' => $otherBox->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit' => 'kg',
            'is_extra' => false,
        ]);

        $this->actingAs($user)
            ->patchJson(route('app.boxes.items.updateQuantity', [$box, $itemInOtherBox]), ['delta' => 1])
            ->assertNotFound();
    }

    public function test_delete_removes_item_and_recalculates_estimated_value(): void
    {
        $user = User::factory()->create();
        $product1 = Product::factory()->create(['unit_price' => 5.00]);
        $product2 = Product::factory()->create(['unit_price' => 3.00]);
        $box = Box::factory()->create([
            'user_id' => $user->id,
            'status' => BoxStatus::Editable,
            'modification_deadline_at' => now()->addDay(),
            'estimated_value' => 13.00,
        ]);
        $item1 = BoxItem::factory()->create([
            'box_id' => $box->id,
            'product_id' => $product1->id,
            'quantity' => 2,
            'unit' => 'kg',
            'is_extra' => false,
        ]);
        BoxItem::factory()->create([
            'box_id' => $box->id,
            'product_id' => $product2->id,
            'quantity' => 1,
            'unit' => 'kg',
            'is_extra' => false,
        ]);

        $response = $this->actingAs($user)
            ->deleteJson(route('app.boxes.items.remove', [$box, $item1]));

        $response->assertOk()
            ->assertJson([
                'estimated_value' => 3.00,
                'removed_item_id' => $item1->id,
            ]);

        $this->assertDatabaseMissing('box_items', ['id' => $item1->id]);
        $this->assertDatabaseHas('boxes', ['id' => $box->id, 'estimated_value' => 3.00]);
    }

    public function test_delete_on_another_users_box_returns_403(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $product = Product::factory()->create();
        $box = Box::factory()->create([
            'user_id' => $owner->id,
            'status' => BoxStatus::Editable,
            'modification_deadline_at' => now()->addDay(),
        ]);
        $item = BoxItem::factory()->create([
            'box_id' => $box->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit' => 'kg',
            'is_extra' => false,
        ]);

        $this->actingAs($otherUser)
            ->deleteJson(route('app.boxes.items.remove', [$box, $item]))
            ->assertForbidden();
    }

    public function test_delete_on_non_editable_box_returns_403(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $box = Box::factory()->create([
            'user_id' => $user->id,
            'status' => BoxStatus::Locked,
            'modification_deadline_at' => now()->addDay(),
        ]);
        $item = BoxItem::factory()->create([
            'box_id' => $box->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit' => 'kg',
            'is_extra' => false,
        ]);

        $this->actingAs($user)
            ->deleteJson(route('app.boxes.items.remove', [$box, $item]))
            ->assertForbidden();
    }

    public function test_delete_when_deadline_passed_returns_403(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $box = Box::factory()->create([
            'user_id' => $user->id,
            'status' => BoxStatus::Editable,
            'modification_deadline_at' => now()->subHour(),
        ]);
        $item = BoxItem::factory()->create([
            'box_id' => $box->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit' => 'kg',
            'is_extra' => false,
        ]);

        $this->actingAs($user)
            ->deleteJson(route('app.boxes.items.remove', [$box, $item]))
            ->assertForbidden();
    }

    public function test_delete_with_item_from_different_box_returns_404(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $box = Box::factory()->create([
            'user_id' => $user->id,
            'status' => BoxStatus::Editable,
            'modification_deadline_at' => now()->addDay(),
        ]);
        $otherBox = Box::factory()->create([
            'user_id' => $user->id,
            'status' => BoxStatus::Editable,
            'modification_deadline_at' => now()->addDay(),
        ]);
        $itemInOtherBox = BoxItem::factory()->create([
            'box_id' => $otherBox->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit' => 'kg',
            'is_extra' => false,
        ]);

        $this->actingAs($user)
            ->deleteJson(route('app.boxes.items.remove', [$box, $itemInOtherBox]))
            ->assertNotFound();
    }

    public function test_post_add_item_creates_extra_and_recalculates_value(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'unit_price' => 4.50,
            'is_available' => true,
            'metadata' => ['unit' => 'kg'],
        ]);
        $box = Box::factory()->create([
            'user_id' => $user->id,
            'status' => BoxStatus::Editable,
            'modification_deadline_at' => now()->addDay(),
            'estimated_value' => 0,
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('app.boxes.items.add', $box), [
                'product_id' => $product->id,
                'quantity' => 2,
            ]);

        $response->assertOk()
            ->assertJson([
                'estimated_value' => 9.00,
                'quantity' => 2.0,
            ])
            ->assertJsonStructure(['item_id']);

        $this->assertDatabaseHas('box_items', [
            'box_id' => $box->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit' => 'kg',
            'is_extra' => true,
        ]);
        $this->assertDatabaseHas('boxes', ['id' => $box->id, 'estimated_value' => 9.00]);
    }

    public function test_post_add_item_rejects_unpublished_product(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'is_available' => true,
            'is_published' => false,
        ]);
        $box = Box::factory()->create([
            'user_id' => $user->id,
            'status' => BoxStatus::Editable,
            'modification_deadline_at' => now()->addDay(),
        ]);

        $this->actingAs($user)
            ->postJson(route('app.boxes.items.add', $box), [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['product_id']);
    }

    public function test_post_add_item_increments_existing_product(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create([
            'unit_price' => 3.00,
            'is_available' => true,
        ]);
        $box = Box::factory()->create([
            'user_id' => $user->id,
            'status' => BoxStatus::Editable,
            'modification_deadline_at' => now()->addDay(),
            'estimated_value' => 3.00,
        ]);
        BoxItem::factory()->create([
            'box_id' => $box->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit' => 'kg',
            'is_extra' => false,
        ]);

        $response = $this->actingAs($user)
            ->postJson(route('app.boxes.items.add', $box), [
                'product_id' => $product->id,
                'quantity' => 1.5,
            ]);

        $response->assertOk()
            ->assertJson(['estimated_value' => 7.50, 'quantity' => 2.5]);

        $this->assertDatabaseHas('box_items', [
            'box_id' => $box->id,
            'product_id' => $product->id,
            'quantity' => 2.5,
        ]);
    }

    public function test_post_add_item_rejects_invalid_product(): void
    {
        $user = User::factory()->create();
        $box = Box::factory()->create([
            'user_id' => $user->id,
            'status' => BoxStatus::Editable,
            'modification_deadline_at' => now()->addDay(),
        ]);

        $this->actingAs($user)
            ->postJson(route('app.boxes.items.add', $box), [
                'product_id' => 99999,
                'quantity' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_id');
    }

    public function test_post_add_item_on_another_users_box_returns_403(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $product = Product::factory()->create(['is_available' => true]);
        $box = Box::factory()->create([
            'user_id' => $owner->id,
            'status' => BoxStatus::Editable,
            'modification_deadline_at' => now()->addDay(),
        ]);

        $this->actingAs($otherUser)
            ->postJson(route('app.boxes.items.add', $box), [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertForbidden();
    }

    public function test_post_add_item_returns_403_when_box_status_is_locked(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_available' => true]);
        $box = Box::factory()->create([
            'user_id' => $user->id,
            'status' => BoxStatus::Locked,
            'modification_deadline_at' => now()->addDay(),
        ]);

        $this->actingAs($user)
            ->postJson(route('app.boxes.items.add', $box), [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertForbidden();
    }

    public function test_post_add_item_returns_403_when_box_status_is_delivered(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_available' => true]);
        $box = Box::factory()->create([
            'user_id' => $user->id,
            'status' => BoxStatus::Delivered,
            'modification_deadline_at' => now()->addDay(),
        ]);

        $this->actingAs($user)
            ->postJson(route('app.boxes.items.add', $box), [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertForbidden();
    }

    public function test_post_add_item_returns_403_when_deadline_is_past(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_available' => true]);
        $box = Box::factory()->create([
            'user_id' => $user->id,
            'status' => BoxStatus::Editable,
            'modification_deadline_at' => now()->subHour(),
        ]);

        $this->actingAs($user)
            ->postJson(route('app.boxes.items.add', $box), [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertForbidden();
    }

    public function test_post_add_item_rejects_unavailable_product_with_422(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create(['is_available' => false]);
        $box = Box::factory()->create([
            'user_id' => $user->id,
            'status' => BoxStatus::Editable,
            'modification_deadline_at' => now()->addDay(),
        ]);

        $this->actingAs($user)
            ->postJson(route('app.boxes.items.add', $box), [
                'product_id' => $product->id,
                'quantity' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_id');
    }

    public function test_patch_quantity_on_delivered_box_returns_403(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $box = Box::factory()->create([
            'user_id' => $user->id,
            'status' => BoxStatus::Delivered,
            'modification_deadline_at' => now()->addDay(),
        ]);
        $item = BoxItem::factory()->create([
            'box_id' => $box->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit' => 'kg',
            'is_extra' => false,
        ]);

        $this->actingAs($user)
            ->patchJson(route('app.boxes.items.updateQuantity', [$box, $item]), ['delta' => 1])
            ->assertForbidden();
    }

    public function test_delete_on_delivered_box_returns_403(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->create();
        $box = Box::factory()->create([
            'user_id' => $user->id,
            'status' => BoxStatus::Delivered,
            'modification_deadline_at' => now()->addDay(),
        ]);
        $item = BoxItem::factory()->create([
            'box_id' => $box->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit' => 'kg',
            'is_extra' => false,
        ]);

        $this->actingAs($user)
            ->deleteJson(route('app.boxes.items.remove', [$box, $item]))
            ->assertForbidden();
    }

    public function test_show_edit_mode_on_delivered_box_shows_blocked_reason(): void
    {
        $user = User::factory()->create();
        $box = Box::factory()->create([
            'user_id' => $user->id,
            'status' => BoxStatus::Delivered,
            'modification_deadline_at' => now()->addDay(),
        ]);

        $this->actingAs($user)
            ->get(route('app.boxes.show', ['box' => $box, 'mode' => 'edit']))
            ->assertOk()
            ->assertSee('Edit mode is blocked because this box status is not editable.')
            ->assertDontSee('Back to view mode');
    }
}
