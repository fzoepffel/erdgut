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
        // Default payment_method to 'online' when missing — the form
        // always submits one of the two radio values, but tests + raw
        // POSTs can omit it and we want sane behaviour.
        if (! $this->has('payment_method') || $this->input('payment_method') === null || $this->input('payment_method') === '') {
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
            // Phone is required when delivery is chosen so we can coordinate
            // hand-off; otherwise optional.
            'phone' => ['nullable', 'required_if:fulfillment_mode,delivery', 'string', 'max:40'],
            'asparagus_type' => ['required', Rule::in(['green', 'white'])],
            // 100 g floor avoids zero / sub-portion orders. 20 kg ceiling
            // is a sanity guard against UI quirks. Quantities must be
            // multiples of 100 g — same step as the form input — so the
            // server doesn't accept e.g. 233 g if the customer fiddles
            // with the input directly.
            'quantity_grams' => [
                'required',
                'integer',
                'min:'.SpargelOrderService::QUANTITY_MIN_GRAMS,
                'max:'.SpargelOrderService::QUANTITY_MAX_GRAMS,
                'multiple_of:'.SpargelOrderService::QUANTITY_STEP_GRAMS,
            ],
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
            $grams = (int) $this->input('quantity_grams');
            $mode = (string) $this->input('fulfillment_mode');

            // Hard rule from the brief: orders below 500 g are pickup only.
            // Surface as a field error so the form can highlight the
            // delivery card.
            if ($mode === 'delivery' && $grams < SpargelOrderService::DELIVERY_MIN_GRAMS) {
                $v->errors()->add(
                    'fulfillment_mode',
                    __('spargel.errors.delivery_minimum', ['min' => SpargelOrderService::DELIVERY_MIN_GRAMS])
                );
            }

            // Pay-in-person is only available with self-pickup — we can't
            // accept cash for a delivery the customer never sees us during.
            $payMethod = (string) $this->input('payment_method');
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
            'asparagus_type' => __('spargel.asparagus_type'),
            'quantity_grams' => __('spargel.quantity_grams'),
            'fulfillment_mode' => __('spargel.fulfillment_mode'),
            'delivery_street' => __('spargel.delivery_street'),
            'delivery_postal_code' => __('spargel.delivery_postal_code'),
            'delivery_city' => __('spargel.delivery_city'),
        ];
    }
}
