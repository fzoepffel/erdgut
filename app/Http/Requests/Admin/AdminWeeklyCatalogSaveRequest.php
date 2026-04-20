<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProductCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AdminWeeklyCatalogSaveRequest extends FormRequest
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
            'rows' => ['present', 'array'],
            'rows.*.mode' => ['required', 'string', Rule::in(['existing', 'new'])],
            'rows.*.stock_id' => ['nullable', 'integer', 'exists:product_week_stocks,id'],
            'rows.*._delete' => ['sometimes', 'boolean'],
            'rows.*.quantity' => ['required', 'numeric', 'min:0', 'max:5000'],
            'rows.*.unit' => ['nullable', 'string', 'max:32'],

            'rows.*.product_id' => ['required_if:rows.*.mode,existing', 'nullable', 'integer', 'exists:products,id'],

            'rows.*.new.name' => ['required_if:rows.*.mode,new', 'nullable', 'string', 'max:255'],
            'rows.*.new.producer_id' => ['nullable', 'integer', 'exists:producers,id'],
            'rows.*.new.category' => ['required_if:rows.*.mode,new', 'nullable', 'string', Rule::in(ProductCategory::values())],
            'rows.*.new.unit_price_cents' => ['required_if:rows.*.mode,new', 'nullable', 'integer', 'min:0'],
            'rows.*.new.is_published' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if ($v->errors()->has('week_start_date')) {
                return;
            }

            $week = Carbon::parse($this->input('week_start_date'))->startOfDay();
            if (! $week->isMonday()) {
                $v->errors()->add(
                    'week_start_date',
                    __('admin.weekly_catalog.validation_week_monday')
                );
            }
        });

        $validator->after(function (Validator $v): void {
            $rows = $this->input('rows', []);
            if (! is_array($rows)) {
                return;
            }

            $seen = [];
            foreach ($rows as $i => $row) {
                if (! is_array($row)) {
                    continue;
                }
                if (! empty($row['_delete'])) {
                    continue;
                }
                if (($row['mode'] ?? null) !== 'existing') {
                    continue;
                }
                $pid = $row['product_id'] ?? null;
                if ($pid === null || $pid === '') {
                    continue;
                }
                $key = (int) $pid;
                if (isset($seen[$key])) {
                    $v->errors()->add(
                        "rows.{$i}.product_id",
                        __('admin.weekly_catalog.validation_duplicate_product')
                    );
                } else {
                    $seen[$key] = true;
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $rows = $this->input('rows', []);
        if (! is_array($rows)) {
            $this->merge(['rows' => []]);

            return;
        }

        $kept = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $mode = $row['mode'] ?? null;
            $isDelete = ! empty($row['_delete']);

            $productId = $row['product_id'] ?? null;
            $newName = trim((string) ($row['new']['name'] ?? ''));

            // Drop blank placeholder rows (no mode meaningful fields at all and not a deletion).
            if (! $isDelete
                && ($mode !== 'existing' || $productId === null || $productId === '')
                && ($mode !== 'new' || $newName === '')
            ) {
                continue;
            }

            if (isset($row['_delete'])) {
                $row['_delete'] = (bool) $row['_delete'];
            }
            if (isset($row['new']['is_published'])) {
                $row['new']['is_published'] = (bool) $row['new']['is_published'];
            }
            if (array_key_exists('product_id', $row) && ($row['product_id'] === '' || $row['product_id'] === null)) {
                $row['product_id'] = null;
            } elseif (isset($row['product_id'])) {
                $row['product_id'] = (int) $row['product_id'];
            }
            if (isset($row['stock_id']) && ($row['stock_id'] === '' || $row['stock_id'] === null)) {
                $row['stock_id'] = null;
            } elseif (isset($row['stock_id'])) {
                $row['stock_id'] = (int) $row['stock_id'];
            }

            $kept[] = $row;
        }

        $this->merge(['rows' => $kept]);
    }
}
