<?php

namespace App\Services\Restaurant;

use App\Enums\RestaurantOrderStatus;
use App\Enums\RestaurantStandingOrderStatus;
use App\Models\RestaurantOrder;
use App\Models\RestaurantStandingOrder;
use App\Services\Shop\ShopClosureService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Recurring weekly restaurant deliveries. A standing order is born Pending from
 * the first order's weekly lines; admin confirmation of that order activates it;
 * {@see materializeDue()} then generates auto-confirmed orders for upcoming weeks.
 */
class RestaurantStandingOrderService
{
    public function __construct(
        private readonly RestaurantOrderService $orders,
        private readonly ShopClosureService $closures,
    ) {}

    /**
     * Create the Pending standing order from the first order's weekly lines and
     * link the order back to it. Each descriptor mirrors a submit() item plus an
     * optional `until` date.
     *
     * @param  list<array{product_id?:?int, variant_id?:?int, name?:?string, unit?:?string, quantity:float, until?:?string}>  $weeklyLines
     */
    public function createFromOrder(RestaurantOrder $order, array $weeklyLines): ?RestaurantStandingOrder
    {
        $weeklyLines = array_values(array_filter($weeklyLines, fn ($l) => (float) ($l['quantity'] ?? 0) > 0));
        if ($weeklyLines === []) {
            return null;
        }

        $firstDate = $order->expected_delivery_date->copy();
        $standing = RestaurantStandingOrder::create([
            'user_id' => $order->user_id,
            'delivery_weekday' => $firstDate->dayOfWeek,
            'delivery_street' => $order->delivery_street,
            'delivery_postal_code' => $order->delivery_postal_code,
            'delivery_city' => $order->delivery_city,
            'delivery_notes' => $order->delivery_notes,
            'status' => RestaurantStandingOrderStatus::Pending,
            'locale' => $order->locale,
        ]);

        // Recurrence begins the week AFTER the first (manual) delivery.
        $startsOn = $firstDate->copy()->addWeek()->toDateString();

        foreach ($weeklyLines as $line) {
            $productId = (int) ($line['product_id'] ?? 0) ?: null;
            $name = trim((string) ($line['name'] ?? '')) ?: null;
            if ($productId === null && $name === null) {
                continue;
            }
            $until = trim((string) ($line['until'] ?? '')) ?: null;

            $standing->items()->create([
                'product_id' => $productId,
                'variant_id' => $productId !== null ? ((int) ($line['variant_id'] ?? 0) ?: null) : null,
                'name_snapshot' => $name,
                'unit' => $line['unit'] ?? null,
                'quantity' => (float) $line['quantity'],
                'starts_on' => $startsOn,
                'ends_on' => $until,
            ]);
        }

        $order->forceFill([
            'source_standing_order_id' => $standing->id,
            'original_delivery_date' => $firstDate->toDateString(),
        ])->save();

        return $standing;
    }

    /**
     * Admin confirmed the first order → start recurring. Every upcoming week
     * within the horizon materializes immediately so each delivery exists as a
     * real order (visible in the admin overview, reserving stock, packable).
     */
    public function activate(RestaurantStandingOrder $standing, ?int $adminId): void
    {
        if (! $standing->isPending()) {
            return;
        }
        $standing->forceFill([
            'status' => RestaurantStandingOrderStatus::Active,
            'confirmed_at' => now(),
            'confirmed_by_user_id' => $adminId,
        ])->save();

        $this->materializeHorizon($standing->refresh()->load('items', 'user'));
    }

    /** Pausing removes the not-yet-delivered future weeks; resume regenerates them. */
    public function pause(RestaurantStandingOrder $standing): void
    {
        if ($standing->isActive()) {
            $standing->forceFill(['status' => RestaurantStandingOrderStatus::Paused])->save();
            $this->cancelFutureMaterialized($standing);
        }
    }

    public function resume(RestaurantStandingOrder $standing): void
    {
        if ($standing->status === RestaurantStandingOrderStatus::Paused) {
            $standing->forceFill(['status' => RestaurantStandingOrderStatus::Active])->save();
            $this->materializeHorizon($standing->load('items', 'user'));
        }
    }

