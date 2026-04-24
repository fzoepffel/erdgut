<?php

namespace App\Http\Requests;

use App\Enums\FulfillmentMode;
use App\Enums\SubscriptionPlan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubscriptionUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'plan' => ['required', Rule::enum(SubscriptionPlan::class)],
            'fulfillment_mode' => ['required', Rule::enum(FulfillmentMode::class)],
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
                ->map(static fn (mixed $allergy): string => trim((string) $allergy))
                ->filter()
                ->values()
                ->all();
        } else {
            $allergies = collect(explode(',', (string) $allergiesInput))
                ->map(static fn (string $allergy): string => trim($allergy))
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
