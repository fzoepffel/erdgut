<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminAddBoxItemRequest;
use App\Http\Requests\Admin\AdminRemoveBoxItemRequest;
use App\Http\Requests\Admin\AdminUpdateBoxItemRequest;
use App\Models\Box;
use App\Models\BoxItem;
use App\Models\Product;
use App\Support\BoxValueRecalculationService;
use Illuminate\Http\RedirectResponse;

class AdminBoxItemController extends Controller
{
    public function store(
        AdminAddBoxItemRequest $request,
        Box $box,
        BoxValueRecalculationService $boxValueRecalculationService
    ): RedirectResponse {
        $this->authorize('admin');

        $productId = (int) $request->validated('product_id');
        $quantity = (float) $request->validated('quantity');

        $product = Product::findOrFail($productId);
        $unit = $product->metadata['unit'] ?? 'kg';

        $existingItem = $box->items()->where('product_id', $productId)->first();

        if ($existingItem instanceof BoxItem) {
            $existingItem->quantity = (float) $existingItem->quantity + $quantity;
            $existingItem->save();
        } else {
            $box->items()->create([
                'product_id' => $productId,
                'quantity' => $quantity,
                'unit' => $unit,
                'is_extra' => true,
            ]);
        }

        $boxValueRecalculationService->recalculate($box);

        return redirect()
            ->route('app.admin.boxes.show', $box)
            ->with('status', __('admin.box_items.flash_added'));
    }

    public function update(
        AdminUpdateBoxItemRequest $request,
        Box $box,
        BoxItem $item,
        BoxValueRecalculationService $boxValueRecalculationService
    ): RedirectResponse {
        $this->authorize('admin');

        $item->quantity = (float) $request->validated('quantity');
        $item->save();

        $boxValueRecalculationService->recalculate($box);

        return redirect()
            ->route('app.admin.boxes.show', $box)
            ->with('status', __('admin.box_items.flash_updated'));
    }

    public function destroy(
        AdminRemoveBoxItemRequest $request,
        Box $box,
        BoxItem $item,
        BoxValueRecalculationService $boxValueRecalculationService
    ): RedirectResponse {
        $this->authorize('admin');

        $item->delete();
        $boxValueRecalculationService->recalculate($box);

        return redirect()
            ->route('app.admin.boxes.show', $box)
            ->with('status', __('admin.box_items.flash_removed'));
    }
}
