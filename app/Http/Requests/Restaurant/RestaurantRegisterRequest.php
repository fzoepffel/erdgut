<?php

namespace App\Http\Requests\Restaurant;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Validates a public restaurant (B2B) account application. The account is
 * created in a pending state and only opens once an admin verifies it, so
 * there's no payment or consent-to-charge here — just business details.
 */
class RestaurantRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // public application form
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:160'],
            'contact_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Password::defaults()],
            'phone' => ['required', 'string', 'max:40'],
            'handelsregisternummer' => ['nullable', 'string', 'max:60'],
            'ust_id_nr' => ['nullable', 'string', 'max:40'],
            'billing_street' => ['required', 'string', 'max:160'],
            'billing_postal_code' => ['required', 'string', 'max:16'],
            'billing_city' => ['required', 'string', 'max:120'],
        ];
    }
}
