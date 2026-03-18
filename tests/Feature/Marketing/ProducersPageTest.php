<?php

namespace Tests\Feature\Marketing;

use Database\Seeders\ProducerSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProducersPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_producers_page_renders_producer_cards_with_origin_details(): void
    {
        $this->seed(ProducerSeeder::class);

        $response = $this->get(route('public.producers'));

        $response->assertOk();
        $response->assertSee('Meet the producers behind your weekly Erdgut box.', false);
        $response->assertSee('Hof Sonnenfeld', false);
        $response->assertSee('Region: Bayern', false);
        $response->assertSee('Familienbetrieb mit Fokus auf saisonales Freilandgemuese', false);
        $response->assertSee('sm:grid-cols-2', false);
        $response->assertSee('lg:grid-cols-3', false);
    }

    public function test_producers_page_shows_empty_state_when_no_producers_exist(): void
    {
        $response = $this->get(route('public.producers'));

        $response->assertOk();
        $response->assertSee('No producers available yet', false);
        $response->assertSee('We are currently preparing producer profiles', false);
    }
}
