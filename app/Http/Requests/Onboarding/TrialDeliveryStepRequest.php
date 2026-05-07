<?php

namespace App\Http\Requests\Onboarding;

use App\Support\DeliveryZoneResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TrialDeliveryStepRequest extends FormRequest
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
        // C3 (UX notes): timeslot must be one of the actually-supported
        // ones for the user's zone (Wed 12-16 today). C4 (UX notes):
        // the older free-text `preferred_delivery_window` field is
        // gone — replaced with a strict date input bound to the
        // current scheduling-promise window so operators get a real
        // date.
        $zone = app(DeliveryZoneResolver::class);
        $zoneKey = $zone->resolveZoneKey($this->user()?->id);
        $promiseDays = (int) config('box.trial_scheduling_promise_days', 14);
        $minDate = CarbonImmutable::today()->addDay()->toDateString();
        $maxDate = CarbonImmutable::today()->addDays(max($promiseDays, 28))->toDateString();

        return [
            'preferred_timeslot' => ['required', Rule::in($zone->allowedTimeslots($zoneKey))],
            'preferred_delivery_date' => [
                'nullable',
                'date',
                'after_or_equal:'.$minDate,
                'before_or_equal:'.$maxDate,
            ],
            'special_instructions' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
