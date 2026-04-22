<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreProducerRequest extends FormRequest
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
        $rules = [
            'translations' => ['required', 'array'],
        ];

        foreach ($this->locales() as $locale) {
            $rules["translations.$locale"] = ['required', 'array'];
            $rules["translations.$locale.name"] = ['required', 'string', 'max:255'];
            $rules["translations.$locale.region"] = ['required', 'string', 'max:255'];
            $rules["translations.$locale.description"] = ['required', 'string'];
        }

        return [
            ...$rules,
            'image_url' => ['nullable', 'string', 'max:2048'],
            'is_test' => ['boolean'],
            'image' => ['nullable', 'image', 'max:5120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_test' => $this->boolean('is_test'),
        ]);
    }
}
