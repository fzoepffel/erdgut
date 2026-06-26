<?php

namespace Tests\Unit\Support;

use App\Support\QuantityLadder;
use Tests\TestCase;

class QuantityLadderTest extends TestCase
{
    public function test_counts_list_every_unit(): void
    {
        $this->assertSame([1, 2, 3, 4, 5], QuantityLadder::values(1, 5, true));
        $this->assertSame(range(1, 12), QuantityLadder::values(1, 12, true));
    }

    public function test_returns_empty_when_cap_below_step(): void
    {
        $this->assertSame([], QuantityLadder::values(50, 0));
        $this->assertSame([], QuantityLadder::values(50, 40));
        $this->assertSame([], QuantityLadder::values(0, 1000));
    }

    public function test_small_range_is_fully_fine(): void
    {
        $this->assertSame([50, 100, 150, 200, 250, 300], QuantityLadder::values(50, 300));
    }

    public function test_large_lot_is_bounded_but_starts_fine(): void
    {
        $values = QuantityLadder::values(50, 50000);

        // Regression for the ~1000-option <select>: the ladder must stay small.
        $this->assertLessThan(100, count($values));

        // Fine 50 g steps remain available in the everyday range.
        $this->assertSame([50, 100, 150, 200], array_slice($values, 0, 4));

        // Strictly increasing + unique.
        $sorted = $values;
        sort($sorted);
        $this->assertSame($sorted, $values);
        $this->assertSame(array_values(array_unique($values)), $values);

        // The exact remaining stock is always reachable as the last rung.
        $this->assertSame(50000, end($values));
    }

    public function test_exact_cap_is_always_offered_even_when_coarse_steps_skip_it(): void
    {
        $values = QuantityLadder::values(50, 12300);
        $this->assertSame(12300, end($values));
    }
}
