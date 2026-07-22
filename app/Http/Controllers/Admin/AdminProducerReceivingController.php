<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CustomerType;
use App\Http\Controllers\Controller;
use App\Models\Producer;
use App\Models\ProducerReceipt;
use App\Models\ShopOrder;
use App\Services\Admin\ProducerAllocationService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Orders grouped by the farm that supplies them, for two jobs:
 *  1. Hand a farmer their order (reuses the shop-orders farmer export ZIP).
 *  2. Tick off produce as it arrives (the receiving checklist, backed by the
 *     producer_receipts table — operational only, never touches inventory).
 *
 * Deliberately isolated from AdminShopOrderController: it calls the public
 * ProducerAllocationService directly and mirrors the small delivery-date /
 * type filter so the on-screen totals line up with the farmer export.
 */
class AdminProducerReceivingController extends Controller
{
    /** Pseudo-status: the firm order set (Paid, Fulfilled, in-person pending). */
    private const FILTER_FINALIZED = 'finalized';

    public function index(Request $request): View
    {
        $this->authorize('admin');

        $filter = $this->resolveFilter($request);
        $producers = $this->buildProducers($filter);

        return view('admin.producer-receiving.index', [
            'producers' => $producers,
            'filter' => $filter,
        ]);
    }

    /**
     * Tick a single line on or off. Row present in producer_receipts means
     * received; toggling removes it. Idempotent per call.
     */
    public function toggleReceipt(Request $request): JsonResponse
    {
        $this->authorize('admin');

        $data = $request->validate([
            'producer_id' => ['required', 'integer', 'exists:producers,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'variant_id' => ['nullable', 'integer'],
            'delivery_date' => ['required', 'date'],
        ]);

        $key = [
            'producer_id' => (int) $data['producer_id'],
            'product_id' => (int) $data['product_id'],
            'variant_id' => (int) ($data['variant_id'] ?? 0),
            'delivery_date' => Carbon::parse($data['delivery_date'])->toDateString(),
        ];

        $existing = ProducerReceipt::query()->where($key)->first();
        if ($existing !== null) {
            $existing->delete();

            return response()->json(['received' => false]);
        }

        ProducerReceipt::create($key + ['received_by_user_id' => $request->user()?->id]);

        return response()->json(['received' => true]);
    }

    /**
     * Mark every line of one producer (for the current filter) as received.
     * Recomputes the producer's row set server-side so the frontend cannot
     * drive us past what the orders actually contain.
     */
    public function receiveAll(Request $request): JsonResponse
    {
        $this->authorize('admin');

        $producerId = (int) $request->input('producer_id', 0);
        abort_unless($producerId > 0, 422, 'producer_id is required');

        $filter = $this->resolveFilter($request);
        $producers = $this->buildProducers($filter);
        $producer = collect($producers)->firstWhere('id', $producerId);
        abort_if($producer === null, 404);

        $keys = [];
        foreach ($producer['rows'] as $row) {
            ProducerReceipt::firstOrCreate([
                'producer_id' => $producerId,
                'product_id' => $row['product_id'],
                'variant_id' => $row['variant_id'],
                'delivery_date' => $row['date'],
            ], ['received_by_user_id' => $request->user()?->id]);
            $keys[] = $row['key'];
        }

        return response()->json(['keys' => $keys]);
    }

    /**
     * One entry per producer that supplies any of the filtered orders, each
     * holding the (date, product, variant) lines it owes with received flags.
     *
     * @param  array{status: string, type: string, delivery_from: string, delivery_to: string}  $filter
     * @return list<array<string, mixed>>
     */
    private function buildProducers(array $filter): array
    {
        $orders = $this->filteredOrdersQuery($filter)
            ->with([
                'items.product.producer.translations',
                'items.product.translations',
                'items.product.variants',
            ])
            ->get();

        $allocation = $this->allocation($orders);
        if ($allocation === []) {
            return [];
        }

        $meta = $this->productMeta($orders);
        $received = $this->receivedKeys($orders);

        // Gather rows per producer from the allocation shares.
        $byProducer = [];
        foreach ($allocation as $iso => $byPid) {
            foreach ($byPid as $pid => $byVkey) {
                foreach ($byVkey as $vkey => $shares) {
                    foreach ($shares as $producerId => $qty) {
                        if ($producerId <= 0 || $qty <= 1e-9) {
                            continue; // 0 = unattributable demand
                        }
                        $m = $meta[$pid.'|'.($vkey ?: 0)] ?? null;
                        if ($m === null) {
                            continue;
                        }
                        $variantId = (int) ($vkey ?: 0);
                        $key = "p{$producerId}-{$pid}-{$variantId}-{$iso}";
                        $byProducer[$producerId][] = [
                            'key' => $key,
                            'product_id' => (int) $pid,
                            'variant_id' => $variantId,
                            'date' => $iso,
                            'product_name' => $m['product_name'],
                            'variant_name' => $m['variant_name'],
                            'qty_display' => round($m['factor'] > 0 ? $qty / $m['factor'] : $qty, 4),
                            'unit' => $m['unit'],
                            'received' => isset($received[$producerId.'|'.$pid.'|'.$variantId.'|'.$iso]),
                        ];
                    }
                }
            }
        }

        $names = Producer::query()
            ->whereIn('id', array_keys($byProducer))
            ->with('translations')
            ->get()
            ->mapWithKeys(fn (Producer $p): array => [$p->id => $p->name]);

        $producers = [];
        foreach ($byProducer as $producerId => $rows) {
            usort($rows, fn (array $a, array $b): int => [$a['date'], $a['product_name'], (string) ($a['variant_name'] ?? '')]
                <=> [$b['date'], $b['product_name'], (string) ($b['variant_name'] ?? '')]);

            $receivedCount = count(array_filter($rows, fn (array $r): bool => $r['received']));
            $producers[] = [
                'id' => (int) $producerId,
                'name' => $names[$producerId] ?? ('#'.$producerId),
                'rows' => $rows,
                'total_count' => count($rows),
                'received_count' => $receivedCount,
                'done' => $receivedCount === count($rows) && $rows !== [],
            ];
        }

        usort($producers, fn (array $a, array $b): int => [$a['done'] ? 1 : 0, mb_strtolower($a['name'])]
            <=> [$b['done'] ? 1 : 0, mb_strtolower($b['name'])]);

        return $producers;
    }

    /**
     * Set of already-received keys "producer|product|variant|iso" for the
     * filtered delivery window, so lookups are O(1) while building rows.
     *
     * @param  Collection<int, ShopOrder>  $orders
     * @return array<string, true>
     */
    private function receivedKeys($orders): array
    {
        $dates = $orders
            ->map(fn (ShopOrder $o): ?string => $o->expected_delivery_date?->toDateString())
            ->filter()
            ->unique()
            ->values()
            ->all();
        if ($dates === []) {
            return [];
        }

        $keys = [];
        ProducerReceipt::query()
            ->whereIn('delivery_date', $dates)
            ->get()
            ->each(function (ProducerReceipt $r) use (&$keys): void {
                $keys[$r->producer_id.'|'.$r->product_id.'|'.$r->variant_id.'|'.$r->delivery_date] = true;
            });

        return $keys;
    }

    /**
     * Display metadata per (product, variant): name, unit and the canonical
     * -> display conversion factor. Mirrors the farmer export.
     *
     * @param  Collection<int, ShopOrder>  $orders
     * @return array<string, array<string, mixed>>
     */
    private function productMeta($orders): array
    {
        $meta = [];
        foreach ($orders as $order) {
            foreach ($order->items as $item) {
                $product = $item->product;
                if ($product === null) {
                    continue;
                }
                $metaKey = $product->id.'|'.($item->variant_id ?? 0);
                if (isset($meta[$metaKey])) {
                    continue;
                }
                $variant = $item->variant_id !== null
                    ? $product->variants->firstWhere('id', $item->variant_id)
                    : null;
                $unit = $product->unit;
                $displayUnit = $product->price_display_unit ?? $unit?->defaultDisplayUnit();
                $meta[$metaKey] = [
                    'product_name' => $product->name,
                    'variant_name' => $variant?->localizedName(),
                    'factor' => ($unit !== null && $displayUnit !== null)
                        ? $unit->canonicalUnitsPerDisplay($displayUnit)
                        : 1,
                    'unit' => $product->localizedUnit(),
                ];
            }
        }

        return $meta;
    }

    /**
     * Allocation of the filtered demand across producers:
     * [iso][pid][vkey][producerId => qty].
     *
     * @param  Collection<int, ShopOrder>  $orders
     */
    private function allocation($orders): array
    {
        $demand = [];
        $dates = [];
        foreach ($orders as $order) {
            $iso = $order->expected_delivery_date?->toDateString();
            if ($iso === null) {
                continue;
            }
            foreach ($order->items as $item) {
                if ($item->product_id === null) {
                    continue; // custom lines have no farm
                }
                $pid = (int) $item->product_id;
                $vkey = (int) ($item->variant_id ?? 0);
                $demand[$pid][$vkey][$iso] = ($demand[$pid][$vkey][$iso] ?? 0.0) + (float) $item->quantity;
                $dates[$iso] = true;
            }
        }
        if ($demand === []) {
            return [];
        }

        return app(ProducerAllocationService::class)
            ->allocate(array_keys($demand), array_keys($dates), $demand);
    }

    /**
     * @return array{status: string, type: string, delivery_from: string, delivery_to: string}
     */
    private function resolveFilter(Request $request): array
    {
        $from = (string) $request->query('delivery_from', '');
        $to = (string) $request->query('delivery_to', '');

        // Fresh visit (no date params at all): focus on the NEXT delivery day
        // that has orders. Explicitly cleared inputs mean "all dates".
        if (! $request->has('delivery_from') && ! $request->has('delivery_to')) {
            $next = $this->nextDeliveryDayWithOrders();
            if ($next !== null) {
                $from = $to = $next;
            }
        }

        $type = (string) $request->query('type', '');
        if (! in_array($type, ['', 'b2c', 'b2b'], true)) {
            $type = '';
        }

        return [
            'status' => self::FILTER_FINALIZED,
            'type' => $type,
            'delivery_from' => $from,
            'delivery_to' => $to,
        ];
    }

    /**
     * Firm shop orders in the delivery-date window. Matches the farmer export
     * (shop orders only) so the checklist and the export ZIP agree.
     *
     * @param  array{status: string, type: string, delivery_from: string, delivery_to: string}  $filter
     * @return Builder<ShopOrder>
     */
    private function filteredOrdersQuery(array $filter): Builder
    {
        return ShopOrder::query()
            ->finalized()
            ->when($filter['type'], fn (Builder $q, string $type): Builder => match ($type) {
                'b2c' => $q->where('customer_type', CustomerType::Private->value),
                'b2b' => $q->where('customer_type', CustomerType::Business->value),
                default => $q,
            })
            ->when($filter['delivery_from'] !== '', fn (Builder $q): Builder => $q->whereDate('expected_delivery_date', '>=', $filter['delivery_from']))
            ->when($filter['delivery_to'] !== '', fn (Builder $q): Builder => $q->whereDate('expected_delivery_date', '<=', $filter['delivery_to']));
    }

    /** Next date (today or later) that has any firm shop order. */
    private function nextDeliveryDayWithOrders(): ?string
    {
        $next = ShopOrder::query()
            ->finalized()
            ->whereDate('expected_delivery_date', '>=', Carbon::today()->toDateString())
            ->orderBy('expected_delivery_date')
            ->value('expected_delivery_date');

        return $next !== null ? Carbon::parse($next)->toDateString() : null;
    }
}
