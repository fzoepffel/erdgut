<?php

namespace App\Http\Requests;

use App\Enums\Allergen;
use App\Enums\SubscriptionPlan;
use App\Enums\Timeslot;
use App\Support\AllergyInputNormaliser;
use App\Support\DeliveryZoneResolver;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubscriptionStoreRequest extends FormRequest
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
        $zone = app(DeliveryZoneResolver::class);
        $zoneKey = $zone->resolveZoneKey($this->user()?->id);

        return [
            'plan' => ['required', Rule::enum(SubscriptionPlan::class)],
            'box_size' => ['required', 'string', Rule::in(['small', 'medium', 'large'])],
            'preferences' => ['required', 'array'],
            'preferences.veg_only' => ['required', 'boolean'],
            'preferences.meat_add_on' => ['required', 'boolean'],
            'preferences.eggs_dairy_add_on' => ['required', 'boolean'],
            'allergies' => ['nullable', 'array'],
            'allergies.*' => ['string', Rule::enum(Allergen::class)],
            'allergies_note' => ['nullable', 'string', 'max:500'],
            'delivery_weekday' => ['nullable', 'integer', 'between:0,6', Rule::in($zone->allowedWeekdays($zoneKey))],
            'preferred_timeslot' => ['nullable', 'string', Rule::in($zone->allowedTimeslots($zoneKey)), Rule::enum(Timeslot::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $weekday = $this->input('delivery_weekday');
        $timeslot = $this->input('preferred_timeslot');

        $this->merge([
            'preferences' => [
                'veg_only' => $this->boolean('preferences.veg_only'),
                'meat_add_on' => $this->boolean('preferences.meat_add_on'),
                'eggs_dairy_add_on' => $this->boolean('preferences.eggs_dairy_add_on'),
            ],
            'allergies' => AllergyInputNormaliser::toArray($this->input('allergies')),
            'allergies_note' => AllergyInputNormaliser::normaliseNote($this->input('allergies_note')),
            'delivery_weekday' => is_numeric($weekday) ? (int) $weekday : null,
            'preferred_timeslot' => is_string($timeslot) && $timeslot !== '' ? $timeslot : null,
        ]);
    }
}
