<?php

namespace App\Support;

/**
 * Deterministic Beta / Gamma sampler for the product-ranking Thompson bandit.
 *
 * PHP has no native Beta distribution, so we draw `Beta(α, β)` as
 * `G(α) / (G(α) + G(β))` where each `G` is a Gamma(shape, 1) draw via the
 * Marsaglia–Tsang method. Randomness comes from an *instance-local* 32-bit
 * xorshift PRNG — deliberately NOT PHP's global `mt_rand`, so two samplers are
 * independent and a given seed reproduces the exact same stream regardless of
 * any other RNG use. That reproducibility is what gives the storefront a
 * stable per-session order and makes the ranking testable.
 *
 * Construct with a seed once per request, then draw repeatedly; successive
 * draws advance this instance's own stream.
 */
class BetaSampler
{
    private int $state;

    public function __construct(int $seed)
    {
        // Fold to a non-zero 32-bit state (xorshift must not start at 0).
        $this->state = ($seed & 0xFFFFFFFF) ?: 0x9E3779B9;
    }

    /** Uniform in (0, 1), open interval (never exactly 0 or 1). */
    private function uniform(): float
    {
        // xorshift32 — all ops stay within 32 bits, well inside PHP's 64-bit int.
        $x = $this->state;
        $x ^= ($x << 13) & 0xFFFFFFFF;
        $x ^= ($x >> 17);
        $x ^= ($x << 5) & 0xFFFFFFFF;
        $this->state = $x & 0xFFFFFFFF;

        return min(1.0 - 1e-12, max(1e-12, $this->state / 0x100000000));
    }

    /** Standard normal via Box–Muller. */
    private function normal(): float
    {
        return sqrt(-2.0 * log($this->uniform())) * cos(2.0 * M_PI * $this->uniform());
    }

    /** Gamma(shape, scale=1) via Marsaglia–Tsang. */
    public function gamma(float $shape): float
    {
        if ($shape <= 0.0) {
            return 0.0;
        }

        // Boost shapes < 1 into the valid range, then correct.
        if ($shape < 1.0) {
            $u = $this->uniform();

            return $this->gamma($shape + 1.0) * $u ** (1.0 / $shape);
        }

        $d = $shape - 1.0 / 3.0;
        $c = 1.0 / sqrt(9.0 * $d);

        while (true) {
            do {
                $x = $this->normal();
                $v = 1.0 + $c * $x;
            } while ($v <= 0.0);

            $v = $v ** 3;
            $u = $this->uniform();

            if ($u < 1.0 - 0.0331 * ($x ** 4)) {
                return $d * $v;
            }
            if (log($u) < 0.5 * $x * $x + $d * (1.0 - $v + log($v))) {
                return $d * $v;
            }
        }
    }

    /** Draw from Beta(α, β) ∈ (0, 1). */
    public function beta(float $alpha, float $beta): float
    {
        $a = $this->gamma($alpha);
        $b = $this->gamma($beta);

        $sum = $a + $b;
        if ($sum <= 0.0) {
            return 0.5;
        }

        return $a / $sum;
    }
}
