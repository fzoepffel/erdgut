<?php

namespace App\Http\Requests;

use App\Models\Delivery;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeliveryWeeklyOverrideRequest extends FormRequest
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
            'address_id' => [
                'nullable',
                'integer',
                Rule::exists('addresses', 'id')->where('user_id', $this->user()->id),
            ],
            'timeslot' => ['nullable', 'string', Rule::in(Delivery::allowedTimeslots())],
            'special_instructions' => ['nullable', 'string', 'max:2000'],
            'skip_week' => ['nullable', 'boolean'],
            'reset_override' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'skip_week' => $this->boolean('skip_week'),
            'reset_override' => $this->boolean('reset_override'),
            'special_instructions' => $this->filled('special_instructions')
                ? trim((string) $this->input('special_instructions'))
                : null,
            'timeslot' => $this->filled('timeslot')
                ? trim((string) $this->input('timeslot'))
                : null,
        ]);
    }
}
