<?php

namespace App\Http\Requests;

use App\Enums\ProductUnit;
use App\Enums\ShopStockAudience;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Admin\InventoryService;
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
        if (! $this->has('items') && ($this->has('quantities') || $this->has('variant_quantities'))) {
            $items = [];

            // Default/"Standard" grade lines: quantities[productId] => qty.
            foreach ((array) $this->input('quantities', []) as $productId => $quantity) {
                $qty = (float) $quantity;
                if ($qty > 0) {
                    $items[] = [
                        'product_id' => (int) $productId,
                        'quantity' => $qty,
                        'variant_id' => null,
                    ];
                }
            }

            // Named grade lines: variant_quantities[variantId] => qty. Resolve
            // the owning product_id from the variant so the line is complete.
            $variantQuantities = (array) $this->input('variant_quantities', []);
            $variantQuantities = array_filter($variantQuantities, fn ($q): bool => (float) $q > 0);
            if ($variantQuantities !== []) {
                $variants = ProductVariant::query()
                    ->whereIn('id', array_map('intval', array_keys($variantQuantities)))
                    ->get(['id', 'product_id'])
                    ->keyBy('id');

                foreach ($variantQuantities as $variantId => $quantity) {
                    $variant = $variants->get((int) $variantId);
                    if ($variant !== null) {
                        $items[] = [
                            'product_id' => (int) $variant->product_id,
                            'quantity' => (float) $quantity,
                            'variant_id' => (int) $variantId,
                        ];
                    }
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
            'items.*.variant_id' => ['nullable', 'integer'],

            'expected_delivery_date' => ['required', 'date', Rule::in($availableDates)],
            'fulfillment_mode' => ['required', Rule::in(['pickup', 'delivery'])],
            'payment_method' => ['required', Rule::in(['online', 'in_person'])],

            'delivery_street' => ['nullable', 'required_if:fulfillment_mode,delivery', 'string', 'max:160'],
            'delivery_postal_code' => ['nullable', 'required_if:fulfillment_mode,delivery', 'string', 'max:16'],
            'delivery_city' => ['nullable', 'required_if:fulfillment_mode,delivery', 'string', 'max:120'],
            'delivery_notes' => ['nullable', 'string', 'max:500'],

            // Optional coupon code — re-validated server-side at order create
            // against the live subtotal + email (the typed value here is just
            // shape-checked; an invalid code is silently dropped, never blocks
            // checkout).
            'coupon_code' => ['nullable', 'string', 'max:64'],

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
            $products = Product::query()->with('variants')->whereIn('id', $productIds)->get()->keyBy('id');

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

                // Optional grade must belong to this product and be active.
                $variantId = isset($line['variant_id']) && $line['variant_id'] !== null
                    ? (int) $line['variant_id']
                    : null;
                if ($variantId !== null) {
                    $variant = $product->variants->firstWhere('id', $variantId);
                    if ($variant === null || ! $variant->is_active) {
                        $v->errors()->add(
                            "items.$index.variant_id",
                            __('shop.errors.variant_unavailable', ['name' => $product->name])
                        );

                        continue;
                    }
                }

                // Quantity step per unit kind. piece/bunch must be whole counts;
                // mass/volume use the product's own step (falls back to config).
                $step = $product->effectiveQuantityStep();
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
                        __('shop.errors.quantity_step', ['name' => $product->name, 'step' => $product->displayQuantity($step)])
                    );

                    continue;
                }

                // Remaining-stock cap for the chosen date and grade (NULL line
                // → the default grade, key 0), so grades cap independently.
                if ($date !== null) {
                    $remaining = app(InventoryService::class)
                        ->availableOnDate($product, $date, ShopStockAudience::Consumer, $variantId ?? 0);
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
