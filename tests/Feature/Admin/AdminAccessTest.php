<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_for_admin_dashboard(): void
    {
        $this->get(route('app.admin.dashboard'))
            ->assertRedirect('/auth/login');
    }

    public function test_non_admin_receives_forbidden_on_admin_dashboard(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        $this->actingAs($user)
            ->get(route('app.admin.dashboard'))
            ->assertForbidden();
    }

    public function test_admin_can_access_admin_dashboard(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->get(route('app.admin.dashboard'))
            ->assertOk()
            ->assertSee('Admin area placeholder')
            ->assertSee('Administration', false);
    }

    public function test_unverified_admin_is_redirected_before_admin_middleware(): void
    {
        $user = User::factory()->admin()->unverified()->create();

        $this->actingAs($user)
            ->get(route('app.admin.dashboard'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_admin_sees_admin_nav_link_on_customer_dashboard(): void
    {
        $user = User::factory()->admin()->create();

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertSee(route('app.admin.dashboard', absolute: false), false);
    }

    public function test_non_admin_does_not_see_admin_nav_link_on_customer_dashboard(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)
            ->get(route('app.dashboard'))
            ->assertOk()
            ->assertDontSee(route('app.admin.dashboard', absolute: false), false);
    }
}
