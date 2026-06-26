<?php

namespace App\Http\Requests\Shop;

use App\Enums\ProductUnit;
use App\Enums\ShopStockAudience;
use App\Http\Requests\ShopOrderRequest;
use App\Models\Product;
use App\Services\Admin\InventoryService;
use App\Services\Shop\CheckoutDraft;
use App\Services\Shop\ShopOrderService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Step 1 → 2 of the storefront checkout. Validates the basket payload the
 * customer collected on /shop (items[] + the picked delivery date) before
 * we persist it to the {@see CheckoutDraft} and send
 * them to the details page. Per-product step + stock checks mirror the
 * original {@see ShopOrderRequest} so a stale basket
 * can't slip through to step 2 only to fail on the final submit.
 */
class CheckoutBasketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $availableDates = app(ShopOrderService::class)
            ->availableDates()
            ->map(fn (array $row): string => $row['date']->toDateString())
            ->all();

        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.variant_id' => ['nullable', 'integer'],

            'expected_delivery_date' => ['required', 'date', Rule::in($availableDates)],
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
            $products = Product::query()->with('variants')->whereIn('id', $productIds)->get()->keyBy('id');

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
