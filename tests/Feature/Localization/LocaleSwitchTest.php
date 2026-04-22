<?php

namespace Tests\Feature\Localization;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_switch_to_french_stores_locale_and_subsequent_request_renders_french(): void
    {
        $this->get(route('public.locale.switch', 'fr'))
            ->assertRedirect();

        $this->assertSame('fr', session('locale'));

        $response = $this->get(route('public.home'));

        $response->assertOk();
        $response->assertSee('Des légumes frais arrivent bientôt.', false);
        $response->assertSee('data-current-locale="fr"', false);
    }

    public function test_switch_to_german_renders_german(): void
    {
        $this->get(route('public.locale.switch', 'de'));

        $this->assertSame('de', session('locale'));

        $response = $this->get(route('public.home'));

        $response->assertOk();
        $response->assertSee('Frisches Gemüse ist unterwegs.', false);
        $response->assertSee('data-current-locale="de"', false);
    }

    public function test_unsupported_locale_is_rejected_without_mutating_session(): void
    {
        session(['locale' => 'de']);

        $response = $this->get(route('public.locale.switch', 'es'));

        $response->assertRedirect();
        $this->assertSame('de', session('locale'));
    }

    public function test_accept_language_header_selects_french_for_first_time_visitor(): void
    {
        $response = $this->withHeaders([
            'Accept-Language' => 'fr-FR,fr;q=0.9,en;q=0.8',
        ])->get(route('public.home'));

        $response->assertOk();
        $response->assertSee('Des légumes frais arrivent bientôt.', false);
    }

    public function test_session_locale_takes_precedence_over_accept_language(): void
    {
        session(['locale' => 'en']);

        $response = $this->withHeaders([
            'Accept-Language' => 'fr-FR,fr;q=0.9',
        ])->get(route('public.home'));

        $response->assertOk();
        $response->assertSee('Fresh produce is on its way.', false);
        $response->assertDontSee('Des légumes frais arrivent bientôt.', false);
    }

    public function test_default_locale_is_english_without_session_or_header(): void
    {
        $response = $this->get(route('public.home'));

        $response->assertOk();
        $response->assertSee('Fresh produce is on its way.', false);
    }

    public function test_selector_is_rendered_on_coming_soon_page(): void
    {
        $response = $this->get(route('public.home'));

        $response->assertOk();
        $response->assertSee('data-testid="language-selector"', false);
        $response->assertSee('data-locale="fr"', false);
        $response->assertSee('data-locale="de"', false);
        $response->assertSee('data-locale="en"', false);
    }
}
