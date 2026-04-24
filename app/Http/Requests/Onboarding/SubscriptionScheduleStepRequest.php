<?php

namespace App\Http\Requests\Onboarding;

use App\Enums\FulfillmentMode;
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
            'fulfillment_mode' => ['required', Rule::enum(FulfillmentMode::class)],
            'preferred_timeslot' => ['required_if:fulfillment_mode,delivery', 'nullable', Rule::in(Delivery::allowedTimeslots())],
            'delivery_instructions' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
