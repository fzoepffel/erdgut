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

        return view('products.index', [
            'products' => $products,
            'activeCategory' => $activeCategory,
            'categories' => ProductCategory::casesSortedByLabel(),
        ]);
    }

    public function show(Product $product): View
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

        return view('products.show', ['product' => $product]);
    }
}
