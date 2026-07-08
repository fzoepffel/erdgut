<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProductUnit;
use App\Enums\RestaurantVerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminCreateRestaurantRequestRequest;
use App\Models\Product;
use App\Models\RestaurantProfile;
use App\Models\User;
use App\Services\Restaurant\RestaurantOrderService;
use App\Services\Restaurant\RestaurantStandingOrderService;
use App\Support\AuditLogger;
use App\Support\RestaurantAccountProvisioner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Admin-entered B2B requests (Anfragen) on behalf of a restaurant: phone
 * requests, walk-ins, internal bookkeeping. The created request lands in the
 * normal Anfragen queue as Submitted and is settled with the existing
 * accept/decline/confirm machinery. Unlike restaurant-submitted requests, NO
 * mail is sent on creation: the admin is in direct contact with the customer,
 * and notifying themselves is pointless.
 */
class AdminRestaurantRequestController extends Controller
{
    public function __construct(
        private readonly RestaurantOrderService $orders,
        private readonly RestaurantStandingOrderService $standing,
        private readonly RestaurantAccountProvisioner $accounts,
    ) {}

    public function create(): View
    {
        $restaurantAccounts = RestaurantProfile::query()
            ->where('verification_status', RestaurantVerificationStatus::Verified->value)
            ->with('user')
            ->get()
            ->map(fn ($p) => [
                'user_id' => $p->user_id,
                'label' => trim(($p->business_name ?: ($p->user?->name ?? '')).' · '.($p->user?->email ?? '')),
                'name' => $p->contact_name ?: ($p->user?->name ?? ''),
                'email' => $p->user?->email ?? '',
                'street' => $p->billing_street ?? '',
                'postal' => $p->billing_postal_code ?? '',
                'city' => $p->billing_city ?? '',
            ])
            ->sortBy('label')
            ->values();

        // One option per orderable grade. No stock or price data: requests
        // ignore stock, and the service snapshots business prices itself.
        $productOptions = Product::query()
            ->visibleInCatalog()
            ->with(['translations', 'producer.translations', 'variants'])
            ->get()
            ->flatMap(function (Product $product): array {
                $displayUnit = $product->price_display_unit ?? $product->unit?->defaultDisplayUnit() ?? 'piece';
                $factor = $product->unit?->canonicalUnitsPerDisplay($displayUnit) ?? 1;
                $producer = $product->producer?->name;

                $option = fn (?int $variantId, ?string $gradeLabel): array => [
                    'key' => $product->id.':'.($variantId ?? 0),
                    'product_id' => $product->id,
                    'variant_id' => $variantId,
                    'label' => $product->name
                        .($gradeLabel !== null && $gradeLabel !== '' ? ' · '.$gradeLabel : '')
                        .($producer ? ' ('.$producer.')' : ''),
                    'unit_symbol' => ProductUnit::displaySymbol($displayUnit),
                    'factor' => $factor,
                ];

                $variants = $product->variants->where('is_active', true)->values();
                if ($variants->isEmpty()) {
                    return [$option(null, null)];
                }

                return $variants->map(fn ($v): array => $option($v->id, $v->label))->all();
            })
            ->sortBy('label', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return view('admin.restaurant-orders.create', [
            'restaurantAccounts' => $restaurantAccounts,
            'productOptions' => $productOptions,
        ]);
    }

    public function store(AdminCreateRestaurantRequestRequest $request): RedirectResponse
    {
        $accountId = (int) ($request->validated('restaurant_user_id') ?? 0);
        $user = $accountId > 0
            ? User::findOrFail($accountId)
            : $this->accounts->findOrCreateVerified(
                (string) $request->validated('name'),
                (string) $request->validated('email'),
                [
                    'street' => (string) $request->validated('delivery_street'),
                    'postal_code' => (string) $request->validated('delivery_postal_code'),
                    'city' => (string) $request->validated('delivery_city'),
                ],
                (int) $request->user()->id,
            );

        $items = $request->validated('items');

        try {
            $order = $this->orders->submitRequest($user, [
                'items' => $items,
                'expected_delivery_date' => $request->validated('expected_delivery_date'),
                'delivery_street' => $request->validated('delivery_street'),
                'delivery_postal_code' => $request->validated('delivery_postal_code'),
                'delivery_city' => $request->validated('delivery_city'),
                'delivery_notes' => $request->validated('delivery_notes'),
                'locale' => $user->locale ?? app()->getLocale(),
            ]);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        } catch (InvalidArgumentException) {
            // Every line was dropped (e.g. unpublished between render and submit).
            return back()->withInput()->withErrors(['items' => __('restaurant.admin_create_request_no_lines')]);
        }

        // Weekly lines become a Pending standing order; confirming the request
        // in the queue activates it (activateStandingIfPending).
        $weeklyLines = array_values(array_filter(
            $items,
            fn (array $line): bool => ($line['recurrence'] ?? 'one_time') === 'weekly',
        ));
        $standingOrder = $weeklyLines !== [] ? $this->standing->createFromOrder($order, $weeklyLines) : null;

        AuditLogger::log(
            entityType: 'restaurant_order',
            entityId: (int) $order->id,
            action: 'create_request',
            fromState: null,
            toState: $order->status->value,
            metadata: ['channel' => 'admin', 'standing_order_id' => $standingOrder?->id],
            actorUserId: (int) $request->user()->id,
        );

        return redirect()
            ->route('app.admin.restaurant-orders.show', $order)
            ->with('status', __('restaurant.admin_create_request_flash', ['id' => $order->id]));
    }
}
