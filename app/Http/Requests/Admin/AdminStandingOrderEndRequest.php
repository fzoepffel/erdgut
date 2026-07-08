<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin change of a standing order's end date. Any future date is fine:
 * the end trim and the materializer only act on weekday occurrences at or
 * before it, so the date need not land on the delivery weekday.
 */
class AdminStandingOrderEndRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ends_on' => ['required', 'date', 'after_or_equal:today'],
        ];
    }
}
