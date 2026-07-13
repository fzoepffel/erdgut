<?php

namespace App\Http\Requests\Admin;

use App\Rules\ProducerSuppliesProduct;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate the inflow-edit form on the Inventar page.
 *
 * Same shape as {@see AdminInventoryInflowStoreRequest} but without
 * `product_id` — the product can't be changed via edit, only the
 * physical properties of the receipt. To move a row to a different
 * product, the admin still deletes + recreates.
 *
 * `received_on` is allowed to be any date (past or future) — admins
 * may be correcting a typo or shifting a future occurrence to a
 * different day. The materializer recognises the move via the
 * stable `original_received_on` column and won't re-create the
 * original date.
 */
class AdminInventoryInflowUpdateRequest extends FormRequest
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
            'received_on' => ['required', 'date'],
            // Optional grade this batch belongs to. NULL = the default grade.
            'variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'producer_id' => ['nullable', 'integer', 'exists:producers,id',
                new ProducerSuppliesProduct((int) ($this->route('inflow')?->product_id ?? 0) ?: null)],
            'quantity' => ['required', 'numeric', 'min:0.01', 'max:999999.99'],
            'freshness_window_days' => ['nullable', 'integer', 'between:1,60'],
            'notes' => ['nullable', 'string', 'max:500'],
            'b2b_only' => ['sometimes', 'boolean'],
        ];
    }
}
