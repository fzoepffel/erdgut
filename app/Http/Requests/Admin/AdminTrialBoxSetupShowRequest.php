<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class AdminTrialBoxSetupShowRequest extends FormRequest
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
            'order_ids' => ['required', 'array', 'min:1'],
            'order_ids.*' => ['integer', 'exists:orders,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $week = $this->route('week');
            if (! is_string($week) || $week === '') {
                return;
            }

            $d = Carbon::parse($week)->startOfDay();
            if (! $d->isMonday()) {
                $validator->errors()->add('week', __('admin.box_setup.validation_week_monday'));
            }
        });
    }

    /**
     * @return list<int>
     */
    public function orderIds(): array
    {
        return array_values(array_unique(array_map('intval', (array) $this->input('order_ids', []))));
    }

    protected function prepareForValidation(): void
    {
        $orderIds = $this->input('order_ids', []);
        if (is_string($orderIds)) {
            $parsed = array_filter(array_map('trim', explode(',', $orderIds)));
            $this->merge(['order_ids' => $parsed]);
        }
    }
}
