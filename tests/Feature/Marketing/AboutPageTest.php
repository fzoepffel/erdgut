<?php

namespace Tests\Feature\Marketing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AboutPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_about_page_renders_story_sourcing_and_faq_teaser_sections(): void
    {
        $response = $this->get(route('public.about'));

        $response->assertOk();
        $response->assertSee('About Erdgut', false);
        $response->assertSee('A transparent bridge between Berlin households and local farms.', false);
        $response->assertSee('Our project story', false);
        $response->assertSee('Sourcing philosophy', false);
        $response->assertSee('FAQ teaser', false);
        $response->assertSee('sm:px-6', false);
        $response->assertSee('md:grid-cols-2', false);
        $response->assertSee('lg:px-8', false);
    }
}
