<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ShopOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Support\PickupLocations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Product-by-product packing for a delivery day's shop orders.
 *
 * The team picks one product, drops it into every order that ordered it, then
 * moves to the next — so the screen is grouped by product, each carrying the
 * orders (lines) that need it. Packing is tracked per
 * {@see ShopOrderItem::$packed_at}; an order is "ready" once all its lines are
 * packed (fulfilment stays a manual step on the order page).
 */
class AdminPackingController extends Controller
{
    /** Orders to pack on a day: finalized, not yet handed over. */
    private function packableQuery(string $date)
    {
        return ShopOrder::query()
            ->finalized()
            ->where('status', '!=', ShopOrderStatus::Fulfilled->value)
            ->whereDate('expected_delivery_date', $date);
    }

    public function index(Request $request): View
    {
        $this->authorize('admin');

        $date = $this->resolveDate((string) $request->query('date', ''));

        $orders = $this->packableQuery($date->toDateString())
            ->with(['items.product.translations', 'items.product.producer.translations', 'items.variant'])
            ->orderBy('id')
            ->get();

        return view('admin.shop-orders.packing', [
            'date' => $date,
            'prevDate' => $date->copy()->subDay()->toDateString(),
            'nextDate' => $date->copy()->addDay()->toDateString(),
            'products' => $this->buildProducts($orders),
            'orders' => $this->buildOrders($orders),
        ]);
    }

    public function toggleItem(Request $request, ShopOrderItem $item): JsonResponse
    {
        $this->authorize('admin');

        $order = $item->shopOrder;
        abort_unless($order !== null && $this->isPackable($order), 404);

        $item->forceFill([
            'packed_at' => $item->packed_at === null ? now() : null,
            'packed_by_user_id' => $item->packed_at === null ? $request->user()?->id : null,
        ])->save();

        return response()->json([
            'packed' => $item->packed_at !== null,
            'order' => $this->orderProgress($order->fresh()->load('items')),
        ]);
    }

