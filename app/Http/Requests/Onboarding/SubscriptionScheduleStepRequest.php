<?php

namespace App\Http\Requests\Onboarding;

use App\Enums\SubscriptionPlan;
use App\Models\Delivery;
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
        return [
            'plan' => ['required', Rule::enum(SubscriptionPlan::class)],
            'preferred_timeslot' => ['required', Rule::in(Delivery::allowedTimeslots())],
            'delivery_instructions' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
