<?php

namespace App\Services\Shop;

use App\Models\ShopClosedDay;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Operational closures for the public shop delivery-date picker.
 *
 * Each closed future day is one row. Today and the past are implicitly
 * non-bookable (same as the storefront horizon) and are not stored.
 */
class ShopClosureService
{
    /**
     * First day closures can be managed — mirrors the public shop (no same-day delivery).
     */
    public function earliestManageableDate(): Carbon
    {
        return Carbon::today()->addDay()->startOfDay();
    }

    public function isManageableDate(Carbon $date): bool
    {
        return $date->copy()->startOfDay()->gte($this->earliestManageableDate());
    }

    public function purgeExpired(): void
    {
        ShopClosedDay::query()
            ->whereDate('closed_on', '<', $this->earliestManageableDate()->toDateString())
            ->delete();
    }

    public function isClosed(Carbon $date): bool
    {
        $date = $date->copy()->startOfDay();

        if ($date->lt($this->earliestManageableDate())) {
            return true;
        }

        return ShopClosedDay::query()
            ->whereDate('closed_on', $date->toDateString())
            ->exists();
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
        $existing = ShopClosedDay::query()->whereDate('closed_on', $iso)->first();

        if ($existing !== null) {
            $existing->delete();

            return false;
        }

        ShopClosedDay::query()->create([
            'closed_on' => $iso,
            'created_by_user_id' => $createdByUserId,
        ]);

        return true;
    }

    /**
     * @return list<string> ISO dates stored as closed within [from, to] inclusive.
     */
    public function closedIsoDatesBetween(Carbon $from, Carbon $to): array
    {
        return ShopClosedDay::query()
            ->whereDate('closed_on', '>=', $from->toDateString())
            ->whereDate('closed_on', '<=', $to->toDateString())
            ->orderBy('closed_on')
            ->pluck('closed_on')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->all();
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
}
