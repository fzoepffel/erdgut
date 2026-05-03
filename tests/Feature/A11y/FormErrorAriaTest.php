<?php

namespace Tests\Feature\A11y;

use Tests\TestCase;

class FormErrorAriaTest extends TestCase
{
    /**
     * When a form input fails validation, x-text-input must announce the error
     * to assistive tech via aria-invalid="true" + aria-describedby pointing at
     * the matching x-input-error list. This guards the auto-wired binding in
     * resources/views/components/text-input.blade.php.
     */
    public function test_login_with_validation_error_wires_input_to_error_list(): void
    {
        $response = $this
            ->from('/auth/login')
            ->post('/auth/login', ['email' => 'invalid', 'password' => '']);
        $response->assertRedirect('/auth/login');

        $follow = $this
            ->withSession($this->app['session']->all())
            ->get('/auth/login');

        $html = $follow->getContent();

        // Extract the email input tag, then assert it carries both aria attrs.
        // (Attribute order varies because Blade ComponentAttributeBag merges
        // them — match the whole tag, not a fixed sequence.)
        preg_match('/<input\b[^>]*\bname="email"[^>]*>/s', $html, $m);
        $emailInput = $m[0] ?? '';

        $this->assertStringContainsString(
            'aria-invalid="true"',
            $emailInput,
            'expected aria-invalid="true" on email input after failed validation'
        );
        $this->assertStringContainsString(
            'aria-describedby="email-error"',
            $emailInput,
            'expected aria-describedby="email-error" on email input after failed validation'
        );
        $this->assertMatchesRegularExpression(
            '/<ul[^>]*id="email-error"/',
            $html,
            'expected x-input-error to render id="email-error" so input can reference it'
        );
    }
}
