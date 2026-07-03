<?php

namespace App\Http\Requests\Admin;

use App\Enums\CustomerType;
use App\Enums\ShopStockAudience;
use App\Models\Product;
use App\Models\User;
use App\Services\Admin\InventoryService;
use App\Support\PickupLocations;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates an admin-entered manual shop order.
 *
 * Diverges from the public {@see ShopOrderRequest} in
 * several intentional ways:
 *  - name / email / phone are nullable (contact details often unavailable for
 *    restaurant and trade orders negotiated by phone).
 *  - No `consent` checkbox — admin is acting on behalf of the buyer.
 *  - `expected_delivery_date` accepts any valid date; the availability-horizon
 *    restriction is removed (admin knows what they're doing, and the stock
 *    check inside {@see ShopOrderService::createOrder()} will
 *    fail naturally if there is no supply on the chosen date).
 *  - `customer_type` field for B2C / B2B classification.
 *  - Per-line gross totals and VAT rates for negotiated B2B pricing.
 *  - `order_source` is hardcoded to 'admin' by the controller — not a form field.
 *
 * Quantity-step rules and catalog-visibility checks are kept identical so
 * the service receives well-formed data.
 */
class AdminCreateShopOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Contact details are optional — trade orders often have no individual contact.
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],

            'items' => ['required', 'array', 'min:1'],
            // product_id is optional: a line with no product but a `name` is an
            // off-catalog custom line (cross-checked in withValidator()).
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.name' => ['nullable', 'string', 'max:160'],
            'items.*.unit' => ['nullable', 'string', Rule::in(['piece', 'bunch', 'kg', 'g', 'l', 'ml'])],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.line_total_eur' => ['required', 'numeric', 'min:0', 'max:99999.99'],
            'items.*.vat_rate_percent' => ['required', 'integer', Rule::in([7, 19])],

            // Any future (or even past) date — admin takes responsibility.
            'expected_delivery_date' => ['required', 'date'],

            'fulfillment_mode' => ['required', Rule::in(['pickup', 'delivery'])],
            // payment_method is hardcoded to 'in_person' by the controller —
            // admin orders are always pay-at-pickup, no SumUp widget needed.

            'delivery_street' => ['nullable', 'required_if:fulfillment_mode,delivery', 'string', 'max:160'],
            'delivery_postal_code' => ['nullable', 'required_if:fulfillment_mode,delivery', 'string', 'max:16'],
            'delivery_city' => ['nullable', 'required_if:fulfillment_mode,delivery', 'string', 'max:120'],
            'delivery_notes' => ['nullable', 'string', 'max:500'],

            'coupon_code' => ['nullable', 'string', 'max:64'],

            'pickup_location' => [
                'nullable',
                'required_if:fulfillment_mode,pickup',
                Rule::in(PickupLocations::keys()),
            ],

            'customer_type' => ['nullable', Rule::in(['private', 'business'])],

            // Recurring weekly B2B delivery: routes through the restaurant
            // order machinery (needs a verified restaurant account).
            'restaurant_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'recurring' => ['nullable', 'boolean'],
            'recurring_until' => ['nullable', 'date', 'after:expected_delivery_date'],

            'delivery_fee_eur' => ['nullable', 'required_if:fulfillment_mode,delivery', 'numeric', 'min:0', 'max:999.99'],
            'delivery_fee_vat_rate_percent' => ['nullable', 'required_if:fulfillment_mode,delivery', 'integer', Rule::in([7, 19])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if ($v->errors()->hasAny(['items', 'expected_delivery_date'])) {
                return;
            }

            $items = (array) $this->input('items', []);

            // Recurring weekly delivery: only for business customers tied to a
            // verified restaurant account, delivered (not picked up), and with
            // catalog lines only — the B2B machinery prices and materializes it.
            if ($this->boolean('recurring')) {
                if ($this->input('customer_type') !== CustomerType::Business->value) {
                    $v->errors()->add('recurring', __('shop.admin.errors.recurring_business_only'));
                }
                if ($this->input('fulfillment_mode') !== 'delivery') {
                    $v->errors()->add('recurring', __('shop.admin.errors.recurring_delivery_only'));
                }
                // Either an existing verified account — or enough contact data
                // (name + email) to auto-create one from the customer details.
                if ((int) $this->input('restaurant_user_id') > 0) {
                    $accountUser = User::find((int) $this->input('restaurant_user_id'));
                    if ($accountUser?->restaurantProfile === null || ! $accountUser->restaurantProfile->isVerified()) {
                        $v->errors()->add('restaurant_user_id', __('shop.admin.errors.recurring_needs_account'));
                    }
                } elseif (trim((string) $this->input('name')) === '' || trim((string) $this->input('email')) === '') {
                    $v->errors()->add('restaurant_user_id', __('shop.admin.errors.recurring_needs_contact'));
                }
                foreach ($items as $index => $line) {
                    if ((int) ($line['product_id'] ?? 0) === 0) {
                        $v->errors()->add("items.$index.name", __('shop.admin.errors.recurring_no_custom_lines'));
                    }
                }
            }

            $deliveryDate = (string) $this->input('expected_delivery_date');
            $date = $deliveryDate !== '' ? Carbon::parse($deliveryDate) : null;

            $productIds = collect($items)->pluck('product_id')->map(fn ($id): int => (int) $id)->all();
            $products = Product::query()->whereIn('id', $productIds)->get()->keyBy('id');
            $audience = $this->input('customer_type') === CustomerType::Business->value
                ? ShopStockAudience::BusinessTrade
                : ShopStockAudience::Consumer;
            $inventory = app(InventoryService::class);

            foreach ($items as $index => $line) {
                $productId = (int) ($line['product_id'] ?? 0);
                $quantity = (float) ($line['quantity'] ?? 0);
                $hasName = trim((string) ($line['name'] ?? '')) !== '';

                // Every line must be either a catalog product or a named custom line.
                if ($productId === 0 && ! $hasName) {
                    $v->errors()->add(
                        "items.$index.name",
                        __('shop.admin.errors.line_needs_product_or_name'),
                    );

                    continue;
                }

                $product = $products->get($productId);

                // Custom line (no product) — nothing to stock-check.
                if ($product === null) {
                    continue;
                }

                if (! ($product->is_available && $product->is_published)) {
                    $v->errors()->add(
                        "items.$index.product_id",
                        __('shop.errors.product_unavailable', ['name' => $product->name])
                    );

                    continue;
                }

                // No quantity-step restriction for admin orders — the operator
                // may need to enter any amount (e.g. 37 g on a product normally
                // sold in 50 g increments). Stock is still checked so the admin
                // sees a clear warning if they exceed available supply.
                if ($date !== null) {
                    $remaining = $inventory->availableOnDate($product, $date, $audience);
                    if ($quantity > $remaining + 1e-6) {
                        $v->errors()->add(
                            "items.$index.quantity",
                            __('shop.errors.quantity_stock', [
                                'name' => $product->name,
                                'remaining' => $product->displayQuantity(max(0, (int) floor($remaining))),
                            ])
                        );
                    }
                }
            }

            if ($this->input('fulfillment_mode') === 'pickup') {
                $deliveryFee = $this->input('delivery_fee_eur');
                if ($deliveryFee !== null && $deliveryFee !== '' && (float) $deliveryFee > 0) {
                    $v->errors()->add(
                        'delivery_fee_eur',
                        __('shop.admin.errors.delivery_fee_on_pickup'),
                    );
                }
            }
        });
    }
}
