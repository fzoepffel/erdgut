<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdminDeliveriesSyncFromBoxesRequest extends FormRequest
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
            'from' => ['nullable', 'date'],
        ];
    }
}
