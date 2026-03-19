<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SubscriptionPauseRequest extends FormRequest
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
            'pause_from' => ['required', 'date', 'after_or_equal:today'],
            'pause_until' => ['required', 'date', 'after_or_equal:pause_from'],
        ];
    }
}
