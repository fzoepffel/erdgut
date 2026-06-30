<?php

namespace Tests\Feature\Errors;

use Tests\TestCase;

class SessionExpiredPageTest extends TestCase
{
    public function test_419_view_renders_with_back_to_shop_and_no_login(): void
    {
        app()->setLocale('en');

        $response = $this->view('errors.419');

        $response->assertSee('Session expired');
        // Shop header + a neutral "Back to shop" CTA — never the box-area
        // login link the parked account surface would expose.
        $response->assertSee('data-testid="customer-header-shop"', false);
        $response->assertSee(route('public.shop.index'));
        $response->assertDontSee(route('auth.login'));
    }

    public function test_419_view_renders_in_german(): void
    {
        app()->setLocale('de');

        $response = $this->view('errors.419');

        $response->assertSee('Sitzung abgelaufen');
        $response->assertSee('Zurück zum Hofladen');
        $response->assertDontSee('melde dich erneut an');
    }
}
