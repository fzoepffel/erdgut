<?php

namespace App\Support;

use App\Models\Producer;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * Turns a set of producers into the customer-facing label: names joined
 * with a middot, collapsed to a generic "several farms" label when the
 * list gets long. Order is the caller's (allocation share desc, or pivot
 * position with the primary first).
 */
class ProducerLabels
{
    public const COLLAPSE_THRESHOLD = 3;

    public const SHOW_ROUTE_SHOP = 'public.shop.producers.show';

    public const INDEX_ROUTE_SHOP = 'public.shop.producers.index';

    public const SHOW_ROUTE_MARKETING = 'public.producers.show';

    public const INDEX_ROUTE_MARKETING = 'public.producers';

    /** @param  iterable<Producer|string|null>  $producers */
    public static function label(iterable $producers): string
    {
        $names = self::names($producers);
        if ($names === []) {
            return '';
        }
        if (count($names) > self::COLLAPSE_THRESHOLD) {
            return __('shop.producers_multiple');
        }

        return implode(' · ', $names);
    }

    /**
     * Link data for surfaces that render each farm as its own anchor.
     * When collapsed, callers render the "several farms" label linking to
     * the producers directory instead of the individual items.
     *
     * @param  iterable<Producer|null>  $producers
     * @return array{collapsed: bool, items: list<array{id: int, name: string, url: string}>, index_url: string}
     */
    public static function linkData(
        iterable $producers,
        string $showRoute = self::SHOW_ROUTE_SHOP,
        string $indexRoute = self::INDEX_ROUTE_SHOP,
    ): array {
        $items = [];
        $seen = [];
        foreach ($producers as $producer) {
            if (! $producer instanceof Producer) {
                continue;
            }
            if (! filled($producer->slug)) {
                continue;
            }
            $name = (string) $producer->name;
            if ($name === '' || isset($seen[$producer->id])) {
                continue;
            }
            $seen[$producer->id] = true;
            $items[] = [
                'id' => (int) $producer->id,
                'name' => $name,
                'url' => route($showRoute, $producer),
            ];
        }

        return [
            'collapsed' => count($items) > self::COLLAPSE_THRESHOLD,
            'items' => $items,
            'index_url' => route($indexRoute),
        ];
    }

    /**
     * Prefer the multi-farm pivot list; fall back to the primary producer.
     *
     * Always resolves via the relation (lazy-loads when needed) so surfaces
     * that forgot to eager-load still show every registered farm — not only
     * the primary. Callers that care about N+1 should eager-load `producers`.
     *
     * @return Collection<int, Producer>
     */
    public static function forProduct(?Product $product): Collection
    {
        if ($product === null) {
            return collect();
        }

        if ($product->producers->isNotEmpty()) {
            return $product->producers;
        }

        if ($product->producer !== null) {
            return collect([$product->producer]);
        }

        return collect();
    }

    /**
     * @param  iterable<Producer|string|null>  $producers
     * @return list<string>
     */
    private static function names(iterable $producers): array
    {
        $names = [];
        foreach ($producers as $producer) {
            $name = $producer instanceof Producer ? (string) $producer->name : (string) ($producer ?? '');
            if ($name !== '' && ! in_array($name, $names, true)) {
                $names[] = $name;
            }
        }

        return $names;
    }
}
