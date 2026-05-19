<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductStockInflowSchedule;
use App\Services\Admin\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Recurring inflow schedules — the daily-inventory replacement for
 * `AdminProductScheduleController`. Three POSTs (store / cancel /
 * adjust) mirror the legacy controller's shape so the Blade forms
 * already in the codebase can be retargeted with minimal change.
 */
class AdminInflowScheduleController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function store(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $validated = $request->validate([
            'weekday' => ['required', 'integer', 'between:0,6'],
            'planned_quantity' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'freshness_window_days' => ['nullable', 'integer', 'between:1,60'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->inventory->createSchedule(
            product: $product,
            weekday: (int) $validated['weekday'],
            plannedQuantity: $this->canonicalQuantity($product, (float) $validated['planned_quantity']),
            startsOn: Carbon::parse($validated['starts_on']),
            endsOn: ! empty($validated['ends_on']) ? Carbon::parse($validated['ends_on']) : null,
            freshnessWindowDays: $validated['freshness_window_days'] ?? null,
            notes: $validated['notes'] ?? null,
            createdByUserId: (int) $request->user()->id,
        );

        return $this->redirectBack($request, __('admin.inflow_schedules.flash_created'));
    }

    public function cancel(Request $request, Product $product, ProductStockInflowSchedule $schedule): RedirectResponse
    {
        $this->authorize('update', $product);
        abort_if($schedule->product_id !== $product->id, 404);

        $validated = $request->validate([
            'from_date' => ['required', 'date'],
        ]);

        $this->inventory->cancelScheduleFrom($schedule->id, Carbon::parse($validated['from_date']));

        return $this->redirectBack($request, __('admin.inflow_schedules.flash_cancelled'));
    }

    public function adjust(Request $request, Product $product, ProductStockInflowSchedule $schedule): RedirectResponse
    {
        $this->authorize('update', $product);
        abort_if($schedule->product_id !== $product->id, 404);

        $validated = $request->validate([
            'from_date' => ['required', 'date'],
            'new_quantity' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
        ]);

        $this->inventory->adjustScheduleFrom(
            scheduleId: $schedule->id,
            fromDate: Carbon::parse($validated['from_date']),
            newQuantity: $this->canonicalQuantity($product, (float) $validated['new_quantity']),
            createdByUserId: (int) $request->user()->id,
        );

        return $this->redirectBack($request, __('admin.inflow_schedules.flash_adjusted'));
    }

    /**
     * Convert an admin-entered display-unit quantity (e.g. 10 kg) into
     * canonical storage units (10000 g) for the product.
     */
    private function canonicalQuantity(Product $product, float $displayQuantity): float
    {
        if ($product->unit === null) {
            return $displayQuantity;
        }

        $displayUnit = $product->price_display_unit ?? $product->unit->defaultDisplayUnit();

        return round($displayQuantity * $product->unit->canonicalUnitsPerDisplay($displayUnit), 4);
    }

    private function redirectBack(Request $request, string $flash): RedirectResponse
    {
        $target = $request->input('_redirect');
        if (! is_string($target) || $target === '') {
            $target = $request->headers->get('referer') ?: route('app.admin.inventory.index');
        }

        return redirect($target)->with('status', $flash);
    }
}
