<?php

namespace App\Rules;

use App\Models\Product;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * An inflow's (or schedule's) producer must be one of the product's
 * registered suppliers (product_producer pivot). Prevents attributing a
 * batch to a farm that doesn't deliver the product.
 */
class ProducerSuppliesProduct implements ValidationRule
{
    public function __construct(private readonly ?int $productId) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '' || $this->productId === null) {
            return;
        }

        $product = Product::query()->find($this->productId);
        if ($product === null) {
            return; // product existence is validated separately
        }

        $supplies = $product->producers()->whereKey((int) $value)->exists()
            || (int) $product->producer_id === (int) $value;

        if (! $supplies) {
            $fail(__('validation.producer_not_supplier'));
        }
    }
}
