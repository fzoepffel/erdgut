<?php

namespace Tests\Feature\Marketing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AboutPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_about_page_renders_story_values_faq_and_ctas(): void
    {
        $response = $this->get(route('public.about'));

        $response->assertOk();
        // New editorial "almanac" copy: manifesto, how-it-works, principles, FAQ.
        $response->assertSee('The Erdgut almanac', false);
        $response->assertSee('A short book about one weekly box from Berlin.', false);
        $response->assertSee('Why we started Erdgut.', false);
        $response->assertSee('How a box is put together.', false);
        $response->assertSee('Four principles we don', false);
        $response->assertSee('t trade away.', false);
        $response->assertSee('Seasonal first.', false);
        $response->assertSee('A small circle of hands.', false);
        $response->assertSee('Short answers, out loud.', false);
        $response->assertSee('Where do you deliver?', false);

        $response->assertSee(route('auth.register'), false);
        $response->assertSee(route('public.contact'), false);
        $response->assertSee(route('public.producers'), false);

        $response->assertSee('data-testid="about-founder-letter"', false);
        $response->assertSee('data-testid="about-how-steps"', false);
        $response->assertSee('data-testid="about-values-row"', false);
        $response->assertSee('data-testid="about-producers-pitch"', false);
        $response->assertSee('data-testid="about-faq-accordion"', false);
        $response->assertSee('data-testid="about-bottom-ctas"', false);
        $response->assertSee('data-testid="about-bottom-register"', false);
        $response->assertSee('data-testid="about-bottom-contact"', false);
        $response->assertSee('id="faq"', false);
    }
}
