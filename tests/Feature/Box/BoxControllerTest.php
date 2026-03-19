<?php

namespace Tests\Feature\Box;

use App\Enums\BoxStatus;
use App\Models\Box;
use App\Models\BoxItem;
use App\Models\Product;
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
            ->assertSee('Box details')
            ->assertSee('Test Tomatoes')
            ->assertSee('1.50 kg')
            ->assertSee('Estimated value: EUR 45.80')
            ->assertSee('Extra');
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
            ->assertSee('Edit mode')
            ->assertSee('Quantity and remove actions are scaffolded and will be activated in ERD-065/066.');
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
}
