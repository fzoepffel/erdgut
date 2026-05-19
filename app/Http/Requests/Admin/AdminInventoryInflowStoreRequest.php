<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate the "manual one-off receipt" form on the Inventar page.
 *
 * `quantity` is admin-entered in the product's display unit (e.g.
 * "5" kg). The controller multiplies by `ProductUnit::canonicalUnitsPerDisplay`
 * before handing to `InventoryService::recordInflow`. Validation here
 * only checks shape — unit conversion is a controller concern.
 */
class AdminInventoryInflowStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('admin') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'received_on' => ['required', 'date'],
            'quantity' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'freshness_window_days' => ['nullable', 'integer', 'between:1,60'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
