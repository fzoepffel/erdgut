<?php

namespace Tests\Feature\Marketing;

use App\Models\OnboardingDraft;
use App\Models\Product;
use App\Models\User;
use App\Services\Onboarding\OnboardingDraftManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Locks three customer-facing changes (UX notes A1 + B1 + the new
 * "Produkte" header entry):
 *
 *  - The product catalogue is publicly reachable at /products and
 *    /products/{product} — no login required, marketing layout.
 *  - The marketing header lists "Products" alongside Home / About /
 *    Producers / Contact.
 *  - The homepage chapter tiles (1-5) link to the public products
 *    page with a category filter, not to the auth-gated /app/products
 *    route.
 *  - The onboarding wizard header no longer contains a logout button
 *    (removed because a single misclick during onboarding logged the
 *    customer out and the verified-middleware then blocked them from
 *    resuming).
 */
class PublicProductsAndOnboardingChromeTest extends TestCase
{
    use RefreshDatabase;

    private function makePublishedProduct(string $category = 'produce'): Product
    {
        // Product factory's `configure()` afterCreating hook already
        // seeds DE / EN / FR translations with random names — no manual
        // ProductTranslation::create call needed.
        return Product::factory()->create([
            'category' => $category,
            'is_available' => true,
            'is_published' => true,
        ]);
    }

    public function test_public_products_index_is_reachable_without_auth(): void
    {
        $this->makePublishedProduct();

        $response = $this->get(route('public.products.index'));

        $response->assertOk();
        $response->assertSee('data-testid="products-grid"', false);
        // Marketing-shell chrome is rendered (not the auth app shell).
        $response->assertSee('data-testid="customer-header-marketing"', false);
    }

    public function test_public_products_show_is_reachable_without_auth(): void
    {
        $product = $this->makePublishedProduct();
        $product->loadMissing(['translations', 'producer.translations']);

        $response = $this->get(route('public.products.show', $product));

        $response->assertOk();
        // Product's localized name (factory seeded it for the active
        // locale) should render on the detail page.
        $response->assertSee($product->name, false);
        $response->assertSee('data-testid="customer-header-marketing"', false);
    }

    public function test_public_products_index_filters_by_category(): void
    {
        // D6/D7/F8 (UX-review notes): chapter-aligned taxonomy.
        $veg = $this->makePublishedProduct('produce');
        $bread = $this->makePublishedProduct('bread');

        $response = $this->get(route('public.products.index', ['category' => 'bread']));

        $response->assertOk();
        $html = $response->getContent();

        // The bread product should appear; the produce one should not
        // (filter narrows the list).
        $this->assertStringContainsString('href="'.route('public.products.show', $bread).'"', $html);
        $this->assertStringNotContainsString('href="'.route('public.products.show', $veg).'"', $html);
    }

    public function test_marketing_header_lists_products_link(): void
    {
        $response = $this->get(route('public.marketing-home'));

        $response->assertOk();
        // The header pill "Products" links to the public catalogue.
        $response->assertSee('href="'.route('public.products.index').'"', false);
    }

    public function test_home_chapter_tiles_link_to_public_product_categories(): void
    {
        $response = $this->get(route('public.marketing-home'));

        $response->assertOk();
        $html = $response->getContent();

        // D6/D7/F8 (UX-review notes): the chapter strip points at the
        // canonical produce / fish / meat / eggs_and_dairy / bread
        // category values shared with the admin filter and the chip
        // labels.
        foreach (['produce', 'meat', 'fish', 'eggs_and_dairy', 'bread'] as $cat) {
            $this->assertStringContainsString(
                'href="'.route('public.products.index', ['category' => $cat]).'"',
                $html,
                "Chapter tile for {$cat} should point to public.products.index?category={$cat}."
            );
        }

        // The auth-gated routes for the same categories must not leak.
        $this->assertStringNotContainsString(
            route('app.products.index', ['category' => 'produce']),
            $html
        );
    }

    public function test_onboarding_wizard_header_has_no_logout_button(): void
    {
        // The /onboard/account show controller short-circuits to the
        // address step when an authenticated user hits it (account is
        // a no-op for already-authenticated visitors). Use the address
        // step to actually render the wizard chrome.
        $user = User::factory()->create(['email_verified_at' => now()]);
        OnboardingDraft::create([
            'user_id' => $user->id,
            'intent' => OnboardingDraftManager::INTENT_SUBSCRIPTION,
            'step' => OnboardingDraftManager::STEP_ADDRESS,
            'data' => ['account_completed' => true],
            'expires_at' => now()->addDays(7),
        ]);

        $response = $this->actingAs($user)->get(route('app.onboarding.step.address'));

        $response->assertOk();
        $html = $response->getContent();

        // The wizard variant of the customer header must NOT render the
        // logout form. Test for both the form element and the data-action
        // tag we apply on logout buttons.
        $this->assertStringContainsString('data-testid="customer-header-wizard"', $html);

        // Pull just the header section (between the wizard header opener
        // and the first </header>) to keep this assertion stable even if
        // a logout form appears later in the page body.
        $headerStart = strpos($html, 'data-testid="customer-header-wizard"');
        $headerEnd = strpos($html, '</header>', $headerStart);
        $headerHtml = substr($html, $headerStart, $headerEnd - $headerStart);

        $this->assertStringNotContainsString('data-action="logout"', $headerHtml);
        $this->assertStringNotContainsString('action="'.route('auth.logout').'"', $headerHtml);
    }
}
