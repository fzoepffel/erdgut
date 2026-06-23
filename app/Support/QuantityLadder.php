<?php

namespace App\Support;

/**
 * Builds the list of selectable quantities ("rungs") for the storefront
 * quantity stepper / picker dropdown.
 *
 * Counts (piece/bunch) list every unit — they're naturally small. Mass and
 * volume use the fine `step` in the everyday range, then grow the increment
 * so a large lot (e.g. 50 kg at a 50 g step) yields a few dozen sensible
 * options instead of ~1000 entries crammed into a single native <select>.
 */
class QuantityLadder
{
    /**
     * @return list<int> ordered canonical quantities, excluding 0
     */
    public static function values(int $step, int $cap, bool $isCount = false): array
    {
        if ($step <= 0 || $cap < $step) {
            return [];
        }

        $values = [];
        for ($q = $step; $q <= $cap;) {
            $values[] = $q;

            if ($isCount) {
                $q += $step;

                continue;
            }

            // Progressive increment keyed off how many fine steps deep we are:
            // fine to 20 steps, then ×5, then ×20.
            $mult = match (true) {
                $q >= 100 * $step => 20,
                $q >= 20 * $step => 5,
                default => 1,
            };
            $q += $step * $mult;
        }

        // Always offer the exact remaining stock as the final rung so a
        // customer can take everything that's left, even when the coarse
        // increments would otherwise skip past it.
        if (! $isCount && end($values) !== $cap) {
            $values[] = $cap;
        }

        return $values;
    }
}
