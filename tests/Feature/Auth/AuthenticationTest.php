<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/auth/login');

        $response->assertStatus(200);
        // W2-01: the guest layout now uses the unified customer header
        // (green chapter-produce bar, same as marketing). The logo is the
        // home link; the centered "guest-layout-home" wrapper is gone.
        $response->assertSee(route('public.marketing-home', absolute: false), false);
        $response->assertSee('data-testid="guest-layout"', false);
        $response->assertSee('data-testid="customer-header-auth"', false);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $this->startSession();
        $oldSessionId = session()->getId();

        $response = $this->post('/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $this->assertNotSame($oldSessionId, session()->getId());
        $response->assertRedirect(route('app.dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->withSession(['ephemeral_key' => 'value'])
            ->post('/auth/logout');

        $this->assertGuest();
        $response->assertSessionMissing('ephemeral_key');
        $response->assertRedirect('/');
    }
}
