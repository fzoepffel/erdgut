<?php

namespace App\Http\Requests\Onboarding;

use Illuminate\Foundation\Http\FormRequest;

class AddressStepRequest extends FormRequest
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
            'recipient_name' => ['required', 'string', 'max:255'],
            'street_line_1' => ['required', 'string', 'max:255'],
            'street_line_2' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:20'],
            'city' => ['required', 'string', 'max:255'],
            'country_code' => ['required', 'string', 'size:2'],
            'phone' => ['nullable', 'string', 'max:32'],
            'building_code' => ['nullable', 'string', 'max:32'],
            'floor' => ['nullable', 'string', 'max:32'],
            'delivery_instructions' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
