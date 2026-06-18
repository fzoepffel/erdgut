<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductPriceRule;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Per-product pricing calendar — Phase 1 manages date/period overrides for the
 * default scope (no variant, all customer types). Rules created here are kept
 * non-overlapping so each day has exactly one override; saving a range splits
 * or trims any existing rules it overlaps.
 */
class ProductPriceRuleController extends Controller
{
    public function calendar(Request $request, Product $product): View
    {
        $this->authorize('admin');

        $month = $this->resolveMonth((string) $request->query('month', ''));

        return view('admin.products.pricing', [
            'product' => $product,
            // Prices are shown + entered in the display unit (€/kg), matching
            // the product form; stored canonical (€/g) under the hood.
            'defaultPrice' => $product->displayUnitPrice(),
            'displayUnit' => $product->price_display_unit ?? $product->unit?->defaultDisplayUnit(),
            'calendar' => $this->calendarPayload($product, $month),
        ]);
    }

    /** Canonical units per display unit (1000 for kg/L, 1 for piece/g/…). */
    private function displayFactor(Product $product): int
    {
        if ($product->unit === null) {
            return 1;
        }
        $displayUnit = $product->price_display_unit ?? $product->unit->defaultDisplayUnit();
        $factor = $product->unit->canonicalUnitsPerDisplay($displayUnit);

        return $factor > 0 ? $factor : 1;
    }

