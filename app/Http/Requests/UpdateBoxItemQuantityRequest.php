<?php

namespace App\Http\Requests;

use App\Enums\BoxStatus;
use App\Models\Box;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBoxItemQuantityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $box = $this->route('box');

        if (! $box instanceof Box) {
            return false;
        }

        if ((int) $box->user_id !== (int) $this->user()?->id) {
            return false;
        }

        if ($box->status !== BoxStatus::Editable) {
            return false;
        }

        $deadline = $box->modification_deadline_at;
        if ($deadline === null || ! $deadline->isFuture()) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'delta' => [
                'required',
                'integer',
                Rule::in([-1, 1]),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ((int) $value !== -1) {
                        return;
                    }
                    $boxItem = $this->route('item');
                    if ($boxItem === null) {
                        return;
                    }
                    $currentQty = (float) $boxItem->quantity;
                    if ($currentQty <= 1) {
                        $fail('Use Remove to remove this item from the box.');
                    }
                },
            ],
        ];
    }
}