    public function cancel(RestaurantStandingOrder $standing): void
    {
        $standing->forceFill(['status' => RestaurantStandingOrderStatus::Cancelled])->save();
        $this->cancelFutureMaterialized($standing);
    }

    /**
     * The owner changed the recurring template (quantities / removed items).
     * Future auto-generated weeks are rebuilt from the new template by
     * REUSING the trim + horizon machinery; hand-adjusted weeks, weeks whose
     * ordering already closed, skipped weeks and invoiced deliveries survive
     * untouched.
     *
     * @param  list<array{id: int|string, quantity?: float|string, remove?: mixed}>  $lines
     */
    public function updateItems(RestaurantStandingOrder $standing, array $lines): void
    {
        $items = $standing->items()->get()->keyBy('id');
        $byId = collect($lines)->keyBy(fn ($l) => (int) ($l['id'] ?? 0));

        $surviving = $items->filter(function ($item) use ($byId): bool {
            $payload = $byId->get($item->id);

            return $payload === null || empty($payload['remove']);
        })->count();
        if ($surviving === 0) {
            throw ValidationException::withMessages([
                'lines' => __('restaurant.standing_keep_one_item'),
            ]);
        }

        foreach ($items as $item) {
            $payload = $byId->get($item->id);
            if ($payload === null) {
                continue;
            }
            if (! empty($payload['remove'])) {
                $item->delete();

                continue;
            }
            $qty = round((float) ($payload['quantity'] ?? $item->quantity), 4);
            if ($qty <= 0) {
                throw ValidationException::withMessages([
                    'lines' => __('restaurant.adjust_qty_invalid'),
                ]);
            }
            $item->forceFill(['quantity' => $qty])->save();
        }

        // Rebuild the untouched future weeks from the new template.
        $standing->refresh()->load('items', 'user');
        $this->cancelFutureMaterialized($standing);
        $this->materializeHorizon($standing);
    }

    /**
     * The owner moved the recurrence end: trim materialized weeks beyond the
     * new end and fill up to it (idempotent both ways).
     */
    public function applyEndChange(RestaurantStandingOrder $standing): void
    {
        $ends = $standing->items()->min('ends_on');
        if ($ends !== null) {
            $this->cancelFutureMaterialized($standing, Carbon::parse($ends));
        }
        if ($standing->isActive()) {
            $this->materializeHorizon($standing->load('items', 'user'));
        }
    }

    /**
     * Materialize every occurrence of this standing order from tomorrow through
     * the shop horizon (idempotent per week). No per-week mails — the customer
     * approved the schedule and sees it under "Wiederkehrende Lieferungen".
     */
    public function materializeHorizon(RestaurantStandingOrder $standing, ?CarbonInterface $today = null): int
    {
        if (! $standing->isActive()) {
            return 0;
        }

        $today = Carbon::parse($today ?? Carbon::today())->startOfDay();
        $windowEnd = $today->copy()->addWeeks(max(1, (int) config('shop.horizon_weeks', 8)));
        $written = 0;

        foreach ($this->occurrencesInWindow((int) $standing->delivery_weekday, $today, $windowEnd) as $date) {
            if ($this->materializeOne($standing, $date)) {
                $written++;
            }
        }

        return $written;
    }

