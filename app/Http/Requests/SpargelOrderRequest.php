<?php

namespace App\Http\Requests;

use App\Services\SpargelOrderService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SpargelOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Anyone with the link can buy — no auth.
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Default missing fields so tests + raw POSTs don't have to fill them.
        $defaults = [];
        if (! $this->has('payment_method') || (string) $this->input('payment_method') === '') {
            $defaults['payment_method'] = 'online';
        }
        if (! $this->has('quantity_green_grams') || $this->input('quantity_green_grams') === null || $this->input('quantity_green_grams') === '') {
            $defaults['quantity_green_grams'] = 0;
        }
        if (! $this->has('quantity_white_grams') || $this->input('quantity_white_grams') === null || $this->input('quantity_white_grams') === '') {
            $defaults['quantity_white_grams'] = 0;
        }
        if ($defaults !== []) {
            $this->merge($defaults);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $perVarietyRule = [
            'required',
            'integer',
            'min:0',
            'max:'.SpargelOrderService::QUANTITY_PER_VARIETY_MAX_GRAMS,
            'multiple_of:'.SpargelOrderService::QUANTITY_STEP_GRAMS,
        ];

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190'],
            // Phone required when delivery is chosen so we can coordinate
            // hand-off; otherwise optional.
            'phone' => ['nullable', 'required_if:fulfillment_mode,delivery', 'string', 'max:40'],
            'quantity_green_grams' => $perVarietyRule,
            'quantity_white_grams' => $perVarietyRule,
            'fulfillment_mode' => ['required', Rule::in(['pickup', 'delivery'])],
            'payment_method' => ['required', Rule::in(['online', 'in_person'])],
            'delivery_street' => ['nullable', 'required_if:fulfillment_mode,delivery', 'string', 'max:160'],
            'delivery_postal_code' => ['nullable', 'required_if:fulfillment_mode,delivery', 'string', 'max:16'],
            'delivery_city' => ['nullable', 'required_if:fulfillment_mode,delivery', 'string', 'max:120'],
            'delivery_notes' => ['nullable', 'string', 'max:500'],
            'consent' => ['accepted'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $green = (int) $this->input('quantity_green_grams', 0);
            $white = (int) $this->input('quantity_white_grams', 0);
            $totalGrams = $green + $white;
            $mode = (string) $this->input('fulfillment_mode');
            $payMethod = (string) $this->input('payment_method');

            // Combined-total validation. The per-variety rules already
            // cover step + max for each side; here we enforce that the
            // *order as a whole* is large enough to matter and small
            // enough to honour without a phone call.
            if ($totalGrams < SpargelOrderService::QUANTITY_COMBINED_MIN_GRAMS) {
                $v->errors()->add(
                    'quantity_green_grams',
                    __('spargel.errors.combined_minimum', ['min' => SpargelOrderService::QUANTITY_COMBINED_MIN_GRAMS])
                );
            }
            if ($totalGrams > SpargelOrderService::QUANTITY_COMBINED_MAX_GRAMS) {
                $v->errors()->add(
                    'quantity_green_grams',
                    __('spargel.errors.combined_maximum', ['max' => SpargelOrderService::QUANTITY_COMBINED_MAX_GRAMS])
                );
            }

            // Below DELIVERY_MIN_GRAMS combined: pickup only.
            if ($mode === 'delivery' && $totalGrams < SpargelOrderService::DELIVERY_MIN_GRAMS) {
                $v->errors()->add(
                    'fulfillment_mode',
                    __('spargel.errors.delivery_minimum', ['min' => SpargelOrderService::DELIVERY_MIN_GRAMS])
                );
            }

            // Pay-in-person is only available with self-pickup.
            if ($payMethod === 'in_person' && $mode !== 'pickup') {
                $v->errors()->add(
                    'payment_method',
                    __('spargel.errors.in_person_pickup_only')
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('Name'),
            'email' => __('Email'),
            'phone' => __('Phone'),
            'quantity_green_grams' => __('spargel.quantity_green_grams'),
            'quantity_white_grams' => __('spargel.quantity_white_grams'),
            'fulfillment_mode' => __('spargel.fulfillment_mode'),
            'delivery_street' => __('spargel.delivery_street'),
            'delivery_postal_code' => __('spargel.delivery_postal_code'),
            'delivery_city' => __('spargel.delivery_city'),
        ];
    }
}
