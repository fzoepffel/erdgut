<?php

namespace App\Http\Requests\Admin;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminPaymentsIndexRequest extends FormRequest
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
            'status' => ['nullable', 'string', Rule::in([
                Payment::STATUS_PENDING,
                Payment::STATUS_PAID,
                Payment::STATUS_FAILED,
            ])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'date_field' => ['nullable', 'string', Rule::in(['created_at', 'paid_at', 'failed_at'])],
            'sort' => ['nullable', 'string', Rule::in(['created_at', 'amount', 'status', 'attempt_number'])],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'stale_pending' => ['nullable', 'string', Rule::in(['1'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'subscription_id' => ['nullable', 'integer', 'exists:subscriptions,id'],
        ];
    }
}
