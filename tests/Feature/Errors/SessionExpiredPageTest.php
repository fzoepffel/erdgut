<?php

namespace Tests\Feature\Errors;

use Tests\TestCase;

class SessionExpiredPageTest extends TestCase
{
    public function test_419_view_renders_with_login_link(): void
    {
        app()->setLocale('en');

        $response = $this->view('errors.419');

        $response->assertSee('Session expired');
        $response->assertSee(route('auth.login'));
    }

    public function test_419_view_renders_in_german(): void
    {
        app()->setLocale('de');

        $response = $this->view('errors.419');

        $response->assertSee('Sitzung abgelaufen');
        $response->assertSee('melde dich erneut an');
    }
}
