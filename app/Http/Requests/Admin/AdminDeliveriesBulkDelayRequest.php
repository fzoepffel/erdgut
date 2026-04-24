<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdminDeliveriesBulkDelayRequest extends FormRequest
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
            'date' => ['required', 'date'],
            'delta_days' => ['required', 'integer', 'min:1', 'max:30'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
