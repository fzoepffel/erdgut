<?php

namespace App\Http\Requests;

use App\Enums\BoxStatus;
use App\Models\Box;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class AddBoxItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $box = $this->route('box');

        if (! $box instanceof Box) {
            return false;
        }

        if ((int) $box->user_id !== (int) $this->user()?->id) {
            return false;
        }

        if ($box->status !== BoxStatus::Editable) {
            return false;
        }

        $deadline = $box->modification_deadline_at;
        if ($deadline === null || ! $deadline->isFuture()) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id', function (string $attribute, mixed $value, \Closure $fail): void {
                $product = Product::find($value);
                if ($product === null || ! $product->is_available) {
                    $fail(__('The selected product is not available.'));
                }
            }],
            'quantity' => ['required', 'numeric', 'min:0.1'],
        ];
    }
}
