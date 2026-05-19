<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminInventoryInflowStoreRequest;
use App\Models\Product;
use App\Models\ProductStockInflow;
use App\Services\Admin\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Daily-inventory admin page — the post-Phase-2 replacement for the
 * "Wochenkatalog".
 *
 * `index()` renders a 14-day window starting from a chosen date.
 * `storeInflow()` records one ad-hoc receipt; `destroyInflow()`
 * removes one. Recurring schedules live on a sibling controller
 * (`AdminInflowScheduleController`) so the routes stay tidy.
 */
class AdminInventoryController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function index(Request $request): View
    {
        $this->authorize('admin');

        $from = $request->query('from');
        $start = ($from !== null && $from !== '')
            ? Carbon::parse((string) $from)->startOfDay()
            : Carbon::today();

        // 14-day window, inclusive on both ends.
        $end = $start->copy()->addDays(13);

        // Products surfaced on the page: anything visible-in-catalog,
        // plus anything with an inflow row in this window even if not
        // currently published, so admin can see lingering inventory
        // for an unpublished product.
        $products = Product::query()
            ->with(['translations', 'producer.translations'])
            ->where(function ($q) use ($start, $end): void {
                $q->where(function ($vis): void {
                    $vis->where('is_available', true)->where('is_published', true);
                })->orWhereHas('stockInflows', function ($iq) use ($start, $end): void {
                    $iq->whereBetween('received_on', [$start->toDateString(), $end->toDateString()]);
                });
            })
            ->orderBy('id')
            ->get();

        // Build the per-product window in one pass each. Keys are
        // Y-m-d strings so the Blade can lookup by date in the matrix.
        $availabilityMatrix = $products->mapWithKeys(function (Product $p) use ($start, $end): array {
            return [$p->id => $this->inventory->availabilityWindow($p, $start, $end)];
        });

        // Inflow batches in the window — used for section A's table.
        $inflows = ProductStockInflow::query()
            ->whereBetween('received_on', [$start->toDateString(), $end->toDateString()])
            ->with(['product.translations', 'sourceSchedule'])
            ->orderBy('received_on')
            ->get();

        return view('admin.inventory.index', [
            'start' => $start,
            'end' => $end,
            'previousWindow' => $start->copy()->subDays(14)->toDateString(),
            'nextWindow' => $start->copy()->addDays(14)->toDateString(),
            'today' => Carbon::today()->toDateString(),
            'products' => $products,
            'availabilityMatrix' => $availabilityMatrix,
            'inflows' => $inflows,
        ]);
    }

    public function storeInflow(AdminInventoryInflowStoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $product = Product::query()->findOrFail((int) $validated['product_id']);
        $this->authorize('update', $product);

        $unit = $product->unit;
        $displayUnit = $product->price_display_unit ?? $unit?->defaultDisplayUnit();
        $factor = ($unit !== null && $displayUnit !== null)
            ? $unit->canonicalUnitsPerDisplay($displayUnit)
            : 1;

        $this->inventory->recordInflow(
            product: $product,
            receivedOn: Carbon::parse($validated['received_on']),
            quantity: round((float) $validated['quantity'] * $factor, 4),
            freshnessWindowDays: $validated['freshness_window_days'] ?? null,
            sourceScheduleId: null,
            notes: $validated['notes'] ?? null,
            createdByUserId: (int) $request->user()->id,
        );

        return $this->redirectToInventory($request, $validated['received_on'], __('admin.inventory.flash_inflow_created'));
    }

    public function destroyInflow(Request $request, ProductStockInflow $inflow): RedirectResponse
    {
        $this->authorize('update', $inflow->product);

        $receivedOn = $inflow->received_on?->toDateString();

        $inflow->delete();

        return $this->redirectToInventory($request, $receivedOn, __('admin.inventory.flash_inflow_deleted'));
    }

    private function redirectToInventory(Request $request, ?string $contextDate, string $flash): RedirectResponse
    {
        $target = $request->input('_redirect');
        if (! is_string($target) || $target === '') {
            $target = route('app.admin.inventory.index', $contextDate ? ['from' => $contextDate] : []);
        }

        return redirect($target)->with('status', $flash);
    }
}
