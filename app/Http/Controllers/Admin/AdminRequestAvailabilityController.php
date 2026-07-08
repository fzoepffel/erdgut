<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Anfrage-Sortiment: one switchboard for what restaurants can currently
 * request. Every catalog product in one list — season window, its
 * visibility rule, and the EFFECTIVE state today (green = requestable).
 * Clicking a product toggles it with an optional expiry: turn an available
 * product off "until X" (it falls back to the season logic afterwards) or
 * feature an unavailable one "until Y". Permanent settings live on the
 * product form; this page is for the day-to-day.
 */
class AdminRequestAvailabilityController extends Controller
{
    public function index(): View
    {
        $this->authorize('admin');

        $products = Product::query()
            ->visibleInCatalog()
            ->with(['translations', 'producer.translations'])
            ->get()
            ->sortBy(fn (Product $p) => mb_strtolower($p->name), SORT_NATURAL)
            ->values()
            ->map(function (Product $p): array {
                $requestable = $p->isRequestableOn();

                return [
                    'product' => $p,
                    'requestable' => $requestable,
                    'visibility' => (string) ($p->request_visibility ?? 'auto'),
                    'effectiveVisibility' => $p->requestVisibilityOn(),
                    'until' => $p->request_visibility_until,
                    // For the modal: what would happen WITHOUT an override.
                    'seasonEnd' => $p->currentSeasonEnd(),
                    'nextStart' => $p->nextSeasonStart(),
                ];
            });

        return view('admin.request-availability.index', ['rows' => $products]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $this->authorize('admin');

        $validated = $request->validate([
            'action' => ['required', Rule::in(['on', 'off', 'auto'])],
            'until' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        $product->forceFill(match ($validated['action']) {
            'on' => [
                'request_visibility' => 'always',
                'request_visibility_until' => $validated['until'] ?? null,
            ],
            'off' => [
                'request_visibility' => 'never',
                'request_visibility_until' => $validated['until'] ?? null,
            ],
            'auto' => [
                'request_visibility' => 'auto',
                'request_visibility_until' => null,
            ],
        })->save();

        return redirect()
            ->route('app.admin.request-availability.index')
            ->with('status', __('products.request_availability_saved', ['name' => $product->name]));
    }
}
