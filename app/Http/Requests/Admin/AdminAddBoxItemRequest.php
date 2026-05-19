<?php

namespace App\Http\Requests\Admin;

use App\Models\Box;
use App\Models\Product;
use App\Support\BoxDeliveryDateResolver;
use Illuminate\Foundation\Http\FormRequest;

class AdminAddBoxItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $box = $this->route('box');

        return $box instanceof Box
            && ($this->user()?->can('admin') ?? false)
            && $box->allowsAdminLineEdits();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Box|null $box */
        $box = $this->route('box');

        return [
            'product_id' => ['required', 'integer', 'exists:products,id', function (string $attribute, mixed $value, \Closure $fail) use ($box): void {
                $product = Product::find($value);
                if ($product === null || ! $product->is_available || ! $product->is_published) {
                    $fail(__('The selected product is not available.'));

                    return;
                }
                if ($box instanceof Box && $box->week_start_date !== null) {
                    $deliveryDate = BoxDeliveryDateResolver::deliveryDate($box);
                    if ($product->availableOnDate($deliveryDate) <= 0) {
                        $fail(__('admin.box_items.product_unavailable_this_week'));
                    }
                }
            }],
            'quantity' => ['required', 'numeric', 'min:0.1'],
        ];
    }
}
