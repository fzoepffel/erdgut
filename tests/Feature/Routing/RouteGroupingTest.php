<?php

namespace Tests\Feature\Routing;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route as RouteFacade;
use Tests\TestCase;

class RouteGroupingTest extends TestCase
{
    use RefreshDatabase;

    public function test_core_named_routes_use_expected_paths(): void
    {
        $this->assertSame('/', route('public.home', absolute: false));
        $this->assertSame('/app/dashboard', route('app.dashboard', absolute: false));
        $this->assertSame('/account/profile', route('account.profile.edit', absolute: false));
        $this->assertSame('/auth/login', route('auth.login', absolute: false));
        $this->assertSame('/auth/register', route('auth.register', absolute: false));
        $this->assertSame('/auth/logout', route('auth.logout', absolute: false));

        // Keep default Laravel route names used by auth internals.
        $this->assertSame('/auth/forgot-password', route('password.request', absolute: false));
        $this->assertSame('/auth/verify-email', route('verification.notice', absolute: false));
    }

    public function test_guest_is_redirected_to_auth_login_for_protected_pages(): void
    {
        $this->get('/app/dashboard')->assertRedirect('/auth/login');
        $this->get('/account/profile')->assertRedirect('/auth/login');
    }

    public function test_signed_middleware_is_active_for_email_verification_route(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get('/auth/verify-email/'.$user->id.'/invalid-signature')
            ->assertForbidden();
    }

    public function test_critical_routes_keep_expected_middleware_contracts(): void
    {
        $dashboardRoute = RouteFacade::getRoutes()->getByName('app.dashboard');
        $verificationRoute = RouteFacade::getRoutes()->getByName('verification.verify');
        $verificationSendRoute = RouteFacade::getRoutes()->getByName('verification.send');

        $this->assertNotNull($dashboardRoute);
        $this->assertNotNull($verificationRoute);
        $this->assertNotNull($verificationSendRoute);

        $this->assertContains('auth', $dashboardRoute->gatherMiddleware());
        $this->assertContains('verified', $dashboardRoute->gatherMiddleware());

        $this->assertContains('auth', $verificationRoute->gatherMiddleware());
        $this->assertContains('signed', $verificationRoute->gatherMiddleware());
        $this->assertContains('throttle:6,1', $verificationRoute->gatherMiddleware());

        $this->assertContains('auth', $verificationSendRoute->gatherMiddleware());
        $this->assertContains('throttle:6,1', $verificationSendRoute->gatherMiddleware());
    }
}
