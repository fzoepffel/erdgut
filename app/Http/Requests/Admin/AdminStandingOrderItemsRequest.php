<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** Admin edit of a standing-order template: quantities / line removal. */
class AdminStandingOrderItemsRequest extends FormRequest
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
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.id' => ['required', 'integer'],
            'lines.*.quantity' => ['nullable', 'numeric', 'gt:0'],
            'lines.*.remove' => ['nullable', 'boolean'],
        ];
    }
}
