<?php

namespace Tests\Feature\Marketing;

use App\Models\Producer;
use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Models\ProductTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Guards the homepage payload for slow phones: card images must be the
 * generated thumbnails, the hero video must not ship a <source> in the
 * initial HTML (hero-video.js attaches it on larger viewports only), and
 * decorative chapter art must be the small WebP files with lazy loading.
 */
class HomepagePerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-06-10 10:00:00'));
        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(null);
        parent::tearDown();
    }

    private function makeFeaturedProduct(string $imagePath): Product
    {
        $producer = Producer::factory()->create();
        $product = Product::factory()->create([
            'producer_id' => $producer->id,
            'category' => 'produce',
            'unit_price' => 4.50,
            'is_available' => true,
            'is_published' => true,
            'image_path' => $imagePath,
        ]);
        ProductTranslation::query()->where('product_id', $product->id)->update([
            'taste_profile' => str_repeat('Eine wunderbar aromatische Sorte aus der Region. ', 5),
        ]);
        Storage::disk('public')->put($imagePath, 'fake-bytes');
        ProductStockInflow::create([
            'product_id' => $product->id,
            'received_on' => '2026-06-11',
            'quantity' => 20,
            'freshness_window_days' => 3,
        ]);

        return $product->refresh();
    }

    public function test_in_season_card_serves_the_thumbnail_not_the_original(): void
    {
        $this->makeFeaturedProduct('products/featured.jpg');
        Storage::disk('public')->put('products/thumb_featured.jpg', 'fake-thumb-bytes');

        $response = $this->get(route('public.home'))->assertOk();

        $response->assertSee('data-testid="shop-home-in-season"', false);
        $response->assertSee('products/thumb_featured.jpg', false);
        // The multi-megabyte original must not be referenced by the card.
        $this->assertStringNotContainsString(
            'src="'.Storage::disk('public')->url('products/featured.jpg').'"',
            $response->getContent(),
        );
    }

    public function test_in_season_card_falls_back_to_the_original_without_thumbnail(): void
    {
        $this->makeFeaturedProduct('products/featured.jpg');

        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('data-testid="shop-home-in-season"', false)
            ->assertSee('products/featured.jpg', false);
    }

    public function test_hero_video_ships_without_a_source_element(): void
    {
        $response = $this->get(route('public.home'))->assertOk();

        // hero-video.js attaches the source on >=640px viewports; phones
        // only ever load the still image.
        $response->assertSee('data-video-src', false);
        $response->assertSee('preload="none"', false);
        $this->assertStringNotContainsString('<source', $response->getContent());
    }

    public function test_hero_still_image_sits_under_the_video(): void
    {
        $response = $this->get(route('public.home'))->assertOk();
        $html = $response->getContent();

        // The still is a real <img> that paints before the video arrives and
        // remains the hero when autoplay is blocked (iOS battery-saving mode).
        $response->assertSee('data-testid="shop-home-hero-still"', false);
        $response->assertSee('images/mock/hero/shop-hero-poster.jpg', false);

        // A `poster` attribute on the video would bring back the native play
        // overlay when autoplay is refused; the underlay replaces it.
        preg_match('/<video[^>]*>/', $html, $video);
        $this->assertNotEmpty($video);
        $this->assertStringNotContainsString('poster=', $video[0]);
    }

    public function test_chapter_art_is_webp_and_lazy(): void
    {
        $response = $this->get(route('public.home'))->assertOk();
        $html = $response->getContent();

        $response->assertSee('images/chapters/catalog-strip-cabbage.webp', false);
        $this->assertStringNotContainsString('catalog-strip-cabbage.png', $html);

        // Every chapter-strip illustration is below the fold and lazy.
        preg_match_all('/<img[^>]*catalog-strip-[^>]*>/', $html, $strips);
        $this->assertNotEmpty($strips[0]);
        foreach ($strips[0] as $tag) {
            $this->assertStringContainsString('loading="lazy"', $tag);
        }
    }

    public function test_reveal_content_is_not_hidden_server_side(): void
    {
        // The hidden start state is applied by reveal-observer.js only;
        // markup that ships `reveal-pending` would blank the page for
        // visitors whose JS never loads.
        $this->get(route('public.home'))
            ->assertOk()
            ->assertDontSee('reveal-pending', false);
    }
}
