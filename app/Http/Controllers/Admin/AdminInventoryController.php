<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ShopStockAudience;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminInventoryInflowStoreRequest;
use App\Http\Requests\Admin\AdminInventoryInflowUpdateRequest;
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
            ->with(['translations', 'producer.translations', 'producers.translations', 'variants'])
            ->where(function ($q) use ($start, $end): void {
                $q->where(function ($vis): void {
                    $vis->where('is_available', true)->where('is_published', true);
                })->orWhereHas('stockInflows', function ($iq) use ($start, $end): void {
                    $iq->whereBetween('received_on', [$start->toDateString(), $end->toDateString()]);
                });
            })
            ->get()
            // Alphabetical by the localized display name (translations live in
            // a relation, so sorting happens after hydration).
            ->sortBy(fn (Product $p) => mb_strtolower($p->name), SORT_NATURAL)
            ->values();

        // Build the per-product window in one pass each. Keys are
        // Y-m-d strings so the Blade can lookup by date in the matrix.
        $availabilityMatrix = $products->mapWithKeys(function (Product $p) use ($start, $end): array {
            return [$p->id => $this->inventory->availabilityWindow($p, $start, $end)];
        });

        // Trade view of the same window: includes b2b_only batches. Where it
        // exceeds the consumer value, the matrix shows the extra as
        // gastro-reserved stock.
        $b2bAvailabilityMatrix = $products->mapWithKeys(function (Product $p) use ($start, $end): array {
            return [$p->id => $this->inventory->availabilityWindow($p, $start, $end, ShopStockAudience::BusinessTrade)];
        });

        // Per-grade lanes for products that have grades, so the matrix shows
        // each grade's own stock under the product total. productId =>
        // (variantKey => (iso => qty)); variantKey 0 = the default grade.
        $variantMatrix = $products
            ->filter(fn (Product $p): bool => $p->variants->isNotEmpty())
            ->mapWithKeys(fn (Product $p): array => [$p->id => $this->inventory->variantAvailabilityWindow($p, $start, $end)]);

        // Inflow batches in the window — used for section A's table.
        $inflows = ProductStockInflow::query()
            ->whereBetween('received_on', [$start->toDateString(), $end->toDateString()])
            ->with(['product.translations', 'product.producer.translations', 'product.producers.translations', 'product.variants', 'producer.translations', 'sourceSchedule'])
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
            'b2bAvailabilityMatrix' => $b2bAvailabilityMatrix,
            'variantMatrix' => $variantMatrix,
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

        $variantId = $this->resolveVariantId($product, $validated['variant_id'] ?? null);

        $this->inventory->recordInflow(
            product: $product,
            receivedOn: Carbon::parse($validated['received_on']),
            quantity: round((float) $validated['quantity'] * $factor, 4),
            freshnessWindowDays: $validated['freshness_window_days'] ?? null,
            sourceScheduleId: null,
            notes: $validated['notes'] ?? null,
            createdByUserId: (int) $request->user()->id,
            producerId: isset($validated['producer_id']) ? (int) $validated['producer_id'] : null,
            b2bOnly: (bool) ($validated['b2b_only'] ?? false),
            variantId: $variantId,
        );

        return $this->redirectToInventory($request, $validated['received_on'], __('admin.inventory.flash_inflow_created'));
    }

    public function updateInflow(AdminInventoryInflowUpdateRequest $request, ProductStockInflow $inflow): RedirectResponse
    {
        $this->authorize('update', $inflow->product);

        $product = $inflow->product;
        $unit = $product?->unit;
        $displayUnit = $product?->price_display_unit ?? $unit?->defaultDisplayUnit();
        $factor = ($unit !== null && $displayUnit !== null)
            ? $unit->canonicalUnitsPerDisplay($displayUnit)
            : 1;

        $validated = $request->validated();

        // `original_received_on` is intentionally NOT touched here —
        // it stays at whatever the materializer (or the recordInflow
        // path for one-offs) set, so the cron continues to recognise
        // this row as fulfilling its scheduled occurrence even after
        // an admin moves it to a different day.
        $inflow->fill([
            'received_on' => Carbon::parse($validated['received_on'])->startOfDay()->toDateString(),
            'variant_id' => $this->resolveVariantId($product, $validated['variant_id'] ?? null),
            'producer_id' => isset($validated['producer_id'])
                ? (int) $validated['producer_id']
                : ($inflow->producer_id ?? $product?->producer_id),
            'quantity' => round((float) $validated['quantity'] * $factor, 4),
            'freshness_window_days' => max(1, (int) (
                $validated['freshness_window_days']
                ?? $inflow->freshness_window_days
                ?? $product?->default_freshness_window_days
                ?? 1
            )),
            'b2b_only' => (bool) ($validated['b2b_only'] ?? false),
            'notes' => $validated['notes'] ?? null,
        ])->save();

        return $this->redirectToInventory($request, $validated['received_on'], __('admin.inventory.flash_inflow_updated'));
    }

    public function destroyInflow(Request $request, ProductStockInflow $inflow): RedirectResponse
    {
        $this->authorize('update', $inflow->product);

        $receivedOn = $inflow->received_on?->toDateString();

        $inflow->delete();

        return $this->redirectToInventory($request, $receivedOn, __('admin.inventory.flash_inflow_deleted'));
    }

    /**
     * Validate that a chosen grade belongs to the product (and is real),
     * returning its id or NULL for the default grade. Rejects a variant from
     * another product so an inflow can't be mis-attributed.
     */
    private function resolveVariantId(?Product $product, int|string|null $variantId): ?int
    {
        if ($variantId === null || $variantId === '' || $product === null) {
            return null;
        }

        $belongs = $product->variants()->whereKey((int) $variantId)->exists();
        abort_unless($belongs, 422, 'The selected grade does not belong to this product.');

        return (int) $variantId;
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
