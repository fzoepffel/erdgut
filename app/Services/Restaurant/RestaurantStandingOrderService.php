<?php

namespace App\Services\Restaurant;

use App\Enums\RestaurantOrderStatus;
use App\Enums\RestaurantStandingOrderStatus;
use App\Mail\RestaurantOrderSubmittedMail;
use App\Models\RestaurantOrder;
use App\Models\RestaurantStandingOrder;
use App\Services\Shop\ShopClosureService;
use App\Support\RestaurantOrderAdminNotifier;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
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

    /** Admin confirmed the first order → start recurring. */
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
    }

    public function pause(RestaurantStandingOrder $standing): void
    {
        if ($standing->isActive()) {
            $standing->forceFill(['status' => RestaurantStandingOrderStatus::Paused])->save();
        }
    }

    public function resume(RestaurantStandingOrder $standing): void
    {
        if ($standing->status === RestaurantStandingOrderStatus::Paused) {
            $standing->forceFill(['status' => RestaurantStandingOrderStatus::Active])->save();
        }
    }

    public function cancel(RestaurantStandingOrder $standing): void
    {
        $standing->forceFill(['status' => RestaurantStandingOrderStatus::Cancelled])->save();
    }

    /**
     * Generate auto-confirmed orders for every active standing order whose next
     * delivery occurrences fall within the lead window. Idempotent.
     */
    public function materializeDue(CarbonInterface $today): int
    {
        $today = Carbon::parse($today)->startOfDay();
        $windowEnd = $today->copy()->addDays((int) config('shop.restaurant_standing_lead_days', 9));
        $written = 0;

        RestaurantStandingOrder::query()
            ->active()
            ->with(['items', 'user'])
            ->chunkById(100, function ($standingOrders) use ($today, $windowEnd, &$written): void {
                foreach ($standingOrders as $standing) {
                    foreach ($this->occurrencesInWindow((int) $standing->delivery_weekday, $today, $windowEnd) as $date) {
                        if ($this->materializeOne($standing, $date)) {
                            $written++;
                        }
                    }
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

        try {
            Mail::to($standing->user->email)
                ->locale($standing->locale ?? app()->getLocale())
                ->send(new RestaurantOrderSubmittedMail($order));
        } catch (Throwable $e) {
            report($e);
        }
        RestaurantOrderAdminNotifier::submitted($order);

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
