<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdminBoxOverrideEditRequest extends FormRequest
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
            'minutes' => ['nullable', 'integer', 'min:5', 'max:240'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
