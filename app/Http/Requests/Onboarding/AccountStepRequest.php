<?php

namespace App\Http\Requests\Onboarding;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules;
use Illuminate\Validation\Validator;

class AccountStepRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class.',email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'phone' => ['nullable', 'string', 'max:32'],
        ];
    }

    /**
     * B6 (UX notes): when the email uniqueness rule fires, flash a
     * sentinel flag so the account-step blade can render a recovery
     * panel ("Hier anmelden / Passwort zurücksetzen") instead of just
     * the generic "already taken" inline error. Without this the user
     * — who very likely registered before and forgot — has no path
     * forward and tends to drop out.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $email = strtolower(trim((string) $this->input('email')));
            if ($email === '') {
                return;
            }

            $emailErrors = $validator->errors()->get('email');
            if ($emailErrors === []) {
                return;
            }

            $userExists = User::query()->where('email', $email)->exists();
            if (! $userExists) {
                return;
            }

            // Flash the email so the recovery links can hint at the
            // address the user just typed (without echoing it back into
            // a normal error message).
            $this->session()->flash('account_step_email_already_registered', $email);
        });
    }
}
