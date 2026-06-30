<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Error pages must wear the shop header (no sign-in / sign-up / box-area
 * nav) so an error never hands visitors a simple route into the parked
 * account surface.
 */
class ErrorPageHeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_404_uses_the_shop_header_without_account_or_box_links(): void
    {
        $response = $this->get('/a-route-that-does-not-exist');

        $response->assertNotFound();
        // Shop header variant, not the marketing one that carries auth links.
        $response->assertSee('data-testid="customer-header-shop"', false);
        $response->assertDontSee('data-testid="customer-header-marketing"', false);
        // No simple path to the parked box / auth surface from a 404.
        $response->assertDontSee(route('auth.login'), false);
        $response->assertDontSee(route('auth.register'), false);
    }

    public function test_419_offers_back_to_shop_with_no_auth_links(): void
    {
        // The session-expired page used to push "Log in" (a box path) and
        // "sign in again" copy — neutralised to a "Back to shop" CTA so an
        // anonymous shop visitor who times out is never nudged into the
        // parked account surface.
        $html = view('errors.419')->render();

        $this->assertStringContainsString('data-testid="customer-header-shop"', $html);
        $this->assertStringContainsString(route('public.shop.index'), $html);
        $this->assertStringNotContainsString(route('auth.login'), $html);
        $this->assertStringNotContainsString(route('auth.register'), $html);
    }
}
