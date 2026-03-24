<?php

namespace App\Http\Controllers;

use App\Enums\BoxStatus;
use App\Http\Requests\AddBoxItemRequest;
use App\Http\Requests\RemoveBoxItemRequest;
use App\Http\Requests\UpdateBoxItemQuantityRequest;
use App\Models\Box;
use App\Models\BoxItem;
use App\Models\Product;
use App\Support\BoxValueRecalculationService;
use App\Support\BudgetRemainderService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BoxController extends Controller
{
    public function current(Request $request): RedirectResponse
    {
        $currentBox = Box::query()
            ->where('user_id', $request->user()->id)
            ->whereDate('week_start_date', '<=', now()->toDateString())
            ->whereDate('week_start_date', '>=', now()->subDays(6)->toDateString())
            ->orderByDesc('week_start_date')
            ->first();

        if (! $currentBox instanceof Box) {
            return redirect()
                ->route('app.dashboard')
                ->with('status', 'current-box-missing');
        }

        return redirect()->route('app.boxes.show', $currentBox);
    }

    public function show(Request $request, Box $box, BudgetRemainderService $budgetRemainderService): View
    {
        abort_unless((int) $box->user_id === (int) $request->user()->id, 403);
        $box->loadMissing('items.product');
        $modificationDeadline = $box->modification_deadline_at;
        $canModifyBox = $box->status === BoxStatus::Editable && $modificationDeadline?->isFuture();
        $isEditModeRequested = $request->query('mode') === 'edit';
        $isEditModeActive = $isEditModeRequested && $canModifyBox;
        $isEditModeBlocked = $isEditModeRequested && ! $canModifyBox;
        $blockedReason = null;

        if ($isEditModeBlocked) {
            if ($box->status !== BoxStatus::Editable) {
                $blockedReason = 'box-edit-status-not-editable';
            } else {
                $blockedReason = 'box-edit-deadline-expired';
            }
        }

        $budgetDetails = $budgetRemainderService->getBudgetDetails($request->user(), $box);

        return view('boxes.show', [
            'box' => $box,
            'modificationDeadline' => $modificationDeadline,
            'canModifyBox' => $canModifyBox,
            'isEditModeRequested' => $isEditModeRequested,
            'isEditModeActive' => $isEditModeActive,
            'isEditModeBlocked' => $isEditModeBlocked,
            'blockedReason' => $blockedReason,
            'budgetDetails' => $budgetDetails,
            'shopExtrasEnabled' => (bool) config('features.shop_extras_enabled', true),
        ]);
    }

    public function updateItemQuantity(
        UpdateBoxItemQuantityRequest $request,
        Box $box,
        BoxItem $item,
        BoxValueRecalculationService $boxValueRecalculationService
    ): JsonResponse {
        $delta = (int) $request->validated('delta');
        $newQuantity = (float) $item->quantity + $delta;

        $item->quantity = $newQuantity;
        $item->save();

        $estimatedValue = $boxValueRecalculationService->recalculate($box);

        return response()->json([
            'quantity' => (float) $item->quantity,
            'unit' => $item->unit,
            'estimated_value' => $estimatedValue,
        ]);
    }

    public function addItem(
        AddBoxItemRequest $request,
        Box $box,
        BoxValueRecalculationService $boxValueRecalculationService
    ): JsonResponse {
        $productId = (int) $request->validated('product_id');
        $quantity = (float) $request->validated('quantity');

        $product = Product::findOrFail($productId);
        $unit = $product->metadata['unit'] ?? 'kg';

        $existingItem = $box->items()->where('product_id', $productId)->first();

        if ($existingItem instanceof BoxItem) {
            $existingItem->quantity = (float) $existingItem->quantity + $quantity;
            $existingItem->save();
            $itemId = $existingItem->id;
            $newQuantity = (float) $existingItem->quantity;
        } else {
            $newItem = $box->items()->create([
                'product_id' => $productId,
                'quantity' => $quantity,
                'unit' => $unit,
                'is_extra' => true,
            ]);
            $itemId = $newItem->id;
            $newQuantity = (float) $newItem->quantity;
        }

        $estimatedValue = $boxValueRecalculationService->recalculate($box);

        return response()->json([
            'estimated_value' => $estimatedValue,
            'item_id' => $itemId,
            'quantity' => $newQuantity,
        ]);
    }

    public function removeItem(
        RemoveBoxItemRequest $request,
        Box $box,
        BoxItem $item,
        BoxValueRecalculationService $boxValueRecalculationService
    ): JsonResponse {
        $removedItemId = $item->id;
        $item->delete();

        $estimatedValue = $boxValueRecalculationService->recalculate($box);

        return response()->json([
            'estimated_value' => $estimatedValue,
            'removed_item_id' => $removedItemId,
        ]);
    }
}
