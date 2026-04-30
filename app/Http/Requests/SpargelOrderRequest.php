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
            // is a sanity guard against UI quirks. Step is 50 g on the
            // frontend (not enforced server-side — admin can always
            // accept odd quantities).
            'quantity_grams' => ['required', 'integer', 'min:100', 'max:20000'],
            'fulfillment_mode' => ['required', Rule::in(['pickup', 'delivery'])],
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
