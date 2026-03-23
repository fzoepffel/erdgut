<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GiftDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'recipient_name' => ['required', 'string', 'max:255'],
            'street_line_1' => ['required', 'string', 'max:255'],
            'street_line_2' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['required', 'string', 'max:20'],
            'city' => ['required', 'string', 'max:100'],
            'country_code' => ['required', 'string', 'size:2'],
            'phone' => ['nullable', 'string', 'max:50'],
            'delivery_instructions' => ['nullable', 'string', 'max:1000'],
            'target_date' => ['required', 'date', 'after_or_equal:today'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->filled('country_code')) {
            $this->merge(['country_code' => 'DE']);
        }
    }
}
