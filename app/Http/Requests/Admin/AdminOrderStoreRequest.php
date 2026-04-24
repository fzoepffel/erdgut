<?php

namespace App\Http\Requests\Admin;

use App\Enums\Timeslot;
use App\Support\DeliveryZoneResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdminOrderStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('admin') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(DeliveryZoneResolver $zones): array
    {
        $userId = (int) ($this->input('user_id') ?? 0);
        $zoneKey = $zones->resolveZoneKey($userId > 0 ? $userId : null);

        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q->where('is_admin', false)),
            ],
            'address_id' => [
                'required',
                'integer',
                Rule::exists('addresses', 'id')->where(fn ($q) => $q->where('user_id', $userId)),
            ],
            'box_size' => ['required', 'string', Rule::in(['small', 'medium', 'large'])],
            'preferences' => ['array'],
            'preferences.veg_only' => ['sometimes'],
            'preferences.meat_add_on' => ['sometimes'],
            'preferences.eggs_dairy_add_on' => ['sometimes'],
            'allergies' => ['array'],
            'allergies.*' => ['string', 'max:80'],
            'preferred_delivery_window' => ['nullable', 'string', 'max:255'],
            'preferred_timeslot' => [
                'nullable',
                'string',
                Rule::in($zones->allowedTimeslots($zoneKey)),
                Rule::enum(Timeslot::class),
            ],
            'special_instructions' => ['nullable', 'string', 'max:1000'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('preferred_timeslot') === '') {
            $this->merge(['preferred_timeslot' => null]);
        }
    }
}
