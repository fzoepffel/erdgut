<?php

namespace App\Support;

use App\Models\Producer;

/**
 * Turns a set of producers into the customer-facing label: names joined
 * with a middot, collapsed to a generic "several farms" label when the
 * list gets long. Order is the caller's (allocation share desc, or pivot
 * position with the primary first).
 */
class ProducerLabels
{
    public const COLLAPSE_THRESHOLD = 3;

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
     * @return array{collapsed: bool, items: list<array{id: int, name: string, url: string}>}
     */
    public static function linkData(iterable $producers): array
    {
        $items = [];
        $seen = [];
        foreach ($producers as $producer) {
            if (! $producer instanceof Producer) {
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
                'url' => route('public.shop.producers.show', $producer),
            ];
        }

        return ['collapsed' => count($items) > self::COLLAPSE_THRESHOLD, 'items' => $items];
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
