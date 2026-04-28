<?php

namespace Tests\Feature\Box;

use App\Enums\BoxStatus;
use App\Models\Box;
use App\Models\BoxItem;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BoxControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_open_box_detail_page(): void
    {
        $user = User::factory()->create();
        $this->subscribeUserFully($user);
        $box = Box::factory()->create([
            'user_id' => $user->id,
            'week_start_date' => now()->startOfWeek()->toDateString(),
            'estimated_value' => 45.80,
        ]);
        $product = Product::factory()->create();
        $product->translations()->where('locale', 'en')->update(['name' => 'Test Tomatoes']);
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
            ->assertSee('Your weekly food box')
            ->assertSee('Test Tomatoes')
            ->assertSee('1.50 kg')
            ->assertSee('Estimated value')
            ->assertSee('45,80')
            ->assertSee('Extra add-on');
    }

    public function test_box_show_is_read_only_for_customers(): void
    {
        $user = User::factory()->create();
        $this->subscribeUserFully($user);
        $box = Box::factory()->create([
            'user_id' => $user->id,
            'status' => BoxStatus::Editable,
            'modification_deadline_at' => now()->addDay(),
        ]);

        $response = $this->actingAs($user)
            ->get(route('app.boxes.show', $box))
            ->assertOk();

        $response->assertDontSee('Edit box contents');
        $response->assertDontSee('box-edit-contents-link', false);
    }

    public function test_box_show_displays_budget_details_when_user_has_active_subscription(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->withPaymentToken()->create([
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
            ->assertSee('35,00')
            ->assertSee('Remaining budget')
            ->assertSee('25,00');
    }

    public function test_non_owner_cannot_open_box_placeholder_page(): void
    {
        $owner = User::factory()->create();
        $this->subscribeUserFully($owner);
        $otherUser = User::factory()->create();
        $this->subscribeUserFully($otherUser);
        $box = Box::factory()->create([
            'user_id' => $owner->id,
        ]);

        $this->actingAs($otherUser)
            ->get(route('app.boxes.show', $box))
            ->assertForbidden();
    }

    public function test_current_route_redirects_to_current_week_box_when_available(): void
    {
        $user = User::factory()->create();
        $this->subscribeUserFully($user);
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
        $this->subscribeUserFully($user);
        Box::factory()->create([
            'user_id' => $user->id,
            'week_start_date' => now()->addWeek()->startOfWeek()->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('app.boxes.current'))
            ->assertRedirect(route('app.dashboard'))
            ->assertSessionHas('status', 'current-box-missing');
    }

    public function test_customer_box_item_mutation_routes_no_longer_exist(): void
    {
        $this->assertFalse(Route::has('app.boxes.items.add'));
        $this->assertFalse(Route::has('app.boxes.items.updateQuantity'));
        $this->assertFalse(Route::has('app.boxes.items.remove'));
    }
}
