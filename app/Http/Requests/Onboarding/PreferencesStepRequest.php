<?php

namespace App\Http\Requests\Onboarding;

use App\Enums\Allergen;
use App\Support\AllergyInputNormaliser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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

    /**
     * D3 (UX notes): hard-block the impossible combination of "I'm
     * allergic to eggs" + "I want the eggs add-on". Without this guard
     * the wizard happily accepts both and the customer ends up
     * declared-allergic-yet-charged-and-shipped eggs.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allergies = (array) $this->input('allergies', []);
            $eggsAddOn = (bool) $this->boolean('preferences.eggs_dairy_add_on');

            if ($eggsAddOn && in_array(Allergen::Eggs->value, $allergies, true)) {
                $validator->errors()->add(
                    'preferences.eggs_dairy_add_on',
                    __('You cannot add the eggs add-on when eggs is in your allergies.')
                );
            }
        });
    }
}
