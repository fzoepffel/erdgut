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

class HomepageInSeasonTest extends TestCase
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

    private function makeProduct(string $longDescription, ?string $imagePath, ?string $receivedOn): Product
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
        // Replace the factory's short taste_profile with a controlled one.
        ProductTranslation::query()->where('product_id', $product->id)->update([
            'taste_profile' => $longDescription,
        ]);
        if ($imagePath !== null) {
            Storage::disk('public')->put($imagePath, 'fake-bytes');
        }
        if ($receivedOn !== null) {
            ProductStockInflow::create([
                'product_id' => $product->id,
                'received_on' => $receivedOn,
                'quantity' => 20,
                'freshness_window_days' => 3,
            ]);
        }

        return $product->refresh();
    }

    public function test_qualifying_in_season_product_is_featured(): void
    {
        $long = str_repeat('Eine wunderbar aromatische Sorte aus der Region. ', 5); // >140 chars
        $product = $this->makeProduct($long, 'products/featured.jpg', '2026-06-11');

        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('data-testid="shop-home-in-season"', false)
            ->assertSee($product->name, false)
            ->assertSee(route('public.shop.products.show', $product), false)
            // The in-season eyebrow ("From this week's harvest") must not be
            // double-escaped (regression: section-heading props were passed via
            // interpolated attributes, escaping the apostrophe twice).
            ->assertDontSee('&amp;#039;', false);
    }

    public function test_in_season_section_renders_normalized_apostrophes_from_corrupted_db_text(): void
    {
        $corrupted = str_repeat(
            'Fraîches et noisettées avec une nette note d\\u0027asperge — l\\u0027épluchage est rarement nécessaire. ',
            3,
        );
        $product = $this->makeProduct($corrupted, 'products/asparagus.jpg', '2026-06-11');

        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('data-testid="shop-home-in-season"', false)
            // Blade escapes apostrophes in {{ }}; corruption must not leak as \u0027.
            ->assertSee('d&#039;asperge', false)
            ->assertDontSee('\\u0027', false);
    }

    public function test_product_without_image_is_not_featured(): void
    {
        $long = str_repeat('Eine wunderbar aromatische Sorte aus der Region. ', 5);
        $this->makeProduct($long, null, '2026-06-11');

        $this->get(route('public.home'))
            ->assertOk()
            ->assertDontSee('data-testid="shop-home-in-season"', false);
    }

    public function test_product_with_short_description_is_not_featured(): void
    {
        $this->makeProduct('Zu kurz.', 'products/short.jpg', '2026-06-11');

        $this->get(route('public.home'))
            ->assertOk()
            ->assertDontSee('data-testid="shop-home-in-season"', false);
    }

    public function test_product_out_of_window_is_not_featured(): void
    {
        $long = str_repeat('Eine wunderbar aromatische Sorte aus der Region. ', 5);
        // Inflow well outside the today-7..+7 window.
        $this->makeProduct($long, 'products/late.jpg', '2026-08-01');

        $this->get(route('public.home'))
            ->assertOk()
            ->assertDontSee('data-testid="shop-home-in-season"', false);
    }

    public function test_figures_in_founder_letter_carry_their_ctas(): void
    {
        $response = $this->get(route('public.home'))->assertOk();
        // The three facts live inside the founder-letter section.
        $response->assertSee('data-testid="shop-home-figures"', false);
        $response->assertSee('farms disappear every day in Germany', false);
        $response->assertSee('of crop varieties lost in the last century', false);
        $response->assertSee('of the food price reaches the producer', false);
        // Each fact opens a different door.
        $response->assertSee('Get to know our producers', false);
        $response->assertSee(route('public.shop.producers.index'), false);
        $response->assertSee('Browse the products', false);
        $response->assertSee(route('public.shop.products.index'), false);
        // The dropped facts from the old standalone band must be gone.
        $response->assertDontSee('EU farms lost since 2005', false);
        $response->assertDontSee('named producer behind every product', false);
    }

    public function test_hero_has_single_primary_cta_and_periodic_heading(): void
    {
        $response = $this->get(route('public.home'))->assertOk();
        // Hero has one primary plus a secondary catalogue link (not two buttons).
        $response->assertSee('Start your order', false);
        $response->assertSee('Or browse the full catalogue', false);
        // Heading now carries a trailing period.
        $response->assertSee('The people behind our food.', false);
    }
}
