<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Services\Restaurant\RestaurantCatalogService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * B2B catalog + order builder for verified restaurants. For the selected
 * delivery date it lists products available to order (in stock for business
 * trade, or in season) at net business prices; the restaurant builds a request
 * and submits it (see RestaurantOrderController).
 */
class RestaurantCatalogController extends Controller
{
    public function __construct(private readonly RestaurantCatalogService $catalog) {}

    public function index(Request $request): View
    {
        $selectableDates = $this->catalog->selectableDates();

        $requested = (string) $request->query('date', '');
        $selectedDate = $selectableDates
            ->first(fn (array $row): bool => $row['date']->toDateString() === $requested)['date']
            ?? $selectableDates->first()['date']
            ?? null;

        $products = $selectedDate instanceof Carbon
            ? $this->catalog->productsForDate($selectedDate)
            : collect();

        return view('restaurant.catalog', [
            'selectableDates' => $selectableDates,
            'selectedDate' => $selectedDate,
            'products' => $products,
            'profile' => $request->user()->restaurantProfile,
        ]);
    }
}
