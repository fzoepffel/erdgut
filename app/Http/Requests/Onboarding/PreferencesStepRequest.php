<?php

namespace App\Http\Requests\Onboarding;

use App\Enums\Allergen;
use App\Support\AllergyInputNormaliser;
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
            'allergies.*' => ['string', Rule::enum(Allergen::class)],
            'allergies_note' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'preferences' => [
                'veg_only' => $this->boolean('preferences.veg_only'),
                'meat_add_on' => $this->boolean('preferences.meat_add_on'),
                'eggs_dairy_add_on' => $this->boolean('preferences.eggs_dairy_add_on'),
            ],
            'allergies' => AllergyInputNormaliser::toArray($this->input('allergies')),
            'allergies_note' => AllergyInputNormaliser::normaliseNote($this->input('allergies_note')),
        ]);
    }
}
