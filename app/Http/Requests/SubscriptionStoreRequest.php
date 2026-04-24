<?php

namespace App\Http\Requests;

use App\Enums\SubscriptionPlan;
use App\Enums\Timeslot;
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
            'allergies.*' => ['string', 'max:255'],
            'delivery_weekday' => ['nullable', 'integer', 'between:0,6', Rule::in($zone->allowedWeekdays($zoneKey))],
            'preferred_timeslot' => ['nullable', 'string', Rule::in($zone->allowedTimeslots($zoneKey)), Rule::enum(Timeslot::class)],
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

        $weekday = $this->input('delivery_weekday');
        $timeslot = $this->input('preferred_timeslot');

        $this->merge([
            'preferences' => [
                'veg_only' => $this->boolean('preferences.veg_only'),
                'meat_add_on' => $this->boolean('preferences.meat_add_on'),
                'eggs_dairy_add_on' => $this->boolean('preferences.eggs_dairy_add_on'),
            ],
            'allergies' => $allergies,
            'delivery_weekday' => is_numeric($weekday) ? (int) $weekday : null,
            'preferred_timeslot' => is_string($timeslot) && $timeslot !== '' ? $timeslot : null,
        ]);
    }
}
