<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Admin\ProductMergeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Merges a duplicate product into the one that stays. Two steps: a
 * confirmation page spelling out exactly what moves and what is
 * dropped, then the destructive POST. `$product` is always the LOSER
 * (the page lives on its edit screen); `winner_id` names the survivor.
 */
class AdminProductMergeController extends Controller
{
    public function __construct(private readonly ProductMergeService $service) {}

    public function confirm(Request $request, Product $product): View|RedirectResponse
    {
        $winner = $this->resolveWinner($request, $product);
        if ($winner instanceof RedirectResponse) {
            return $winner;
        }

        return view('admin.products.merge-confirm', [
            'loser' => $product,
            'winner' => $winner,
            'counts' => $this->service->counts($winner, $product),
        ]);
    }

    public function execute(Request $request, Product $product): RedirectResponse
    {
        $winner = $this->resolveWinner($request, $product);
        if ($winner instanceof RedirectResponse) {
            return $winner;
        }

        $loserName = (string) $product->name;

        try {
            $this->service->merge($winner, $product, $request->user()?->id);
        } catch (RuntimeException $e) {
            return redirect()
                ->route('app.admin.products.edit', $product)
                ->with('error', $e->getMessage());
        }

        return redirect()
            ->route('app.admin.products.edit', $winner)
            ->with('status', __('admin.products.merge.success', ['name' => $loserName]));
    }

    private function resolveWinner(Request $request, Product $loser): Product|RedirectResponse
    {
        $this->authorize('delete', $loser);

        $validated = $request->validate([
            'winner_id' => ['required', 'integer', 'exists:products,id'],
        ]);

        if ((int) $validated['winner_id'] === $loser->id) {
            return redirect()
                ->route('app.admin.products.edit', $loser)
                ->with('error', __('admin.products.merge.error_self'));
        }
        if (in_array($loser->id, ProductMergeService::protectedProductIds(), true)) {
            return redirect()
                ->route('app.admin.products.edit', $loser)
                ->with('error', __('admin.products.merge.error_protected'));
        }

        $winner = Product::query()->findOrFail((int) $validated['winner_id']);
        $this->authorize('update', $winner);

        return $winner;
    }
}
