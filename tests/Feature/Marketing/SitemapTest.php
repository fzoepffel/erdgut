<?php

namespace Tests\Feature\Marketing;

use App\Models\Producer;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** /sitemap.xml lists the public surface; the homepage carries a real title. */
class SitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_sitemap_lists_static_pages_products_and_producers(): void
    {
        Cache::forget('sitemap.xml');
        $producer = Producer::factory()->create();
        $product = Product::factory()->create(['is_available' => true, 'is_published' => true]);
        $hidden = Product::factory()->create(['is_available' => true, 'is_published' => false]);

        $response = $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=utf-8');

        $xml = $response->getContent();
        $this->assertStringContainsString('<loc>'.route('public.home').'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.route('public.shop.index').'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.route('restaurant.landing').'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.route('public.products.show', $product).'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.route('public.producers.show', $producer).'</loc>', $xml);
        // Unpublished products stay out of the index.
        $this->assertStringNotContainsString(route('public.products.show', $hidden).'</loc>', $xml);
        // Private surfaces never appear.
        $this->assertStringNotContainsString('/app/', $xml);
    }

    public function test_homepage_carries_seo_title_and_description(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<title>'.__('seo.home_title').'</title>', false)
            ->assertSee(__('seo.home_description'));
    }
}
