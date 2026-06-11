<?php

namespace Tests\Unit\Support;

use App\Support\BetaSampler;
use PHPUnit\Framework\TestCase;

class BetaSamplerTest extends TestCase
{
    public function test_same_seed_produces_identical_draws(): void
    {
        $a = new BetaSampler(12345);
        $b = new BetaSampler(12345);

        for ($i = 0; $i < 20; $i++) {
            $this->assertSame($a->beta(2.0, 5.0), $b->beta(2.0, 5.0));
        }
    }

    public function test_draws_stay_in_unit_interval(): void
    {
        $s = new BetaSampler(7);
        for ($i = 0; $i < 200; $i++) {
            $x = $s->beta(0.5, 0.5);
            $this->assertGreaterThanOrEqual(0.0, $x);
            $this->assertLessThanOrEqual(1.0, $x);
        }
    }

    public function test_sample_mean_approximates_alpha_over_alpha_plus_beta(): void
    {
        $s = new BetaSampler(99);
        $alpha = 3.0;
        $beta = 7.0;
        $n = 5000;

        $sum = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $sum += $s->beta($alpha, $beta);
        }
        $mean = $sum / $n;

        // Expected mean = 0.3; allow generous tolerance for sampling noise.
        $this->assertEqualsWithDelta(0.3, $mean, 0.03);
    }

    public function test_wide_prior_has_more_variance_than_narrow(): void
    {
        $variance = function (int $seed, float $a, float $b): float {
            $s = new BetaSampler($seed);
            $n = 2000;
            $xs = [];
            for ($i = 0; $i < $n; $i++) {
                $xs[] = $s->beta($a, $b);
            }
            $mean = array_sum($xs) / $n;

            return array_sum(array_map(fn ($x) => ($x - $mean) ** 2, $xs)) / $n;
        };

        // Few observations (wide) vs many (narrow), same mean 0.5.
        $wide = $variance(1, 1.0, 1.0);
        $narrow = $variance(1, 100.0, 100.0);

        $this->assertGreaterThan($narrow, $wide);
    }
}
