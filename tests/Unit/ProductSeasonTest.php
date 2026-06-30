<?php

namespace Tests\Unit;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProductSeasonTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $attrs): Product
    {
        return Product::factory()->create($attrs);
    }

    public function test_non_seasonal_product_is_always_in_season(): void
    {
        $p = $this->product(['is_seasonal' => false]);

        $this->assertTrue($p->isInSeason(Carbon::create(2026, 1, 15)));
        $this->assertTrue($p->isInSeason(Carbon::create(2026, 7, 15)));
    }

    public function test_seasonal_without_window_is_in_season(): void
    {
        $p = $this->product([
            'is_seasonal' => true,
            'season_start_month' => null,
            'season_start_day' => null,
            'season_end_month' => null,
            'season_end_day' => null,
        ]);

        $this->assertTrue($p->isInSeason(Carbon::create(2026, 3, 1)));
    }

    public function test_non_wrapping_window_boundaries_are_inclusive(): void
    {
        // Asparagus: 1 Apr – 24 Jun
        $p = $this->product([
            'is_seasonal' => true,
            'season_start_month' => 4, 'season_start_day' => 1,
            'season_end_month' => 6, 'season_end_day' => 24,
        ]);

        $this->assertTrue($p->isInSeason(Carbon::create(2026, 4, 1)));   // start boundary
        $this->assertTrue($p->isInSeason(Carbon::create(2026, 5, 1)));   // mid
        $this->assertTrue($p->isInSeason(Carbon::create(2026, 6, 24)));  // end boundary
        $this->assertFalse($p->isInSeason(Carbon::create(2026, 3, 31))); // day before
        $this->assertFalse($p->isInSeason(Carbon::create(2026, 6, 25))); // day after
        $this->assertFalse($p->isInSeason(Carbon::create(2026, 7, 1)));
    }

    public function test_wrapping_window_spans_year_boundary(): void
    {
        // 15 Nov – 28 Feb
        $p = $this->product([
            'is_seasonal' => true,
            'season_start_month' => 11, 'season_start_day' => 15,
            'season_end_month' => 2, 'season_end_day' => 28,
        ]);

        $this->assertTrue($p->isInSeason(Carbon::create(2026, 12, 1)));
        $this->assertTrue($p->isInSeason(Carbon::create(2026, 1, 15)));
        $this->assertTrue($p->isInSeason(Carbon::create(2026, 11, 15))); // start boundary
        $this->assertTrue($p->isInSeason(Carbon::create(2026, 2, 28)));  // end boundary
        $this->assertFalse($p->isInSeason(Carbon::create(2026, 7, 1)));
        $this->assertFalse($p->isInSeason(Carbon::create(2026, 11, 14)));
        $this->assertFalse($p->isInSeason(Carbon::create(2026, 3, 1)));
    }

    public function test_scope_matches_helper_on_a_fixed_date(): void
    {
        $on = Carbon::create(2026, 5, 10);

        $nonSeasonal = $this->product(['is_seasonal' => false]);
        $inWindow = $this->product([
            'is_seasonal' => true,
            'season_start_month' => 4, 'season_start_day' => 1,
            'season_end_month' => 6, 'season_end_day' => 24,
        ]);
        $outOfWindow = $this->product([
            'is_seasonal' => true,
            'season_start_month' => 9, 'season_start_day' => 1,
            'season_end_month' => 10, 'season_end_day' => 31,
        ]);
        $wrapInWindow = $this->product([
            'is_seasonal' => true,
            'season_start_month' => 11, 'season_start_day' => 1,
            'season_end_month' => 6, 'season_end_day' => 1,
        ]);
        $seasonalNoWindow = $this->product([
            'is_seasonal' => true,
            'season_start_month' => null, 'season_start_day' => null,
            'season_end_month' => null, 'season_end_day' => null,
        ]);

        $scopeIds = Product::query()->inSeason($on)->pluck('id')->sort()->values()->all();

        $expected = Product::query()->get()
            ->filter(fn (Product $p) => $p->isInSeason($on))
            ->pluck('id')->sort()->values()->all();

        $this->assertSame($expected, $scopeIds);

        // Sanity: the in-window / wrap / non-seasonal / no-window ones are in;
        // the out-of-window one is not.
        $this->assertContains($inWindow->id, $scopeIds);
        $this->assertContains($wrapInWindow->id, $scopeIds);
        $this->assertContains($nonSeasonal->id, $scopeIds);
        $this->assertContains($seasonalNoWindow->id, $scopeIds);
        $this->assertNotContains($outOfWindow->id, $scopeIds);
    }

    public function test_season_label(): void
    {
        $p = $this->product([
            'is_seasonal' => true,
            'season_start_month' => 4, 'season_start_day' => 1,
            'season_end_month' => 6, 'season_end_day' => 24,
        ]);

        $this->assertSame('1.4.–24.6.', $p->seasonLabel());

        $this->assertNull($this->product(['is_seasonal' => false])->seasonLabel());
    }
}
