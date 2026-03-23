<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProductCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
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
            'producer_id' => ['required', 'integer', 'exists:producers,id'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', Rule::in(ProductCategory::values())],
            'unit_price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'is_seasonal' => ['boolean'],
            'is_available' => ['boolean'],
            'metadata.unit' => ['nullable', 'string', 'max:64'],
            'metadata.origin' => ['nullable', 'string', 'max:128'],
            'metadata.variety' => ['nullable', 'string', 'max:128'],
            'metadata.taste_profile' => ['nullable', 'string', 'max:512'],
            'is_test' => ['boolean'],
            'image' => ['nullable', 'image', 'max:5120'],
            'remove_image' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_seasonal' => $this->boolean('is_seasonal'),
            'is_available' => $this->boolean('is_available'),
            'is_test' => $this->boolean('is_test'),
            'remove_image' => $this->boolean('remove_image'),
        ]);
    }
}
