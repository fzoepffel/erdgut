<?php

namespace Tests\Feature\Localization;

use App\Models\User;
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
        // Headline is split: "Des légumes frais sont" + <span italic> "en route."
        $response->assertSee('Des produits fermiers de saison, livrés en circuit-court à Berlin.', false);
        $response->assertSee('data-current-locale="fr"', false);
    }

    public function test_switch_to_german_renders_german(): void
    {
        $this->get(route('public.locale.switch', 'de'));

        $this->assertSame('de', session('locale'));

        $response = $this->get(route('public.home'));

        $response->assertOk();
        // Headline is split: "Frisches Gemüse ist" + <span italic> "unterwegs."
        $response->assertSee('Saisonale Lebensmittel von kleinen Höfen, geliefert in ganz Berlin.', false);
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
        // Headline split into two parts in FR locale.
        $response->assertSee('Des produits fermiers de saison, livrés en circuit-court à Berlin.', false);
    }

    public function test_session_locale_takes_precedence_over_accept_language(): void
    {
        session(['locale' => 'en']);

        $response = $this->withHeaders([
            'Accept-Language' => 'fr-FR,fr;q=0.9',
        ])->get(route('public.home'));

        $response->assertOk();
        // EN locale: headline split.
        $response->assertSee('Seasonal food from small farms, delivered across Berlin.', false);
        // FR copy must not appear when session forces EN.
        $response->assertDontSee('Des produits fermiers de saison, livrés en circuit-court à Berlin.', false);
    }

    public function test_default_locale_is_english_without_session_or_header(): void
    {
        $response = $this->get(route('public.home'));

        $response->assertOk();
        // EN locale: headline is "Fresh produce is" + <span italic> "on its way."
        $response->assertSee('Seasonal food from small farms, delivered across Berlin.', false);
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

    public function test_switch_persists_locale_on_authenticated_user(): void
    {
        $user = User::factory()->create(['locale' => 'de']);

        $this->actingAs($user)
            ->get(route('public.locale.switch', 'fr'))
            ->assertRedirect();

        $this->assertSame('fr', $user->fresh()->locale,
            'Switching language as an authenticated user must update the persisted locale on the user record.');
    }

    public function test_authenticated_user_locale_takes_precedence_over_accept_language(): void
    {
        $user = User::factory()->create(['locale' => 'fr']);

        $response = $this->actingAs($user)
            ->withHeaders(['Accept-Language' => 'de-DE,de;q=0.9'])
            ->get(route('public.home'));

        $response->assertOk();
        // FR locale headline is split.
        $response->assertSee('Des produits fermiers de saison, livrés en circuit-court à Berlin.', false);
    }

    public function test_session_locale_still_wins_over_authenticated_user_locale(): void
    {
        // Explicit session choice (e.g., the language switcher in the
        // current session) overrides the persisted preference for that
        // session — useful for "I want this page in English temporarily".
        $user = User::factory()->create(['locale' => 'fr']);
        session(['locale' => 'en']);

        $response = $this->actingAs($user)->get(route('public.home'));

        $response->assertOk();
        // EN locale headline is split: first part + italic second part.
        $response->assertSee('Seasonal food from small farms, delivered across Berlin.', false);
    }
}
