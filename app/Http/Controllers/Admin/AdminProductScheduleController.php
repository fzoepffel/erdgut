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
 * Renders inside the product edit page. Three POSTs: create a new
 * schedule, cancel an existing schedule from a chosen Monday, adjust
 * an existing schedule's quantity from a chosen Monday. All operations
 * delegate to {@see RecurringStockService} which handles materialization
 * and manual-override preservation.
 */
class AdminProductScheduleController extends Controller
{
    public function __construct(private readonly RecurringStockService $recurring) {}

    public function store(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $validated = $request->validate([
            'planned_quantity' => ['required', 'numeric', 'min:0.01', 'max:99999.99'],
            'starts_on_monday' => ['required', 'date'],
            'ends_on_monday' => ['nullable', 'date', 'after_or_equal:starts_on_monday'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->recurring->createSchedule(
            product: $product,
            plannedQuantity: (float) $validated['planned_quantity'],
            startsOnMonday: Carbon::parse($validated['starts_on_monday']),
            endsOnMonday: ! empty($validated['ends_on_monday']) ? Carbon::parse($validated['ends_on_monday']) : null,
            createdByUserId: (int) $request->user()->id,
            notes: $validated['notes'] ?? null,
        );

        return redirect()
            ->route('app.admin.products.edit', $product)
            ->with('status', __('admin.product_schedules.flash_created'));
    }

    public function cancel(Request $request, Product $product, ProductWeekStockSchedule $schedule): RedirectResponse
    {
        $this->authorize('update', $product);
        abort_if($schedule->product_id !== $product->id, 404);

        $validated = $request->validate([
            'from_monday' => ['required', 'date'],
        ]);

        $this->recurring->cancelFrom($schedule->id, Carbon::parse($validated['from_monday']));

        return redirect()
            ->route('app.admin.products.edit', $product)
            ->with('status', __('admin.product_schedules.flash_cancelled'));
    }

    public function adjust(Request $request, Product $product, ProductWeekStockSchedule $schedule): RedirectResponse
    {
        $this->authorize('update', $product);
        abort_if($schedule->product_id !== $product->id, 404);

        $validated = $request->validate([
            'from_monday' => ['required', 'date'],
            'new_quantity' => ['required', 'numeric', 'min:0.01', 'max:99999.99'],
        ]);

        $this->recurring->adjustFrom(
            scheduleId: $schedule->id,
            fromMonday: Carbon::parse($validated['from_monday']),
            newQuantity: (float) $validated['new_quantity'],
            createdByUserId: (int) $request->user()->id,
        );

        return redirect()
            ->route('app.admin.products.edit', $product)
            ->with('status', __('admin.product_schedules.flash_adjusted'));
    }
}
