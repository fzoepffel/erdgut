<?php

namespace App\Http\Controllers;

use App\Enums\ProductCategory;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProductController extends Controller
{
    public function index(Request $request): View
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
            ->with(['producer.translations', 'translations'])
            ->orderBy('pt.name')
            ->paginate(24)
            ->withQueryString();

        // Logged-in users get the customer app shell (sidebar nav,
        // dashboard return path); guests get the marketing-shell version
        // (single canonical view, just different chrome). The auth-shell
        // route stays at /app/products for in-app links; the public-shell
        // route lives at /products so the marketing nav can reach it
        // without forcing visitors through login.
        $template = $request->user() !== null ? 'products.index' : 'products.public';

        return view($template, [
            'products' => $products,
            'activeCategory' => $activeCategory,
            'categories' => ProductCategory::casesSortedByLabel(),
        ]);
    }

    public function show(Request $request, Product $product): View
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

        $template = $request->user() !== null ? 'products.show' : 'products.show-public';

        return view($template, ['product' => $product]);
    }
}
