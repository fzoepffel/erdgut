<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductWeekStockSchedule;
use App\Services\Admin\RecurringStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Admin CRUD for recurring weekly-stock schedules attached to a Product.
 *
 * The forms render inside the weekly catalog view (one expandable panel
 * per row). Three POSTs: create, cancel from a Monday, adjust from a
 * Monday. All operations delegate to {@see RecurringStockService}; we
 * redirect back to the page that posted the form so the admin lands
 * back on the weekly catalog (or product edit if they came from there).
 */
class AdminProductScheduleController extends Controller
{
    public function __construct(private readonly RecurringStockService $recurring) {}

    public function store(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $validated = $request->validate([
            'planned_quantity' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'starts_on_monday' => ['required', 'date'],
            'ends_on_monday' => ['nullable', 'date', 'after_or_equal:starts_on_monday'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->recurring->createSchedule(
            product: $product,
            plannedQuantity: $this->canonicalQuantity($product, (float) $validated['planned_quantity']),
            startsOnMonday: Carbon::parse($validated['starts_on_monday']),
            endsOnMonday: ! empty($validated['ends_on_monday']) ? Carbon::parse($validated['ends_on_monday']) : null,
            createdByUserId: (int) $request->user()->id,
            notes: $validated['notes'] ?? null,
        );

        return $this->redirectBack($request, $product, __('admin.product_schedules.flash_created'));
    }

    public function cancel(Request $request, Product $product, ProductWeekStockSchedule $schedule): RedirectResponse
    {
        $this->authorize('update', $product);
        abort_if($schedule->product_id !== $product->id, 404);

        $validated = $request->validate([
            'from_monday' => ['required', 'date'],
        ]);

        $this->recurring->cancelFrom($schedule->id, Carbon::parse($validated['from_monday']));

        return $this->redirectBack($request, $product, __('admin.product_schedules.flash_cancelled'));
    }

    public function adjust(Request $request, Product $product, ProductWeekStockSchedule $schedule): RedirectResponse
    {
        $this->authorize('update', $product);
        abort_if($schedule->product_id !== $product->id, 404);

        $validated = $request->validate([
            'from_monday' => ['required', 'date'],
            'new_quantity' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
        ]);

        $this->recurring->adjustFrom(
            scheduleId: $schedule->id,
            fromMonday: Carbon::parse($validated['from_monday']),
            newQuantity: $this->canonicalQuantity($product, (float) $validated['new_quantity']),
            createdByUserId: (int) $request->user()->id,
        );

        return $this->redirectBack($request, $product, __('admin.product_schedules.flash_adjusted'));
    }

    /**
     * Convert an admin-entered display-unit quantity (e.g. 10 kg) into
     * the canonical storage units (10000 g) for the product.
     */
    private function canonicalQuantity(Product $product, float $displayQuantity): float
    {
        if ($product->unit === null) {
            return $displayQuantity;
        }

        $displayUnit = $product->price_display_unit ?? $product->unit->defaultDisplayUnit();

        return round($displayQuantity * $product->unit->canonicalUnitsPerDisplay($displayUnit), 4);
    }

    /**
     * Send the admin back to whichever surface posted the form. The
     * preferred path is a `_redirect` hidden field on the form; fallback
     * is the HTTP referer; final fallback is the product edit page.
     */
    private function redirectBack(Request $request, Product $product, string $status): RedirectResponse
    {
        $target = $request->input('_redirect');
        if (! is_string($target) || $target === '') {
            $target = $request->headers->get('referer') ?: route('app.admin.products.edit', $product);
        }

        return redirect($target)->with('status', $status);
    }
}
