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
        $products = Product::query()
            ->visibleInCatalog()
            ->with('producer')
            ->orderBy('name')
            ->paginate(24);

        return view('products.index', compact('products'));
    }

    public function show(Product $product): View
    {
        if (! $product->is_available || ! $product->is_published) {
            throw new NotFoundHttpException;
        }

        $product->loadMissing('producer');

        return view('products.show', ['product' => $product]);
    }
}
