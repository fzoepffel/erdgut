<?php

namespace App\Http\Requests\Onboarding;

use App\Enums\FulfillmentMode;
use App\Enums\SubscriptionPlan;
use App\Enums\Timeslot;
use App\Support\DeliveryZoneResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubscriptionScheduleStepRequest extends FormRequest
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
        $zone = app(DeliveryZoneResolver::class);
        $zoneKey = $zone->resolveZoneKey($this->user()?->id);

        return [
            'plan' => ['required', Rule::enum(SubscriptionPlan::class)],
            'fulfillment_mode' => ['required', Rule::enum(FulfillmentMode::class)],
            'delivery_weekday' => [
                'required_if:fulfillment_mode,delivery',
                'nullable',
                'integer',
                'between:0,6',
                Rule::in($zone->allowedWeekdays($zoneKey)),
            ],
            'preferred_timeslot' => [
                'required_if:fulfillment_mode,delivery',
                'nullable',
                Rule::in($zone->allowedTimeslots($zoneKey)),
                Rule::enum(Timeslot::class),
            ],
            // B10 (UX notes): delivery_instructions is collected on the
            // address step now (single source of truth on the address
            // record). Kept nullable here so any old form post that
            // still ships the field doesn't 422; the controller no
            // longer reads it.
            'delivery_instructions' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $weekday = $this->input('delivery_weekday');
        $this->merge([
            'delivery_weekday' => is_numeric($weekday) ? (int) $weekday : null,
        ]);
    }
}
