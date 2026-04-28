<?php

namespace App\Http\Requests\Admin;

use App\Models\Box;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Bulk-replace the line items of a single box.
 *
 * Lines payload — one entry per kept/added line. Lines that the admin
 * removed are simply absent from the payload.
 *
 *   lines[i][product_id]
 *   lines[i][quantity]
 *   lines[i][is_extra]   (optional, defaults to false)
 *
 * Plus an `ok` flag persisting `composition_admin_ok_at` / `..._by`.
 */
class AdminBoxUpdateRequest extends FormRequest
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
        return [
            'lines' => ['nullable', 'array'],
            'lines.*.product_id' => ['required_with:lines.*', 'integer', 'exists:products,id'],
            'lines.*.quantity' => ['required_with:lines.*', 'numeric', 'min:0.01'],
            'lines.*.is_extra' => ['nullable', 'boolean'],
            'ok' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Strip empty rows + coerce numerics.
     */
    protected function prepareForValidation(): void
    {
        $raw = $this->input('lines', []);
        if (! is_array($raw)) {
            $raw = [];
        }
        $clean = [];
        foreach ($raw as $line) {
            if (! is_array($line)) {
                continue;
            }
            $pid = $line['product_id'] ?? null;
            $qty = $line['quantity'] ?? null;
            if ($pid === null || $pid === '' || (int) $pid < 1) {
                continue;
            }
            if ($qty === null || $qty === '' || (float) $qty <= 0) {
                continue;
            }
            $clean[] = [
                'product_id' => (int) $pid,
                'quantity' => (float) $qty,
                'is_extra' => filter_var($line['is_extra'] ?? false, FILTER_VALIDATE_BOOLEAN),
            ];
        }
        $this->merge([
            'lines' => $clean,
            'ok' => filter_var($this->input('ok', false), FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    /**
     * @return list<array{product_id: int, quantity: float, is_extra: bool}>
     */
    public function lines(): array
    {
        return (array) $this->validated('lines', []);
    }

    public function ok(): bool
    {
        return (bool) $this->validated('ok', false);
    }
}
