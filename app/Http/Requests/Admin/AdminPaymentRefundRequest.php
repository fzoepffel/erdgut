<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdminPaymentRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('admin') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'refund_amount' => ['nullable', 'numeric', 'gt:0', 'max:100000'],
            'refund_reason' => ['nullable', 'string', 'max:500'],
            'refund_reference' => ['nullable', 'string', 'max:160'],
        ];
    }
}
