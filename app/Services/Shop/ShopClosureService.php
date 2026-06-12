<?php

namespace App\Services\Shop;

use App\Models\ShopDeliveryClosure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Operational closures for the public shop delivery-date picker.
 *
 * Closed days remain orderable via the admin manual-order flow; only the
 * anonymous storefront hides them.
 */
class ShopClosureService
{
    public function isClosed(Carbon $date): bool
    {
        $iso = $date->copy()->startOfDay()->toDateString();

        return ShopDeliveryClosure::query()
            ->whereDate('starts_on', '<=', $iso)
            ->whereDate('ends_on', '>=', $iso)
            ->exists();
    }

    /**
     * @return list<string> ISO dates closed within [from, to] inclusive.
     */
    public function closedIsoDatesBetween(Carbon $from, Carbon $to): array
    {
        $fromIso = $from->copy()->startOfDay()->toDateString();
        $toIso = $to->copy()->startOfDay()->toDateString();

        $closed = [];
        foreach (ShopDeliveryClosure::query()
            ->whereDate('starts_on', '<=', $toIso)
            ->whereDate('ends_on', '>=', $fromIso)
            ->get(['starts_on', 'ends_on']) as $closure) {
            $cursor = Carbon::parse($closure->starts_on)->max(Carbon::parse($fromIso))->startOfDay();
            $end = Carbon::parse($closure->ends_on)->min(Carbon::parse($toIso))->startOfDay();

            while ($cursor->lte($end)) {
                $closed[$cursor->toDateString()] = true;
                $cursor->addDay();
            }
        }

        return array_keys($closed);
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
}
