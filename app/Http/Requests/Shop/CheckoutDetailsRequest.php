<?php

namespace App\Http\Requests\Shop;

use App\Services\Shop\CheckoutDraft;
use App\Support\PickupLocations;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Step 2 → 3 of the storefront checkout. Validates fulfillment + payment +
 * contact + delivery-address fields (everything that goes into the order
 * besides the basket itself) before we merge it into the
 * {@see CheckoutDraft} and send the customer on to the
 * review page.
 *
 * The basket validity is asserted by {@see CheckoutBasketRequest} on
 * arrival into step 2; consent is collected on step 3 by
 * {@see CheckoutPlaceRequest}.
 */
class CheckoutDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('payment_method') || (string) $this->input('payment_method') === '') {
            $this->merge(['payment_method' => 'online']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'required_if:fulfillment_mode,delivery', 'string', 'max:40'],

            'fulfillment_mode' => ['required', Rule::in(['pickup', 'delivery'])],
            'payment_method' => ['required', Rule::in(['online', 'in_person'])],

            'delivery_street' => ['nullable', 'required_if:fulfillment_mode,delivery', 'string', 'max:160'],
            'delivery_postal_code' => ['nullable', 'required_if:fulfillment_mode,delivery', 'string', 'max:16'],
            'delivery_city' => ['nullable', 'required_if:fulfillment_mode,delivery', 'string', 'max:120'],
            'delivery_notes' => ['nullable', 'string', 'max:500'],

            'coupon_code' => ['nullable', 'string', 'max:64'],

            'pickup_location' => [
                'nullable',
                'required_if:fulfillment_mode,pickup',
                Rule::in(PickupLocations::keys()),
            ],
        ];
    }
}
