<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class AdminBoxDistributeRequest extends FormRequest
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
        $rules = [
            'week_start_date' => ['required', 'date'],
        ];

        if ($this->isMethod('post')) {
            $rules['confirm_distribute'] = ['required', 'accepted'];
        }

        return $rules;
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
    }
}
