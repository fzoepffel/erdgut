<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Marketing\ProducerController;
use App\Http\Controllers\ProductController;
use App\Models\Producer;
use App\Models\Product;
use App\Services\Shop\ShopOrderService;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Catalog detail pages for the isolated preview shop.
 *
 * Mirrors the public {@see ProductController::show} and
 * {@see ProducerController::show}, but renders
 * them inside the self-contained {@see resources/views/components/shop-layout}
 * chrome (no marketing header/footer, no outbound nav into the rest of the
 * site). The body markup is shared via the same Blade partials; only the
 * wrapping layout and the internal link route names differ.
 */
class ShopCatalogController extends Controller
{
    public function __construct(private readonly ShopOrderService $service) {}

    public function product(Product $product): View
    {
        if (! $product->is_available || ! $product->is_published) {
            throw new NotFoundHttpException;
        }

        $product->loadMissing([
            'producer.translations',
            'translations',
            'images',
            'pairings' => fn ($q) => $q->visibleInCatalog()->with(['translations', 'producer.translations', 'images']),
        ]);

        return view('shop.product', [
            'product' => $product,
            'shopCta' => $this->service->productAvailabilityCta($product),
            'title' => $product->name.' · '.__('product.detail.eyebrow').' · '.config('app.name', 'Erdgut').' '.__('shop.eyebrow'),
        ]);
    }

    public function producer(Producer $producer): View
    {
        $producer->load(['translations', 'products.translations']);

        $products = $producer->products
            ->filter(fn (Product $p): bool => $p->is_available && $p->is_published)
            ->filter(fn (Product $p): bool => $p->name !== '')
            ->values();

        return view('shop.producer', [
            'producer' => $producer,
            'products' => $products,
            'shopCtas' => $products->mapWithKeys(fn (Product $p): array => [
                $p->id => $this->service->productAvailabilityCta($p),
            ]),
            'title' => $producer->name.' · '.__('producer.detail.eyebrow').' · '.config('app.name', 'Erdgut').' '.__('shop.eyebrow'),
        ]);
    }
}
