<?php

namespace Tests\Feature\Admin;

use App\Models\RestaurantOrder;
use App\Models\RestaurantProfile;
use App\Models\RestaurantStandingOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function restaurantUserWithOrders(): User
    {
        $profile = RestaurantProfile::factory()->verified()->create();
        $user = $profile->user;

        RestaurantOrder::query()->create([
            'user_id' => $user->id,
            'expected_delivery_date' => now()->addWeek()->toDateString(),
            'delivery_street' => 'Marktstr. 1',
            'delivery_postal_code' => '10115',
            'delivery_city' => 'Berlin',
        ]);

        RestaurantStandingOrder::query()->create([
            'user_id' => $user->id,
            'delivery_weekday' => 3,
            'delivery_street' => 'Marktstr. 1',
            'delivery_postal_code' => '10115',
            'delivery_city' => 'Berlin',
        ]);

        return $user;
    }

    public function test_deleting_restaurant_account_with_orders_succeeds_and_removes_them(): void
    {
        $admin = User::factory()->admin()->create();
        $user = $this->restaurantUserWithOrders();

        $this->actingAs($admin)
            ->delete(route('app.admin.users.destroy', $user))
            ->assertRedirect(route('app.admin.users.index'))
            ->assertSessionHas('status', __('admin.users.flash_deleted'));

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('restaurant_orders', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('restaurant_standing_orders', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('restaurant_profiles', ['user_id' => $user->id]);
    }

    public function test_deleting_restaurant_account_without_orders_still_works(): void
    {
        $admin = User::factory()->admin()->create();
        $user = RestaurantProfile::factory()->verified()->create()->user;

        $this->actingAs($admin)
            ->delete(route('app.admin.users.destroy', $user))
            ->assertRedirect(route('app.admin.users.index'))
            ->assertSessionHas('status', __('admin.users.flash_deleted'));

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_non_admin_cannot_delete_a_user(): void
    {
        $actor = User::factory()->create(['is_admin' => false]);
        $target = User::factory()->create();

        $this->actingAs($actor)
            ->delete(route('app.admin.users.destroy', $target))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }
}
