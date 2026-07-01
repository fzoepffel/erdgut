<?php

namespace Tests\Feature\Errors;

use Tests\TestCase;

class SessionExpiredPageTest extends TestCase
{
    public function test_419_view_renders_with_back_to_shop(): void
    {
        app()->setLocale('en');

        $response = $this->view('errors.419');

        $response->assertSee('Session expired');
        // Shop header + a neutral "Back to shop" CTA. (The header's utility strip
        // carries a Log in link, which is fine — it is site-wide shop chrome.)
        $response->assertSee('data-testid="customer-header-shop"', false);
        $response->assertSee(route('public.shop.index'));
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
