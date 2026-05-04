<?php

namespace App\Http\Requests\Admin;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminPaymentBulkMarkPaidExternallyRequest extends FormRequest
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
            'payment_ids' => ['required', 'array', 'min:1', 'max:200'],
            'payment_ids.*' => ['integer', Rule::exists((new Payment)->getTable(), 'id')],
            'external_reference' => ['required', 'string', 'max:160'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
