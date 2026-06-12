<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\ShopOrderRequest;
use App\Models\Product;
use App\Services\Shop\ShopOrderService;
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
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],

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
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if ($v->errors()->hasAny(['items', 'expected_delivery_date'])) {
                return;
            }

            $items = (array) $this->input('items', []);

            $deliveryDate = (string) $this->input('expected_delivery_date');
            $date = $deliveryDate !== '' ? Carbon::parse($deliveryDate) : null;

            $productIds = collect($items)->pluck('product_id')->map(fn ($id): int => (int) $id)->all();
            $products = Product::query()->whereIn('id', $productIds)->get()->keyBy('id');

            foreach ($items as $index => $line) {
                $productId = (int) ($line['product_id'] ?? 0);
                $quantity = (float) ($line['quantity'] ?? 0);
                $product = $products->get($productId);

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
                    $remaining = $product->availableOnDate($date);
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
        });
    }
}
