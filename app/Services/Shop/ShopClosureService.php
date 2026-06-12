<?php

namespace App\Services\Shop;

use App\Models\ShopDeliveryClosure;
use App\Models\ShopDeliveryClosureException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Operational closures for the public shop delivery-date picker.
 *
 * Closed days remain orderable via the admin manual-order flow; only the
 * anonymous storefront hides them. Multi-day ranges can carry per-day open
 * exceptions without shrinking the underlying range.
 */
class ShopClosureService
{
    /**
     * First day closures can apply — mirrors the public shop (no same-day delivery).
     */
    public function earliestManageableDate(): Carbon
    {
        return Carbon::today()->addDay()->startOfDay();
    }

    public function isManageableDate(Carbon $date): bool
    {
        return $date->copy()->startOfDay()->gte($this->earliestManageableDate());
    }

    /**
     * Drop closure rows that only affected non-bookable days.
     */
    public function purgeExpired(): void
    {
        $cutoff = $this->earliestManageableDate()->toDateString();

        ShopDeliveryClosureException::query()
            ->whereDate('date', '<', $cutoff)
            ->delete();

        ShopDeliveryClosure::query()
            ->whereDate('ends_on', '<', $cutoff)
            ->delete();

        ShopDeliveryClosure::query()
            ->whereDate('starts_on', '<', $cutoff)
            ->whereDate('ends_on', '>=', $cutoff)
            ->whereColumn('starts_on', '!=', 'ends_on')
            ->each(function (ShopDeliveryClosure $closure) use ($cutoff): void {
                $closure->forceFill(['starts_on' => $cutoff])->save();
            });
    }

    public function isClosed(Carbon $date): bool
    {
        $iso = $date->copy()->startOfDay()->toDateString();

        foreach ($this->coveringClosures($iso) as $closure) {
            if (! $this->hasOpenException($closure, $iso)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Toggle a day between closed and open. Returns the new closed state.
     */
    public function toggleDay(Carbon $date, ?int $createdByUserId = null): bool
    {
        $date = $date->copy()->startOfDay();

        if (! $this->isManageableDate($date)) {
            throw new \InvalidArgumentException('Closure date must be tomorrow or later.');
        }

        $iso = $date->toDateString();

        if ($this->isClosed($date)) {
            $this->openDay($date);

            return false;
        }

        $this->closeDay($date, $createdByUserId);

        return true;
    }

    /**
     * @return list<string> ISO dates closed within [from, to] inclusive.
     */
    public function closedIsoDatesBetween(Carbon $from, Carbon $to): array
    {
        $fromIso = $from->copy()->startOfDay()->toDateString();
        $toIso = $to->copy()->startOfDay()->toDateString();

        $closed = [];
        $cursor = Carbon::parse($fromIso)->startOfDay();
        $end = Carbon::parse($toIso)->startOfDay();

        while ($cursor->lte($end)) {
            if ($this->isClosed($cursor)) {
                $closed[] = $cursor->toDateString();
            }
            $cursor->addDay();
        }

        return $closed;
    }

    /**
     * @param  Collection<int, array{date: Carbon, product_count?: int, product_ids?: int[]}>  $dates
     * @return Collection<int, array{date: Carbon, product_count?: int, product_ids?: int[]}>
     */
    public function filterOpenDates(Collection $dates): Collection
    {
        return $dates
            ->filter(fn (array $row): bool => ! $this->isClosed($row['date']))
            ->values();
    }

    public function rangesOverlap(Carbon $startsOn, Carbon $endsOn, ?int $exceptId = null): bool
    {
        return ShopDeliveryClosure::query()
            ->overlapping($startsOn, $endsOn, $exceptId)
            ->exists();
    }

    /**
     * @return list<array{id: int, starts_on: string, ends_on: string, reason: ?string, open_dates: list<string>}>
     */
    public function activeRanges(): array
    {
        $cutoff = $this->earliestManageableDate()->toDateString();

        return ShopDeliveryClosure::query()
            ->with('openDayExceptions')
            ->whereDate('ends_on', '>=', $cutoff)
            ->whereColumn('starts_on', '!=', 'ends_on')
            ->orderBy('starts_on')
            ->get()
            ->map(fn (ShopDeliveryClosure $closure): array => [
                'id' => $closure->id,
                'starts_on' => $closure->starts_on->toDateString(),
                'ends_on' => $closure->ends_on->toDateString(),
                'reason' => $closure->reason,
                'open_dates' => $closure->openDayExceptions
                    ->map(fn (ShopDeliveryClosureException $e): string => $e->date->toDateString())
                    ->filter(fn (string $iso): bool => $iso >= $cutoff)
                    ->sort()
                    ->values()
                    ->all(),
            ])
            ->all();
    }

    private function openDay(Carbon $date): void
    {
        $iso = $date->toDateString();

        DB::transaction(function () use ($iso): void {
            $singles = ShopDeliveryClosure::query()
                ->whereDate('starts_on', $iso)
                ->whereDate('ends_on', $iso)
                ->get();

            if ($singles->isNotEmpty()) {
                $singles->each->delete();

                return;
            }

            foreach ($this->coveringClosures($iso) as $closure) {
                if ($closure->isSingleDay()) {
                    continue;
                }

                ShopDeliveryClosureException::query()->firstOrCreate([
                    'shop_delivery_closure_id' => $closure->id,
                    'date' => $iso,
                ]);
            }
        });
    }

    private function closeDay(Carbon $date, ?int $createdByUserId): void
    {
        $iso = $date->toDateString();

        DB::transaction(function () use ($iso, $createdByUserId): void {
            ShopDeliveryClosureException::query()
                ->whereDate('date', $iso)
                ->whereIn('shop_delivery_closure_id', $this->coveringClosures($iso)->pluck('id'))
                ->delete();

            if ($this->isClosed(Carbon::parse($iso))) {
                return;
            }

            ShopDeliveryClosure::query()->create([
                'starts_on' => $iso,
                'ends_on' => $iso,
                'created_by_user_id' => $createdByUserId,
            ]);
        });
    }

    /**
     * @return Collection<int, ShopDeliveryClosure>
     */
    private function coveringClosures(string $iso): Collection
    {
        return ShopDeliveryClosure::query()
            ->whereDate('starts_on', '<=', $iso)
            ->whereDate('ends_on', '>=', $iso)
            ->orderBy('starts_on')
            ->get();
    }

    private function hasOpenException(ShopDeliveryClosure $closure, string $iso): bool
    {
        return ShopDeliveryClosureException::query()
            ->where('shop_delivery_closure_id', $closure->id)
            ->whereDate('date', $iso)
            ->exists();
    }
}
