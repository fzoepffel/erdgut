<?php

namespace App\Http\Requests\Admin;

use App\Models\Box;
use App\Models\BoxItem;
use Illuminate\Foundation\Http\FormRequest;

class AdminUpdateBoxItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $box = $this->route('box');
        $item = $this->route('item');

        if (! $box instanceof Box || ! $item instanceof BoxItem) {
            return false;
        }

        if ((int) $item->box_id !== (int) $box->id) {
            return false;
        }

        return ($this->user()?->can('admin') ?? false)
            && $box->allowsAdminLineEdits();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['required', 'numeric', 'min:0.1'],
        ];
    }
}
