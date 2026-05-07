<?php

namespace App\Http\Requests;

use App\Support\DeliveryZoneResolver;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class GiftDeliveryRequest extends FormRequest
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
            // B5 (UX notes): German PLZ format check — gift recipients
            // also have to fall in the configured delivery zone since we
            // physically deliver the gift box.
            'postal_code' => ['required', 'string', 'regex:/^\d{5}$/'],
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
