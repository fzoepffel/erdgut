<?php

namespace App\Http\Controllers\Restaurant;

use App\Http\Controllers\Controller;
use App\Http\Requests\Restaurant\RestaurantOrderRequest;
use App\Mail\RestaurantOrderSubmittedMail;
use App\Models\RestaurantOrder;
use App\Services\Restaurant\RestaurantCatalogService;
use App\Services\Restaurant\RestaurantOrderService;
use App\Services\Restaurant\RestaurantStandingOrderService;
use App\Support\RestaurantOrderAdminNotifier;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * The Anfragen (request) area — deliberately separate from the shop. Season
 * items that aren't in stock, amounts beyond stock, and free-form wishes are
 * requested here (kind=request, nothing reserved); the admin accepts/declines
 * per line and prices before invoicing. The area also lists the restaurant's
 * own requests.
 */
class RestaurantRequestController extends Controller
{
    public function __construct(private readonly RestaurantOrderService $orders) {}

    public function index(Request $request, RestaurantCatalogService $catalog): View
    {
        $selectableDates = $catalog->selectableDates();

        $requested = (string) $request->query('date', '');
        $selectedDate = $selectableDates
            ->first(fn (array $row): bool => $row['date']->toDateString() === $requested)['date']
            ?? $selectableDates->first()['date']
            ?? null;

        // Season rows: in their window but not in stock on the selected day —
        // exactly the set a restaurant can sensibly request.
        $allRows = $selectedDate instanceof Carbon
            ? $catalog->productsForDate($selectedDate)
            : collect();
        $seasonRows = $allRows->filter(fn (array $row): bool => ! $row['inStock'])->values();

        // Shop handoff ("Mehr benötigt?"): the product is usually IN stock — the
        // restaurant wants more than the shop allows. Surface its row here too so
        // the prefilled quantity has something to attach to.
        $prefillProductId = (int) $request->query('product', 0);
        if ($prefillProductId > 0
            && ! $seasonRows->contains(fn (array $row): bool => $row['product']->id === $prefillProductId)) {
            $extra = $allRows->first(fn (array $row): bool => $row['product']->id === $prefillProductId);
            if ($extra !== null) {
                $seasonRows = $seasonRows->prepend($extra)->values();
            }
        }

        $recurrenceWeeks = $selectedDate instanceof Carbon
            ? $catalog->recurrenceWeeks($selectedDate)
            : collect();

        // The full request history lives on its own page; here just the count
        // for the compact link band (the page stays focused on asking).
        $myRequestsCount = RestaurantOrder::query()
            ->where('user_id', $request->user()->id)
            ->requests()
            ->count();

        return view('restaurant.requests.index', [
            'selectableDates' => $selectableDates,
            'selectedDate' => $selectedDate,
            'seasonRows' => $seasonRows,
            'recurrenceWeeks' => $recurrenceWeeks,
            'myRequestsCount' => $myRequestsCount,
            'profile' => $request->user()->restaurantProfile,
            // Shop handoff: /anfragen?product=<id>&qty=<canonical> preselects.
            'prefillProductId' => $prefillProductId,
            'prefillQty' => (float) $request->query('qty', 0),
        ]);
    }

    /** The restaurant's full request history (compact link target). */
    public function list(Request $request): View
    {
        $requests = RestaurantOrder::query()
            ->where('user_id', $request->user()->id)
            ->requests()
            ->withCount('items')
            ->latest()
            ->paginate(20);

        return view('restaurant.requests.list', ['requests' => $requests]);
    }

    public function store(RestaurantOrderRequest $request, RestaurantStandingOrderService $standing): RedirectResponse
    {
        $items = $request->validated('items');

        $order = $this->orders->submitRequest($request->user(), [
            'items' => $items,
            'expected_delivery_date' => $request->validated('expected_delivery_date'),
            'delivery_street' => $request->validated('delivery_street'),
            'delivery_postal_code' => $request->validated('delivery_postal_code'),
            'delivery_city' => $request->validated('delivery_city'),
            'delivery_notes' => $request->validated('delivery_notes'),
            'locale' => app()->getLocale(),
        ]);

        // Weekly request lines create a Pending standing order; it activates
        // when the admin confirms this request (RestaurantOrderService::confirm).
        $weeklyLines = array_values(array_filter(
            $items,
            fn ($line) => ($line['recurrence'] ?? 'one_time') === 'weekly',
        ));
        if ($weeklyLines !== []) {
            $standing->createFromOrder($order, $weeklyLines);
        }

        try {
            Mail::to($request->user()->email)
                ->locale($order->locale ?? app()->getLocale())
                ->queue(new RestaurantOrderSubmittedMail($order));
        } catch (\Throwable $e) {
            report($e);
        }
        RestaurantOrderAdminNotifier::submitted($order);

        return redirect()
            ->route('app.restaurant.orders.show', $order)
            ->with('status', 'restaurant-request-submitted');
    }
}
