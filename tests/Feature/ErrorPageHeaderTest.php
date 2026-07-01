<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Error pages must wear the shop header, not the marketing one — so an error
 * never hands visitors the parked box consumer sign-up. The shop header's
 * utility strip (Für Gastronomie + Log in) is fine; box sign-up is not.
 */
class ErrorPageHeaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_404_uses_the_shop_header_without_box_signup(): void
    {
        $response = $this->get('/a-route-that-does-not-exist');

        $response->assertNotFound();
        // Shop header variant, not the marketing one that carries the box CTAs.
        $response->assertSee('data-testid="customer-header-shop"', false);
        $response->assertDontSee('data-testid="customer-header-marketing"', false);
        // No path to the parked box consumer sign-up.
        $response->assertDontSee(route('auth.register'), false);
    }
}
