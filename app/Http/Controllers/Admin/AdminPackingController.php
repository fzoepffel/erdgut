<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RestaurantOrderStatus;
use App\Enums\ShopOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Producer;
use App\Models\RestaurantOrder;
use App\Models\RestaurantOrderItem;
use App\Models\ShopOrder;
use App\Models\ShopOrderItem;
use App\Services\Admin\ProducerAllocationService;
use App\Support\PickupLocations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Product-by-product packing for a delivery day — B2C shop orders AND B2B
 * restaurant orders on one screen (restaurant lines carry a B2B tag).
 *
 * The team picks one product, drops it into every order that ordered it, then
 * moves to the next — so the screen is grouped by product, each carrying the
 * orders (lines) that need it. Packing is tracked per item (`packed_at`); an
 * order is "ready" once all its lines are packed (fulfilment stays a manual
 * step on the order page). Item/order identifiers are namespaced tokens
 * ("s{id}" shop, "r{id}" restaurant) so the two models never collide.
 */
class AdminPackingController extends Controller
{
    /** Shop orders to pack on a day: finalized, not yet handed over. */
    private function packableShopQuery(string $date)
    {
        return ShopOrder::query()
            ->finalized()
            ->where('status', '!=', ShopOrderStatus::Fulfilled->value)
            ->whereDate('expected_delivery_date', $date);
    }

    /** Restaurant orders to pack: confirmed (firm), not yet delivered. */
    private function packableRestaurantQuery(string $date)
    {
        return RestaurantOrder::query()
            ->where('status', RestaurantOrderStatus::Confirmed->value)
            ->whereDate('expected_delivery_date', $date);
    }

    public function index(Request $request): View
    {
        $this->authorize('admin');

        $date = $this->resolveDate((string) $request->query('date', ''));

        $shopOrders = $this->packableShopQuery($date->toDateString())
            ->with(['items.product.translations', 'items.product.producer.translations', 'items.variant'])
            ->orderBy('id')
            ->get();
        $restaurantOrders = $this->packableRestaurantQuery($date->toDateString())
            ->with(['user', 'items.product.translations', 'items.product.producer.translations', 'items.variant'])
            ->orderBy('id')
            ->get();

        // Producer split labels are the same regardless of grouping, so resolve
        // them once and share between the by-product and by-client projections.
        $producerLabels = $this->producerLabels($shopOrders, $restaurantOrders);

        return view('admin.shop-orders.packing', [
            'date' => $date,
            'prevDate' => $date->copy()->subDay()->toDateString(),
            'nextDate' => $date->copy()->addDay()->toDateString(),
            'products' => $this->buildProducts($shopOrders, $restaurantOrders, $producerLabels),
            'clients' => $this->buildClients($shopOrders, $restaurantOrders, $producerLabels),
            'orders' => $this->buildOrders($shopOrders, $restaurantOrders),
        ]);
    }

