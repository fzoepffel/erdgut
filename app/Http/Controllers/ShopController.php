<?php

namespace App\Http\Controllers;

use App\Enums\BoxStatus;
use App\Enums\ProductCategory;
use App\Models\Box;
use App\Models\Producer;
use App\Models\Product;
use App\Support\BudgetRemainderService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ShopController extends Controller
{
    /**
     * Show the product catalog for extras shopping.
     */
    public function index(Request $request): View
    {
        abort_unless(config('features.shop_extras_enabled', true), 404);

        $validated = $request->validate([
            'category' => ['nullable', 'string', 'in:'.implode(',', ProductCategory::values())],
            'producer' => ['nullable', 'integer', 'exists:producers,id'],
        ]);

        $query = Product::query()
            ->visibleInCatalog()
            ->with('producer');

        if (! empty($validated['category'])) {
            $query->where('category', $validated['category']);
        }

        if (! empty($validated['producer'])) {
            $query->where('producer_id', $validated['producer']);
        }

        $products = $query->orderBy('name')->paginate(24)->withQueryString();

        $producers = Producer::query()->orderBy('name')->get(['id', 'name']);

        $currentBox = $this->getCurrentEditableBox($request->user());
        $budgetRemainder = $currentBox && $request->user()
            ? app(BudgetRemainderService::class)->getRemainder($request->user(), $currentBox)
            : null;

        return view('shop.index', [
            'products' => $products,
            'producers' => $producers,
            'categories' => ProductCategory::cases(),
            'currentBox' => $currentBox,
            'budgetRemainder' => $budgetRemainder,
        ]);
    }

    /**
     * Show the product detail page with storytelling fields.
     */
    public function show(Request $request, Product $product): View
    {
        abort_unless(config('features.shop_extras_enabled', true), 404);

        if (! $product->is_available || ! $product->is_published) {
            throw new NotFoundHttpException;
        }

        $product->loadMissing('producer');

        $currentBox = $this->getCurrentEditableBox($request->user());
        $budgetRemainder = $currentBox && $request->user()
            ? app(BudgetRemainderService::class)->getRemainder($request->user(), $currentBox)
            : null;

        return view('shop.show', [
            'product' => $product,
            'currentBox' => $currentBox,
            'budgetRemainder' => $budgetRemainder,
        ]);
    }

    private function getCurrentEditableBox($user): ?Box
    {
        if ($user === null) {
            return null;
        }

        $box = Box::query()
            ->where('user_id', $user->id)
            ->whereDate('week_start_date', '<=', now()->toDateString())
            ->whereDate('week_start_date', '>=', now()->subDays(6)->toDateString())
            ->orderByDesc('week_start_date')
            ->first();

        if (! $box instanceof Box) {
            return null;
        }

        $box->loadMissing('items');

        $deadline = $box->modification_deadline_at;
        if ($box->status !== BoxStatus::Editable || $deadline === null || ! $deadline->isFuture()) {
            return null;
        }

        return $box;
    }
}
