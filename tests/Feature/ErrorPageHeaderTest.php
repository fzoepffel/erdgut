<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Error pages must wear the shop header (no sign-in / sign-up / box-area nav) so
 * an error never hands consumers a route into the parked account surface. The
 * B2B login lives only on the Geschäftskunden page, never the shop header.
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
}
