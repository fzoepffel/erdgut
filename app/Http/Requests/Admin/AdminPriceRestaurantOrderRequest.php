<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Admin net-pricing / quantity edits / line drops on a restaurant order, before
 * the invoice is issued.
 */
class AdminPriceRestaurantOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('admin');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.net_total_eur' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'items.*.vat_rate_percent' => ['nullable', 'integer', Rule::in([7, 19])],
            'items.*.quantity' => ['nullable', 'numeric', 'gt:0'],
            'items.*.remove' => ['nullable', 'boolean'],
        ];
    }
}
