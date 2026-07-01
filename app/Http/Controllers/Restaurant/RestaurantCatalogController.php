<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Models\ProductSubcategory;
use App\Services\Restaurant\RestaurantCatalogService;
use App\Services\Shop\ProductRankingService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * B2B catalog + order builder for verified restaurants. For the selected
 * delivery date it lists products to order at net business prices, laid out
 * like the consumer shop: a delivery-day picker (promoting days with stock),
 * name/category/subcategory filters, shop-style ranking, and tabs for in-stock
 * vs. in-season-on-request products (plus a free-form special-request tab).
 */
class RestaurantCatalogController extends Controller
{
    public function __construct(
        private readonly RestaurantCatalogService $catalog,
        private readonly ProductRankingService $ranking,
    ) {}

    public function index(Request $request): View
    {
        $locale = app()->getLocale();
        $selectableDates = $this->catalog->selectableDates();

        $requested = (string) $request->query('date', '');
        $selectedDate = $selectableDates
            ->first(fn (array $row): bool => $row['date']->toDateString() === $requested)['date']
            ?? $selectableDates->first()['date']
            ?? null;

        $products = $selectedDate instanceof Carbon
            ? $this->catalog->productsForDate($selectedDate)
            : collect();

        // Upcoming weekly-delivery weeks (for the recurrence "until" picker), each
        // with per-product stock so the picker can colour weeks in stock / on request.
        $recurrenceWeeks = $selectedDate instanceof Carbon
            ? $this->catalog->recurrenceWeeks($selectedDate)
            : collect();

        // Sort like the storefront: the margin-per-impression bandit ranks the
        // orderable (in-stock) rows; the rest keep a stable seeded order. Seed is
        // per session+day so ordering is stable within a visit.
        if ($products->isNotEmpty()) {
            $seed = crc32($request->session()->getId().'|'.now()->toDateString());
            $products = $this->ranking->orderForStorefront($products, $seed);
        }

        // Grid-filter metadata (mirrors ShopController): only categories /
        // subcategories present on the day get chips, so every filter narrows.
        $availableCategories = $products
            ->map(fn (array $row): string => $row['product']->category)
            ->unique()->values()->all();
        $defaultShopCategory = count($availableCategories) === 1 ? $availableCategories[0] : '';

        $subcategoryIdsOnDay = $products
            ->map(fn (array $row): ?int => ($id = $row['product']->subcategory_id) !== null && (int) $id > 0 ? (int) $id : null)
            ->filter()->unique()->values()->all();

        $alpineSubcategories = ProductSubcategory::query()
            ->whereIn('id', $subcategoryIdsOnDay)
            ->orderBy('category')->orderBy('sort_order')
            ->get()
            ->groupBy('category')
            ->map(fn ($g) => $g->map(fn ($s) => ['id' => $s->id, 'label' => $s->label($locale)])->values()->all())
            ->all();

        // Date-picker data — all open days, each with its in-stock count so the
        // picker can promote days that actually have stock.
        $alpineDates = $selectableDates->map(fn (array $row): array => [
            'iso' => $row['date']->toDateString(),
            'label' => $row['date']->locale($locale)->isoFormat('dd, D. MMM'),
            'longLabel' => $row['date']->locale($locale)->isoFormat('dddd, D. MMMM'),
            'kw' => $row['date']->isoWeek(),
            'inStockCount' => $row['in_stock_count'],
            'cutoffLabel' => $row['cutoff_label'],
        ])->values();

        return view('restaurant.catalog', [
            'selectableDates' => $selectableDates,
            'selectedDate' => $selectedDate,
            'products' => $products,
            'recurrenceWeeks' => $recurrenceWeeks,
            'alpineDates' => $alpineDates,
            'availableCategories' => $availableCategories,
            'defaultShopCategory' => $defaultShopCategory,
            'alpineSubcategories' => $alpineSubcategories,
            'profile' => $request->user()->restaurantProfile,
        ]);
    }
}
