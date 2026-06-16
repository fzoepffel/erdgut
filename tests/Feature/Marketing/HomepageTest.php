<?php

namespace Tests\Feature\Marketing;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_renders_shop_landing_page(): void
    {
        $response = $this->get(route('public.home'));

        $response->assertOk();
        // The root URL now serves the shop-first landing page (the
        // pre-launch coming-soon holding page is retired / unrouted).
        $response->assertDontSee('data-testid="coming-soon"', false);

        // Shop landing hero + sections.
        $response->assertSee('Seasonal food from small farms, delivered across Berlin.', false);
        $response->assertSee('data-testid="shop-home-hero-ctas"', false);
        $response->assertSee('data-testid="shop-home-founder-letter"', false);
        $response->assertSee('data-testid="shop-home-chapter-strip"', false);
        $response->assertSee('data-testid="shop-home-producers-pitch"', false);
        $response->assertSee('images/about/producers-circle.jpg', false);
        $response->assertSee('A small circle of hands.', false);
        $response->assertDontSee('data-testid="shop-home-social-proof"', false);
        $response->assertDontSee('id="shop-home-producer-quote-heading"', false);
        $response->assertSee('data-testid="shop-home-how-it-works"', false);

        // Removed sections must not appear.
        $response->assertDontSee('data-testid="shop-home-promo-section"', false);
        $response->assertDontSee('data-testid="shop-home-value-cards"', false);

        // Primary CTAs point at the shop, not the box onboarding flow.
        $response->assertSee('data-testid="shop-home-cta-shop"', false);
        $response->assertSee(route('public.shop.index'), false);
        $response->assertSee(route('public.shop-about'), false);
        $response->assertDontSee('data-testid="home-cta-try-box"', false);
        $response->assertDontSee('data-testid="home-cta-subscribe"', false);
    }

    public function test_marketing_home_renders_for_guest_with_primary_and_secondary_ctas(): void
    {
        $response = $this->get(route('public.marketing-home'));

        $response->assertOk();
        // New editorial hero + founder letter + chapter strip copy.
        $response->assertSee('Weekly from small farms, in the rhythm of the seasons.', false);
        $response->assertSee('Why we started Erdgut.', false);
        $response->assertSee('Seven chapters, one season.', false);
        $response->assertSee(route('public.about'), false);
        $response->assertSee(route('auth.login'), false);
        $response->assertSee(route('app.onboarding.start', ['intent' => 'trial']), false);
        $response->assertSee(route('app.onboarding.start', ['intent' => 'subscription']), false);

        $response->assertSee('data-testid="home-cta-try-box"', false);
        $response->assertSee('data-testid="home-cta-subscribe"', false);
        $response->assertSee('data-testid="home-secondary-cta-login"', false);
        $response->assertSee('data-testid="home-founder-letter"', false);
        $response->assertSee('data-testid="home-chapter-strip"', false);
        $response->assertSee('data-testid="home-how-it-works"', false);
        $response->assertSee('data-testid="home-social-proof"', false);
        $response->assertSee('data-testid="home-value-cards"', false);
        $response->assertSee('data-testid="home-bottom-primary-guest"', false);
    }

    public function test_marketing_home_renders_for_authenticated_user_with_dashboard_and_subscription_links(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('public.marketing-home'));

        $response->assertOk();
        $response->assertSee(route('app.dashboard'), false);
        $response->assertSee(route('app.onboarding.start', ['intent' => 'trial']), false);
        $response->assertSee(route('app.onboarding.start', ['intent' => 'subscription']), false);
        $response->assertSee('data-testid="home-primary-cta-authenticated"', false);
        $response->assertSee('data-testid="home-cta-try-box"', false);
        $response->assertSee('data-testid="home-cta-subscribe"', false);
        $response->assertSee('data-testid="home-bottom-primary-authenticated"', false);
    }

    public function test_shop_landing_header_carries_shop_nav_without_account_chrome(): void
    {
        $response = $this->get(route('public.home'));

        $response->assertOk();
        // Shop-focused header: Startseite / Über uns / Höfe / Produkte /
        // Hofladen / Kontakt.
        $response->assertSee('data-testid="customer-header-shop"', false);
        $response->assertSee('href="'.route('public.home').'"', false);
        $response->assertSee('href="'.route('public.shop-about').'"', false);
        $response->assertSee('href="'.route('public.shop.producers.index').'"', false);
        $response->assertSee('href="'.route('public.shop.products.index').'"', false);
        $response->assertSee('href="'.route('public.shop.index').'"', false);
        $response->assertSee('href="'.route('public.shop-contact').'"', false);

        // The shop surface must not expose sign-up / sign-in (box-only for now).
        $response->assertDontSee('href="'.route('auth.login').'"', false);
        $response->assertDontSee('href="'.route('auth.register').'"', false);
    }

    public function test_empty_category_chapters_show_coming_soon_and_dont_link(): void
    {
        // A produce product exists; meat does not.
        $product = Product::factory()->create([
            'category' => 'produce',
            'is_available' => true,
            'is_published' => true,
        ]);

        $response = $this->get(route('public.home'));

        $response->assertOk();
        // Produce has stock -> live "Open chapter" link to its category.
        $response->assertSee('href="'.route('public.shop.products.index', ['category' => 'produce']).'"', false);
        $response->assertDontSee('data-testid="shop-home-chapter-comingsoon-produce"', false);
        // Meat is empty -> coming-soon marker, no link to the meat category.
        $response->assertSee('data-testid="shop-home-chapter-comingsoon-meat"', false);
        $response->assertDontSee('href="'.route('public.shop.products.index', ['category' => 'meat']).'"', false);
        // Producers and Almanac chapters are removed from the landing page strip.
        $response->assertDontSee('shop-home-chapter-tile-producers', false);
        $response->assertDontSee('shop-home-chapter-tile-almanach', false);
    }

    public function test_box_marketing_home_keeps_account_header(): void
    {
        $response = $this->get(route('public.marketing-home'));

        $response->assertOk();
        // The parked box surface keeps the full account-aware header.
        $response->assertSee('data-testid="customer-header-marketing"', false);
        $response->assertSee('href="'.route('auth.login').'"', false);
        $response->assertSee('href="'.route('auth.register').'"', false);
        // Its nav links to the box pages, not the shop landing.
        $response->assertSee('href="'.route('public.marketing-home').'"', false);
        $response->assertSee('href="'.route('public.about').'"', false);
    }

    public function test_shop_about_page_renders(): void
    {
        $response = $this->get(route('public.shop-about'));

        $response->assertOk();
        $response->assertSee('data-testid="shop-about-founder-letter"', false);
        $response->assertSee('data-testid="shop-about-founder-image"', false);
        $response->assertSee('images/about/founder-letter.jpg', false);
        $response->assertSee('data-testid="shop-about-model"', false);
        $response->assertSee('data-testid="shop-about-values-row"', false);
        $response->assertSee('data-testid="shop-about-faq-accordion"', false);
        $response->assertSee('Food should never be anonymous.', false);
        $response->assertSee('Box subscription is also on its way', false);
        $response->assertDontSee('data-testid="shop-about-how-steps"', false);
        $response->assertDontSee('data-testid="shop-about-producers-pitch"', false);
        $response->assertDontSee('How an order reaches you.', false);
        $response->assertDontSee('A small circle of hands.', false);
        $response->assertSee(route('public.shop.index'), false);
        // Apostrophes are single-escaped (no double-escaped &amp;#039;).
        $response->assertDontSee('amp;#039;', false);
    }

    public function test_shop_pages_are_localized_in_german(): void
    {
        // Persist the German locale into the session the same way the
        // language selector does, so the request renders in German.
        $this->get(route('public.locale.switch', 'de'));

        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('Saisonale Lebensmittel von kleinen Höfen, geliefert in ganz Berlin.', false)
            ->assertDontSee('Seasonal food from small farms, delivered across Berlin.', false);

        $this->get(route('public.shop-about'))
            ->assertOk()
            ->assertSee('Vier Prinzipien, bei denen wir keine Abstriche machen.', false);
    }
}
