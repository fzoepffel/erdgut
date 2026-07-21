<?php

namespace App\Http\Controllers\Shop;

use App\Enums\ProductCategory;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Marketing\ProducerController;
use App\Http\Controllers\ProductController;
use App\Models\Producer;
use App\Models\Product;
use App\Services\Shop\ShopOrderService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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

    /**
     * Isolated product catalogue index — mirrors {@see ProductController::index}
     * but always renders the public-shell body inside shop chrome with shop
     * route names, never the auth/dashboard shell.
     */
    public function products(Request $request): View
    {
        $locale = app()->getLocale();
        $activeCategory = ProductCategory::tryFrom((string) $request->query('category', ''));

        $products = Product::query()
            ->select('products.*')
            ->join('product_translations as pt', function ($join) use ($locale): void {
                $join->on('pt.product_id', '=', 'products.id')->where('pt.locale', '=', $locale);
            })
            ->visibleInCatalog()
            ->when($activeCategory, fn ($q) => $q->where('products.category', $activeCategory->value))
            ->with(['producer.translations', 'producers.translations', 'translations'])
            ->orderBy('pt.name')
            ->paginate(24)
            ->withQueryString();

        return view('shop.products', [
            'products' => $products,
            'activeCategory' => $activeCategory,
            'categories' => ProductCategory::casesInDisplayOrder(),
            'title' => __('shop.nav_products').' · '.config('app.name', 'Erdgut').' '.__('shop.eyebrow'),
        ]);
    }

    /**
     * Isolated producer directory — mirrors {@see ProducerController::index}
     * data prep, rendered inside shop chrome with shop route names and no
     * marketing/auth CTAs.
     */
    public function producers(Request $request): View
    {
        $locale = app()->getLocale();

        $regions = Producer::query()
            ->select('pt.region')
            ->join('producer_translations as pt', function ($join) use ($locale): void {
                $join->on('pt.producer_id', '=', 'producers.id')->where('pt.locale', '=', $locale);
            })
            ->whereNotNull('pt.region')
            ->where('pt.region', '!=', '')
            ->distinct()
            ->orderBy('pt.region')
            ->pluck('pt.region')
            ->values();

        $regionInput = $request->input('region');
        $regionNormalized = ($regionInput !== null && $regionInput !== '') ? (string) $regionInput : null;
        $qInput = $request->input('q');
        $qNormalized = ($qInput !== null && trim((string) $qInput) !== '') ? trim((string) $qInput) : null;

        $regionRules = ['nullable', 'string', 'max:255'];
        if ($regions->isNotEmpty()) {
            $regionRules[] = Rule::in($regions->all());
        }

        $validated = validator(
            ['region' => $regionNormalized, 'q' => $qNormalized],
            ['region' => $regionRules, 'q' => ['nullable', 'string', 'max:100']],
        )->validate();

        $query = Producer::query()
            ->select('producers.*')
            ->join('producer_translations as pt', function ($join) use ($locale): void {
                $join->on('pt.producer_id', '=', 'producers.id')->where('pt.locale', '=', $locale);
            })
            ->with('translations')
            ->orderBy('pt.name');

        if (! empty($validated['region'])) {
            $query->where('pt.region', $validated['region']);
        }

        if (! empty($validated['q'])) {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($validated['q']));
            $pattern = '%'.$escaped.'%';
            $query->where(function ($q) use ($pattern) {
                $q->where('pt.name', 'like', $pattern)
                    ->orWhere('pt.description', 'like', $pattern);
            });
        }

        return view('shop.producers', [
            'producers' => $query->get(),
            'regions' => $regions,
            'filters' => [
                'region' => $validated['region'] ?? '',
                'q' => $validated['q'] ?? '',
            ],
            'hasAnyProducers' => Producer::query()->exists(),
            'filtersActive' => ! empty($validated['region']) || ! empty($validated['q']),
            'title' => __('shop.nav_producers').' · '.config('app.name', 'Erdgut').' '.__('shop.eyebrow'),
        ]);
    }

    public function product(Product $product): View
    {
        if (! $product->is_available || ! $product->is_published) {
            throw new NotFoundHttpException;
        }

        $product->loadMissing([
            'producer.translations',
            'producers.translations',
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
        $producer->load(['translations', 'products.translations', 'suppliedProducts.translations']);

        // All supplied products, not just primary ones.
        $products = $producer->allProducts()
            ->filter(fn (Product $p): bool => $p->is_available && $p->is_published)
            ->filter(fn (Product $p): bool => $p->name !== '')
            ->values();

        return view('shop.producer', [
            'producer' => $producer,
            'products' => $products,
            'shopCtas' => $this->service->productAvailabilityCtas($products),
            'title' => $producer->name.' · '.__('producer.detail.eyebrow').' · '.config('app.name', 'Erdgut').' '.__('shop.eyebrow'),
        ]);
    }
}
