<?php

namespace Tests\Feature\Admin;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_admin_user_from_admin_users_page(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('app.admin.users.store-admin'), [
                'name' => 'New Admin',
                'email' => 'new-admin@example.com',
                'password' => 'a-very-strong-password',
            ])
            ->assertRedirect(route('app.admin.users.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'new-admin@example.com',
            'is_admin' => 1,
        ]);
    }

    public function test_admin_cannot_delete_self_or_last_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->delete(route('app.admin.users.destroy', $admin))
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'is_admin' => 1,
        ]);
    }

    public function test_admin_can_delete_non_admin_user_and_cascade_subscriptions(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $sub = Subscription::factory()->for($user)->create([
            'status' => SubscriptionStatus::Active,
        ]);

        $this->actingAs($admin)
            ->delete(route('app.admin.users.destroy', $user))
            ->assertRedirect(route('app.admin.users.index'));

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('subscriptions', ['id' => $sub->id]);
    }

    public function test_admin_can_promote_user_to_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($admin)
            ->patch(route('app.admin.users.set-role', $user), ['role' => 'admin'])
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'is_admin' => 1]);
    }

    public function test_admin_can_demote_other_admin_when_more_than_one_exists(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch(route('app.admin.users.set-role', $other), ['role' => 'user'])
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $other->id, 'is_admin' => 0]);
    }

    public function test_admin_cannot_demote_last_admin(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch(route('app.admin.users.set-role', $admin), ['role' => 'user'])
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'is_admin' => 1]);
    }

    public function test_admin_cannot_change_own_role(): void
    {
        // Make sure the "last admin" guard isn't what's protecting self.
        $admin = User::factory()->admin()->create();
        User::factory()->admin()->create();

        $this->actingAs($admin)
            ->patch(route('app.admin.users.set-role', $admin), ['role' => 'user'])
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['id' => $admin->id, 'is_admin' => 1]);
    }

    public function test_non_admin_cannot_change_roles(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $other = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->patch(route('app.admin.users.set-role', $other), ['role' => 'admin'])
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $other->id, 'is_admin' => 0]);
    }

    public function test_admin_subscriptions_index_excludes_admin_users(): void
    {
        $admin = User::factory()->admin()->create();

        $adminWithSubscription = User::factory()->admin()->create(['email' => 'admin-with-sub@example.com']);
        Subscription::factory()->for($adminWithSubscription)->create([
            'status' => SubscriptionStatus::Active,
        ]);

        $customer = User::factory()->create(['email' => 'customer@example.com']);
        Subscription::factory()->for($customer)->create([
            'status' => SubscriptionStatus::Active,
        ]);

        $html = $this->actingAs($admin)
            ->get(route('app.admin.subscriptions.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('customer@example.com', $html);
        $this->assertStringNotContainsString('admin-with-sub@example.com', $html);
    }
}