    /** Toggle one line; `item` is a namespaced token ("s{id}" | "r{id}"). */
    public function toggleItem(Request $request, string $item): JsonResponse
    {
        $this->authorize('admin');

        if (str_starts_with($item, 'r')) {
            $line = RestaurantOrderItem::query()->findOrFail((int) substr($item, 1));
            $order = $line->restaurantOrder;
            abort_unless($order !== null && $order->status === RestaurantOrderStatus::Confirmed, 404);
        } else {
            $line = ShopOrderItem::query()->findOrFail((int) ltrim($item, 's'));
            $order = $line->shopOrder;
            abort_unless($order !== null && $this->isPackableShop($order), 404);
        }

        $line->forceFill([
            'packed_at' => $line->packed_at === null ? now() : null,
            'packed_by_user_id' => $line->packed_at === null ? $request->user()?->id : null,
        ])->save();

        return response()->json([
            'packed' => $line->packed_at !== null,
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
        $date = (string) Carbon::parse($validated['date'])->toDateString();

        $now = now();
        $touched = [];
        $pack = function ($orders) use ($validated, $variantId, $now, $request, &$touched): void {
            foreach ($orders as $order) {
                foreach ($order->items as $item) {
                    $matches = (int) $item->product_id === (int) $validated['product_id']
                        && (int) ($item->variant_id ?? 0) === (int) ($variantId ?? 0);
                    if ($matches && $item->packed_at === null) {
                        $item->forceFill(['packed_at' => $now, 'packed_by_user_id' => $request->user()?->id])->save();
                        $touched[$this->orderKey($order)] = $order;
                    }
                }
            }
        };

        $pack($this->packableShopQuery($date)->with('items')->get());
        $pack($this->packableRestaurantQuery($date)->with('items')->get());

        return response()->json([
            'orders' => collect($touched)
                ->map(fn ($o) => $this->orderProgress($o->fresh()->load('items')))
                ->values(),
        ]);
    }

    /**
     * Pack every remaining line of one order — the by-client analogue of
     * packProduct. `order` is a namespaced token ("s{id}" | "r{id}").
     */
    public function packOrder(Request $request): JsonResponse
    {
        $this->authorize('admin');

        $validated = $request->validate([
            'order' => ['required', 'string'],
        ]);

        $token = $validated['order'];
        $now = now();

        if (str_starts_with($token, 'r')) {
            $order = RestaurantOrder::query()->with('items')->findOrFail((int) substr($token, 1));
            abort_unless($order->status === RestaurantOrderStatus::Confirmed, 404);
            $items = $order->items->filter(fn (RestaurantOrderItem $i) => ! $i->isDeclined());
        } else {
            $order = ShopOrder::query()->with('items')->findOrFail((int) ltrim($token, 's'));
            abort_unless($this->isPackableShop($order), 404);
            $items = $order->items;
        }

        foreach ($items as $item) {
            if ($item->packed_at === null) {
                $item->forceFill(['packed_at' => $now, 'packed_by_user_id' => $request->user()?->id])->save();
            }
        }

        return response()->json([
            'order' => $this->orderProgress($order->fresh()->load('items')),
        ]);
    }

    private function isPackableShop(ShopOrder $order): bool
    {
        return $order->status !== ShopOrderStatus::Fulfilled
            && ($order->status === ShopOrderStatus::Paid
                || ($order->status === ShopOrderStatus::PendingPayment && $order->payment_method === 'in_person'));
    }

    private function orderKey(ShopOrder|RestaurantOrder $order): string
    {
        return ($order instanceof RestaurantOrder ? 'r' : 's').$order->id;
    }

    /**
     * Group every order line of the day by product+grade — the picking unit.
     *
     * @param  array<string, string>  $producerLabels
     * @return array<int, array<string, mixed>>
     */
    private function buildProducts(Collection $shopOrders, Collection $restaurantOrders, array $producerLabels): array
    {
        $groups = [];

        $addLine = function ($order, $item, bool $isB2b) use (&$groups, $producerLabels): void {
            $product = $item->product;
            $isCustom = $item->product_id === null;
            $variantId = $item->variant_id !== null ? (int) $item->variant_id : null;
            // Catalog lines group by product+grade; custom lines group by their
            // name so identically-named ad-hoc items pool together.
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
                    'producer' => $producerLabels[$item->product_id.'|'.($variantId ?? 0)]
                    ?? ($product?->producer?->name ?? ''),
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
                'item_id' => ($isB2b ? 'r' : 's').$item->id,
                'order_id' => $order->id,
                'b2b' => $isB2b,
                'customer' => $isB2b ? ($order->user?->name ?? '—') : ($order->name ?? '—'),
                'qty_label' => $item->displayQuantityLabel(),
                'fulfillment' => $isB2b ? $this->restaurantFulfillmentLabel($order) : $this->fulfillmentLabel($order),
                'window' => (! $isB2b && $order->hasDeliveryWindow())
                    ? substr((string) $order->delivery_window_from, 0, 5).'–'.substr((string) $order->delivery_window_to, 0, 5)
                    : null,
                'packed' => $packed,
            ];
        };

        foreach ($shopOrders as $order) {
            foreach ($order->items as $item) {
                $addLine($order, $item, false);
            }
        }
        foreach ($restaurantOrders as $order) {
            foreach ($order->items as $item) {
                if ($item->isDeclined()) {
                    continue; // declined request lines are never packed
                }
                $addLine($order, $item, true);
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
     * Group the day's lines by client (order) — the by-client analogue of
     * buildProducts. Each client carries every product line in their order, so
     * the team can pack one customer fully before moving to the next.
     *
     * @param  array<string, string>  $producerLabels
     * @return array<int, array<string, mixed>>
     */
    private function buildClients(Collection $shopOrders, Collection $restaurantOrders, array $producerLabels): array
    {
        $clients = [];

        $add = function ($order, bool $isB2b) use (&$clients, $producerLabels): void {
            $lines = [];
            $packedCount = 0;

            foreach ($order->items as $item) {
                if ($isB2b && $item->isDeclined()) {
                    continue; // declined request lines are never packed
                }
                $variantId = $item->variant_id !== null ? (int) $item->variant_id : null;
                $name = $item->displayName()
                    .($item->variant_name_snapshot ? ' · '.$item->variant_name_snapshot : '');
                $packed = $item->packed_at !== null;
                if ($packed) {
                    $packedCount++;
                }
                $lines[] = [
                    'item_id' => ($isB2b ? 'r' : 's').$item->id,
                    'name' => $name,
                    'producer' => $producerLabels[$item->product_id.'|'.($variantId ?? 0)]
                        ?? ($item->product?->producer?->name ?? ''),
                    'qty_label' => $item->displayQuantityLabel(),
                    'packed' => $packed,
                ];
            }

            if ($lines === []) {
                return; // e.g. a B2B order whose only lines were declined
            }

            // Unpacked lines first, then by name — mirrors the product view.
            usort($lines, fn ($a, $b) => [$a['packed'], $a['name']] <=> [$b['packed'], $b['name']]);
            $total = count($lines);

            $clients[] = [
                'key' => $this->orderKey($order),
                'id' => $order->id,
                'b2b' => $isB2b,
                'customer' => $isB2b ? ($order->user?->name ?? '—') : ($order->name ?? '—'),
                'fulfillment' => $isB2b ? $this->restaurantFulfillmentLabel($order) : $this->fulfillmentLabel($order),
                'window' => (! $isB2b && $order->hasDeliveryWindow())
                    ? substr((string) $order->delivery_window_from, 0, 5).'–'.substr((string) $order->delivery_window_to, 0, 5)
                    : null,
                'lines' => $lines,
                'packed_count' => $packedCount,
                'total_count' => $total,
                'done' => $packedCount >= $total,
            ];
        };

        foreach ($shopOrders as $order) {
            $add($order, false);
        }
        foreach ($restaurantOrders as $order) {
            $add($order, true);
        }

        // Fully-packed clients sink to the bottom, then alphabetical by name.
        usort($clients, fn ($a, $b) => [$a['done'], mb_strtolower($a['customer'])] <=> [$b['done'], mb_strtolower($b['customer'])]);

        return $clients;
    }

    /**
     * Per-order packing progress for the day's orders strip.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildOrders(Collection $shopOrders, Collection $restaurantOrders): array
    {
        $shop = $shopOrders->map(fn (ShopOrder $o) => array_merge(
            $this->orderProgress($o),
            ['customer' => $o->name ?? '—', 'fulfillment' => $this->fulfillmentLabel($o)],
        ));
        $restaurant = $restaurantOrders->map(fn (RestaurantOrder $o) => array_merge(
            $this->orderProgress($o),
            ['customer' => $o->user?->name ?? '—', 'fulfillment' => $this->restaurantFulfillmentLabel($o)],
        ));

        return $shop->concat($restaurant)->values()->all();
    }

    /**
     * @return array{key: string, id: int, b2b: bool, url: string, packed_count: int, total_count: int, ready: bool}
     */
    private function orderProgress(ShopOrder|RestaurantOrder $order): array
    {
        $isB2b = $order instanceof RestaurantOrder;
        $items = $isB2b
            ? $order->items->filter(fn (RestaurantOrderItem $i) => ! $i->isDeclined())
            : $order->items;
        $total = $items->count();
        $packed = $items->filter(fn ($i) => $i->packed_at !== null)->count();

        return [
            'key' => $this->orderKey($order),
            'id' => $order->id,
            'b2b' => $isB2b,
            'url' => $isB2b
                ? route('app.admin.restaurant-orders.show', $order)
                : route('app.admin.shop-orders.show', $order),
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

    private function restaurantFulfillmentLabel(RestaurantOrder $order): string
    {
        $address = trim(($order->delivery_street ?? '').' '.($order->delivery_city ?? ''));

        return trim(__('admin.packing.delivery').($address !== '' ? ' · '.$address : ''));
    }

    private function resolveDate(string $date): Carbon
    {
        if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1) {
            return Carbon::parse($date)->startOfDay();
        }

        // Default to the nearest upcoming day that has packable orders.
        $nextShop = ShopOrder::query()
            ->finalized()
            ->where('status', '!=', ShopOrderStatus::Fulfilled->value)
            ->whereDate('expected_delivery_date', '>=', Carbon::today()->toDateString())
            ->orderBy('expected_delivery_date')
            ->value('expected_delivery_date');
        $nextRestaurant = RestaurantOrder::query()
            ->where('status', RestaurantOrderStatus::Confirmed->value)
            ->whereDate('expected_delivery_date', '>=', Carbon::today()->toDateString())
            ->orderBy('expected_delivery_date')
            ->value('expected_delivery_date');

        $candidates = array_filter([$nextShop, $nextRestaurant]);
        if ($candidates === []) {
            return Carbon::today();
        }

        return Carbon::parse(min($candidates))->startOfDay();
    }

    /**
     * Producer label per (product, grade) for the packing day: one farm's
     * name, or "Farm A 3 kg · Farm B 2 kg" when the day's demand splits.
     * Demand is injected from exactly the orders being packed; uncovered
     * demand falls back to the primary producer inside the allocator.
     *
     * @return array<string, string> "productId|variantKey" => label
     */
    private function producerLabels(Collection $shopOrders, Collection $restaurantOrders): array
    {
        $demand = [];
        $dates = [];
        $products = [];
        $fold = function ($order, $item) use (&$demand, &$dates, &$products): void {
            if ($item->product_id === null) {
                return;
            }
            $iso = $order->expected_delivery_date?->toDateString();
            if ($iso === null) {
                return;
            }
            $pid = (int) $item->product_id;
            $vkey = (int) ($item->variant_id ?? 0);
            $demand[$pid][$vkey][$iso] = ($demand[$pid][$vkey][$iso] ?? 0.0) + (float) $item->quantity;
            $dates[$iso] = true;
            $products[$pid] = $item->product;
        };
        foreach ($shopOrders as $order) {
            foreach ($order->items as $item) {
                $fold($order, $item);
            }
        }
        foreach ($restaurantOrders as $order) {
            foreach ($order->items as $item) {
                if ($item->isDeclined()) {
                    continue;
                }
                $fold($order, $item);
            }
        }
        if ($demand === []) {
            return [];
        }

        $allocation = app(ProducerAllocationService::class)
            ->allocate(array_keys($demand), array_keys($dates), $demand);

        $totals = [];
        foreach ($allocation as $byPid) {
            foreach ($byPid as $pid => $byVkey) {
                foreach ($byVkey as $vkey => $shares) {
                    foreach ($shares as $producerId => $qty) {
                        $totals[$pid][$vkey][$producerId] = ($totals[$pid][$vkey][$producerId] ?? 0.0) + $qty;
                    }
                }
            }
        }

        $ids = [];
        foreach ($totals as $byVkey) {
            foreach ($byVkey as $shares) {
                foreach (array_keys($shares) as $id) {
                    if ($id > 0) {
                        $ids[$id] = true;
                    }
                }
            }
        }
        $names = Producer::query()->with('translations')
            ->whereIn('id', array_keys($ids))->get()
            ->mapWithKeys(fn ($p) => [$p->id => (string) $p->name]);

        $labels = [];
        foreach ($totals as $pid => $byVkey) {
            foreach ($byVkey as $vkey => $shares) {
                $shares = array_filter($shares, fn (float $q): bool => $q > 1e-9);
                if ($shares === []) {
                    continue;
                }
                arsort($shares);
                if (count($shares) === 1) {
                    $only = array_key_first($shares);
                    $labels[$pid.'|'.$vkey] = $only > 0 ? (string) ($names[$only] ?? '') : '';

                    continue;
                }
                $product = $products[$pid] ?? null;
                $parts = [];
                foreach ($shares as $producerId => $qty) {
                    $label = $producerId > 0 ? (string) ($names[$producerId] ?? '') : '?';
                    $qtyLabel = $product !== null ? $product->displayQuantity($qty) : (string) round($qty, 2);
                    $parts[] = trim($label.' '.$qtyLabel);
                }
                $labels[$pid.'|'.$vkey] = implode(' · ', $parts);
            }
        }

        return $labels;
    }
}
