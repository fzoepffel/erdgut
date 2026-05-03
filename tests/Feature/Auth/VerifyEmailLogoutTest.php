<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerifyEmailLogoutTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Regression: the "Log out" button on the verify-email page used the
     * <x-secondary-button> component, whose default `type` was "button"
     * (instead of "submit"), so clicking it never submitted the wrapping
     * logout form. Defaulting to "submit" — matching primary/danger button
     * components — restored the expected behavior.
     */
    public function test_unverified_user_can_log_out_from_verify_email_page(): void
    {
        $user = User::factory()->unverified()->create();

        // Page renders the logout form's button as type="submit"
        $page = $this->actingAs($user)->get(route('verification.notice'));
        $page->assertOk();

        $html = $page->getContent();

        // Both forms on this page (resend-verification + log-out) should
        // render with type="submit" buttons.
        $this->assertSame(
            2,
            preg_match_all('/<button\b[^>]*type="submit"[^>]*>/', $html),
            'expected both verify-email forms to render submit buttons'
        );

        // And the logout submission actually logs the user out.
        $logout = $this->actingAs($user)->post(route('auth.logout'));
        $logout->assertRedirect();
        $this->assertGuest();
    }
}
