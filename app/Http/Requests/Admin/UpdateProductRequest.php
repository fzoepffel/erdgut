<?php

namespace App\Http\Requests\Admin;

use App\Enums\ProductCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    /**
     * @return list<string>
     */
    private function locales(): array
    {
        return ['de', 'en', 'fr'];
    }

    public function authorize(): bool
    {
        return $this->user()?->can('admin') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $translationRules = [
            'translations' => ['required', 'array'],
        ];
        foreach ($this->locales() as $locale) {
            $translationRules["translations.$locale"] = ['required', 'array'];
            $translationRules["translations.$locale.name"] = ['required', 'string', 'max:255'];
            $translationRules["translations.$locale.unit"] = ['required', 'string', 'max:64'];
            $translationRules["translations.$locale.origin"] = ['required', 'string', 'max:128'];
            $translationRules["translations.$locale.variety"] = ['required', 'string', 'max:128'];
            $translationRules["translations.$locale.taste_profile"] = ['required', 'string', 'max:512'];
        }

        return [
            ...$translationRules,
            'producer_id' => ['required', 'integer', 'exists:producers,id'],
            'category' => ['required', 'string', Rule::in(ProductCategory::values())],
            'unit_price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'is_seasonal' => ['boolean'],
            'is_available' => ['boolean'],
            'is_published' => ['boolean'],
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
            'is_published' => $this->boolean('is_published'),
            'is_test' => $this->boolean('is_test'),
            'remove_image' => $this->boolean('remove_image'),
        ]);
    }
}
