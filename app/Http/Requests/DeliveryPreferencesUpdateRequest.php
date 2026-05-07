<?php

namespace App\Http\Requests;

use App\Support\DeliveryZoneResolver;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class DeliveryPreferencesUpdateRequest extends FormRequest
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
            'recipient_name' => ['required', 'string', 'max:255'],
            'street_line_1' => ['required', 'string', 'max:255'],
            'street_line_2' => ['nullable', 'string', 'max:255'],
            // B5 (UX notes): same German-PLZ + Berlin-zone check as the
            // onboarding address step. Edits to an existing primary
            // address must also stay inside the delivery zone.
            'postal_code' => ['required', 'string', 'regex:/^\d{5}$/'],
            'city' => ['required', 'string', 'max:255'],
            'country_code' => ['required', 'string', 'size:2'],
            'phone' => ['nullable', 'string', 'max:50'],
            'building_code' => ['nullable', 'string', 'max:255'],
            'floor' => ['nullable', 'string', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'preferred_timeslot' => ['required', 'string', 'max:100'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('country_code')) {
            $this->merge([
                'country_code' => strtoupper((string) $this->input('country_code')),
            ]);
        }
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $resolver = app(DeliveryZoneResolver::class);
            $expectedCountry = $resolver->defaultZoneCountryCode();
            $country = strtoupper((string) $this->input('country_code', ''));
            if ($country !== $expectedCountry) {
                $validator->errors()->add(
                    'country_code',
                    __('We currently only deliver to :country.', ['country' => $expectedCountry])
                );

                return;
            }
            $postal = (string) $this->input('postal_code', '');
            if (! $resolver->isPostalCodeInDefaultZone($postal)) {
                $hint = $resolver->defaultZonePostalCodeHint();
                $validator->errors()->add(
                    'postal_code',
                    $hint !== null
                        ? __('We currently only deliver in Berlin (PLZ :hint).', ['hint' => $hint])
                        : __('We currently only deliver in Berlin.')
                );
            }
        });
    }
}
