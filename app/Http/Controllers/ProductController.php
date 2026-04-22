<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        $locale = app()->getLocale();
        $products = Product::query()
            ->select('products.*')
            ->join('product_translations as pt', function ($join) use ($locale): void {
                $join->on('pt.product_id', '=', 'products.id')->where('pt.locale', '=', $locale);
            })
            ->visibleInCatalog()
            ->with(['producer.translations', 'translations'])
            ->orderBy('pt.name')
            ->paginate(24);

        return view('products.index', compact('products'));
    }

    public function show(Product $product): View
    {
        if (! $product->is_available || ! $product->is_published) {
            throw new NotFoundHttpException;
        }

        $product->loadMissing(['producer.translations', 'translations']);

        return view('products.show', ['product' => $product]);
    }
}