    /**
     * Remove future auto-generated weeks (recurrence weeks only — never the
     * origin order, never fulfilled or invoiced deliveries). They are DELETED,
     * not cancelled: nothing shipped, nothing billed — and deletion lets a
     * later resume/extension re-materialize the week. An INDIVIDUALLY
     * cancelled week keeps its cancelled row, which deliberately blocks
     * re-materialization (the customer skipped exactly that delivery). The
     * reservation releases implicitly. No mails: the change mirrors an action
     * the owner or admin just took on the standing order itself.
     */
    private function cancelFutureMaterialized(RestaurantStandingOrder $standing, ?Carbon $keepThrough = null): void
    {
        $minStart = $standing->items()->min('starts_on');
        if ($minStart === null) {
            return;
        }
        $cutoff = Carbon::parse($minStart)->startOfDay();
        if ($keepThrough !== null && $keepThrough->gte($cutoff)) {
            $cutoff = $keepThrough->copy()->addDay()->startOfDay();
        }
        $floor = Carbon::tomorrow()->startOfDay();
        if ($floor->gt($cutoff)) {
            $cutoff = $floor;
        }

        RestaurantOrder::query()
            ->where('source_standing_order_id', $standing->id)
            ->where('status', RestaurantOrderStatus::Confirmed->value)
            ->whereNull('invoice_number')
            // Hand-tuned weeks are the customer's word — never regenerate them.
            ->whereNull('adjusted_at')
            ->whereDate('expected_delivery_date', '>=', $cutoff->toDateString())
            ->get()
            // A week whose ordering has already closed is being sourced/packed;
            // pausing or editing the schedule must not make it vanish.
            ->reject(fn (RestaurantOrder $order): bool => $this->closures->isClosed($order->expected_delivery_date->copy()))
            ->each(function (RestaurantOrder $order): void {
                $order->items()->delete();
                $order->delete();
            });
    }

    /**
     * Rolling nightly sweep: keep every active standing order materialized
     * through the shop horizon (new weeks appear as the horizon advances).
     * Idempotent per (standing order, week).
     */
    public function materializeDue(CarbonInterface $today): int
    {
        $written = 0;

        RestaurantStandingOrder::query()
            ->active()
            ->with(['items', 'user'])
            ->chunkById(100, function ($standingOrders) use ($today, &$written): void {
                foreach ($standingOrders as $standing) {
                    $written += $this->materializeHorizon($standing, $today);
                }
            });

        return $written;
    }

    private function materializeOne(RestaurantStandingOrder $standing, Carbon $date): bool
    {
        if ($this->closures->isClosed($date)) {
            return false;
        }
        if ($standing->user === null) {
            return false;
        }

        $activeItems = $standing->items->filter(fn ($item) => $item->activeOn($date));
        if ($activeItems->isEmpty()) {
            return false;
        }

        $exists = RestaurantOrder::query()
            ->where('source_standing_order_id', $standing->id)
            ->whereDate('original_delivery_date', $date->toDateString())
            ->exists();
        if ($exists) {
            return false;
        }

        try {
            $order = $this->orders->submitMaterialized($standing->user, [
                'items' => $activeItems->map(fn ($item) => $item->toPayloadItem())->values()->all(),
                'expected_delivery_date' => $date->toDateString(),
                'delivery_street' => $standing->delivery_street,
                'delivery_postal_code' => $standing->delivery_postal_code,
                'delivery_city' => $standing->delivery_city,
                'delivery_notes' => $standing->delivery_notes,
                'locale' => $standing->locale,
            ]);
        } catch (Throwable $e) {
            Log::warning('restaurant.standing_order.materialize_failed', [
                'standing_order_id' => $standing->id,
                'date' => $date->toDateString(),
                'message' => $e->getMessage(),
            ]);

            return false;
        }

        // The standing order is admin-approved, so the generated order is
        // auto-confirmed; link it back for dedup + traceability.
        $order->forceFill([
            'source_standing_order_id' => $standing->id,
            'original_delivery_date' => $date->toDateString(),
            'status' => RestaurantOrderStatus::Confirmed,
        ])->save();

        // Deliberately NO mail / admin ping per generated week: the owner
        // approved the schedule (and manages it under Wiederkehrende
        // Lieferungen); the admin sees every week in the order overview.
        return true;
    }

    /**
     * Delivery dates matching $weekday strictly after $from through $to inclusive.
     *
     * @return list<Carbon>
     */
    private function occurrencesInWindow(int $weekday, Carbon $from, Carbon $to): array
    {
        $cursor = $from->copy()->addDay(); // deliveries are tomorrow or later
        $cursor->addDays(($weekday - $cursor->dayOfWeek + 7) % 7);

        $dates = [];
        while ($cursor->lte($to)) {
            $dates[] = $cursor->copy();
            $cursor->addWeek();
        }

        return $dates;
    }
}
