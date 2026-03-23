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
        $response->assertSee('About Erdgut', false);
        $response->assertSee('A transparent bridge between Berlin households and regional organic food.', false);
        $response->assertSee('Our story', false);
        $response->assertSee('Why Berlin', false);
        $response->assertSee('Sourcing values', false);
        $response->assertSee('Seasonal first', false);
        $response->assertSee('Small team, local partners', false);
        $response->assertSee('Common questions', false);
        $response->assertSee('Where do you deliver?', false);

        $response->assertSee(route('auth.register'), false);
        $response->assertSee(route('public.contact'), false);
        $response->assertSee(route('public.producers'), false);

        $response->assertSee('data-testid="about-hero"', false);
        $response->assertSee('data-testid="about-cta-register"', false);
        $response->assertSee('data-testid="about-cta-contact"', false);
        $response->assertSee('data-testid="about-story-arc"', false);
        $response->assertSee('data-testid="about-values-row"', false);
        $response->assertSee('data-testid="about-faq-accordion"', false);
        $response->assertSee('data-testid="about-bottom-ctas"', false);
        $response->assertSee('id="faq"', false);
    }
}
