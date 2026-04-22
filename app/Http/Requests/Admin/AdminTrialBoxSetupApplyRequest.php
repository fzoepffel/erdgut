<?php

namespace App\Http\Requests\Admin;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductWeekStock;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class AdminTrialBoxSetupApplyRequest extends FormRequest
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
            'order_ids' => ['required', 'array', 'min:1'],
            'order_ids.*' => ['integer', 'exists:orders,id'],
            'sizes' => ['required', 'array'],
            'sizes.*' => ['array'],
            'sizes.*.*.product_id' => ['required', 'integer'],
            'sizes.*.*.quantity' => ['required', 'integer', 'min:1', 'max:500'],
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
                $validator->errors()->add('week_start_date', __('admin.box_setup.validation_week_monday'));
            }
        });

        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $week = Carbon::parse($this->input('week_start_date'))->startOfDay();
            $validSizes = array_keys(config('box.included_value_by_size', []));
            $sizes = $this->input('sizes', []);

            foreach ($sizes as $size => $lines) {
                if (! in_array($size, $validSizes, true)) {
                    $validator->errors()->add("sizes.{$size}", __('admin.box_setup.validation_invalid_size'));

                    continue;
                }
                if (! is_array($lines)) {
                    continue;
                }
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
                            "sizes.{$size}.{$i}.product_id",
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
                            "sizes.{$size}.{$i}.product_id",
                            __('admin.box_setup.validation_product_not_available_this_week')
                        );
                    }
                }
            }
        });

        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $ids = array_values(array_unique(array_map('intval', (array) $this->input('order_ids', []))));
            if ($ids === []) {
                return;
            }

            $count = (int) Order::query()
                ->whereIn('id', $ids)
                ->where('product_type', 'trial_box')
                ->count();
            if ($count !== count($ids)) {
                $validator->errors()->add('order_ids', __('admin.trial_box_setup.validation_invalid_orders'));
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $sizes = $this->input('sizes', []);
        if (! is_array($sizes)) {
            $this->merge(['sizes' => []]);
        } else {
            $clean = [];
            foreach ($sizes as $size => $lines) {
                if (! is_string($size) || ! is_array($lines)) {
                    continue;
                }
                $filtered = [];
                foreach ($lines as $line) {
                    if (! is_array($line)) {
                        continue;
                    }
                    if (! empty($line['_delete'])) {
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
                $clean[$size] = $filtered;
            }
            $this->merge(['sizes' => $clean]);
        }

        $orderIds = $this->input('order_ids', []);
        if (is_string($orderIds)) {
            $parsed = array_filter(array_map('trim', explode(',', $orderIds)));
            $this->merge(['order_ids' => $parsed]);
        }
    }

    /**
     * @return list<int>
     */
    public function orderIds(): array
    {
        return array_values(array_unique(array_map('intval', (array) $this->input('order_ids', []))));
    }

    /**
     * @return array<string, list<array{product_id: int, quantity: int}>>
     */
    public function sizeLines(): array
    {
        /** @var array<string, list<array{product_id: int, quantity: int}>> $sizes */
        $sizes = $this->validated('sizes', []);

        return $sizes;
    }
}