    public function packProduct(Request $request): JsonResponse
    {
        $this->authorize('admin');

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'product_id' => ['required', 'integer'],
            'variant_id' => ['nullable', 'integer'],
        ]);

        $variantId = isset($validated['variant_id']) ? (int) $validated['variant_id'] : null;

        $orders = $this->packableQuery((string) Carbon::parse($validated['date'])->toDateString())
            ->with('items')
            ->get();

        $now = now();
        $touchedOrderIds = [];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $matches = (int) $item->product_id === (int) $validated['product_id']
                    && (int) ($item->variant_id ?? 0) === (int) ($variantId ?? 0);
                if ($matches && $item->packed_at === null) {
                    $item->forceFill(['packed_at' => $now, 'packed_by_user_id' => $request->user()?->id])->save();
                    $touchedOrderIds[$order->id] = true;
                }
            }
        }

        return response()->json([
            'orders' => $orders
                ->whereIn('id', array_keys($touchedOrderIds))
                ->map(fn (ShopOrder $o) => $this->orderProgress($o->fresh()->load('items')))
                ->values(),
        ]);
    }

    /** Whether an order is still in the packable set (finalized, not fulfilled). */
    private function isPackable(ShopOrder $order): bool
    {
        return $order->status !== ShopOrderStatus::Fulfilled
            && ($order->status === ShopOrderStatus::Paid
                || ($order->status === ShopOrderStatus::PendingPayment && $order->payment_method === 'in_person'));
    }

    /**
     * Group every order line of the day by product+grade — the picking unit.
     *
     * @param  Collection<int, ShopOrder>  $orders
     * @return array<int, array<string, mixed>>
     */
    private function buildProducts($orders): array
    {
        $groups = [];

        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $product = $item->product;
                $isCustom = $item->product_id === null;
                $variantId = $item->variant_id !== null ? (int) $item->variant_id : null;
                // Catalog lines group by product+grade; custom lines group by
                // their name so identically-named ad-hoc items pool together.
                $key = $isCustom
                    ? 'c-'.md5(mb_strtolower((string) $item->name_snapshot))
                    : $item->product_id.'-'.($variantId ?? 0);

                if (! isset($groups[$key])) {
                    $name = $item->displayName()
                        .($item->variant_name_snapshot ? ' · '.$item->variant_name_snapshot : '');
                    $groups[$key] = [
                        'key' => $key,
                        'product_id' => (int) ($item->product_id ?? 0),
                        'variant_id' => $variantId,
                        'is_custom' => $isCustom,
                        'name' => $name,
                        'producer' => $product?->producer?->name ?? '',
                        'unit' => $product,
                        'custom_unit' => $isCustom ? (string) ($item->unit ?? '') : '',
                        'total_qty' => 0.0,
                        'lines' => [],
                        'packed_count' => 0,
                    ];
                }

                $groups[$key]['total_qty'] += (float) $item->quantity;
                $packed = $item->packed_at !== null;
                if ($packed) {
                    $groups[$key]['packed_count']++;
                }
                $groups[$key]['lines'][] = [
                    'item_id' => $item->id,
                    'order_id' => $order->id,
                    'customer' => $order->name ?? '—',
                    'qty_label' => $item->displayQuantityLabel(),
                    'fulfillment' => $this->fulfillmentLabel($order),
                    'window' => $order->hasDeliveryWindow()
                        ? substr((string) $order->delivery_window_from, 0, 5).'–'.substr((string) $order->delivery_window_to, 0, 5)
                        : null,
                    'packed' => $packed,
                ];
            }
        }

        // Finalize: display-unit total, counts, sort unpacked-first then name.
        $out = array_map(function (array $g): array {
            $product = $g['unit'];
            if ($product !== null) {
                $g['total_qty_label'] = $product->displayQuantity($g['total_qty']);
            } else {
                // Custom group: sum the entered amounts + the free-text unit.
                $sum = rtrim(rtrim(number_format($g['total_qty'], 4, '.', ''), '0'), '.');
                $g['total_qty_label'] = trim($sum.' '.$g['custom_unit']);
            }
            $g['total_count'] = count($g['lines']);
            $g['done'] = $g['packed_count'] >= $g['total_count'];
            unset($g['unit'], $g['total_qty'], $g['custom_unit']);

            return $g;
        }, array_values($groups));

        usort($out, fn ($a, $b) => [$a['done'], $a['name']] <=> [$b['done'], $b['name']]);

        return $out;
    }

    /**
     * Per-order packing progress for the day's orders strip.
     *
     * @param  Collection<int, ShopOrder>  $orders
     * @return array<int, array<string, mixed>>
     */
    private function buildOrders($orders): array
    {
        return $orders->map(fn (ShopOrder $o) => array_merge(
            $this->orderProgress($o),
            ['customer' => $o->name ?? '—', 'fulfillment' => $this->fulfillmentLabel($o)],
        ))->values()->all();
    }

    /**
     * @return array{id: int, packed_count: int, total_count: int, ready: bool}
     */
    private function orderProgress(ShopOrder $order): array
    {
        $total = $order->items->count();
        $packed = $order->items->filter(fn (ShopOrderItem $i) => $i->packed_at !== null)->count();

        return [
            'id' => $order->id,
            'packed_count' => $packed,
            'total_count' => $total,
            'ready' => $total > 0 && $packed === $total,
        ];
    }

    private function fulfillmentLabel(ShopOrder $order): string
    {
        if ($order->fulfillment_mode === 'pickup') {
            $loc = PickupLocations::find($order->pickup_location);

            return (string) ($loc['name'] ?? __('admin.packing.pickup'));
        }

        $address = trim(($order->delivery_street ?? '').' '.($order->delivery_city ?? ''));

        return trim(__('admin.packing.delivery').($address !== '' ? ' · '.$address : ''));
    }

    private function resolveDate(string $date): Carbon
    {
        if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
            return Carbon::parse($date)->startOfDay();
        }

        // Default to the nearest upcoming day that has packable orders.
        $next = ShopOrder::query()
            ->finalized()
            ->where('status', '!=', ShopOrderStatus::Fulfilled->value)
            ->whereDate('expected_delivery_date', '>=', Carbon::today()->toDateString())
            ->orderBy('expected_delivery_date')
            ->value('expected_delivery_date');

        return $next !== null ? Carbon::parse($next)->startOfDay() : Carbon::today();
    }
}
