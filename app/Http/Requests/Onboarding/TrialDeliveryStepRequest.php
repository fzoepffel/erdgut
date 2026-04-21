<?php

namespace App\Http\Requests\Onboarding;

use App\Models\Delivery;
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
        return [
            'preferred_timeslot' => ['required', Rule::in(Delivery::allowedTimeslots())],
            'preferred_delivery_window' => ['nullable', 'string', 'max:255'],
            'special_instructions' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
