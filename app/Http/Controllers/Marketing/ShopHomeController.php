<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Shop\ShopOrderService;
use Illuminate\Contracts\View\View;

class ShopHomeController extends Controller
{
    public function __construct(private readonly ShopOrderService $shop) {}

    /**
     * Render the public shop landing page served at the root URL.
     *
     * This is the primary marketing front door now that Erdgut leads with
     * its shop. The box-centric homepage stays reachable at /home
     * (public.marketing-home) for the subscription product, and the
     * pre-launch "coming soon" holding page is retired (no route).
     */
    public function __invoke(): View
    {
        // Categories that actually have catalogue products. The landing
        // chapter strip flags the rest as "coming soon" (non-clickable)
        // so we don't link to empty category pages.
        $availableCategories = Product::query()
            ->visibleInCatalog()
            ->select('category')
            ->distinct()
            ->pluck('category')
            ->map(fn ($category) => $category instanceof \BackedEnum ? $category->value : (string) $category)
            ->all();

        return view('marketing.shop-home', [
            'availableCategories' => $availableCategories,
            // Real, in-season products to feature (empty → section hidden).
            'inSeasonProducts' => $this->shop->featuredInSeasonProducts(),
            // Search snippet: this is what Google shows for erd-gut.de.
            'title' => __('seo.home_title'),
            'description' => __('seo.home_description'),
        ]);
    }
}
