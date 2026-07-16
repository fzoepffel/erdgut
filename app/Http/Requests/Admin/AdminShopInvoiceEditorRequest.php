<?php

namespace App\Http\Requests\Admin;

use App\Services\Shop\ShopOrderService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The WYSIWYG invoice editor for SHOP orders, mirroring the B2B editor:
 * money edits per line (net entry, converted to the order's gross
 * snapshots), per-document display names, NEW off-catalog lines, the
 * delivery fee, the dates, and whitelisted text overrides. Overrides live
 * in receipt_overrides, shared with the Kaufbeleg.
 */
class AdminShopInvoiceEditorRequest extends FormRequest
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
        $rules = [
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.display_name' => ['nullable', 'string', 'max:160'],
            // New off-catalog lines (no id).
            'items.*.name' => ['nullable', 'string', 'max:160'],
            'items.*.unit' => ['nullable', 'string', Rule::in(['piece', 'bunch', 'kg', 'g', 'l', 'ml'])],
            'items.*.net_total_eur' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'items.*.vat_rate_percent' => ['nullable', 'integer', Rule::in([7, 19])],
            'items.*.quantity' => ['nullable', 'numeric', 'gt:0'],
            'items.*.remove' => ['nullable', 'boolean'],

            'delivery_fee_net_eur' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'delivery_fee_vat_rate_percent' => ['nullable', 'required_with:delivery_fee_net_eur', 'integer', Rule::in([7, 19])],

            'invoice_due_date' => ['nullable', 'date'],
            'expected_delivery_date' => ['nullable', 'date'],

            'overrides' => ['nullable', 'array'],
        ];

        foreach (ShopOrderService::RECEIPT_OVERRIDE_KEYS as $key => $maxLength) {
            $rules["overrides.$key"] = ['nullable', 'string', "max:$maxLength"];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            foreach ((array) $this->input('items', []) as $index => $line) {
                // A new line (no id) needs at least a printed name.
                if ((int) ($line['id'] ?? 0) === 0
                    && empty($line['remove'])
                    && trim((string) ($line['name'] ?? '')) === '') {
                    $v->errors()->add("items.$index.name", __('restaurant.invoice_editor_line_needs_name'));
                }
            }
        });
    }
}
