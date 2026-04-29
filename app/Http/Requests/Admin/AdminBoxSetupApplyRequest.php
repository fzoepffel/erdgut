<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use App\Models\ProductWeekStock;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class AdminBoxSetupApplyRequest extends FormRequest
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
            'week_start_date' => ['required', 'date'],

            // Per-customer payload (preferred). Each key is a user_id.
            'customers' => ['nullable', 'array'],
            'customers.*' => ['array'],
            'customers.*.*.product_id' => ['required_with:customers', 'integer'],
            'customers.*.*.quantity' => ['required_with:customers', 'integer', 'min:1', 'max:500'],

            // Legacy per-size payload — kept for backwards compatibility.
            // One of `customers` or `sizes` must be present.
            'sizes' => ['nullable', 'array'],
            'sizes.*' => ['array'],
            'sizes.*.*.product_id' => ['required_with:sizes', 'integer'],
            'sizes.*.*.quantity' => ['required_with:sizes', 'integer', 'min:1', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('week_start_date')) {
                return;
            }
            $week = Carbon::parse($this->input('week_start_date'))->startOfDay();
            if (! $week->isMonday()) {
                $validator->errors()->add(
                    'week_start_date',
                    __('admin.box_setup.validation_week_monday')
                );
            }
        });

        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $week = Carbon::parse($this->input('week_start_date'))->startOfDay();
            $validSizes = array_keys(config('box.included_value_by_size', []));
            $sizes = (array) $this->input('sizes', []);
            $customers = (array) $this->input('customers', []);

            // Empty payloads are a no-op (e.g. the week has no eligible subs);
            // no validation error needed — the controller just won't update
            // anything.
            foreach ($sizes as $size => $lines) {
                if (! in_array($size, $validSizes, true)) {
                    $validator->errors()->add("sizes.{$size}", __('admin.box_setup.validation_invalid_size'));

                    continue;
                }
                $this->validateLines($validator, "sizes.{$size}", (array) $lines, $week);
            }

            foreach ($customers as $userId => $lines) {
                $this->validateLines($validator, "customers.{$userId}", (array) $lines, $week);
            }
        });
    }

    private function validateLines(Validator $validator, string $prefix, array $lines, Carbon $week): void
    {
        foreach ($lines as $i => $line) {
            $pid = (int) ($line['product_id'] ?? 0);
            if ($pid < 1) {
                continue;
            }
            $inCatalog = Product::query()
                ->whereKey($pid)
                ->visibleInCatalog()
                ->exists();
            if (! $inCatalog) {
                $validator->errors()->add(
                    "{$prefix}.{$i}.product_id",
                    __('admin.box_setup.validation_product_not_in_catalog')
                );

                continue;
            }
            $hasStock = ProductWeekStock::query()
                ->where('product_id', $pid)
                ->whereDate('week_start_date', $week->toDateString())
                ->where('planned_quantity', '>', 0)
                ->exists();
            if (! $hasStock) {
                $validator->errors()->add(
                    "{$prefix}.{$i}.product_id",
                    __('admin.box_setup.validation_product_not_available_this_week')
                );
            }
        }
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'sizes' => $this->normaliseLineMap($this->input('sizes', []), keyType: 'string'),
            'customers' => $this->normaliseLineMap($this->input('customers', []), keyType: 'customer_key'),
        ]);
    }

    /**
     * Strip empty rows + `_delete` rows, coerce numerics, drop invalid keys.
     *
     * Customer keys accept three shapes for backwards compatibility:
     *   - `s_<user_id>`  — subscription customer
     *   - `t_<order_id>` — trial customer
     *   - `<user_id>`    — legacy bare integer (treated as subscription)
     *
     * @param  mixed  $raw
     * @return array<int|string, list<array{product_id: int, quantity: int}>>
     */
    private function normaliseLineMap($raw, string $keyType): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $clean = [];
        foreach ($raw as $key => $lines) {
            if ($keyType === 'string' && ! is_string($key)) {
                continue;
            }
            if ($keyType === 'int' && (! is_numeric($key) || (int) $key < 1)) {
                continue;
            }
            if ($keyType === 'customer_key') {
                $stringKey = (string) $key;
                $isPrefixed = preg_match('/^[st]_\d+$/', $stringKey) === 1;
                $isLegacyInt = is_numeric($key) && (int) $key > 0;
                if (! $isPrefixed && ! $isLegacyInt) {
                    continue;
                }
            }
            if (! is_array($lines)) {
                continue;
            }

            $filtered = [];
            foreach ($lines as $line) {
                if (! is_array($line) || ! empty($line['_delete'])) {
                    continue;
                }
                $pid = $line['product_id'] ?? null;
                $qty = $line['quantity'] ?? null;
                if ($pid === null || $pid === '' || (int) $pid < 1) {
                    continue;
                }
                if ($qty === null || $qty === '' || (int) $qty < 1) {
                    continue;
                }
                $filtered[] = [
                    'product_id' => (int) $pid,
                    'quantity' => (int) $qty,
                ];
            }

            // Preserve original key shape: int when keyType=int, otherwise the
            // stringified key (which keeps `s_<id>` / `t_<id>` intact AND
            // accepts legacy bare integers — the apply controller will
            // dispatch on the prefix).
            if ($keyType === 'int') {
                $clean[(int) $key] = $filtered;
            } else {
                $clean[(string) $key] = $filtered;
            }
        }

        return $clean;
    }
}
