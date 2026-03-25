<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class AdminBoxPlannerCanonicalCustomizeRequest extends FormRequest
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
            'confirm_fill' => ['required', 'accepted'],
            'templates' => ['required', 'array', 'min:1'],
            'templates.*' => ['array'],
            'templates.*.lines' => ['present', 'array'],
            'templates.*.lines.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'templates.*.lines.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:500'],
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
                    __('admin.box_planner.validation_week_monday')
                );
            }
        });

        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $templates = $this->input('templates', []);
            if (! is_array($templates)) {
                return;
            }

            foreach ($templates as $size => $block) {
                if (! is_string($size) || ! is_array($block)) {
                    continue;
                }
                $lines = $block['lines'] ?? [];
                if (! is_array($lines)) {
                    continue;
                }
                foreach ($lines as $i => $line) {
                    if (! is_array($line)) {
                        continue;
                    }
                    $productId = $line['product_id'] ?? null;
                    if ($productId === null || $productId === '') {
                        continue;
                    }
                    $exists = Product::query()
                        ->whereKey((int) $productId)
                        ->visibleInCatalog()
                        ->exists();
                    if (! $exists) {
                        $validator->errors()->add(
                            "templates.{$size}.lines.{$i}.product_id",
                            __('admin.box_fill_canonical.validation_product_not_in_catalog')
                        );
                    }
                }
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $templates = $this->input('templates', []);
        if (! is_array($templates)) {
            return;
        }

        foreach ($templates as $size => $block) {
            if (! is_array($block) || ! isset($block['lines']) || ! is_array($block['lines'])) {
                continue;
            }
            $filtered = [];
            foreach ($block['lines'] as $line) {
                if (! is_array($line)) {
                    continue;
                }
                if (! empty($line['_delete'])) {
                    continue;
                }
                $pid = $line['product_id'] ?? null;
                if ($pid === null || $pid === '') {
                    continue;
                }
                $filtered[] = [
                    'product_id' => (int) $pid,
                    'quantity' => $line['quantity'] ?? 1,
                ];
            }
            $templates[$size]['lines'] = $filtered;
        }

        $this->merge(['templates' => $templates]);
    }
}
