<?php

namespace App\Http\Controllers;

use App\Models\Producer;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    /**
     * Show the product catalog for extras shopping.
     */
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'category' => ['nullable', 'string', 'in:vegetables,fruit,dairy,bakery'],
            'producer' => ['nullable', 'integer', 'exists:producers,id'],
        ]);

        $query = Product::query()
            ->where('is_available', true)
            ->with('producer');

        if (! empty($validated['category'])) {
            $query->where('category', $validated['category']);
        }

        if (! empty($validated['producer'])) {
            $query->where('producer_id', $validated['producer']);
        }

        $products = $query->orderBy('name')->paginate(24)->withQueryString();

        $producers = Producer::query()->orderBy('name')->get(['id', 'name']);

        return view('shop.index', [
            'products' => $products,
            'producers' => $producers,
        ]);
    }
}