    public function store(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('admin');

        $validated = $request->validate([
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'unit_price' => ['required', 'numeric', 'gt:0'],
            'label' => ['nullable', 'string', 'max:120'],
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $start = Carbon::parse($validated['starts_on'])->startOfDay();
        $end = Carbon::parse($validated['ends_on'])->startOfDay();

        if ($start->lt(Carbon::today())) {
            throw ValidationException::withMessages(['starts_on' => [__('admin.product_pricing.validation_past')]]);
        }

        // The admin enters a display-unit price (€/kg); store canonical (€/g).
        $canonical = round((float) $validated['unit_price'] / $this->displayFactor($product), 4);

        DB::transaction(function () use ($product, $start, $end, $validated, $request, $canonical): void {
            $this->clearRange($product, $start, $end);

            ProductPriceRule::create([
                'product_id' => $product->id,
                'starts_on' => $start->toDateString(),
                'ends_on' => $end->toDateString(),
                'unit_price' => $canonical,
                'label' => $validated['label'] ?? null,
                'is_active' => true,
                'created_by_user_id' => $request->user()?->id,
            ]);
        });

        return redirect()
            ->route('app.admin.products.pricing', ['product' => $product->id, 'month' => $validated['month'] ?? $start->format('Y-m')])
            ->with('status', 'price-rule-saved');
    }

    public function destroy(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('admin');

        $validated = $request->validate([
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $start = Carbon::parse($validated['starts_on'])->startOfDay();
        $end = Carbon::parse($validated['ends_on'])->startOfDay();

        DB::transaction(fn () => $this->clearRange($product, $start, $end));

        return redirect()
            ->route('app.admin.products.pricing', ['product' => $product->id, 'month' => $validated['month'] ?? $start->format('Y-m')])
            ->with('status', 'price-rule-cleared');
    }

    /**
     * Remove the [start, end] window from any overlapping date-override rules
     * (default scope), trimming/splitting the remainders so prices outside the
     * window are preserved.
     */
    private function clearRange(Product $product, Carbon $start, Carbon $end): void
    {
        $overlapping = $this->dateOverrideRules($product)
            ->where('starts_on', '<=', $end->toDateString())
            ->where('ends_on', '>=', $start->toDateString())
            ->get();

        foreach ($overlapping as $rule) {
            $leftEnd = $start->copy()->subDay();
            $rightStart = $end->copy()->addDay();

            if ($rule->starts_on->lte($leftEnd)) {
                ProductPriceRule::create([
                    'product_id' => $product->id,
                    'starts_on' => $rule->starts_on->toDateString(),
                    'ends_on' => $leftEnd->toDateString(),
                    'unit_price' => $rule->unit_price,
                    'label' => $rule->label,
                    'is_active' => true,
                    'created_by_user_id' => $rule->created_by_user_id,
                ]);
            }
            if ($rule->ends_on->gte($rightStart)) {
                ProductPriceRule::create([
                    'product_id' => $product->id,
                    'starts_on' => $rightStart->toDateString(),
                    'ends_on' => $rule->ends_on->toDateString(),
                    'unit_price' => $rule->unit_price,
                    'label' => $rule->label,
                    'is_active' => true,
                    'created_by_user_id' => $rule->created_by_user_id,
                ]);
            }

            $rule->delete();
        }
    }

    /** Date-override rules for the default scope (no variant, all customers). */
    private function dateOverrideRules(Product $product)
    {
        return $product->priceRules()
            ->whereNull('variant_id')
            ->whereNull('customer_type')
            ->whereNull('min_quantity')
            ->whereNotNull('starts_on')
            ->whereNotNull('ends_on')
            ->where('is_active', true);
    }

    /**
     * Build the month grid with each in-month day's override price + colour
     * level (0 default; negative = cheaper/green; positive = dearer/red).
     */
    private function calendarPayload(Product $product, Carbon $month): array
    {
        $default = (float) $product->unit_price; // canonical, for the colour ratio
        $factor = $this->displayFactor($product); // canonical → display for the label
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        // iso => override unit_price for the visible range.
        $priceByIso = [];
        foreach ($this->dateOverrideRules($product)->get() as $rule) {
            $cursor = $rule->starts_on->copy();
            while ($cursor->lte($rule->ends_on)) {
                if ($cursor->betweenIncluded($start, $end)) {
                    $priceByIso[$cursor->toDateString()] = (float) $rule->unit_price;
                }
                $cursor->addDay();
            }
        }

        $today = Carbon::today()->toDateString();
        $gridStart = $start->copy()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $end->copy()->endOfWeek(Carbon::SUNDAY);

        $weeks = [];
        $cursor = $gridStart->copy();
        while ($cursor->lte($gridEnd)) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $iso = $cursor->toDateString();
                $price = $priceByIso[$iso] ?? null;
                $week[] = [
                    'iso' => $iso,
                    'day' => (int) $cursor->day,
                    'in_month' => $cursor->month === $month->month,
                    'is_today' => $iso === $today,
                    'is_disabled' => $iso < $today,
                    // Display-unit price for the label; level from canonical ratio.
                    'price' => $price === null ? null : round($price * $factor, 2),
                    'level' => $price === null ? 0 : $this->level($price, $default),
                ];
                $cursor->addDay();
            }
            $weeks[] = $week;
        }

        return [
            'month' => $month->format('Y-m'),
            'label' => $month->translatedFormat('F Y'),
            'prev_month' => $month->copy()->subMonth()->format('Y-m'),
            'next_month' => $month->copy()->addMonth()->format('Y-m'),
            'weekdays' => [
                __('admin.shop_closures.weekday_mon'),
                __('admin.shop_closures.weekday_tue'),
                __('admin.shop_closures.weekday_wed'),
                __('admin.shop_closures.weekday_thu'),
                __('admin.shop_closures.weekday_fri'),
                __('admin.shop_closures.weekday_sat'),
                __('admin.shop_closures.weekday_sun'),
            ],
            'weeks' => $weeks,
        ];
    }

    /** Map a price vs default to a colour level: -4..-1 cheaper, 0, 1..4 dearer. */
    private function level(float $price, float $default): int
    {
        if ($default <= 0) {
            return 0;
        }
        $pct = ($price - $default) / $default;
        $sign = $pct <=> 0;
        $mag = abs($pct);
        $bucket = match (true) {
            $mag >= 0.50 => 4,
            $mag >= 0.30 => 3,
            $mag >= 0.15 => 2,
            $mag > 0.0 => 1,
            default => 0,
        };

        return $sign * $bucket;
    }

    private function resolveMonth(string $month): Carbon
    {
        if ($month !== '' && preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
            return Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        }

        return Carbon::today()->startOfMonth();
    }
}
