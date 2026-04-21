<?php

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreferencesStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'box_size' => ['required', 'string', Rule::in(['small', 'medium', 'large'])],
            'preferences' => ['required', 'array'],
            'preferences.veg_only' => ['required', 'boolean'],
            'preferences.meat_add_on' => ['required', 'boolean'],
            'preferences.eggs_dairy_add_on' => ['required', 'boolean'],
            'allergies' => ['nullable', 'array'],
            'allergies.*' => ['string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $allergiesInput = $this->input('allergies', '');

        if (is_array($allergiesInput)) {
            $allergies = collect($allergiesInput)
                ->map(static fn (mixed $v): string => trim((string) $v))
                ->filter()
                ->values()
                ->all();
        } else {
            $allergies = collect(explode(',', (string) $allergiesInput))
                ->map(static fn (string $v): string => trim($v))
                ->filter()
                ->values()
                ->all();
        }

        $this->merge([
            'preferences' => [
                'veg_only' => $this->boolean('preferences.veg_only'),
                'meat_add_on' => $this->boolean('preferences.meat_add_on'),
                'eggs_dairy_add_on' => $this->boolean('preferences.eggs_dairy_add_on'),
            ],
            'allergies' => $allergies,
        ]);
    }
}
