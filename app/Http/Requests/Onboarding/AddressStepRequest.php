<?php

namespace App\Http\Requests\Onboarding;

use App\Support\DeliveryZoneResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            // B5 (UX notes): German PLZ is exactly 5 digits. The custom
            // delivery-zone check below adds a Berlin-range bound on top.
            'postal_code' => ['required', 'string', 'regex:/^\d{5}$/'],
            'city' => ['required', 'string', 'max:255'],
            'country_code' => ['required', 'string', 'size:2'],
            'phone' => ['nullable', 'string', 'max:32'],
            'building_code' => ['nullable', 'string', 'max:32'],
            'floor' => ['nullable', 'string', 'max:32'],
            'delivery_instructions' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * B5 (UX notes): Berlin-only delivery service must reject addresses
     * outside the configured zone (country + postal code range). We do
     * this in `withValidator` rather than as a baseline rule so the
     * country-code and postal-code error messages stay distinct and
     * actionable for the user.
     */
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
