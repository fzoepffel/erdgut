<?php

namespace App\Http\Requests;

use App\Enums\ProductUnit;
use App\Models\Product;
use App\Services\Shop\ShopOrderService;
use App\Support\PickupLocations;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates an anonymous shop checkout submission.
 *
 * The storefront posts a per-product quantity map (`quantities[ID] = qty`,
 * canonical units) plus the customer + fulfilment fields. We normalize
 * the map into a clean `items` array (dropping zero/blank lines), then
 * validate: each product exists and is catalog-visible, the chosen date
 * is still on the inventory-driven menu, fulfilment fields match the
 * mode, and consent is given. Per-product step + remaining-stock caps
 * run in `withValidator` once the basic shape is known.
 */
class ShopOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Anyone with the link can buy — no auth (guest checkout).
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if (! $this->has('payment_method') || (string) $this->input('payment_method') === '') {
            $merge['payment_method'] = 'online';
        }

        // Normalize the storefront's `quantities[productId] => qty` map
        // into a clean list of `{product_id, quantity}` lines, dropping
        // zero / blank entries. Tests may post `items` directly — leave
        // those untouched.
        if (! $this->has('items') && $this->has('quantities')) {
            $quantities = (array) $this->input('quantities', []);
            $items = [];
            foreach ($quantities as $productId => $quantity) {
                $qty = (float) $quantity;
                if ($qty > 0) {
                    $items[] = [
                        'product_id' => (int) $productId,
                        'quantity' => $qty,
                    ];
                }
            }
            $merge['items'] = $items;
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Whitelist the picked date against the live inventory-driven
        // menu so a stale form can't persist a date that's no longer on
        // offer. No hardcoded weekday filter.
        $availableDates = app(ShopOrderService::class)
            ->availableDates()
            ->map(fn (array $row): string => $row['date']->toDateString())
            ->all();

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'required_if:fulfillment_mode,delivery', 'string', 'max:40'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],

            'expected_delivery_date' => ['required', 'date', Rule::in($availableDates)],
            'fulfillment_mode' => ['required', Rule::in(['pickup', 'delivery'])],
            'payment_method' => ['required', Rule::in(['online', 'in_person'])],

            'delivery_street' => ['nullable', 'required_if:fulfillment_mode,delivery', 'string', 'max:160'],
            'delivery_postal_code' => ['nullable', 'required_if:fulfillment_mode,delivery', 'string', 'max:16'],
            'delivery_city' => ['nullable', 'required_if:fulfillment_mode,delivery', 'string', 'max:120'],
            'delivery_notes' => ['nullable', 'string', 'max:500'],

            'pickup_location' => [
                'nullable',
                'required_if:fulfillment_mode,pickup',
                Rule::in(PickupLocations::keys()),
            ],

            'consent' => ['accepted'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            // Don't double-report once the basic item shape failed.
            if ($v->errors()->hasAny(['items', 'expected_delivery_date'])) {
                return;
            }

            $items = (array) $this->input('items', []);
            $mode = (string) $this->input('fulfillment_mode');
            $payMethod = (string) $this->input('payment_method');

            // Pay-in-person is only available with self-pickup.
            if ($payMethod === 'in_person' && $mode !== 'pickup') {
                $v->errors()->add('payment_method', __('shop.errors.in_person_pickup_only'));
            }

            $deliveryDate = (string) $this->input('expected_delivery_date');
            $date = $deliveryDate !== '' ? Carbon::parse($deliveryDate) : null;

            // Load the referenced products once and validate each line's
            // visibility, quantity step, and remaining stock on the date.
            $productIds = collect($items)->pluck('product_id')->map(fn ($id): int => (int) $id)->all();
            $products = Product::query()->whereIn('id', $productIds)->get()->keyBy('id');

            $step = (int) config('shop.quantity_step', 250);

            foreach ($items as $index => $line) {
                $productId = (int) ($line['product_id'] ?? 0);
                $quantity = (float) ($line['quantity'] ?? 0);
                $product = $products->get($productId);

                if ($product === null) {
                    continue; // exists rule already flagged it
                }

                // Catalog visibility — can't buy hidden/unavailable items.
                if (! ($product->is_available && $product->is_published)) {
                    $v->errors()->add(
                        "items.$index.product_id",
                        __('shop.errors.product_unavailable', ['name' => $product->name])
                    );

                    continue;
                }

                // Quantity step per unit kind: mass/volume step by the
                // configured increment; piece/bunch must be whole counts.
                if ($product->unit === ProductUnit::Piece || $product->unit === ProductUnit::Bunch) {
                    if (abs($quantity - round($quantity)) > 1e-6) {
                        $v->errors()->add(
                            "items.$index.quantity",
                            __('shop.errors.quantity_whole', ['name' => $product->name])
                        );

                        continue;
                    }
                } elseif (abs(fmod($quantity, $step)) > 1e-6) {
                    $v->errors()->add(
                        "items.$index.quantity",
                        __('shop.errors.quantity_step', ['name' => $product->name, 'step' => $step])
                    );

                    continue;
                }

                // Remaining-stock cap for the chosen date.
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
